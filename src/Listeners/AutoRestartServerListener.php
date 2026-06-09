<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Listeners;

use App\Models\Server;
use App\Services\Servers\ServerService;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class AutoRestartServerListener
{
    private ServerService $serverService;

    public function __construct(ServerService $serverService)
    {
        $this->serverService = $serverService;
    }

    public function handle(ResourceAlertEvent $event): void
    {
        if ($event->status !== AlertStatus::OPEN) {
            return;
        }

        if ($event->metric !== AlertMetric::SERVER_CRASHED) {
            return;
        }

        if (!$event->server) {
            return;
        }

        $autoRestart = $this->getAutoRestartSetting($event->server);
        if (!$autoRestart) {
            return;
        }

        $maxRetries = $this->getMaxRetries($event->server);
        $currentRetries = $event->context['auto_restart_attempts'] ?? 0;

        if ($currentRetries >= $maxRetries) {
            Log::warning("ResourceUsageAlerts: Max auto-restart attempts ({$maxRetries}) reached for server {$event->server->name}");
            return;
        }

        try {
            $this->serverService->setServerState($event->server, 'start');
            Log::info("ResourceUsageAlerts: Auto-restarted server {$event->server->name} (attempt " . ($currentRetries + 1) . ")");

            $event->forceFill([
                'context' => array_merge($event->context ?? [], [
                    'auto_restart' => true,
                    'auto_restart_attempts' => $currentRetries + 1,
                    'auto_restart_at' => now()->toIso8601String(),
                ]),
            ])->save();
        } catch (\Throwable $e) {
            Log::error("ResourceUsageAlerts: Auto-restart failed for server {$event->server->name}: {$e->getMessage()}");
        }
    }

    private function getAutoRestartSetting(Server $server): bool
    {
        return $server->data->get('resource_alerts.auto_restart', false);
    }

    private function getMaxRetries(Server $server): int
    {
        return (int) $server->data->get('resource_alerts.max_restart_attempts', 3);
    }
}