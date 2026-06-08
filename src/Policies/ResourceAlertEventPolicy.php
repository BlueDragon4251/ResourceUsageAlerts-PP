<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Policies;

use App\Models\User;
use App\Policies\DefaultAdminPolicies;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class ResourceAlertEventPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'resourceAlertEvent';

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ResourceAlertEvent $event): bool
    {
        return $user->can('update resourceAlertEvent');
    }
}
