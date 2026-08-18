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
        return trans('resourceusagealerts::strings.messages.triggered_title', [
            'severity' => trans('resourceusagealerts::strings.severity.'.$event->severity->value),
            'metric' => $this->metric($event),
            'target' => $this->target($event),
        ]);
    }

    public function triggeredBody(ResourceAlertEvent $event): string
    {
        if ($event->metric->isBoolean()) {
            return trans('resourceusagealerts::strings.messages.boolean_triggered', ['target' => $this->target($event), 'metric' => $this->metric($event), 'minutes' => $event->rule->duration_minutes]);
        }

        return trans('resourceusagealerts::strings.messages.numeric_triggered', [
            'target' => $this->target($event), 'metric' => $this->metric($event), 'value' => $this->number($event->value),
            'minutes' => $event->rule->duration_minutes, 'operator' => $event->rule->operator->value, 'threshold' => $this->number($event->threshold),
        ]);
    }

    public function resolvedTitle(ResourceAlertEvent $event): string
    {
        return trans('resourceusagealerts::strings.messages.resolved_title', ['metric' => $this->metric($event), 'target' => $this->target($event)]);
    }

    public function resolvedBody(ResourceAlertEvent $event): string
    {
        return trans('resourceusagealerts::strings.messages.resolved_body', ['target' => $this->target($event), 'value' => $this->displayValue($event)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function discordPayload(ResourceAlertEvent $event, bool $resolved = false): array
    {
        $title = $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event);
        $body = $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event);

        $color = $resolved ? 0x22C55E : match ($event->severity->value) {
            'critical' => 0xEF4444,
            'warning' => 0xF59E0B,
            default => 0x3B82F6,
        };

        $fields = [
            [
                'name' => trans('resourceusagealerts::strings.messages.target'),
                'value' => $this->target($event),
                'inline' => true,
            ],
            [
                'name' => trans('resourceusagealerts::strings.messages.metric'),
                'value' => $this->metric($event),
                'inline' => true,
            ],
        ];

        if (! $event->metric->isBoolean()) {
            $fields[] = [
                'name' => trans('resourceusagealerts::strings.messages.value'),
                'value' => $this->number($event->value).'%',
                'inline' => true,
            ];

            if ($event->threshold !== null) {
                $fields[] = [
                    'name' => trans('resourceusagealerts::strings.messages.threshold'),
                    'value' => $event->rule->operator->value.' '.$this->number($event->threshold).'%',
                    'inline' => true,
                ];
            }
        }

        $fields[] = [
            'name' => trans('resourceusagealerts::strings.messages.duration'),
            'value' => $event->rule->duration_minutes.' min',
            'inline' => true,
        ];

        $fields[] = [
            'name' => trans('resourceusagealerts::strings.messages.severity'),
            'value' => trans('resourceusagealerts::strings.severity.'.$event->severity->value),
            'inline' => true,
        ];

        if ($event->triggered_at) {
            $fields[] = [
                'name' => trans('resourceusagealerts::strings.messages.triggered'),
                'value' => $event->triggered_at->diffForHumans(),
                'inline' => true,
            ];
        }

        if ($event->acknowledged_at) {
            $fields[] = [
                'name' => trans('resourceusagealerts::strings.messages.acknowledged'),
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
                    'name' => '✅ '.trans('resourceusagealerts::strings.events.status_resolved'),
                ] : [
                    'name' => '🚨 '.trans('resourceusagealerts::strings.messages.triggered'),
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
            $text .= "\n\n👁️ ".trans('resourceusagealerts::strings.messages.acknowledged').": {$event->acknowledged_at->diffForHumans()}";
        }

        $url = $this->eventUrl($event);
        if ($url !== '/') {
            $text .= "\n\n🔗 [".trans('resourceusagealerts::strings.messages.view_details')."]({$url})";
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

        if (! $event->metric->isBoolean() && $event->threshold !== null) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Value:*\n{$this->number($event->value)}% → {$event->rule->operator->value} {$this->number($event->threshold)}%",
            ];
        }

        $fields[] = [
            'type' => 'mrkdwn',
            'text' => '*'.trans('resourceusagealerts::strings.messages.severity').":*\n".trans('resourceusagealerts::strings.severity.'.$event->severity->value),
        ];

        $fields[] = [
            'type' => 'mrkdwn',
            'text' => '*'.trans('resourceusagealerts::strings.messages.duration').":*\n{$event->rule->duration_minutes} min",
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
                        'text' => trans('resourceusagealerts::strings.messages.status').": *{$event->status->value}*",
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
        $payload = [
            'title' => $resolved ? $this->resolvedTitle($event) : $this->triggeredTitle($event),
            'body' => $resolved ? $this->resolvedBody($event) : $this->triggeredBody($event),
            'icon' => '/favicon.ico',
            'badge' => '/favicon.ico',
            'url' => $this->eventUrl($event),
            'tag' => "resource-alert-{$event->id}",
        ];

        $sound = data_get($event->rule->config, 'push.sound');
        if (is_string($sound) && $sound !== '') {
            $payload['sound'] = $sound;
        }
        $actionUrl = data_get($event->rule->config, 'push.action_url');
        if (is_string($actionUrl) && filter_var($actionUrl, FILTER_VALIDATE_URL)) {
            $payload['url'] = $actionUrl;
            $payload['actions'] = [['action' => 'open', 'title' => trans('resourceusagealerts::strings.messages.open')]];
        }

        return $payload;
    }

    private function target(ResourceAlertEvent $event): string
    {
        return $event->server?->name ?? $event->node?->name ?? $event->user?->username ?? 'Pelican';
    }

    private function metric(ResourceAlertEvent $event): string
    {
        return $event->metric->label();
    }

    private function displayValue(ResourceAlertEvent $event): string
    {
        return $event->metric->isBoolean()
            ? trans('resourceusagealerts::strings.messages.'.((float) $event->value >= 1 ? 'active' : 'normal'))
            : $this->number($event->value).'%';
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
