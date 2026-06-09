<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\Pages\ListResourceAlertEvents;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\Pages\ViewResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Jobs\SendAlertNotificationJob;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use Filament\Tables\Enums\ActionsPosition;

class ResourceAlertEventResource extends Resource
{
    protected static ?string $model = ResourceAlertEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-alert-triangle';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.events.title');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', AlertStatus::OPEN)->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('triggered_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AlertStatus $state) => $state->filamentColor())
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (AlertSeverity $state) => $state->filamentStatus()),
                TextColumn::make('metric')->badge(),
                TextColumn::make('server.name')->placeholder('-')->searchable(),
                TextColumn::make('node.name')->placeholder('-')->searchable(),
                TextColumn::make('value')->numeric(decimalPlaces: 1),
                TextColumn::make('triggered_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::options(AlertStatus::cases())),
                SelectFilter::make('severity')->options(self::options(AlertSeverity::cases())),
                SelectFilter::make('metric')->options(self::options(AlertMetric::cases())),
                SelectFilter::make('server_id')->relationship('server', 'name')->searchable()->preload(),
                SelectFilter::make('node_id')->relationship('node', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('acknowledge')
                    ->label(trans('resourceusagealerts::strings.events.acknowledge'))
                    ->icon('tabler-eye-check')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ResourceAlertEvent $record) => $record->status === AlertStatus::OPEN)
                    ->action(function (ResourceAlertEvent $record): void {
                        $record->update([
                            'status' => AlertStatus::ACKNOWLEDGED,
                            'acknowledged_at' => now(),
                        ]);
                        Notification::make()
                            ->success()
                            ->title(trans('resourceusagealerts::strings.events.acknowledged'))
                            ->send();
                    }),
                Action::make('resolve')
                    ->icon('tabler-circle-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ResourceAlertEvent $record) => $record->status !== AlertStatus::RESOLVED)
                    ->action(function (ResourceAlertEvent $record): void {
                        $record->update(['status' => AlertStatus::RESOLVED, 'resolved_at' => now()]);
                        SendAlertNotificationJob::dispatch($record->id, true);
                    }),
                Action::make('resend')
                    ->icon('tabler-send')
                    ->action(function (ResourceAlertEvent $record): void {
                        SendAlertNotificationJob::dispatch($record->id, $record->status === AlertStatus::RESOLVED);
                        Notification::make()->success()->title(trans('resourceusagealerts::strings.events.queued'))->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('rule.name'),
            TextEntry::make('status')
                ->badge()
                ->color(fn (AlertStatus $state) => $state->filamentColor()),
            TextEntry::make('severity')->badge(),
            TextEntry::make('metric')->badge(),
            TextEntry::make('server.name')->placeholder('-'),
            TextEntry::make('node.name')->placeholder('-'),
            TextEntry::make('value'),
            TextEntry::make('threshold')->placeholder('-'),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('triggered_at')->dateTime(),
            TextEntry::make('acknowledged_at')->dateTime()->placeholder('-'),
            TextEntry::make('resolved_at')->dateTime()->placeholder('-'),
            TextEntry::make('notification_count'),
        ])->columns(2);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListResourceAlertEvents::route('/'),
            'view' => ViewResourceAlertEvent::route('/{record}'),
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (\BackedEnum $case) => [
            $case->value => str((string) $case->value)->replace('_', ' ')->title()->toString(),
        ])->all();
    }
}
