<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertDeliveryAttempt;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class DeliveryTrackerService
{
    public function allowed(ResourceAlertEvent $event, ResourceAlertChannel $channel): bool
    {
        if (! Schema::hasTable('resource_alert_delivery_attempts')) {
            return true;
        }

        $minutes = max(0, (int) data_get(
            $channel->config,
            'cooldown_minutes',
            data_get($event->rule->config, 'per_channel_cooldown_minutes', 0)
        ));
        if ($minutes === 0) {
            return true;
        }

        return ! ResourceAlertDeliveryAttempt::query()
            ->where('event_id', $event->id)
            ->where('channel_id', $channel->id)
            ->where('status', 'sent')
            ->where('attempted_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }

    public function record(
        ResourceAlertEvent $event,
        string $channelType,
        string $status,
        ?int $channelId,
        ?int $responseStatus,
        ?string $failureReason,
        int $durationMilliseconds
    ): void {
        if (! Schema::hasTable('resource_alert_delivery_attempts')) {
            return;
        }

        ResourceAlertDeliveryAttempt::query()->create([
            'event_id' => $event->id,
            'channel_id' => $channelId,
            'channel_type' => $channelType,
            'status' => $status,
            'response_status' => $responseStatus,
            'failure_reason' => $failureReason !== null ? str($failureReason)->limit(255)->toString() : null,
            'duration_ms' => $durationMilliseconds,
            'attempted_at' => now(),
        ]);
    }
}
