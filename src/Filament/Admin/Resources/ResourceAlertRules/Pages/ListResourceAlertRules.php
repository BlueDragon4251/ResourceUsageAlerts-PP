<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages;

use Filament\Resources\Pages\ListRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\ResourceAlertRuleResource;

class ListResourceAlertRules extends ListRecords
{
    protected static string $resource = ResourceAlertRuleResource::class;
}
