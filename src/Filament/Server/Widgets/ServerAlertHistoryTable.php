<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class ServerAlertHistoryTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->heading(trans('resourceusagealerts::strings.server.history'))
            ->query(ResourceAlertEvent::query()->where('server_id', $server->id)->latest('triggered_at'))
            ->columns([
                TextColumn::make('status')->badge(),
                TextColumn::make('severity')->badge(),
                TextColumn::make('metric')->badge(),
                TextColumn::make('triggered_at')->dateTime(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    AlertStatus::OPEN->value => trans('resourceusagealerts::strings.events.status_open'),
                    AlertStatus::RESOLVED->value => trans('resourceusagealerts::strings.events.status_resolved'),
                ]),
            ])
            ->defaultPaginationPageOption(10);
    }
}
