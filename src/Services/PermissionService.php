<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\Server;
use App\Models\User;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class PermissionService
{
    public function canViewServerAlerts(User $user, Server $server): bool
    {
        return $server->owner_id === $user->id
            || $user->can('alerts.view', $server)
            || $user->can('view resourceAlertEvent');
    }

    public function canCreateServerRule(User $user, Server $server): bool
    {
        if (! $this->booleanConfig('resourceusagealerts.allow_user_rules', true)) {
            return false;
        }

        return $server->owner_id === $user->id || $user->can('alerts.create', $server);
    }

    public function canUpdateServerRule(User $user, ResourceAlertRule $rule, Server $server): bool
    {
        return $server->owner_id === $user->id || $user->can('alerts.update', $server);
    }

    public function canDeleteServerRule(User $user, ResourceAlertRule $rule, Server $server): bool
    {
        return $server->owner_id === $user->id || $user->can('alerts.delete', $server);
    }

    public function canManageChannel(User $user, ResourceAlertChannel $channel): bool
    {
        return $this->booleanConfig('resourceusagealerts.allow_user_channels', true)
            && ($channel->user_id === $user->id || $user->can('update resourceAlertChannel'));
    }

    private function booleanConfig(string $key, bool $default): bool
    {
        return filter_var(config($key, $default), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
