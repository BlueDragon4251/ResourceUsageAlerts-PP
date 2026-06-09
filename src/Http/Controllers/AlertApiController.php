<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Http\Controllers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class AlertApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $query = ResourceAlertEvent::query()
            ->with(['rule', 'server', 'node'])
            ->latest('triggered_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->has('server_id')) {
            $query->where('server_id', $request->input('server_id'));
        }

        $events = $query->paginate($request->integer('per_page', 25));

        return response()->json($events);
    }

    public function show(int $id): JsonResponse
    {
        $event = ResourceAlertEvent::with(['rule', 'server', 'node', 'user'])->find($id);

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        return response()->json($event);
    }

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $event = ResourceAlertEvent::find($id);

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        if ($event->status !== AlertStatus::OPEN) {
            return response()->json(['error' => 'Event is not open'], 422);
        }

        $event->update([
            'status' => AlertStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['message' => 'Event acknowledged']);
    }

    public function resolve(int $id): JsonResponse
    {
        $event = ResourceAlertEvent::find($id);

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        if ($event->status === AlertStatus::RESOLVED) {
            return response()->json(['error' => 'Event is already resolved'], 422);
        }

        $event->update([
            'status' => AlertStatus::RESOLVED,
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Event resolved']);
    }

    public function rules(Request $request): JsonResponse
    {
        $query = ResourceAlertRule::query()
            ->with(['server', 'node', 'user']);

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        $rules = $query->get();

        return response()->json($rules);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'open' => ResourceAlertEvent::where('status', AlertStatus::OPEN)->count(),
            'acknowledged' => ResourceAlertEvent::where('status', AlertStatus::ACKNOWLEDGED)->count(),
            'resolved' => ResourceAlertEvent::where('status', AlertStatus::RESOLVED)->count(),
            'critical' => ResourceAlertEvent::where('status', AlertStatus::OPEN)->where('severity', AlertSeverity::CRITICAL)->count(),
            'warning' => ResourceAlertEvent::where('status', AlertStatus::OPEN)->where('severity', AlertSeverity::WARNING)->count(),
            'last_24h' => ResourceAlertEvent::where('triggered_at', '>=', now()->subDay())->count(),
            'active_rules' => ResourceAlertRule::where('enabled', true)->count(),
        ];

        return response()->json($stats);
    }
}