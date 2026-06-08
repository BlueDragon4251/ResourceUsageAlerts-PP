<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class PushServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            file_get_contents(plugin_path('resourceusagealerts', 'resources', 'js', 'resource-usage-alerts-sw.js')),
            200,
            [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Service-Worker-Allowed' => '/',
            ]
        );
    }
}
