<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Listeners;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use Throwable;

class AutoRestartServerListener
{
    public function __construct(private DaemonServerRepository $serverRepository) {}

    public function handle(ResourceAlertEvent $event): void
    {
        if ($event->status !== AlertStatus::OPEN) {
            return;
        }

        if ($event->metric !== AlertMetric::SERVER_CRASHED) {
            return;
        }

        if (! $event->server instanceof Server) {
            return;
        }

        if (! filter_var(config('resourceusagealerts.auto_restart_enabled', false), FILTER_VALIDATE_BOOLEAN)
            || ! filter_var(data_get($event->rule->config, 'auto_restart.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $maxRetries = $this->getMaxRetries($event);
        $cacheKey = 'resource-alerts:auto-restart:'.$event->server->id;
        $currentRetries = (int) Cache::get($cacheKey.':attempts', 0);

        if ($currentRetries >= $maxRetries) {
            Log::warning('ResourceUsageAlerts: max auto-restart attempts reached.', [
                'server_id' => $event->server->id,
                'server_name' => $event->server->name,
                'max_retries' => $maxRetries,
            ]);

            return;
        }

        $lock = Cache::lock($cacheKey.':lock', 60);
        $acquired = false;
        try {
            if (! $lock->get()) {
                return;
            }
            $acquired = true;
            $this->serverRepository->setServer($event->server)->power('start');
            Cache::put($cacheKey.':attempts', $currentRetries + 1, now()->addMinutes((int) config('resourceusagealerts.auto_restart_cooldown_minutes', 30)));

            $event->forceFill([
                'context' => array_merge($event->context ?? [], [
                    'auto_restart' => true,
                    'auto_restart_attempts' => $currentRetries + 1,
                    'auto_restart_at' => now()->toIso8601String(),
                ]),
            ])->save();
            Log::info('ResourceUsageAlerts: auto-restarted crashed server.', [
                'server_id' => $event->server->id,
                'server_name' => $event->server->name,
                'attempt' => $currentRetries + 1,
            ]);
        } catch (Throwable $exception) {
            Log::warning('ResourceUsageAlerts: auto-restart failed.', [
                'server_id' => $event->server->id,
                'server_name' => $event->server->name,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }

    private function getMaxRetries(ResourceAlertEvent $event): int
    {
        return max(1, min(
            (int) config('resourceusagealerts.auto_restart_max_attempts', 2),
            (int) data_get($event->rule->config ?? [], 'auto_restart.max_attempts', 2)
        ));
    }
}
