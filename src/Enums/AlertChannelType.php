<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertChannelType: string
{
    case PANEL = 'panel';
    case DISCORD = 'discord';
    case EMAIL = 'email';
    case TELEGRAM = 'telegram';
    case SLACK = 'slack';
    case CUSTOM_WEBHOOK = 'custom_webhook';
    case PUSH = 'push';
    case NTFY = 'ntfy';
    case GOTIFY = 'gotify';
    case MATRIX = 'matrix';
}
