<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\ResourceAlertRuleResource;

class ViewResourceAlertRule extends ViewRecord
{
    protected static string $resource = ResourceAlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
