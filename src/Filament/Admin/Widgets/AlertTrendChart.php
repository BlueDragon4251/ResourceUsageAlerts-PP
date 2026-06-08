<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertTrendChart extends ChartWidget
{
    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return trans('resourceusagealerts::strings.dashboard.trend');
    }

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo)->startOfDay());
        $counts = $days->map(fn ($day) => ResourceAlertEvent::query()
            ->whereBetween('triggered_at', [$day, $day->copy()->endOfDay()])
            ->count());

        return [
            'datasets' => [[
                'label' => trans('resourceusagealerts::strings.events.title'),
                'data' => $counts->all(),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $days->map->format('Y-m-d')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
