<x-filament-widgets::widget>
    <div class="fi-card p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ trans('resourceusagealerts::strings.dashboard.trend') }}
            </h3>
        </div>
        <div class="relative h-64">
            <canvas
                x-data="{
                    init() {
                        new Chart($el, {
                            type: 'line',
                            data: {
                                labels: @js($labels ?? []),
                                datasets: [
                                    {
                                        label: 'Critical',
                                        data: @js($criticalData ?? []),
                                        borderColor: '#ef4444',
                                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Warning',
                                        data: @js($warningData ?? []),
                                        borderColor: '#f59e0b',
                                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { stepSize: 1 }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                    }
                }"
                x-init="init()"
            ></canvas>
        </div>
    </div>
</x-filament-widgets::widget>