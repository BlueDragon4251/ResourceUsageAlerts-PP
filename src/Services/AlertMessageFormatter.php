<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\ResourceAlertEventResource;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Pages\ResourceAlerts;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use Throwable;

class AlertMessageFormatter
{
    public function triggeredTitle(ResourceAlertEvent $event): string
    {
        return sprintf('%s %s Alert: %s', ucfirst($event->severity->value), $this->metric($event), $this->target($event));
    }

    public function triggeredBody(ResourceAlertEvent $event): string
    {
        if ($event->metric->isBoolean()) {
            return sprintf('%s is reporting %s for at least %d minute(s).', $this->target($event), $this->metric($event), $event->rule->duration_minutes);
        }

        return sprintf(
            '%s has reported %s at %s%% for at least %d minute(s). Threshold: %s %s%%.',
            $this->target($event),
            $this->metric($event),
            $this->number($event->value),
            $event->rule->duration_minutes,
            $event->rule->operator->value,
            $this->number($event->threshold)
        );
    }

    public function resolvedTitle(ResourceAlertEvent $event): string
    {
        return sprintf('Resolved %s Alert: %s', $this->metric($event), $this->target($event));
    }

    public function resolvedBody(ResourceAlertEvent $event): string
    {
        return sprintf('%s has returned to a normal state. Current value: %s.', $this->target($event), $this->displayValue($event));
    }

    /**
     * @return array<string, mixed>
     */
    public function discordPayload(ResourceAlertEvent $event, bool $resolved = false): array
    {
        $title = $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event);
        $body = $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event);

        return [
            'embeds' => [[
                'title' => $title,
                'description' => $body,
                'color' => $resolved ? 0x22c55e : match ($event->severity->value) {
                    'critical' => 0xef4444,
                    'warning' => 0xf59e0b,
                    default => 0x3b82f6,
                },
                'timestamp' => now()->toIso8601String(),
                'footer' => ['text' => 'Pelican Resource Usage Alerts'],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pushPayload(ResourceAlertEvent $event, bool $resolved = false): array
    {
        return [
            'title' => $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event),
            'body' => $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event),
            'icon' => '/favicon.ico',
            'badge' => '/favicon.ico',
            'url' => $this->eventUrl($event),
            'tag' => "resource-alert-{$event->id}",
        ];
    }

    private function target(ResourceAlertEvent $event): string
    {
        return $event->server?->name ?? $event->node?->name ?? $event->user?->username ?? 'Pelican';
    }

    private function metric(ResourceAlertEvent $event): string
    {
        return str($event->metric->value)->replace('_', ' ')->title()->toString();
    }

    private function displayValue(ResourceAlertEvent $event): string
    {
        return $event->metric->isBoolean() ? ((float) $event->value >= 1 ? 'active' : 'normal') : $this->number($event->value) . '%';
    }

    private function number(mixed $value): string
    {
        return number_format((float) $value, 1, '.', '');
    }

    private function eventUrl(ResourceAlertEvent $event): string
    {
        try {
            if ($event->server) {
                return ResourceAlerts::getUrl(panel: 'server', tenant: $event->server);
            }

            return ResourceAlertEventResource::getUrl('view', ['record' => $event], panel: 'admin');
        } catch (Throwable) {
            return '/';
        }
    }
}
