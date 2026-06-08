<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Enums\BackupStatus;
use App\Enums\ContainerStatus;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;
use Throwable;

class ResourceSampleService
{
    /**
     * @return array<string, ResourceAlertSample>
     */
    public function collectServerSamples(Server $server): array
    {
        $samples = [];

        try {
            $resources = $this->tryPanelStats($server);
            $status = $server->retrieveStatus();
            $statusContext = ['status' => $status->value];
            $previous = ResourceAlertSample::query()
                ->where('server_id', $server->id)
                ->where('metric', AlertMetric::SERVER_OFFLINE)
                ->latest('sampled_at')
                ->first();
            $previousStatus = data_get($previous?->context, 'status');
            $previousCrash = ResourceAlertSample::query()
                ->where('server_id', $server->id)
                ->where('metric', AlertMetric::SERVER_CRASHED)
                ->latest('sampled_at')
                ->first();

            $samples[AlertMetric::CPU_PERCENT->value] = $this->storeSample(
                $server->id,
                null,
                AlertMetric::CPU_PERCENT->value,
                isset($resources['cpu_absolute']) ? (float) $resources['cpu_absolute'] : null,
                ['source' => 'server.retrieveResources']
            );

            $samples[AlertMetric::RAM_PERCENT->value] = $this->storePercentageSample(
                $server,
                AlertMetric::RAM_PERCENT,
                $resources['memory_bytes'] ?? null,
                $server->memory > 0 ? $server->memory * $this->megabyte() : null
            );

            $samples[AlertMetric::DISK_PERCENT->value] = $this->storePercentageSample(
                $server,
                AlertMetric::DISK_PERCENT,
                $resources['disk_bytes'] ?? null,
                $server->disk > 0 ? $server->disk * $this->megabyte() : null
            );

            $isOffline = in_array($status, [
                ContainerStatus::Offline,
                ContainerStatus::Exited,
                ContainerStatus::Dead,
            ], true);

            $samples[AlertMetric::SERVER_OFFLINE->value] = $this->storeSample(
                $server->id,
                null,
                AlertMetric::SERVER_OFFLINE->value,
                $status === ContainerStatus::Missing ? null : ($isOffline ? 1 : 0),
                $statusContext
            );

            $crashed = $this->isCrashTransition($previousStatus, $status)
                || ((float) ($previousCrash?->value ?? 0) >= 1 && $isOffline);

            $samples[AlertMetric::SERVER_CRASHED->value] = $this->storeSample(
                $server->id,
                null,
                AlertMetric::SERVER_CRASHED->value,
                $status === ContainerStatus::Missing ? null : ($crashed ? 1 : 0),
                $statusContext + ['previous_status' => $previousStatus]
            );

            $latestBackup = Backup::query()
                ->where('server_id', $server->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();
            $backupFailed = $latestBackup?->status === BackupStatus::Failed;

            $samples[AlertMetric::BACKUP_FAILED->value] = $this->storeSample(
                $server->id,
                null,
                AlertMetric::BACKUP_FAILED->value,
                $backupFailed ? 1 : 0,
                ['backup_id' => $latestBackup?->id, 'completed_at' => $latestBackup?->completed_at?->toIso8601String()]
            );
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts could not collect server statistics.', [
                'server_id' => $server->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $samples;
    }

    /**
     * @return array<string, ResourceAlertSample>
     */
    public function collectNodeSamples(Node $node): array
    {
        $samples = [];

        try {
            $systemInformation = $node->systemInformation();
            $offline = isset($systemInformation['exception']);
            $statistics = $offline ? [] : $node->statistics();

            $samples[AlertMetric::NODE_OFFLINE->value] = $this->storeSample(
                null,
                $node->id,
                AlertMetric::NODE_OFFLINE->value,
                $offline ? 1 : 0,
                ['source' => 'node.systemInformation', 'error' => $systemInformation['exception'] ?? null]
            );

            if (!$offline) {
                $samples[AlertMetric::CPU_PERCENT->value] = $this->storeSample(
                    null,
                    $node->id,
                    AlertMetric::CPU_PERCENT->value,
                    $statistics['cpu_percent'] ?? null,
                    ['source' => 'node.statistics']
                );
                $samples[AlertMetric::RAM_PERCENT->value] = $this->storeRatioSample(
                    null,
                    $node->id,
                    AlertMetric::RAM_PERCENT,
                    $statistics['memory_used'] ?? null,
                    $statistics['memory_total'] ?? null
                );
                $samples[AlertMetric::DISK_PERCENT->value] = $this->storeRatioSample(
                    null,
                    $node->id,
                    AlertMetric::DISK_PERCENT,
                    $statistics['disk_used'] ?? null,
                    $statistics['disk_total'] ?? null
                );
            }
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts could not collect node statistics.', [
                'node_id' => $node->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $samples;
    }

    public function storeSample(?int $serverId, ?int $nodeId, string $metric, mixed $value, array $context = []): ResourceAlertSample
    {
        return ResourceAlertSample::query()->create([
            'server_id' => $serverId,
            'node_id' => $nodeId,
            'metric' => $metric,
            'value' => is_numeric($value) ? (float) $value : null,
            'sampled_at' => now(),
            'context' => $context,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function tryPanelStats(Server $server): array
    {
        return $server->retrieveResources();
    }

    /**
     * @return array<string, mixed>
     */
    public function tryWingsApiStats(Server $server): array
    {
        return $server->retrieveResources();
    }

    /**
     * @return array<string, mixed>
     */
    public function tryDatabaseStats(Server $server): array
    {
        return [
            'cpu_absolute' => null,
            'memory_bytes' => null,
            'disk_bytes' => null,
            'status' => $server->status?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackUnknown(string $reason): array
    {
        return ['available' => false, 'reason' => $reason];
    }

    public function isCrashTransition(?string $previousStatus, ContainerStatus $currentStatus): bool
    {
        return $previousStatus === ContainerStatus::Running->value
            && in_array($currentStatus, [ContainerStatus::Exited, ContainerStatus::Dead, ContainerStatus::Offline], true);
    }

    private function storePercentageSample(Server $server, AlertMetric $metric, mixed $used, mixed $limit): ResourceAlertSample
    {
        return $this->storeRatioSample($server->id, null, $metric, $used, $limit);
    }

    private function storeRatioSample(?int $serverId, ?int $nodeId, AlertMetric $metric, mixed $used, mixed $limit): ResourceAlertSample
    {
        if (!is_numeric($used) || !is_numeric($limit) || (float) $limit <= 0) {
            return $this->storeSample($serverId, $nodeId, $metric->value, null, [
                'available' => false,
                'reason' => 'missing_or_unlimited_limit',
            ]);
        }

        return $this->storeSample($serverId, $nodeId, $metric->value, ((float) $used / (float) $limit) * 100, [
            'used' => (float) $used,
            'limit' => (float) $limit,
        ]);
    }

    private function megabyte(): int
    {
        return config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000;
    }
}
