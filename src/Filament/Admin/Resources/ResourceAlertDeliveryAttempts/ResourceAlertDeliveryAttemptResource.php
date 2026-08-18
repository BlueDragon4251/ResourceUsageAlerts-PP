<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertDeliveryAttempts;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertDeliveryAttempts\Pages\ListResourceAlertDeliveryAttempts;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertDeliveryAttempt;

class ResourceAlertDeliveryAttemptResource extends Resource
{
    protected static ?string $model = ResourceAlertDeliveryAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-send-2';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.deliveries.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('attempted_at', 'desc')->columns([
            TextColumn::make('event_id')->sortable(), TextColumn::make('channel_type')->badge(), TextColumn::make('status')->badge(),
            TextColumn::make('response_status'), TextColumn::make('failure_reason')->limit(50), TextColumn::make('duration_ms')->suffix(' ms'), TextColumn::make('attempted_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('status')->options(['sent' => trans('resourceusagealerts::strings.deliveries.sent'), 'failed' => trans('resourceusagealerts::strings.deliveries.failed')])]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListResourceAlertDeliveryAttempts::route('/')];
    }
}
