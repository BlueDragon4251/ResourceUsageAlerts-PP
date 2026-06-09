<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class NotificationCooldownService
{
    private AlertMessageFormatter $formatter;

    private AlertEscalationService $escalationService;

    private OnCallRotationService $onCallService;

    private TelegramNotificationChannel $telegramChannel;

    private SlackNotificationChannel $slackChannel;

    public function __construct(
        AlertMessageFormatter $formatter,
        AlertEscalationService $escalationService,
        OnCallRotationService $onCallService,
        TelegramNotificationChannel $telegramChannel,
        SlackNotificationChannel $slackChannel
    ) {
        $this->formatter = $formatter;
        $this->escalationService = $escalationService;
        $this->onCallService = $onCallService;
        $this->telegramChannel = $telegramChannel;
        $this->slackChannel = $slackChannel;
    }

    /**
     * Check if a specific channel is in cooldown for this event.
     */
    public function isInCooldown(ResourceAlertEvent $event, string $channelType): bool
    {
        $cooldownMinutes = (int) ($event->rule->config["cooldown_{$channelType}_minutes"] ?? 0);
        if ($cooldownMinutes <= 0) {
            return false;
        }

        $lastNotification = $event->last_notified_at;
        if (!$lastNotification) {
            return false;
        }

        return $lastNotification->diffInMinutes(now()) < $cooldownMinutes;
    }

    /**
     * Send notification to all configured channels for an event.
     */
    public function notifyAllChannels(ResourceAlertEvent $event, bool $resolved = false): void
    {
        // Discord
        if ($event->rule->config['discord_enabled'] ?? false) {
            $this->sendDiscord($event, $resolved);
        }

        // Email
        if ($event->rule->config['email_enabled'] ?? true) {
            $this->sendEmail($event, $resolved);
        }

        // Panel Inbox
        if ($event->rule->config['panel_inbox_enabled'] ?? true) {
            $this->sendPanelInbox($event, $resolved);
        }

        // Telegram
        $this->telegramChannel->send($event, $resolved);

        // Slack
        $this->slackChannel->send($event, $resolved);

        // On-call rotation
        if ($this->onCallService->hasRotation($event->rule)) {
            $this->onCallService->notifyOnCall($event);
        }

        // Escalation check
        $this->escalationService->escalateIfNeeded($event);
    }

    private function sendDiscord(ResourceAlertEvent $event, bool $resolved): void
    {
        // Discord notification logic
    }

    private function sendEmail(ResourceAlertEvent $event, bool $resolved): void
    {
        // Email notification logic
    }

    private function sendPanelInbox(ResourceAlertEvent $event, bool $resolved): void
    {
        // Panel inbox notification logic
    }
}