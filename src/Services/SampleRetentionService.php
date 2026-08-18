<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class SampleRetentionService
{
    private const RETENTION_BY_METRIC = [
        AlertMetric::CPU_PERCENT => 7,
        AlertMetric::RAM_PERCENT => 7,
        AlertMetric::DISK_PERCENT => 14,
        AlertMetric::SWAP_PERCENT => 7,
        AlertMetric::NETWORK_IN => 3,
        AlertMetric::NETWORK_OUT => 3,
        AlertMetric::PROCESS_COUNT => 3,
        AlertMetric::INODE_PERCENT => 14,
        AlertMetric::DISK_IOPS => 3,
    ];

    public function cleanStaleSamples(?int $defaultRetentionDays = 7): int
    {
        $totalDeleted = 0;

        foreach (AlertMetric::cases() as $metric) {
            $retentionDays = self::RETENTION_BY_METRIC[$metric] ?? $defaultRetentionDays;
            $deleted = ResourceAlertSample::query()
                ->where('metric', $metric)
                ->where('sampled_at', '<', now()->subDays($retentionDays))
                ->delete();
            $totalDeleted += $deleted;
        }

        return $totalDeleted;
    }

    public function getRetentionDays(AlertMetric $metric, ?int $defaultRetentionDays = 7): int
    {
        return self::RETENTION_BY_METRIC[$metric] ?? $defaultRetentionDays;
    }

    public function getRetentionConfig(): array
    {
        return self::RETENTION_BY_METRIC;
    }
}
