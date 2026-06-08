<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertPushSubscription;
use PelicanPlugins\ResourceUsageAlerts\Services\WebPushNotificationService;

class PushSubscriptionController extends Controller
{
    public function configuration(Request $request, WebPushNotificationService $push): JsonResponse
    {
        $configured = $push->isConfigured();

        return response()->json([
            'enabled' => $configured,
            'publicKey' => $configured ? config('resourceusagealerts.vapid_public_key') : null,
            'subscribed' => $this->subscriptionsAvailable()
                && ResourceAlertPushSubscription::query()->where('user_id', $request->user()->id)->exists(),
        ]);
    }

    public function store(Request $request, WebPushNotificationService $push): JsonResponse
    {
        abort_unless($push->isConfigured() && $this->subscriptionsAvailable(), 503);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'expirationTime' => ['nullable', 'numeric'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:1024'],
            'keys.auth' => ['required', 'string', 'max:1024'],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        ResourceAlertPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'subscription' => $data,
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
                'failure_count' => 0,
            ]
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (!$this->subscriptionsAvailable()) {
            return response()->json(['subscribed' => false]);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
        ]);

        ResourceAlertPushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    public function test(Request $request, WebPushNotificationService $push): JsonResponse
    {
        if (!$push->isConfigured()) {
            return response()->json([
                'sent' => false,
                'reason' => 'not_configured',
                'message' => trans('resourceusagealerts::strings.push.not_configured'),
            ], 503);
        }

        if (!$this->subscriptionsAvailable()) {
            return response()->json([
                'sent' => false,
                'reason' => 'migration_missing',
                'message' => trans('resourceusagealerts::strings.push.migration_missing'),
            ], 503);
        }

        $subscriptions = ResourceAlertPushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return response()->json([
                'sent' => false,
                'reason' => 'subscription_missing',
                'message' => trans('resourceusagealerts::strings.push.subscription_missing'),
            ], 422);
        }

        $results = [];
        foreach ($subscriptions as $subscription) {
            $results[] = $push->sendWithResult($subscription, [
                'title' => trans('resourceusagealerts::strings.push.test_title'),
                'body' => trans('resourceusagealerts::strings.push.test_body'),
                'icon' => '/favicon.ico',
                'url' => '/',
                'tag' => 'resource-alert-test',
            ]);
        }

        $sent = collect($results)->contains(fn (array $result): bool => $result['sent']);
        $failure = collect($results)->first(fn (array $result): bool => !$result['sent']);

        return response()->json([
            'sent' => $sent,
            'reason' => $sent ? 'sent' : ($failure['reason'] ?? 'delivery_failed'),
            'providerStatus' => $failure['status'] ?? null,
            'message' => $sent
                ? trans('resourceusagealerts::strings.push.test_sent')
                : $this->failureMessage($failure['reason'] ?? 'delivery_failed', $failure['status'] ?? null),
        ], $sent ? 200 : 502);
    }

    private function subscriptionsAvailable(): bool
    {
        return Schema::hasTable('resource_alert_push_subscriptions');
    }

    private function failureMessage(string $reason, ?int $status): string
    {
        return match ($reason) {
            'subscription_expired' => trans('resourceusagealerts::strings.push.subscription_expired'),
            'subscription_invalid' => trans('resourceusagealerts::strings.push.subscription_invalid'),
            'provider_rejected' => trans('resourceusagealerts::strings.push.provider_rejected', [
                'status' => $status ?? trans('resourceusagealerts::strings.push.unknown_status'),
            ]),
            default => trans('resourceusagealerts::strings.push.delivery_failed'),
        };
    }
}
