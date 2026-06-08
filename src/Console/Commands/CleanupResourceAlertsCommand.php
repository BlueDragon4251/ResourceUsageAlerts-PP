<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use Illuminate\Console\Command;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class CleanupResourceAlertsCommand extends Command
{
    protected $signature = 'resource-alerts:cleanup';

    protected $description = 'Delete expired Resource Usage Alert samples and resolved events.';

    public function handle(): int
    {
        $samples = ResourceAlertSample::query()
            ->where('sampled_at', '<', now()->subDays((int) config('resourceusagealerts.sample_retention_days', 14)))
            ->delete();
        $events = ResourceAlertEvent::query()
            ->where('status', AlertStatus::RESOLVED)
            ->where('resolved_at', '<', now()->subDays((int) config('resourceusagealerts.event_retention_days', 90)))
            ->delete();

        $this->info("Deleted {$samples} samples and {$events} resolved events.");

        return self::SUCCESS;
    }
}
