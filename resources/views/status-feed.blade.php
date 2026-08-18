{!! '<'.'?xml version="1.0" encoding="utf-8"?'.'>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ trans('resourceusagealerts::strings.status.title') }}</title>
    <id>{{ route('resourceusagealerts.status', ['token' => $token]) }}</id>
    <updated>{{ now()->toAtomString() }}</updated>
    @foreach($events as $event)
        <entry><title>{{ $event->metric->label() }} - {{ $event->status->value }}</title><id>urn:resource-alert:{{ $event->id }}</id><updated>{{ ($event->updated_at ?? $event->triggered_at)->toAtomString() }}</updated><content type="text">{{ $event->message }}</content></entry>
    @endforeach
</feed>
