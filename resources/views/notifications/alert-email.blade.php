@component('mail::message')
# {{ $event->rule->name }}

@if($resolved)
    ✅ {{ trans('resourceusagealerts::strings.events.resolved') }}
@else
    🚨 {{ $event->status->label() }}
@endif

@component('mail::table')
| | |
|:---|:---|
| **Server** | {{ $event->server?->name ?? '-' }} |
| **Node** | {{ $event->node?->name ?? '-' }} |
| **Metric** | {{ str($event->metric->value)->replace('_', ' ')->title() }} |
@if(!$event->metric->isBoolean() && $event->threshold !== null)
| **Value** | {{ number_format((float) $event->value, 1) }}% → {{ $event->rule->operator->value }} {{ number_format((float) $event->threshold, 1) }}% |
@endif
| **Severity** | {{ ucfirst($event->severity->value) }} |
| **Duration** | {{ $event->rule->duration_minutes }} min |
| **Triggered** | {{ $event->triggered_at?->diffForHumans() }} |
@if($event->acknowledged_at)
| **Acknowledged** | {{ $event->acknowledged_at->diffForHumans() }} |
@endif
@endcomponent

@component('mail::button', ['url' => $eventUrl])
View Details
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent