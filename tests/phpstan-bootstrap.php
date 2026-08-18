<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$loader = require $root.'/pelican/vendor/autoload.php';
$loader->addPsr4('PelicanPlugins\\ResourceUsageAlerts\\', dirname(__DIR__).'/src/');
