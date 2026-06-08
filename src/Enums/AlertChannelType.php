<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertChannelType: string
{
    case PANEL = 'panel';
    case DISCORD = 'discord';
    case EMAIL = 'email';
    case PUSH = 'push';
}
