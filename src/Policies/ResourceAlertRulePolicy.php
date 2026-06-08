<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Policies;

use App\Policies\DefaultAdminPolicies;

class ResourceAlertRulePolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'resourceAlertRule';
}
