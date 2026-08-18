<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\WebPush;
use PelicanPlugins\ResourceUsageAlerts\Services\RuntimeHealthService;
use Throwable;

class DoctorResourceAlertsCommand extends Command
{
    protected $signature = 'resource-alerts:doctor';

    protected $description = 'Check Resource Usage Alerts migrations, scheduler, queue, push, and encrypted secrets.';

    public function handle(RuntimeHealthService $health): int
    {
        $checks = [];
        foreach ([
            'resource_alert_rules',
            'resource_alert_events',
            'resource_alert_samples',
            'resource_alert_channels',
            'resource_alert_push_subscriptions',
            'resource_alert_channel_audits',
            'resource_alert_comments',
        ] as $table) {
            $checks[] = [$table, Schema::hasTable($table) ? 'OK' : 'MISSING', 'database'];
        }

        $pollMinutes = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5));
        foreach (['collection', 'evaluation'] as $operation) {
            $status = $health->status($operation);
            $fresh = $status['completed_at']?->isAfter(now()->subMinutes(($pollMinutes * 2) + 2)) ?? false;
            $checks[] = [
                $operation,
                $fresh ? 'OK' : 'STALE',
                $status['completed_at']?->toDateTimeString() ?? 'never',
            ];
        }

        $queue = (string) config('queue.default', 'sync');
        $checks[] = ['queue driver', $queue === 'sync' ? 'WARNING' : 'OK', $queue];
        $pushConfigured = filled(config('resourceusagealerts.vapid_public_key'))
            && filled(config('resourceusagealerts.vapid_private_key'))
            && class_exists(WebPush::class);
        $checks[] = ['browser push', $pushConfigured ? 'OK' : 'WARNING', $pushConfigured ? 'configured' : 'incomplete'];

        foreach ([
            'global_discord_webhook',
            'global_slack_webhook',
            'global_telegram_bot_token',
            'global_telegram_chat_id',
            'vapid_private_key',
        ] as $key) {
            $checks[] = [$key, $this->secretHealthy($key) ? 'OK' : 'INVALID', 'secret'];
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $checks[] = ['failed jobs', $failed === 0 ? 'OK' : 'WARNING', (string) $failed];
        }

        $this->table(['Check', 'Status', 'Detail'], $checks);

        return collect($checks)->contains(fn (array $check): bool => in_array($check[1], ['MISSING', 'INVALID'], true))
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function secretHealthy(string $key): bool
    {
        $value = config('resourceusagealerts.'.$key);
        if (! is_string($value) || $value === '') {
            return true;
        }
        if (! str_starts_with($value, 'encrypted:')) {
            return true;
        }

        try {
            Crypt::decryptString(substr($value, 10));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
