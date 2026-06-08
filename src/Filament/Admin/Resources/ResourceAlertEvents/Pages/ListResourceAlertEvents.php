<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\Pages;

use Filament\Resources\Pages\ListRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\ResourceAlertEventResource;

class ListResourceAlertEvents extends ListRecords
{
    protected static string $resource = ResourceAlertEventResource::class;
}
