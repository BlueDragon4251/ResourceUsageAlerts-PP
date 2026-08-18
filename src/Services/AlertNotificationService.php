<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationGroup;
use PelicanPlugins\ResourceUsageAlerts\Notifications\ResourceAlertMailNotification;
use Throwable;

class AlertNotificationService
{
    public function __construct(
        private readonly AlertMessageFormatter $formatter,
        private readonly WebPushNotificationService $webPush,
        private readonly OutboundEndpointGuard $endpointGuard,
        private readonly DeliveryTrackerService $deliveryTracker,
        private readonly OnCallRotationService $onCallRotation
    ) {}

    public function sendTriggered(ResourceAlertEvent $event): void
    {
        $this->send($event, false);
    }

    public function sendResolved(ResourceAlertEvent $event): void
    {
        $this->send($event, true);
    }

    public function sendToPanel(ResourceAlertEvent $event, bool $resolved = false): void
    {
        $originalLocale = app()->getLocale();
        try {
            foreach ($this->recipients($event) as $recipient) {
                app()->setLocale(str_starts_with(strtolower((string) $recipient->language), 'de') ? 'de' : 'en');
                $title = $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event);
                $body = $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event);
                Notification::make()
                    ->status($resolved ? 'success' : $event->severity->filamentStatus())
                    ->title($title)
                    ->body($body)
                    ->sendToDatabase($recipient);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
        $this->deliveryTracker->record($event, 'panel', 'sent');
    }

    public function sendToDiscord(ResourceAlertEvent $event, string $webhookUrl, bool $resolved = false, ?int $channelId = null): void
    {
        $startedAt = microtime(true);
        try {
            $this->endpointGuard->assertAllowed($webhookUrl, ['discord.com', 'discordapp.com']);
            $response = Http::connectTimeout(2)
                ->timeout((int) config('resourceusagealerts.discord_timeout_seconds', 5))
                ->retry(2, 250, throw: false)
                ->post($webhookUrl, $this->formatter->discordPayload($event, $resolved))
                ->throw();
            $this->deliveryTracker->record($event, 'discord', 'sent', $channelId, $response->status(), null, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, 'discord', 'failed', $channelId, null, $exception::class, (int) ((microtime(true) - $startedAt) * 1000));
            Log::warning('Resource Usage Alerts could not deliver a Discord notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendToTelegram(ResourceAlertEvent $event, string $botToken, string $chatId, bool $resolved = false, ?int $channelId = null): void
    {
        $startedAt = microtime(true);
        try {
            $response = Http::connectTimeout(2)
                ->timeout((int) config('resourceusagealerts.telegram_timeout_seconds', 5))
                ->retry(2, 250, throw: false)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->formatter->telegramPayload($event, $resolved),
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                ])
                ->throw();
            $this->deliveryTracker->record($event, 'telegram', 'sent', $channelId, $response->status(), null, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, 'telegram', 'failed', $channelId, null, $exception::class, (int) ((microtime(true) - $startedAt) * 1000));
            Log::warning('Resource Usage Alerts could not deliver a Telegram notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendToSlack(ResourceAlertEvent $event, string $webhookUrl, bool $resolved = false, ?int $channelId = null): void
    {
        $startedAt = microtime(true);
        try {
            $this->endpointGuard->assertAllowed($webhookUrl, ['hooks.slack.com']);
            $response = Http::connectTimeout(2)
                ->timeout((int) config('resourceusagealerts.slack_timeout_seconds', 5))
                ->retry(2, 250, throw: false)
                ->post($webhookUrl, $this->formatter->slackPayload($event, $resolved))
                ->throw();
            $this->deliveryTracker->record($event, 'slack', 'sent', $channelId, $response->status(), null, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, 'slack', 'failed', $channelId, null, $exception::class, (int) ((microtime(true) - $startedAt) * 1000));
            Log::warning('Resource Usage Alerts could not deliver a Slack notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    /** @param array<string, mixed> $target */
    public function sendToCustomWebhook(ResourceAlertEvent $event, array $target, bool $resolved = false): void
    {
        $url = (string) ($target['url'] ?? '');
        $secret = (string) ($target['secret'] ?? '');
        if ($url === '' || strlen($secret) < 32) {
            return;
        }

        $startedAt = microtime(true);
        try {
            $allowedDomains = array_values(array_filter(array_map(
                'strval',
                (array) config('resourceusagealerts.custom_webhook_allowed_domains', [])
            )));
            $this->endpointGuard->assertAllowed($url, $allowedDomains);

            $timestamp = (string) now()->timestamp;
            $nonce = bin2hex(random_bytes(16));
            $payload = [
                'event_id' => $event->id,
                'type' => $resolved ? 'resolved' : 'triggered',
                'status' => $event->status->value,
                'severity' => $event->severity->value,
                'metric' => $event->metric->value,
                'title' => $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event),
                'body' => $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event),
                'server_id' => $event->server_id,
                'node_id' => $event->node_id,
                'occurred_at' => now()->toIso8601String(),
            ];
            $template = (string) ($target['payload_template'] ?? '');
            if ($template !== '') {
                $payload = $this->renderWebhookTemplate($template, $payload);
            }
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $signed = $timestamp.'.'.$nonce.'.'.$body;
            $headers = [
                'X-Resource-Alerts-Timestamp' => $timestamp,
                'X-Resource-Alerts-Nonce' => $nonce,
                'X-Resource-Alerts-Signature' => 'sha256='.hash_hmac('sha256', $signed, $secret),
            ];
            $previousSecret = (string) ($target['previous_secret'] ?? '');
            if (strlen($previousSecret) >= 32) {
                $headers['X-Resource-Alerts-Previous-Signature'] = 'sha256='.hash_hmac('sha256', $signed, $previousSecret);
            }

            $response = Http::connectTimeout(2)
                ->timeout((int) config('resourceusagealerts.custom_webhook_timeout_seconds', 10))
                ->retry(2, 250, throw: false)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url)
                ->throw();
            $this->deliveryTracker->record($event, 'custom_webhook', 'sent', isset($target['channel_id']) ? (int) $target['channel_id'] : null, $response->status(), null, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, 'custom_webhook', 'failed', isset($target['channel_id']) ? (int) $target['channel_id'] : null, null, $exception::class, (int) ((microtime(true) - $startedAt) * 1000));
            Log::warning('Resource Usage Alerts could not deliver a signed custom webhook.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendToEmail(ResourceAlertEvent $event, string $email, bool $resolved = false): void
    {
        if (! config('mail.default') || config('mail.default') === 'log') {
            return;
        }

        try {
            $title = $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event);
            $body = $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event);

            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new ResourceAlertMailNotification($title, $body));
            $this->deliveryTracker->record($event, 'email', 'sent');
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, 'email', 'failed', null, null, $exception::class);
            Log::warning('Resource Usage Alerts could not deliver an email notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    /** @param array<string, mixed> $target */
    public function sendToSelfHostedPush(ResourceAlertEvent $event, AlertChannelType $type, array $target, bool $resolved = false): void
    {
        $startedAt = microtime(true);
        $channelId = isset($target['channel_id']) ? (int) $target['channel_id'] : null;
        $url = rtrim((string) ($target['endpoint_url'] ?? ''), '/');
        $token = (string) ($target['api_token'] ?? '');
        $title = $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event);
        $body = $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event);

        try {
            $this->endpointGuard->assertAllowed($url);
            $request = Http::connectTimeout(2)->timeout(10)->retry(2, 250, throw: false);
            $response = match ($type) {
                AlertChannelType::NTFY => $request->withHeaders(array_filter([
                    'Authorization' => $token !== '' ? 'Bearer '.$token : null,
                    'Title' => $title,
                    'Priority' => $event->severity->value === 'critical' ? 'urgent' : 'default',
                ]))->withBody($body, 'text/plain')->post($url.'/'.rawurlencode((string) ($target['topic'] ?? 'pelican-alerts'))),
                AlertChannelType::GOTIFY => $request->post($url.'/message?token='.rawurlencode($token), [
                    'title' => $title, 'message' => $body, 'priority' => $event->severity->value === 'critical' ? 10 : 5,
                ]),
                AlertChannelType::MATRIX => $request->withToken($token)->put(
                    $url.'/_matrix/client/v3/rooms/'.rawurlencode((string) ($target['room_id'] ?? '')).'/send/m.room.message/'.bin2hex(random_bytes(8)),
                    ['msgtype' => 'm.text', 'body' => $title."\n\n".$body]
                ),
                default => throw new \InvalidArgumentException('Unsupported self-hosted notification channel.'),
            };
            $response->throw();
            $this->deliveryTracker->record($event, $type->value, 'sent', $channelId, $response->status(), null, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $exception) {
            $this->deliveryTracker->record($event, $type->value, 'failed', $channelId, null, $exception::class, (int) ((microtime(true) - $startedAt) * 1000));
            Log::warning('Resource Usage Alerts could not deliver a self-hosted notification.', ['event_id' => $event->id, 'channel' => $type->value, 'exception' => $exception::class]);
        }
    }

    private function send(ResourceAlertEvent $event, bool $resolved): void
    {
        $event->loadMissing(['rule', 'server.user', 'server.subusers.user', 'node', 'user']);

        $minimum = AlertSeverity::tryFrom((string) config('resourceusagealerts.minimum_notification_severity', 'info')) ?? AlertSeverity::INFO;
        if (! $resolved && $event->severity->weight() < $minimum->weight()) {
            return;
        }

        $channels = $event->rule->channels ?: config('resourceusagealerts.default_channels', ['panel']);
        if (($event->context['escalated'] ?? false) === true) {
            $channels = array_values(array_unique(array_merge(
                $channels,
                (array) data_get($event->rule->config, 'escalation_channels', [])
            )));
        }
        if (in_array(AlertChannelType::PANEL->value, $channels, true)) {
            $this->sendToPanel($event, $resolved);
        }

        if (in_array(AlertChannelType::EMAIL->value, $channels, true)) {
            foreach ($this->emailAddresses($event) as $email) {
                $this->sendToEmail($event, $email, $resolved);
            }
        }

        if (in_array(AlertChannelType::DISCORD->value, $channels, true)) {
            foreach ($this->discordWebhooks($event) as $webhook) {
                $this->sendToDiscord($event, (string) $webhook['url'], $resolved, $webhook['channel_id']);
            }
        }

        if (in_array(AlertChannelType::TELEGRAM->value, $channels, true)) {
            foreach ($this->telegramTargets($event) as $target) {
                $this->sendToTelegram($event, (string) $target['bot_token'], (string) $target['chat_id'], $resolved, $target['channel_id']);
            }
        }

        if (in_array(AlertChannelType::SLACK->value, $channels, true)) {
            foreach ($this->slackWebhooks($event) as $webhook) {
                $this->sendToSlack($event, (string) $webhook['url'], $resolved, $webhook['channel_id']);
            }
        }

        if (in_array(AlertChannelType::CUSTOM_WEBHOOK->value, $channels, true)) {
            foreach ($this->customWebhookTargets($event) as $target) {
                $this->sendToCustomWebhook($event, $target, $resolved);
            }
        }

        if (in_array(AlertChannelType::PUSH->value, $channels, true)) {
            $this->webPush->sendToUsers(
                $this->recipients($event),
                $this->formatter->pushPayload($event, $resolved)
            );
            $this->deliveryTracker->record($event, 'push', 'queued');
        }

        foreach ([AlertChannelType::NTFY, AlertChannelType::GOTIFY, AlertChannelType::MATRIX] as $type) {
            if (in_array($type->value, $channels, true)) {
                foreach ($this->selfHostedTargets($event, $type) as $target) {
                    $this->sendToSelfHostedPush($event, $type, $target, $resolved);
                }
            }
        }

        $event->forceFill([
            'last_notified_at' => now(),
            'notification_count' => $event->notification_count + 1,
            'message' => $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event),
        ])->save();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(ResourceAlertEvent $event): Collection
    {
        if ($event->rule->scope === AlertScope::GLOBAL || $event->rule->scope === AlertScope::NODE) {
            $recipients = User::permission('receive resourceAlertEvent')->get();
        } elseif ($event->rule->scope === AlertScope::USER && $event->rule->user) {
            $recipients = collect([$event->rule->user]);
        } elseif ($event->server) {
            $recipients = collect([$event->server->user]);
            foreach ($event->server->subusers as $subuser) {
                if (in_array('alerts.receive', $subuser->permissions ?? [], true)) {
                    $recipients->push($subuser->user);
                }
            }
        } else {
            $recipients = collect();
        }

        $groupUserIds = $this->notificationGroups($event)
            ->flatMap(fn (ResourceAlertNotificationGroup $group): array => (array) $group->recipient_user_ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter();
        if ($groupUserIds->isNotEmpty()) {
            $recipients = $recipients->merge(User::query()->whereKey($groupUserIds)->get());
        }

        $onCall = $this->onCallRotation->getOnCallUser($event->rule);
        if ($onCall) {
            $recipients->push($onCall);
        }

        return $recipients->filter()->unique('id')->values();
    }

    /**
     * @return Collection<int, array{url: string, channel_id: ?int}>
     */
    private function discordWebhooks(ResourceAlertEvent $event): Collection
    {
        $webhooks = collect();
        $global = (string) config('resourceusagealerts.global_discord_webhook', '');

        if ($global !== '') {
            try {
                $webhooks->push(['url' => str_starts_with($global, 'encrypted:')
                    ? Crypt::decryptString(substr($global, 10))
                    : $global, 'channel_id' => null]);
            } catch (Throwable) {
                Log::warning('Resource Usage Alerts global Discord webhook could not be decrypted.');
            }
        }

        $recipientIds = $this->recipients($event)->pluck('id');
        ResourceAlertChannel::query()
            ->enabled()
            ->forEvent($event)
            ->where('type', AlertChannelType::DISCORD)
            ->where(function ($query) use ($recipientIds, $event): void {
                $query->whereIn('user_id', $recipientIds)->orWhereIn('id', $this->groupChannelIds($event));
            })
            ->get()
            ->filter(fn (ResourceAlertChannel $channel): bool => $this->deliveryTracker->allowed($event, $channel))
            ->each(function (ResourceAlertChannel $channel) use ($webhooks): void {
                $url = data_get($channel->config, 'webhook_url');
                if (is_string($url) && $url !== '') {
                    $webhooks->push(['url' => $url, 'channel_id' => $channel->id]);
                }
            });

        return $webhooks->filter()->unique('url')->values();
    }

    /**
     * @return Collection<int, array{bot_token: string, chat_id: string, channel_id: ?int}>
     */
    private function telegramTargets(ResourceAlertEvent $event): Collection
    {
        $targets = collect();
        $globalBotToken = (string) config('resourceusagealerts.global_telegram_bot_token', '');
        $globalChatId = (string) config('resourceusagealerts.global_telegram_chat_id', '');

        if ($globalBotToken !== '' && $globalChatId !== '') {
            try {
                $targets->push([
                    'bot_token' => str_starts_with($globalBotToken, 'encrypted:')
                        ? Crypt::decryptString(substr($globalBotToken, 10))
                        : $globalBotToken,
                    'chat_id' => str_starts_with($globalChatId, 'encrypted:')
                        ? Crypt::decryptString(substr($globalChatId, 10))
                        : $globalChatId,
                    'channel_id' => null,
                ]);
            } catch (Throwable) {
                Log::warning('Resource Usage Alerts global Telegram credentials could not be decrypted.');
            }
        }

        $recipientIds = $this->recipients($event)->pluck('id');
        ResourceAlertChannel::query()
            ->enabled()
            ->forEvent($event)
            ->where('type', AlertChannelType::TELEGRAM)
            ->where(function ($query) use ($recipientIds, $event): void {
                $query->whereIn('user_id', $recipientIds)->orWhereIn('id', $this->groupChannelIds($event));
            })
            ->get()
            ->filter(fn (ResourceAlertChannel $channel): bool => $this->deliveryTracker->allowed($event, $channel))
            ->each(function (ResourceAlertChannel $channel) use ($targets): void {
                $botToken = data_get($channel->config, 'bot_token');
                $chatId = data_get($channel->config, 'chat_id');

                if (is_string($botToken) && $botToken !== '' && is_string($chatId) && $chatId !== '') {
                    $targets->push([
                        'bot_token' => $botToken,
                        'chat_id' => $chatId,
                        'channel_id' => $channel->id,
                    ]);
                }
            });

        return $targets->unique(fn (array $target): string => $target['bot_token'].':'.$target['chat_id'])->values();
    }

    /**
     * @return Collection<int, array{url: string, channel_id: ?int}>
     */
    private function slackWebhooks(ResourceAlertEvent $event): Collection
    {
        $webhooks = collect();
        $global = (string) config('resourceusagealerts.global_slack_webhook', '');

        if ($global !== '') {
            try {
                $webhooks->push(['url' => str_starts_with($global, 'encrypted:')
                    ? Crypt::decryptString(substr($global, 10))
                    : $global, 'channel_id' => null]);
            } catch (Throwable) {
                Log::warning('Resource Usage Alerts global Slack webhook could not be decrypted.');
            }
        }

        $recipientIds = $this->recipients($event)->pluck('id');
        ResourceAlertChannel::query()
            ->enabled()
            ->forEvent($event)
            ->where('type', AlertChannelType::SLACK)
            ->where(function ($query) use ($recipientIds, $event): void {
                $query->whereIn('user_id', $recipientIds)->orWhereIn('id', $this->groupChannelIds($event));
            })
            ->get()
            ->filter(fn (ResourceAlertChannel $channel): bool => $this->deliveryTracker->allowed($event, $channel))
            ->each(function (ResourceAlertChannel $channel) use ($webhooks): void {
                $url = data_get($channel->config, 'webhook_url');
                if (is_string($url) && $url !== '') {
                    $webhooks->push(['url' => $url, 'channel_id' => $channel->id]);
                }
            });

        return $webhooks->filter()->unique('url')->values();
    }

    /** @return Collection<int, array{url: string, secret: string, previous_secret: string, channel_id: int}> */
    private function customWebhookTargets(ResourceAlertEvent $event): Collection
    {
        $targets = collect();
        $recipientIds = $this->recipients($event)->pluck('id');

        ResourceAlertChannel::query()
            ->enabled()
            ->forEvent($event)
            ->where('type', AlertChannelType::CUSTOM_WEBHOOK)
            ->where(function ($query) use ($recipientIds, $event): void {
                $query->whereIn('user_id', $recipientIds)->orWhereIn('id', $this->groupChannelIds($event));
            })
            ->get()
            ->filter(fn (ResourceAlertChannel $channel): bool => $this->deliveryTracker->allowed($event, $channel))
            ->each(function (ResourceAlertChannel $channel) use ($targets): void {
                $url = data_get($channel->config, 'webhook_url');
                $secret = data_get($channel->config, 'signing_secret');
                if (is_string($url) && $url !== '' && is_string($secret) && strlen($secret) >= 32) {
                    $targets->push([
                        'url' => $url,
                        'secret' => $secret,
                        'previous_secret' => (string) data_get($channel->config, 'previous_signing_secret', ''),
                        'payload_template' => (string) data_get($channel->config, 'payload_template', ''),
                        'channel_id' => $channel->id,
                    ]);
                }
            });

        return $targets->unique('url')->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function emailAddresses(ResourceAlertEvent $event): Collection
    {
        $recipients = $this->recipients($event);
        $emails = $recipients->pluck('email');

        ResourceAlertChannel::query()
            ->enabled()
            ->forEvent($event)
            ->where('type', AlertChannelType::EMAIL)
            ->whereIn('user_id', $recipients->pluck('id'))
            ->get()
            ->each(function (ResourceAlertChannel $channel) use ($emails): void {
                $email = data_get($channel->config, 'email');
                if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails->push($email);
                }
            });

        return $emails->filter()->unique()->values();
    }

    /** @return Collection<int, ResourceAlertNotificationGroup> */
    private function notificationGroups(ResourceAlertEvent $event): Collection
    {
        $ids = array_values(array_filter(array_map(
            'intval',
            (array) data_get($event->rule->config, 'notification_group_ids', [])
        )));

        return $ids === [] ? collect() : ResourceAlertNotificationGroup::query()->whereKey($ids)->get();
    }

    /** @return array<int, int> */
    private function groupChannelIds(ResourceAlertEvent $event): array
    {
        return $this->notificationGroups($event)
            ->flatMap(fn (ResourceAlertNotificationGroup $group): array => (array) $group->channel_ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function selfHostedTargets(ResourceAlertEvent $event, AlertChannelType $type): Collection
    {
        $recipientIds = $this->recipients($event)->pluck('id');

        return ResourceAlertChannel::query()
            ->enabled()->forEvent($event)->where('type', $type)
            ->where(function ($query) use ($recipientIds, $event): void {
                $query->whereIn('user_id', $recipientIds)->orWhereIn('id', $this->groupChannelIds($event));
            })
            ->get()
            ->filter(fn (ResourceAlertChannel $channel): bool => $this->deliveryTracker->allowed($event, $channel))
            ->map(fn (ResourceAlertChannel $channel): array => array_merge($channel->config ?? [], ['channel_id' => $channel->id]))
            ->values();
    }

    /** @param array<string, mixed> $variables @return array<string, mixed> */
    private function renderWebhookTemplate(string $template, array $variables): array
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $template = str_replace('{{'.$key.'}}', addcslashes((string) $value, "\\\"\n\r\t"), $template);
            }
        }

        $decoded = json_decode($template, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Custom webhook payload template must be valid JSON.');
        }

        return $decoded;
    }
}
