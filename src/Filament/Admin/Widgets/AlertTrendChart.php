<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;

class AlertTrendChart extends Widget
{
    protected string $view = 'resourceusagealerts::widgets.alert-chart';

    /** @var array<int, string> */
    public array $labels = [];

    /** @var array<int, int> */
    public array $criticalData = [];

    /** @var array<int, int> */
    public array $warningData = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->labels = [];
        $this->criticalData = [];
        $this->warningData = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $nextDay = $day->copy()->addDay();

            $this->labels[] = $day->format('M d');

            $this->criticalData[] = ResourceAlertEvent::query()
                ->where('severity', AlertSeverity::CRITICAL)
                ->where('triggered_at', '>=', $day)
                ->where('triggered_at', '<', $nextDay)
                ->count();

            $this->warningData[] = ResourceAlertEvent::query()
                ->where('severity', AlertSeverity::WARNING)
                ->where('triggered_at', '>=', $day)
                ->where('triggered_at', '<', $nextDay)
                ->count();
        }
    }
}
