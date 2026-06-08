<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Pages;

use Filament\Pages\Page;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertStatsOverview;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertTrendChart;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\RecentOpenAlerts;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\TopServersByAlerts;

class ResourceAlertsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-chart-histogram';

    protected static ?int $navigationSort = 1;

    protected string $view = 'resourceusagealerts::filament.admin.pages.resource-alerts-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.dashboard.title');
    }

    public function getTitle(): string
    {
        return trans('resourceusagealerts::strings.dashboard.title');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AlertStatsOverview::class,
            AlertTrendChart::class,
            RecentOpenAlerts::class,
            TopServersByAlerts::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
