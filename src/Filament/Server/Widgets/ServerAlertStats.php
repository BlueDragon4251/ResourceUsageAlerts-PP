<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class ServerAlertStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            Stat::make(
                trans('resourceusagealerts::strings.server.open_alerts'),
                ResourceAlertEvent::query()->where('server_id', $server->id)->where('status', AlertStatus::OPEN)->count()
            )->icon('tabler-alert-triangle')->color('danger'),
            Stat::make(
                trans('resourceusagealerts::strings.server.active_rules'),
                ResourceAlertRule::query()->where('server_id', $server->id)->where('enabled', true)->count()
            )->icon('tabler-bell-ringing')->color('info'),
            Stat::make(
                trans('resourceusagealerts::strings.server.history'),
                ResourceAlertEvent::query()->where('server_id', $server->id)->count()
            )->icon('tabler-history')->color('gray'),
        ];
    }
}
