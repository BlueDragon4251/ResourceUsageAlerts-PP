<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class CleanupOldAlertSamplesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        ResourceAlertSample::query()
            ->where('sampled_at', '<', now()->subDays((int) config('resourceusagealerts.sample_retention_days', 14)))
            ->delete();

        ResourceAlertEvent::query()
            ->where('status', AlertStatus::RESOLVED)
            ->where('resolved_at', '<', now()->subDays((int) config('resourceusagealerts.event_retention_days', 90)))
            ->delete();
    }

    public function uniqueId(): string
    {
        return 'resource-usage-alerts:cleanup';
    }
}
