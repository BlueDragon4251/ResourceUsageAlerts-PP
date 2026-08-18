<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMaintenanceWindows\Pages;

use Filament\Resources\Pages\ManageRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMaintenanceWindows\ResourceAlertMaintenanceWindowResource;

class ManageResourceAlertMaintenanceWindows extends ManageRecords
{
    protected static string $resource = ResourceAlertMaintenanceWindowResource::class;
}
