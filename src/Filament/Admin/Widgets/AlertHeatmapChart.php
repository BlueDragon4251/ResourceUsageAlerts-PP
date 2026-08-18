<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertHeatmapChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        return trans('resourceusagealerts::strings.dashboard.heatmap');
    }

    protected function getData(): array
    {
        $events = ResourceAlertEvent::query()->where('triggered_at', '>=', now()->subDays(90))->get(['triggered_at']);
        $datasets = collect(range(1, 7))->map(function (int $weekday) use ($events): array {
            return [
                'label' => now()->startOfWeek()->addDays($weekday - 1)->format('D'),
                'data' => collect(range(0, 23))->map(fn (int $hour): int => $events->filter(fn ($event) => $event->triggered_at?->dayOfWeekIso === $weekday && $event->triggered_at?->hour === $hour)->count())->all(),
            ];
        })->all();

        return ['datasets' => $datasets, 'labels' => collect(range(0, 23))->map(fn (int $hour) => sprintf('%02d:00', $hour))->all()];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
