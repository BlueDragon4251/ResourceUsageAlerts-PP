<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function weight(): int
    {
        return match ($this) {
            self::INFO => 0,
            self::WARNING => 1,
            self::CRITICAL => 2,
        };
    }

    public function filamentStatus(): string
    {
        return match ($this) {
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::CRITICAL => 'danger',
        };
    }
}
