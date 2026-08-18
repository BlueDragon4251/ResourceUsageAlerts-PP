<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertNotificationGroups\Pages;

use Filament\Resources\Pages\ManageRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertNotificationGroups\ResourceAlertNotificationGroupResource;

class ManageResourceAlertNotificationGroups extends ManageRecords
{
    protected static string $resource = ResourceAlertNotificationGroupResource::class;
}
