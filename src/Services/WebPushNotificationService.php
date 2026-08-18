<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertPushSubscription;
use Throwable;

class WebPushNotificationService
{
    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(Collection $users, array $payload): void
    {
        if (! $this->isConfigured() || $users->isEmpty()) {
            return;
        }

        ResourceAlertPushSubscription::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->chunkById(100, function ($subscriptions) use ($payload): void {
                foreach ($subscriptions as $subscription) {
                    $this->send($subscription, $payload);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(ResourceAlertPushSubscription $stored, array $payload): bool
    {
        return $this->sendWithResult($stored, $payload)['sent'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sent: bool, reason: string, status: ?int}
     */
    public function sendWithResult(ResourceAlertPushSubscription $stored, array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['sent' => false, 'reason' => 'not_configured', 'status' => null];
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('resourceusagealerts.vapid_subject'),
                    'publicKey' => (string) config('resourceusagealerts.vapid_public_key'),
                    'privateKey' => $this->privateKey(),
                ],
            ]);

            $report = $webPush->sendOneNotification(
                Subscription::create($this->normalizeSubscription($stored->subscription)),
                json_encode($payload, JSON_THROW_ON_ERROR)
            );

            if ($report->isSuccess()) {
                $stored->forceFill([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                ])->save();

                return ['sent' => true, 'reason' => 'sent', 'status' => $this->responseStatus($report)];
            }

            if ($report->isSubscriptionExpired()) {
                $stored->delete();
            } else {
                $stored->increment('failure_count');
            }

            $status = $this->responseStatus($report);
            Log::warning('Resource Usage Alerts push provider rejected a notification.', [
                'subscription_id' => $stored->id,
                'status' => $status,
                'expired' => $report->isSubscriptionExpired(),
            ]);

            return [
                'sent' => false,
                'reason' => $report->isSubscriptionExpired() ? 'subscription_expired' : 'provider_rejected',
                'status' => $status,
            ];
        } catch (Throwable $exception) {
            $stored->increment('failure_count');
            Log::warning('Resource Usage Alerts could not deliver a browser push notification.', [
                'subscription_id' => $stored->id,
                'exception' => $exception::class,
            ]);

            return [
                'sent' => false,
                'reason' => $exception instanceof \InvalidArgumentException ? 'subscription_invalid' : 'delivery_exception',
                'status' => null,
            ];
        }
    }

    public function isConfigured(): bool
    {
        return filter_var(config('resourceusagealerts.push_enabled', true), FILTER_VALIDATE_BOOLEAN)
            && class_exists(WebPush::class)
            && filled(config('resourceusagealerts.vapid_subject'))
            && filled(config('resourceusagealerts.vapid_public_key'))
            && filled(config('resourceusagealerts.vapid_private_key'));
    }

    private function privateKey(): string
    {
        $key = (string) config('resourceusagealerts.vapid_private_key', '');

        return str_starts_with($key, 'encrypted:')
            ? Crypt::decryptString(substr($key, 10))
            : $key;
    }

    /**
     * Browsers expose keys in the PushSubscription JSON shape, while web-push-php
     * expects its own normalized names.
     *
     * @param  array<string, mixed>  $subscription
     * @return array<string, mixed>
     */
    private function normalizeSubscription(array $subscription): array
    {
        return [
            'endpoint' => $subscription['endpoint'] ?? null,
            'publicKey' => $subscription['publicKey'] ?? data_get($subscription, 'keys.p256dh'),
            'authToken' => $subscription['authToken'] ?? data_get($subscription, 'keys.auth'),
            'contentEncoding' => $subscription['contentEncoding'] ?? 'aes128gcm',
        ];
    }

    private function responseStatus(object $report): ?int
    {
        if (! method_exists($report, 'getResponse')) {
            return null;
        }

        $response = $report->getResponse();

        return is_object($response) && method_exists($response, 'getStatusCode')
            ? $response->getStatusCode()
            : null;
    }
}
