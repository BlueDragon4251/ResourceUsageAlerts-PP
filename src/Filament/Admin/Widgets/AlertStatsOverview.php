<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                trans('resourceusagealerts::strings.dashboard.open_critical'),
                ResourceAlertEvent::query()->where('status', AlertStatus::OPEN)->where('severity', AlertSeverity::CRITICAL)->count()
            )->color('danger')->icon('tabler-alert-circle'),
            Stat::make(
                trans('resourceusagealerts::strings.dashboard.open_warning'),
                ResourceAlertEvent::query()->where('status', AlertStatus::OPEN)->where('severity', AlertSeverity::WARNING)->count()
            )->color('warning')->icon('tabler-alert-triangle'),
            Stat::make(
                trans('resourceusagealerts::strings.dashboard.last_24h'),
                ResourceAlertEvent::query()->where('triggered_at', '>=', now()->subDay())->count()
            )->color('info')->icon('tabler-clock-24'),
        ];
    }
}
