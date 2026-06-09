<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertStatus: string
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function isAcknowledged(): bool
    {
        return $this === self::ACKNOWLEDGED;
    }

    public function isResolved(): bool
    {
        return $this === self::RESOLVED;
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => trans('resourceusagealerts::strings.events.status_open'),
            self::ACKNOWLEDGED => trans('resourceusagealerts::strings.events.status_acknowledged'),
            self::RESOLVED => trans('resourceusagealerts::strings.events.status_resolved'),
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::OPEN => 'danger',
            self::ACKNOWLEDGED => 'warning',
            self::RESOLVED => 'success',
        };
    }
}