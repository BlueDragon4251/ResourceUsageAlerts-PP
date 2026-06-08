<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class TopServersByAlerts extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('resourceusagealerts::strings.dashboard.top_servers'))
            ->query(
                ResourceAlertEvent::query()
                    ->selectRaw('MIN(id) AS id, server_id, COUNT(*) AS alerts_count')
                    ->whereNotNull('server_id')
                    ->where('triggered_at', '>=', now()->subDays(30))
                    ->groupBy('server_id')
                    ->orderByDesc('alerts_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('server.name'),
                TextColumn::make('alerts_count')->label(trans('resourceusagealerts::strings.dashboard.alert_count')),
            ])
            ->paginated(false);
    }
}
