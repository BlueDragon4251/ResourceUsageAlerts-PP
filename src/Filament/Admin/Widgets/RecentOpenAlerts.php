<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class RecentOpenAlerts extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('resourceusagealerts::strings.dashboard.recent_open'))
            ->query(ResourceAlertEvent::query()->where('status', AlertStatus::OPEN)->latest('triggered_at')->limit(10))
            ->columns([
                TextColumn::make('severity')->badge(),
                TextColumn::make('metric')->badge(),
                TextColumn::make('server.name')->placeholder('-'),
                TextColumn::make('node.name')->placeholder('-'),
                TextColumn::make('value')->numeric(decimalPlaces: 1),
                TextColumn::make('triggered_at')->since(),
            ])
            ->paginated(false);
    }
}
