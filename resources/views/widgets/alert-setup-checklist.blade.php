<x-filament-widgets::widget>
    <x-filament::section :heading="trans('resourceusagealerts::strings.setup.title')" :description="trans('resourceusagealerts::strings.setup.description')" collapsible collapsed>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($this->checks() as $check)
                <div class="rounded-lg border p-3 dark:border-white/10">
                    <div class="flex items-center gap-2 font-medium">
                        <span class="{{ $check['ready'] ? 'text-success-600' : 'text-warning-600' }}">{{ $check['ready'] ? '✓' : '!' }}</span>
                        {{ $check['label'] }}
                    </div>
                    <code class="mt-1 block text-xs text-gray-500">{{ $check['hint'] }}</code>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
