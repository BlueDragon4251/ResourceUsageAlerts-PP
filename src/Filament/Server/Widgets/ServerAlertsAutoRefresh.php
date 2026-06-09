<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class ServerAlertsAutoRefresh extends Widget
{
    protected string $view = 'resourceusagealerts::widgets.auto-refresh-indicator';

    public int $openCount = 0;

    public int $acknowledgedCount = 0;

    public function mount(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $this->openCount = ResourceAlertEvent::query()
            ->where('server_id', $server->id)
            ->where('status', AlertStatus::OPEN)
            ->count();

        $this->acknowledgedCount = ResourceAlertEvent::query()
            ->where('server_id', $server->id)
            ->where('status', AlertStatus::ACKNOWLEDGED)
            ->count();
    }
}