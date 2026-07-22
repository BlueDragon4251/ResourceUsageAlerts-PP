<?php

declare(strict_types=1);

$boolean = static fn (mixed $value, bool $default): bool => filter_var(
    $value,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
) ?? $default;

return [
    'enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_ENABLED', true), true),
    'poll_interval_minutes' => max(1, (int) env('RESOURCE_USAGE_ALERTS_POLL_INTERVAL', 5)),
    'sample_retention_days' => max(1, (int) env('RESOURCE_USAGE_ALERTS_SAMPLE_RETENTION_DAYS', 14)),
    'event_retention_days' => max(1, (int) env('RESOURCE_USAGE_ALERTS_EVENT_RETENTION_DAYS', 90)),
    'discord_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_DISCORD_TIMEOUT', 5)),
    'telegram_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_TELEGRAM_TIMEOUT', 5)),
    'slack_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_SLACK_TIMEOUT', 5)),
    'allow_user_rules' => $boolean(env('RESOURCE_USAGE_ALERTS_ALLOW_USER_RULES', true), true),
    'allow_user_channels' => $boolean(env('RESOURCE_USAGE_ALERTS_ALLOW_USER_CHANNELS', true), true),
    'global_discord_webhook' => env('RESOURCE_USAGE_ALERTS_GLOBAL_DISCORD_WEBHOOK'),
    'global_telegram_bot_token' => env('RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_BOT_TOKEN'),
    'global_telegram_chat_id' => env('RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_CHAT_ID'),
    'global_slack_webhook' => env('RESOURCE_USAGE_ALERTS_GLOBAL_SLACK_WEBHOOK'),
    'minimum_notification_severity' => env('RESOURCE_USAGE_ALERTS_MINIMUM_SEVERITY', 'info'),
    'push_enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_PUSH_ENABLED', true), true),
    'vapid_subject' => env('RESOURCE_USAGE_ALERTS_VAPID_SUBJECT', env('APP_URL')),
    'vapid_public_key' => env('RESOURCE_USAGE_ALERTS_VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('RESOURCE_USAGE_ALERTS_VAPID_PRIVATE_KEY'),
    'blueit_announcements_enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_BLUEIT_ANNOUNCEMENTS_ENABLED', true), true),
    'blueit_announcements_url' => rtrim((string) env('RESOURCE_USAGE_ALERTS_BLUEIT_ANNOUNCEMENTS_URL', 'https://blueit42.vercel.app/api/announcements'), '/'),
    'blueit_announcements_secret' => env('RESOURCE_USAGE_ALERTS_BLUEIT_ANNOUNCEMENTS_SECRET', 'blueit42-announcements-v1'),
    'blueit_announcements_poll_seconds' => max(5, (int) env('RESOURCE_USAGE_ALERTS_BLUEIT_ANNOUNCEMENTS_POLL_SECONDS', 10)),
    'default_channels' => ['panel', 'push'],
    'chunk_size' => 100,
];
