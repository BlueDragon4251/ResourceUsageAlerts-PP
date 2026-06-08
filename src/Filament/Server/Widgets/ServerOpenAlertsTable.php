<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
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
            ->query(ResourceAlertEvent::query()->where('server_id', $server->id)->where('status', AlertStatus::OPEN)->latest('triggered_at'))
            ->columns([
                TextColumn::make('severity')->badge(),
                TextColumn::make('metric')->badge(),
                TextColumn::make('value')->numeric(decimalPlaces: 1),
                TextColumn::make('threshold')->numeric(decimalPlaces: 1)->placeholder('-'),
                TextColumn::make('triggered_at')->since(),
                TextColumn::make('message')->limit(80)->wrap(),
            ]);
    }
}
