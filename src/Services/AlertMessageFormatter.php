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

        $color = $resolved ? 0x22c55e : match ($event->severity->value) {
            'critical' => 0xef4444,
            'warning' => 0xf59e0b,
            default => 0x3b82f6,
        };

        $fields = [
            [
                'name' => 'Target',
                'value' => $this->target($event),
                'inline' => true,
            ],
            [
                'name' => 'Metric',
                'value' => $this->metric($event),
                'inline' => true,
            ],
        ];

        if (!$event->metric->isBoolean()) {
            $fields[] = [
                'name' => 'Value',
                'value' => $this->number($event->value) . '%',
                'inline' => true,
            ];

            if ($event->threshold !== null) {
                $fields[] = [
                    'name' => 'Threshold',
                    'value' => $event->rule->operator->value . ' ' . $this->number($event->threshold) . '%',
                    'inline' => true,
                ];
            }
        }

        $fields[] = [
            'name' => 'Duration',
            'value' => $event->rule->duration_minutes . ' min',
            'inline' => true,
        ];

        $fields[] = [
            'name' => 'Severity',
            'value' => ucfirst($event->severity->value),
            'inline' => true,
        ];

        if ($event->triggered_at) {
            $fields[] = [
                'name' => 'Triggered',
                'value' => $event->triggered_at->diffForHumans(),
                'inline' => true,
            ];
        }

        if ($event->acknowledged_at) {
            $fields[] = [
                'name' => 'Acknowledged',
                'value' => $event->acknowledged_at->diffForHumans(),
                'inline' => true,
            ];
        }

        return [
            'embeds' => [[
                'title' => $title,
                'description' => $body,
                'url' => $this->eventUrl($event),
                'color' => $color,
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
                'footer' => ['text' => 'Pelican Resource Usage Alerts'],
                'author' => $resolved ? [
                    'name' => '✅ Resolved',
                ] : [
                    'name' => '🚨 Alert Triggered',
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function telegramPayload(ResourceAlertEvent $event, bool $resolved = false): string
    {
        $title = $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event);
        $body = $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event);

        $statusIcon = match (true) {
            $resolved => '✅',
            $event->status->value === 'acknowledged' => '👁️',
            $event->severity->value === 'critical' => '🔴',
            $event->severity->value === 'warning' => '🟡',
            default => '🔵',
        };

        $text = "{$statusIcon} *{$title}*\n\n{$body}";

        if ($event->acknowledged_at) {
            $text .= "\n\n👁️ Acknowledged: {$event->acknowledged_at->diffForHumans()}";
        }

        $url = $this->eventUrl($event);
        if ($url !== '/') {
            $text .= "\n\n🔗 [View Details]({$url})";
        }

        return $text;
    }

    /**
     * @return array{blocks: array<int, array{type: string, text: array{type: string, text: string, text_type?: string, fields?: array<int, array{type: string, text: string}>}}>}}
     */
    public function slackPayload(ResourceAlertEvent $event, bool $resolved = false): array
    {
        $title = $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event);
        $body = $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event);

        $color = $resolved ? '#22c55e' : match ($event->severity->value) {
            'critical' => '#ef4444',
            'warning' => '#f59e0b',
            default => '#3b82f6',
        };

        $fields = [];

        if (!$event->metric->isBoolean() && $event->threshold !== null) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Value:*\n{$this->number($event->value)}% → {$event->rule->operator->value} {$this->number($event->threshold)}%",
            ];
        }

        $fields[] = [
            'type' => 'mrkdwn',
            'text' => "*Severity:*\n" . ucfirst($event->severity->value),
        ];

        $fields[] = [
            'type' => 'mrkdwn',
            'text' => "*Duration:*\n{$event->rule->duration_minutes} min",
        ];

        $contextParts = [":alarm_clock: Triggered {$event->triggered_at->diffForHumans()}"];
        if ($event->acknowledged_at) {
            $contextParts[] = ":white_check_mark: Acknowledged {$event->acknowledged_at->diffForHumans()}";
        }

        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => $title,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $body,
                    ],
                    'fields' => $fields,
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "Status: *{$event->status->value}*",
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => implode(' | ', $contextParts),
                        ],
                    ],
                ],
            ],
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
