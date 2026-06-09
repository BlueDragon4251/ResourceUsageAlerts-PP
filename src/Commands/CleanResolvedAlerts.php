<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class CleanResolvedAlerts extends Command
{
    protected $signature = 'resource-alerts:clean
                            {--days= : Override retention period in days}';

    protected $description = 'Delete resolved alerts and stale samples beyond the configured retention period';

    public function handle(): int
    {
        $eventRetention = (int) ($this->option('days') ?? config('resourceusagealerts.event_retention_days', 14));
        $sampleRetention = (int) config('resourceusagealerts.sample_retention_days', 7);

        $this->line("Cleaning resolved alerts older than {$eventRetention} days...");
        $deletedEvents = ResourceAlertEvent::query()
            ->where('status', AlertStatus::RESOLVED)
            ->where('resolved_at', '<', now()->subDays($eventRetention))
            ->delete();
        $this->line("  Deleted {$deletedEvents} resolved alert events.");

        $this->line("Cleaning samples older than {$sampleRetention} days...");
        $deletedSamples = ResourceAlertSample::query()
            ->where('sampled_at', '<', now()->subDays($sampleRetention))
            ->delete();
        $this->line("  Deleted {$deletedSamples} samples.");

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }
}