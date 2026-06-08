<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\Pages;

use Filament\Resources\Pages\ViewRecord;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\ResourceAlertEventResource;

class ViewResourceAlertEvent extends ViewRecord
{
    protected static string $resource = ResourceAlertEventResource::class;
}
