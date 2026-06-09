<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertStatusPageWidget extends Widget
{
    protected static string $view = 'resourceusagealerts::widgets.alert-status-page';

    public int $totalOpen = 0;

    public int $criticalOpen = 0;

    public int $warningOpen = 0;

    public int $acknowledgedOpen = 0;

    public int $totalResolved24h = 0;

    public int $totalTriggered24h = 0;

    public function mount(): void
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->totalOpen = ResourceAlertEvent::where('status', AlertStatus::OPEN)->count();
        $this->criticalOpen = ResourceAlertEvent::where('status', AlertStatus::OPEN)
            ->where('severity', AlertSeverity::CRITICAL)->count();
        $this->warningOpen = ResourceAlertEvent::where('status', AlertStatus::OPEN)
            ->where('severity', AlertSeverity::WARNING)->count();
        $this->acknowledgedOpen = ResourceAlertEvent::where('status', AlertStatus::ACKNOWLEDGED)->count();
        $this->totalResolved24h = ResourceAlertEvent::where('status', AlertStatus::RESOLVED)
            ->where('resolved_at', '>=', now()->subDay())->count();
        $this->totalTriggered24h = ResourceAlertEvent::where('triggered_at', '>=', now()->subDay())->count();
    }

    public function getStatusColor(): string
    {
        if ($this->criticalOpen > 0) {
            return 'danger';
        }
        if ($this->warningOpen > 0) {
            return 'warning';
        }
        if ($this->acknowledgedOpen > 0) {
            return 'info';
        }

        return 'success';
    }
}