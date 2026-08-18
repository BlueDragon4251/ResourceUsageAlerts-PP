<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertStatusPageController extends Controller
{
    public function html(?string $token = null): Response
    {
        $this->authorizeAccess($token);
        $open = ResourceAlertEvent::query()->with(['server', 'node'])->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])->latest('triggered_at')->get();
        $recent = ResourceAlertEvent::query()->with(['server', 'node'])->where('triggered_at', '>=', now()->subDays(30))->latest('triggered_at')->limit(100)->get();

        return response()->view('resourceusagealerts::status-page', [
            'open' => $open,
            'recent' => $recent,
            'operational' => $open->isEmpty(),
            'feedUrl' => route('resourceusagealerts.status.feed', ['token' => $token]),
        ]);
    }

    public function feed(?string $token = null): Response
    {
        $this->authorizeAccess($token);
        $events = ResourceAlertEvent::query()->with(['server', 'node'])->latest('triggered_at')->limit(50)->get();

        return response()->view('resourceusagealerts::status-feed', compact('events', 'token'))->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }

    private function authorizeAccess(?string $token): void
    {
        abort_unless((bool) config('resourceusagealerts.status_page_enabled', false), 404);
        $required = (string) config('resourceusagealerts.status_page_token', '');
        abort_if($required !== '' && ! hash_equals($required, (string) $token), 403);
    }
}
