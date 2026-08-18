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
    'stale_metric_grace_minutes' => max(0, (int) env('RESOURCE_USAGE_ALERTS_STALE_METRIC_GRACE', 2)),
    'sample_retention_days' => max(1, (int) env('RESOURCE_USAGE_ALERTS_SAMPLE_RETENTION_DAYS', 14)),
    'event_retention_days' => max(1, (int) env('RESOURCE_USAGE_ALERTS_EVENT_RETENTION_DAYS', 90)),
    'backup_stale_days' => max(1, (int) env('RESOURCE_USAGE_ALERTS_BACKUP_STALE_DAYS', 7)),
    'minimum_wings_version' => env('RESOURCE_USAGE_ALERTS_MINIMUM_WINGS_VERSION', ''),
    'status_page_enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_STATUS_PAGE_ENABLED', false), false),
    'status_page_token' => env('RESOURCE_USAGE_ALERTS_STATUS_PAGE_TOKEN', ''),
    'auto_restart_enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_AUTO_RESTART_ENABLED', false), false),
    'auto_restart_max_attempts' => max(1, min(5, (int) env('RESOURCE_USAGE_ALERTS_AUTO_RESTART_MAX_ATTEMPTS', 2))),
    'auto_restart_cooldown_minutes' => max(5, (int) env('RESOURCE_USAGE_ALERTS_AUTO_RESTART_COOLDOWN', 30)),
    'discord_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_DISCORD_TIMEOUT', 5)),
    'telegram_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_TELEGRAM_TIMEOUT', 5)),
    'slack_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_SLACK_TIMEOUT', 5)),
    'custom_webhook_timeout_seconds' => max(1, (int) env('RESOURCE_USAGE_ALERTS_CUSTOM_WEBHOOK_TIMEOUT', 10)),
    'block_private_webhook_ips' => $boolean(env('RESOURCE_USAGE_ALERTS_BLOCK_PRIVATE_WEBHOOK_IPS', true), true),
    'custom_webhook_allowed_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('RESOURCE_USAGE_ALERTS_CUSTOM_WEBHOOK_ALLOWED_DOMAINS', ''))
    ))),
    'allow_user_rules' => $boolean(env('RESOURCE_USAGE_ALERTS_ALLOW_USER_RULES', true), true),
    'allow_user_channels' => $boolean(env('RESOURCE_USAGE_ALERTS_ALLOW_USER_CHANNELS', true), true),
    'global_discord_webhook' => env('RESOURCE_USAGE_ALERTS_GLOBAL_DISCORD_WEBHOOK'),
    'global_telegram_bot_token' => env('RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_BOT_TOKEN'),
    'global_telegram_chat_id' => env('RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_CHAT_ID'),
    'global_slack_webhook' => env('RESOURCE_USAGE_ALERTS_GLOBAL_SLACK_WEBHOOK'),
    'minimum_notification_severity' => env('RESOURCE_USAGE_ALERTS_MINIMUM_SEVERITY', 'info'),
    'push_enabled' => $boolean(env('RESOURCE_USAGE_ALERTS_PUSH_ENABLED', true), true),
    'max_push_subscriptions_per_user' => max(1, (int) env('RESOURCE_USAGE_ALERTS_MAX_PUSH_SUBSCRIPTIONS_PER_USER', 10)),
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
