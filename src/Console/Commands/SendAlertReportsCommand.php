<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use Illuminate\Console\Command;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertReportService;

class SendAlertReportsCommand extends Command
{
    protected $signature = 'resource-alerts:reports';

    protected $description = 'Send due Resource Usage Alerts reports.';

    public function handle(AlertReportService $reports): int
    {
        $this->info((string) $reports->sendDueReports().' report(s) sent.');

        return self::SUCCESS;
    }
}
