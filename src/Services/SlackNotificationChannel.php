<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\Http;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationChannel;

class SlackNotificationChannel
{
    private AlertMessageFormatter $formatter;

    public function __construct(AlertMessageFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function send(ResourceAlertEvent $event, bool $resolved = false): void
    {
        $channel = ResourceAlertNotificationChannel::query()
            ->where('type', 'slack')
            ->where('enabled', true)
            ->first();

        if (!$channel) {
            return;
        }

        $webhookUrl = $channel->config['webhook_url'] ?? null;
        if (!$webhookUrl) {
            return;
        }

        $payload = $this->formatter->slackPayload($event, $resolved);

        try {
            Http::timeout(10)
                ->post($webhookUrl, $payload);
        } catch (\Throwable) {
            // Silently fail
        }
    }
}