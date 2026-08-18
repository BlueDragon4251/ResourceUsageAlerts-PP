<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Services\RuntimeHealthService;

class AlertSystemHealth extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $health = app(RuntimeHealthService::class);
        $collection = $health->status('collection');
        $evaluation = $health->status('evaluation');
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $maximumAge = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5))
            + max(0, (int) config('resourceusagealerts.stale_metric_grace_minutes', 2));
        $staleSamples = Schema::hasTable('resource_alert_samples')
            ? DB::table('resource_alert_samples')
                ->selectRaw('server_id, node_id, metric, MAX(sampled_at) AS latest_sampled_at')
                ->groupBy('server_id', 'node_id', 'metric')
                ->get()
                ->filter(fn (object $sample): bool => filled($sample->latest_sampled_at)
                    && Carbon::parse($sample->latest_sampled_at)->lt(now()->subMinutes($maximumAge)))
                ->count()
            : null;

        return [
            $this->operationStat(trans('resourceusagealerts::strings.health.collection'), $collection),
            $this->operationStat(trans('resourceusagealerts::strings.health.evaluation'), $evaluation),
            Stat::make(trans('resourceusagealerts::strings.health.failed_jobs'), $failedJobs ?? '-')
                ->description(trans('resourceusagealerts::strings.health.queue_driver', ['driver' => (string) config('queue.default', 'sync')]))
                ->color(($failedJobs ?? 0) > 0 ? 'danger' : 'success')
                ->icon('tabler-list-check'),
            Stat::make(trans('resourceusagealerts::strings.health.stale_samples'), $staleSamples ?? '-')
                ->description(trans('resourceusagealerts::strings.health.stale_samples_help', ['minutes' => $maximumAge]))
                ->color(($staleSamples ?? 0) > 0 ? 'warning' : 'success')
                ->icon('tabler-database-exclamation'),
        ];
    }

    /** @param array{completed_at: ?Carbon, processed: int, errors: int, duration_ms: int} $status */
    private function operationStat(string $label, array $status): Stat
    {
        $pollMinutes = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5));
        $fresh = $status['completed_at']?->isAfter(now()->subMinutes(($pollMinutes * 2) + 2)) ?? false;

        return Stat::make($label, $status['completed_at']?->diffForHumans() ?? trans('resourceusagealerts::strings.health.never'))
            ->description(trans('resourceusagealerts::strings.health.processed', [
                'count' => $status['processed'],
                'errors' => $status['errors'],
                'duration' => $status['duration_ms'],
            ]))
            ->color($fresh && $status['errors'] === 0 ? 'success' : 'warning')
            ->icon($fresh ? 'tabler-heartbeat' : 'tabler-clock-exclamation');
    }
}
