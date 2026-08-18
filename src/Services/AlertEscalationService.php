<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Jobs\SendAlertNotificationJob;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertEscalationService
{
    /**
     * Escalate an event if it has been open/acknowledged for too long.
     */
    public function escalateIfNeeded(ResourceAlertEvent $event): void
    {
        if ($event->status === AlertStatus::RESOLVED) {
            return;
        }

        $escalationMinutes = (int) ($event->rule->config['escalation_minutes'] ?? 0);
        if ($escalationMinutes <= 0) {
            return;
        }

        $severityMap = $event->rule->config['escalation_severity'] ?? null;
        if (! $severityMap) {
            return;
        }

        $elapsed = $event->triggered_at->diffInMinutes(now());
        if ($elapsed < $escalationMinutes) {
            return;
        }

        // Check if already escalated
        if (($event->context['escalated'] ?? false) === true) {
            return;
        }

        // Escalate: increase severity and notify on higher channel
        $newSeverity = AlertSeverity::from($severityMap);
        $event->forceFill([
            'severity' => $newSeverity,
            'context' => array_merge($event->context ?? [], [
                'escalated' => true,
                'escalated_at' => now()->toIso8601String(),
                'original_severity' => $event->severity->value,
            ]),
        ])->save();

        SendAlertNotificationJob::dispatch($event->id, false, true);
    }

    /**
     * Check all unresolved events for escalation.
     */
    public function processEscalations(): void
    {
        ResourceAlertEvent::query()
            ->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])
            ->with('rule')
            ->each(function (ResourceAlertEvent $event): void {
                $this->escalateIfNeeded($event);
            });
    }
}
