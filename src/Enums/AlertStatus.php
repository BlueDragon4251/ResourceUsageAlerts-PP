<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}
