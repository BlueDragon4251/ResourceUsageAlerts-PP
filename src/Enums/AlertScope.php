<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertScope: string
{
    case GLOBAL = 'global';
    case NODE = 'node';
    case SERVER = 'server';
    case USER = 'user';
}
