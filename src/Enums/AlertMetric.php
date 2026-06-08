<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertMetric: string
{
    case RAM_PERCENT = 'ram_percent';
    case CPU_PERCENT = 'cpu_percent';
    case DISK_PERCENT = 'disk_percent';
    case SERVER_OFFLINE = 'server_offline';
    case SERVER_CRASHED = 'server_crashed';
    case NODE_OFFLINE = 'node_offline';
    case BACKUP_FAILED = 'backup_failed';

    public function isBoolean(): bool
    {
        return in_array($this, [
            self::SERVER_OFFLINE,
            self::SERVER_CRASHED,
            self::NODE_OFFLINE,
            self::BACKUP_FAILED,
        ], true);
    }

    public function isServerMetric(): bool
    {
        return $this !== self::NODE_OFFLINE;
    }

    public function label(): string
    {
        return trans("resourceusagealerts::strings.metrics.{$this->value}");
    }
}
