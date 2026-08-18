<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="alternate" type="application/atom+xml" href="{{ $feedUrl }}">
    <title>{{ trans('resourceusagealerts::strings.status.title') }}</title>
    <style>body{font-family:system-ui;background:#0b1020;color:#eef2ff;margin:0}.wrap{max-width:960px;margin:auto;padding:40px 20px}.card{background:#171d30;border:1px solid #2d3654;border-radius:14px;padding:18px;margin:14px 0}.ok{color:#4ade80}.bad{color:#fb7185}.muted{color:#aab3cc}</style>
</head>
<body><main class="wrap">
    <h1>{{ trans('resourceusagealerts::strings.status.title') }}</h1>
    <div class="card"><h2 class="{{ $operational ? 'ok' : 'bad' }}">{{ $operational ? trans('resourceusagealerts::strings.status.operational') : trans('resourceusagealerts::strings.status.incidents') }}</h2></div>
    <h2>{{ trans('resourceusagealerts::strings.status.current') }}</h2>
    @forelse($open as $event)
        <article class="card"><strong>{{ $event->severity->value }} · {{ $event->metric->label() }}</strong><p>{{ $event->message }}</p><span class="muted">{{ $event->server?->name ?? $event->node?->name ?? 'Panel' }} · {{ $event->triggered_at?->diffForHumans() }}</span></article>
    @empty
        <p class="muted">{{ trans('resourceusagealerts::strings.status.none') }}</p>
    @endforelse
    <h2>{{ trans('resourceusagealerts::strings.status.history') }}</h2>
    @foreach($recent as $event)
        <article class="card"><strong>{{ $event->metric->label() }}</strong><p class="muted">{{ $event->status->value }} · {{ $event->triggered_at?->toDayDateTimeString() }}</p></article>
    @endforeach
</main></body></html>
