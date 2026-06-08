<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages;

use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\ResourceAlertRuleResource;

class CreateResourceAlertRule extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ResourceAlertRuleResource::class;

    protected static bool $canCreateAnother = false;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(trans('filament-panels::resources/pages/create-record.form.actions.cancel.label'))
                ->color('gray')
                ->icon('tabler-arrow-left')
                ->url(ResourceAlertRuleResource::getUrl('index')),
            Action::make('create')
                ->label(trans('filament-panels::resources/pages/create-record.form.actions.create.label'))
                ->action('create')
                ->keyBindings(['mod+s'])
                ->icon('tabler-device-floppy'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = user()?->id;

        return $data;
    }
}
