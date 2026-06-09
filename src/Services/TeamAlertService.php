<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class TeamAlertService
{
    /**
     * Get all rules visible to a user (based on their servers).
     */
    public function getRulesForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $serverIds = $user->servers()->pluck('id');
        $nodeIds = $user->servers()->pluck('node_id')->unique();

        return ResourceAlertRule::query()
            ->where(function ($query) use ($user, $serverIds, $nodeIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('server_id', $serverIds)
                    ->orWhereIn('node_id', $nodeIds);
            })
            ->get();
    }

    /**
     * Get all events visible to a user.
     */
    public function getEventsForUser(User $user, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        $serverIds = $user->servers()->pluck('id');

        $query = ResourceAlertEvent::query()
            ->with(['rule', 'server', 'node'])
            ->where(function ($query) use ($user, $serverIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('server_id', $serverIds);
            });

        if ($status) {
            $query->where('status', $status);
        }

        return $query->latest('triggered_at')->get();
    }

    /**
     * Get notification channels for a user.
     */
    public function getChannelsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ResourceAlertNotificationChannel::query()
            ->where('user_id', $user->id)
            ->get();
    }

    /**
     * Check if a user can manage a specific rule.
     */
    public function canManageRule(User $user, ResourceAlertRule $rule): bool
    {
        if ($user->isRootAdmin()) {
            return true;
        }

        if ($rule->user_id === $user->id) {
            return true;
        }

        if ($rule->server_id && $user->servers()->where('id', $rule->server_id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Get shared notification groups (team channels).
     */
    public function getSharedGroups(): array
    {
        return ResourceAlertNotificationChannel::query()
            ->where('config->shared', true)
            ->get()
            ->groupBy('config.team_name')
            ->toArray();
    }
}