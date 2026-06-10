<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview-widget">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.dashboard.total_open') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $totalOpen }}</dd>
            </div>
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.dashboard.critical') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-danger-600 dark:text-danger-400">{{ $criticalOpen }}</dd>
            </div>
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.dashboard.warning') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-warning-600 dark:text-warning-400">{{ $warningOpen }}</dd>
            </div>
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.events.status_acknowledged') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-info-600 dark:text-info-400">{{ $acknowledgedOpen }}</dd>
            </div>
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.dashboard.resolved_24h') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-success-600 dark:text-success-400">{{ $totalResolved24h }}</dd>
            </div>
            <div class="fi-card fi-wi-stats-overview-widget-stat flex flex-col gap-1 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <dt class="text-gray-500 dark:text-gray-400">{{ trans('resourceusagealerts::strings.dashboard.triggered_24h') }}</dt>
                <dd class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $totalTriggered24h }}</dd>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>