<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertHeatmapChart;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertSetupChecklist;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertStatsOverview;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertSystemHealth;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\AlertTrendChart;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\IncidentResponseStats;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\RecentOpenAlerts;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets\TopServersByAlerts;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertExportService;

class ResourceAlertsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-chart-histogram';

    protected static ?int $navigationSort = 1;

    protected string $view = 'resourceusagealerts::filament.admin.pages.resource-alerts-dashboard';

    public static function canAccess(): bool
    {
        return Schema::hasTable('resource_alert_events')
            && Schema::hasTable('resource_alert_rules')
            && parent::canAccess();
    }

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
            IncidentResponseStats::class,
            AlertSystemHealth::class,
            AlertSetupChecklist::class,
            AlertTrendChart::class,
            AlertHeatmapChart::class,
            RecentOpenAlerts::class,
            TopServersByAlerts::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(trans('resourceusagealerts::strings.exports.title'))
                ->icon('tabler-download')
                ->schema([
                    Select::make('resource')->options([
                        'events' => trans('resourceusagealerts::strings.exports.events'),
                        'samples' => trans('resourceusagealerts::strings.exports.samples'),
                        'rules' => trans('resourceusagealerts::strings.exports.rules'),
                        'channels' => trans('resourceusagealerts::strings.exports.channels'),
                        'deliveries' => trans('resourceusagealerts::strings.exports.deliveries'),
                    ])->default('events')->required(),
                    Select::make('format')->options(['json' => 'JSON', 'csv' => 'CSV'])->default('json')->required(),
                    Select::make('status')->options(['open' => trans('resourceusagealerts::strings.events.status_open'), 'acknowledged' => trans('resourceusagealerts::strings.events.status_acknowledged'), 'resolved' => trans('resourceusagealerts::strings.events.status_resolved')])->nullable(),
                    DatePicker::make('from')->label(trans('resourceusagealerts::strings.exports.from')),
                    DatePicker::make('to')->label(trans('resourceusagealerts::strings.exports.to')),
                ])
                ->action(function (array $data) {
                    $resource = (string) $data['resource'];
                    $format = (string) $data['format'];
                    $filters = array_filter(['status' => $data['status'] ?? null, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]);
                    $service = app(AlertExportService::class);
                    if ($format === 'csv') {
                        $contents = $service->exportResourceCsv($resource, $filters);
                        $type = 'text/csv';
                    } else {
                        $contents = $service->exportJson($resource, $filters);
                        $format = 'json';
                        $type = 'application/json';
                    }

                    return response()->streamDownload(fn () => print ($contents), "resource-alerts-$resource.".$format, ['Content-Type' => $type]);
                }),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
