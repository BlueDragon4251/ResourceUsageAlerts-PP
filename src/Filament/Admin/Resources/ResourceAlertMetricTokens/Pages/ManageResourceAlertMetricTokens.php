<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMetricTokens\Pages;

use Filament\Resources\Pages\ManageRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMetricTokens\ResourceAlertMetricTokenResource;

class ManageResourceAlertMetricTokens extends ManageRecords
{
    protected static string $resource = ResourceAlertMetricTokenResource::class;
}
