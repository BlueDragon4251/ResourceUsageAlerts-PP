@php
    $values = $getRecord()->recentValues();
    $minimum = $values === [] ? 0.0 : min($values);
    $maximum = $values === [] ? 0.0 : max($values);
    $range = max(0.0001, $maximum - $minimum);
    $count = max(1, count($values) - 1);
    $points = collect($values)->map(fn ($value, $index) => round(($index / $count) * 100, 2) . ',' . round(28 - (((float) $value - $minimum) / $range) * 26, 2))->implode(' ');
@endphp
<div class="min-w-28" title="{{ $values === [] ? trans('resourceusagealerts::strings.rules.no_samples') : trans('resourceusagealerts::strings.rules.latest_value', ['value' => end($values)]) }}">
    @if ($values === [])
        <span class="text-xs text-gray-500">{{ trans('resourceusagealerts::strings.rules.no_samples') }}</span>
    @else
        <svg viewBox="0 0 100 30" role="img" aria-label="{{ trans('resourceusagealerts::strings.rules.recent_values') }}" class="h-8 w-28 overflow-visible">
            <polyline fill="none" stroke="currentColor" stroke-width="2" points="{{ $points }}" class="text-primary-500" />
        </svg>
    @endif
</div>
