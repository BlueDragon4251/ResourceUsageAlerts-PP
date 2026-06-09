<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\Http;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationChannel;

class TelegramNotificationChannel
{
    private AlertMessageFormatter $formatter;

    public function __construct(AlertMessageFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function send(ResourceAlertEvent $event, bool $resolved = false): void
    {
        $channel = ResourceAlertNotificationChannel::query()
            ->where('type', 'telegram')
            ->where('enabled', true)
            ->first();

        if (!$channel) {
            return;
        }

        $botToken = $channel->config['bot_token'] ?? null;
        $chatId = $channel->config['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return;
        }

        $text = $this->formatter->telegramPayload($event, $resolved);

        try {
            Http::timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
        } catch (\Throwable) {
            // Silently fail
        }
    }
}