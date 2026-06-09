<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class OnCallRotationService
{
    /**
     * Get the current on-call user for a given rule.
     */
    public function getOnCallUser(ResourceAlertRule $rule): ?User
    {
        $rotation = $rule->config['on_call_rotation'] ?? null;
        if (!$rotation || !is_array($rotation) || empty($rotation['users'])) {
            return null;
        }

        $users = $rotation['users'];
        $rotationMinutes = (int) ($rotation['rotation_minutes'] ?? 60);

        if (empty($users)) {
            return null;
        }

        $now = Carbon::now();
        $startOfDay = $now->copy()->startOfDay();
        $minutesSinceStart = $startOfDay->diffInMinutes($now);
        $currentIndex = (int) floor($minutesSinceStart / $rotationMinutes) % count($users);

        $userId = $users[$currentIndex] ?? null;
        if (!$userId) {
            return null;
        }

        return User::find($userId);
    }

    /**
     * Notify the current on-call user about an event.
     */
    public function notifyOnCall(ResourceAlertEvent $event): void
    {
        $onCallUser = $this->getOnCallUser($event->rule);
        if (!$onCallUser) {
            return;
        }

        // Send notification to on-call user
        // This could use Mail, notification channels, etc.
    }

    /**
     * Check if on-call rotation is configured for a rule.
     */
    public function hasRotation(ResourceAlertRule $rule): bool
    {
        $rotation = $rule->config['on_call_rotation'] ?? null;
        return is_array($rotation) && !empty($rotation['users']);
    }
}