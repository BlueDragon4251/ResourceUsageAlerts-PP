@props(['event', 'resolved' => false])

@if($resolved)
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px; margin: 8px 0; border-radius: 4px;">
        <strong style="color: #16a34a;">✅ {{ $event->rule->name }}</strong>
        <p style="margin: 4px 0 0;">{{ trans('resourceusagealerts::strings.events.resolved') }}</p>
    @else
    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; margin: 8px 0; border-radius: 4px;">
        <strong style="color: #dc2626;">🚨 {{ $event->rule->name }}</strong>
        <p style="margin: 4px 0 0;">{{ $event->status->label() }}</p>
    @endif

    <table style="margin-top: 12px; font-size: 14px; color: #374151;">
        @if($event->server)
            <tr>
                <td style="padding: 2px 12px 2px 0; font-weight: 600;">Server:</td>
                <td>{{ $event->server->name }}</td>
            </tr>
        @endif
        @if($event->node)
            <tr>
                <td style="padding: 2px 12px 2px 0; font-weight: 600;">Node:</td>
                <td>{{ $event->node->name }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 2px 12px 2px 0; font-weight: 600;">Metric:</td>
            <td>{{ str($event->metric->value)->replace('_', ' ')->title() }}</td>
        </tr>
        @if(!$event->metric->isBoolean() && $event->threshold !== null)
            <tr>
                <td style="padding: 2px 12px 2px 0; font-weight: 600;">Value:</td>
                <td>{{ number_format((float) $event->value, 1) }}% → {{ $event->rule->operator->value }} {{ number_format((float) $event->threshold, 1) }}%</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 2px 12px 2px 0; font-weight: 600;">Severity:</td>
            <td>{{ ucfirst($event->severity->value) }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 12px 2px 0; font-weight: 600;">Time:</td>
            <td>{{ $event->triggered_at?->diffForHumans() }}</td>
        </tr>
        @if($event->acknowledged_at)
            <tr>
                <td style="padding: 2px 12px 2px 0; font-weight: 600;">Acknowledged:</td>
                <td>{{ $event->acknowledged_at->diffForHumans() }}</td>
            </tr>
        @endif
    </table>
</div>