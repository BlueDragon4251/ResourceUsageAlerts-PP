<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Notifications\ResourceAlertMailNotification;
use Throwable;

class AlertNotificationService
{
    public function __construct(
        private readonly AlertMessageFormatter $formatter,
        private readonly WebPushNotificationService $webPush
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
        $title = $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event);
        $body = $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event);

        foreach ($this->recipients($event) as $recipient) {
            Notification::make()
                ->status($resolved ? 'success' : $event->severity->filamentStatus())
                ->title($title)
                ->body($body)
                ->sendToDatabase($recipient);
        }
    }

    public function sendToDiscord(ResourceAlertEvent $event, string $webhookUrl, bool $resolved = false): void
    {
        try {
            Http::connectTimeout(2)
                ->timeout((int) config('resourceusagealerts.discord_timeout_seconds', 5))
                ->retry(2, 250, throw: false)
                ->post($webhookUrl, $this->formatter->discordPayload($event, $resolved))
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('Resource Usage Alerts could not deliver a Discord notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendToEmail(ResourceAlertEvent $event, string $email, bool $resolved = false): void
    {
        if (!config('mail.default') || config('mail.default') === 'log') {
            return;
        }

        try {
            $title = $resolved ? $this->formatter->resolvedTitle($event) : $this->formatter->triggeredTitle($event);
            $body = $resolved ? $this->formatter->resolvedBody($event) : $this->formatter->triggeredBody($event);

            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new ResourceAlertMailNotification($title, $body));
        } catch (Throwable $exception) {
            Log::warning('Resource Usage Alerts could not deliver an email notification.', [
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function send(ResourceAlertEvent $event, bool $resolved): void
    {
        $event->loadMissing(['rule', 'server.user', 'server.subusers.user', 'node', 'user']);

        $minimum = AlertSeverity::tryFrom((string) config('resourceusagealerts.minimum_notification_severity', 'info')) ?? AlertSeverity::INFO;
        if (!$resolved && $event->severity->weight() < $minimum->weight()) {
            return;
        }

        $channels = $event->rule->channels ?: config('resourceusagealerts.default_channels', ['panel']);
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
                $this->sendToDiscord($event, $webhook, $resolved);
            }
        }

        if (in_array(AlertChannelType::PUSH->value, $channels, true)) {
            $this->webPush->sendToUsers(
                $this->recipients($event),
                $this->formatter->pushPayload($event, $resolved)
            );
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
            return User::permission('receive resourceAlertEvent')->get();
        }

        if ($event->rule->scope === AlertScope::USER && $event->rule->user) {
            return collect([$event->rule->user]);
        }

        if (!$event->server) {
            return collect();
        }

        $recipients = collect([$event->server->user]);
        foreach ($event->server->subusers as $subuser) {
            if (in_array('alerts.receive', $subuser->permissions ?? [], true)) {
                $recipients->push($subuser->user);
            }
        }

        return $recipients->filter()->unique('id')->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function discordWebhooks(ResourceAlertEvent $event): Collection
    {
        $webhooks = collect();
        $global = (string) config('resourceusagealerts.global_discord_webhook', '');

        if ($global !== '') {
            try {
                $webhooks->push(str_starts_with($global, 'encrypted:')
                    ? Crypt::decryptString(substr($global, 10))
                    : $global);
            } catch (Throwable) {
                Log::warning('Resource Usage Alerts global Discord webhook could not be decrypted.');
            }
        }

        $recipientIds = $this->recipients($event)->pluck('id');
        ResourceAlertChannel::query()
            ->enabled()
            ->where('type', AlertChannelType::DISCORD)
            ->whereIn('user_id', $recipientIds)
            ->get()
            ->each(function (ResourceAlertChannel $channel) use ($webhooks): void {
                $url = data_get($channel->config, 'webhook_url');
                if (is_string($url) && $url !== '') {
                    $webhooks->push($url);
                }
            });

        return $webhooks->filter()->unique()->values();
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
}
