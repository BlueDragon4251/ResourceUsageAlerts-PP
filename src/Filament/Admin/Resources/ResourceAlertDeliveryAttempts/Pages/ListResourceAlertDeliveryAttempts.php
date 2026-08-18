<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertDeliveryAttempts\Pages;

use Filament\Resources\Pages\ListRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertDeliveryAttempts\ResourceAlertDeliveryAttemptResource;

class ListResourceAlertDeliveryAttempts extends ListRecords
{
    protected static string $resource = ResourceAlertDeliveryAttemptResource::class;
}
