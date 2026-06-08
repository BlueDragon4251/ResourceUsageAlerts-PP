<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Policies;

use App\Models\User;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;

class ResourceAlertChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewList resourceAlertChannel') || $this->userChannelsEnabled();
    }

    public function view(User $user, ResourceAlertChannel $channel): bool
    {
        return $channel->user_id === $user->id || $user->can('view resourceAlertChannel');
    }

    public function create(User $user): bool
    {
        return $this->userChannelsEnabled() || $user->can('create resourceAlertChannel');
    }

    public function update(User $user, ResourceAlertChannel $channel): bool
    {
        return $channel->user_id === $user->id || $user->can('update resourceAlertChannel');
    }

    public function delete(User $user, ResourceAlertChannel $channel): bool
    {
        return $channel->user_id === $user->id || $user->can('delete resourceAlertChannel');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete resourceAlertChannel');
    }

    private function userChannelsEnabled(): bool
    {
        return filter_var(
            config('resourceusagealerts.allow_user_channels', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }
}
