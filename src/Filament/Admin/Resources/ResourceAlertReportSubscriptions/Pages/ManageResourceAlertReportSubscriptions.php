<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertReportSubscriptions\Pages;

use Filament\Resources\Pages\ManageRecords;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertReportSubscriptions\ResourceAlertReportSubscriptionResource;

class ManageResourceAlertReportSubscriptions extends ManageRecords
{
    protected static string $resource = ResourceAlertReportSubscriptionResource::class;
}
