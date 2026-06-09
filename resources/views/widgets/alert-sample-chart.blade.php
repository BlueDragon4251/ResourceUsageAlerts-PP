<x-filament-widgets::widget>
    <div class="fi-card p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                Sample History – {{ $metric ?? 'N/A' }}
            </h3>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $lastValue ?? '-' }}%
            </div>
        </div>
        <div class="relative h-48">
            <canvas
                x-data="{
                    init() {
                        new Chart($el, {
                            type: 'line',
                            data: {
                                labels: @js($labels ?? []),
                                datasets: [{
                                    label: '{{ $metric ?? "Value" }}',
                                    data: @js($values ?? []),
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 2
                                },
                                @if($threshold !== null)
                                {
                                    label: 'Threshold',
                                    data: @js($thresholdLine ?? []),
                                    borderColor: '#ef4444',
                                    borderDash: [5, 5],
                                    pointRadius: 0,
                                    fill: false
                                }
                                @endif
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100,
                                        ticks: { callback: v => v + '%' }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { usePointStyle: true }
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