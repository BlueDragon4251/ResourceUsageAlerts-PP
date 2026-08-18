<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class IncidentResponseStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $events = ResourceAlertEvent::query()->where('triggered_at', '>=', now()->subDays(90))->get();
        $mtta = $events->filter->acknowledged_at->avg(fn ($event) => $event->triggered_at->diffInSeconds($event->acknowledged_at));
        $mttr = $events->filter->resolved_at->avg(fn ($event) => $event->triggered_at->diffInSeconds($event->resolved_at));

        return [Stat::make('MTTA', $this->duration($mtta))->description(trans('resourceusagealerts::strings.dashboard.mtta')), Stat::make('MTTR', $this->duration($mttr))->description(trans('resourceusagealerts::strings.dashboard.mttr'))];
    }

    private function duration(mixed $seconds): string
    {
        return $seconds === null ? '-' : now()->subSeconds((int) $seconds)->diffForHumans(now(), true);
    }
}
