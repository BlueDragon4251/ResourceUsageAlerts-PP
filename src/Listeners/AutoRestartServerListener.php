<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Listeners;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
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

        if (!$event->server instanceof Server) {
            return;
        }

        if (!$this->getAutoRestartSetting($event->server)) {
            return;
        }

        $maxRetries = $this->getMaxRetries($event->server);
        $currentRetries = (int) data_get($event->context, 'auto_restart_attempts', 0);

        if ($currentRetries >= $maxRetries) {
            Log::warning('ResourceUsageAlerts: max auto-restart attempts reached.', [
                'server_id' => $event->server->id,
                'server_name' => $event->server->name,
                'max_retries' => $maxRetries,
            ]);

            return;
        }

        try {
            $this->serverRepository->setServer($event->server)->power('start');

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
        }
    }

    private function getAutoRestartSetting(Server $server): bool
    {
        return (bool) data_get($server->data ?? [], 'resource_alerts.auto_restart', false);
    }

    private function getMaxRetries(Server $server): int
    {
        return max(1, (int) data_get($server->data ?? [], 'resource_alerts.max_restart_attempts', 3));
    }
}
