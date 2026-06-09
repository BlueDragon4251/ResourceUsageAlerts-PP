<x-filament-widgets::widget>
    <div class="fi-card flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
         x-data="{ count: 0, lastUpdate: new Date().toISOString(), refreshInterval: 30 }"
         x-init="
            count = {{ $openCount + $acknowledgedCount }};
            setInterval(() => {
                $wire.$refresh();
                count = {{ $openCount + $acknowledgedCount }};
                lastUpdate = new Date().toISOString();
            }, refreshInterval * 1000);
        ">
        <div class="flex items-center gap-2">
            <div class="h-2 w-2 rounded-full"
                 :class="count > 0 ? 'bg-green-500' : 'bg-gray-300'"
                 style="animation: pulse 2s infinite"></div>
            <span class="text-sm text-gray-500 dark:text-gray-400">Auto-refresh every {{ $refreshInterval }}s</span>
        </div>
        <div class="ml-auto text-xs text-gray-400 dark:text-gray-500" x-text="lastUpdate"></div>
    </div>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</x-filament-widgets::widget>