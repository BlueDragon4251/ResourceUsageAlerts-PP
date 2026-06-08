<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertOperator: string
{
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case EQ = '=';
    case NEQ = '!=';
}
