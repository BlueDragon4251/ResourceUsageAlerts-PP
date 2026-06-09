<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Jobs\SendAlertNotificationJob;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class ServerOpenAlertsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->heading(trans('resourceusagealerts::strings.server.open_alerts'))
            ->query(
                ResourceAlertEvent::query()
                    ->where('server_id', $server->id)
                    ->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])
                    ->latest('triggered_at')
            )
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AlertStatus $state) => $state->filamentColor()),
                TextColumn::make('severity')->badge(),
                TextColumn::make('metric')->badge(),
                TextColumn::make('value')->numeric(decimalPlaces: 1),
                TextColumn::make('threshold')->numeric(decimalPlaces: 1)->placeholder('-'),
                TextColumn::make('triggered_at')->since(),
                TextColumn::make('message')->limit(80)->wrap(),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label(trans('resourceusagealerts::strings.events.acknowledge'))
                    ->icon('tabler-eye-check')
                    ->color('warning')
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
                    ->label(trans('resourceusagealerts::strings.events.resolve'))
                    ->icon('tabler-circle-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ResourceAlertEvent $record) => $record->status !== AlertStatus::RESOLVED)
                    ->action(function (ResourceAlertEvent $record): void {
                        $record->update([
                            'status' => AlertStatus::RESOLVED,
                            'resolved_at' => now(),
                        ]);
                        SendAlertNotificationJob::dispatch($record->id, true);
                        Notification::make()
                            ->success()
                            ->title(trans('resourceusagealerts::strings.events.resolved'))
                            ->send();
                    }),
            ]);
    }
}
