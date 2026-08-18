<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Enums\BackupStatus;
use App\Enums\ContainerStatus;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            $samples[AlertMetric::NETWORK_IN->value] = $this->storeCounterRate($server->id, null, AlertMetric::NETWORK_IN, $resources['network_rx_bytes'] ?? $resources['network_in_bytes'] ?? null);
            $samples[AlertMetric::NETWORK_OUT->value] = $this->storeCounterRate($server->id, null, AlertMetric::NETWORK_OUT, $resources['network_tx_bytes'] ?? $resources['network_out_bytes'] ?? null);
            $samples[AlertMetric::DISK_IOPS->value] = $this->storeCounterRate($server->id, null, AlertMetric::DISK_IOPS, $resources['disk_io_operations'] ?? null);
            $samples[AlertMetric::PROCESS_COUNT->value] = $this->storeSample($server->id, null, AlertMetric::PROCESS_COUNT->value, $resources['pids_current'] ?? $resources['process_count'] ?? null, ['source' => 'server.retrieveResources']);
            $samples[AlertMetric::OOM_EVENTS->value] = $this->storeSample($server->id, null, AlertMetric::OOM_EVENTS->value, ! empty($resources['oom_killed']) || ! empty($resources['oom_events']) ? 1 : 0, ['source' => 'server.retrieveResources']);

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
            $backupDuration = $latestBackup?->completed_at && $latestBackup?->created_at
                ? $latestBackup->created_at->diffInSeconds($latestBackup->completed_at)
                : null;
            $samples[AlertMetric::BACKUP_DURATION->value] = $this->storeSample($server->id, null, AlertMetric::BACKUP_DURATION->value, $backupDuration, ['backup_id' => $latestBackup?->id]);
            $staleDays = max(1, (int) config('resourceusagealerts.backup_stale_days', 7));
            $samples[AlertMetric::BACKUP_STALE->value] = $this->storeSample(
                $server->id,
                null,
                AlertMetric::BACKUP_STALE->value,
                ! $latestBackup || $latestBackup->completed_at->lt(now()->subDays($staleDays)) ? 1 : 0,
                ['backup_id' => $latestBackup?->id, 'stale_days' => $staleDays]
            );
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts could not collect server statistics.', [
                'server_id' => $server->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
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

            if (! $offline) {
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
                $samples[AlertMetric::SWAP_PERCENT->value] = $this->storeRatioSample(null, $node->id, AlertMetric::SWAP_PERCENT, $statistics['swap_used'] ?? null, $statistics['swap_total'] ?? null);
                $samples[AlertMetric::NETWORK_IN->value] = $this->storeCounterRate(null, $node->id, AlertMetric::NETWORK_IN, $statistics['network_rx_bytes'] ?? null);
                $samples[AlertMetric::NETWORK_OUT->value] = $this->storeCounterRate(null, $node->id, AlertMetric::NETWORK_OUT, $statistics['network_tx_bytes'] ?? null);
                $samples[AlertMetric::INODE_PERCENT->value] = $this->storeRatioSample(null, $node->id, AlertMetric::INODE_PERCENT, $statistics['inode_used'] ?? null, $statistics['inode_total'] ?? null);
                $samples[AlertMetric::DISK_IOPS->value] = $this->storeCounterRate(null, $node->id, AlertMetric::DISK_IOPS, $statistics['disk_io_operations'] ?? null);
                $samples[AlertMetric::PROCESS_COUNT->value] = $this->storeSample(null, $node->id, AlertMetric::PROCESS_COUNT->value, $statistics['process_count'] ?? null, ['source' => 'node.statistics']);

                $version = (string) ($systemInformation['version'] ?? $systemInformation['wings_version'] ?? '');
                $minimumVersion = trim((string) config('resourceusagealerts.minimum_wings_version', ''));
                $samples[AlertMetric::WINGS_VERSION->value] = $this->storeSample(null, $node->id, AlertMetric::WINGS_VERSION->value, $minimumVersion !== '' && $version !== '' && version_compare($version, $minimumVersion, '<') ? 1 : 0, ['installed_version' => $version, 'minimum_version' => $minimumVersion]);
                $samples[AlertMetric::SSL_CERT_EXPIRY->value] = $this->storeSample(null, $node->id, AlertMetric::SSL_CERT_EXPIRY->value, $this->certificateDaysRemaining($node), ['host' => $node->fqdn]);
            }
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts could not collect node statistics.', [
                'node_id' => $node->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
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
        if (! is_numeric($used) || ! is_numeric($limit) || (float) $limit <= 0) {
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

    private function storeCounterRate(?int $serverId, ?int $nodeId, AlertMetric $metric, mixed $counter): ResourceAlertSample
    {
        if (! is_numeric($counter)) {
            return $this->storeSample($serverId, $nodeId, $metric->value, null, ['available' => false]);
        }

        $previous = ResourceAlertSample::query()
            ->where('metric', $metric)
            ->when($serverId !== null, fn ($query) => $query->where('server_id', $serverId))
            ->when($serverId === null, fn ($query) => $query->whereNull('server_id'))
            ->when($nodeId !== null, fn ($query) => $query->where('node_id', $nodeId))
            ->when($nodeId === null, fn ($query) => $query->whereNull('node_id'))
            ->latest('sampled_at')
            ->first();
        $previousCounter = data_get($previous?->context, 'counter');
        $seconds = $previous?->sampled_at?->diffInSeconds(now()) ?? 0;
        $rate = is_numeric($previousCounter) && $seconds > 0 && (float) $counter >= (float) $previousCounter
            ? ((float) $counter - (float) $previousCounter) / $seconds
            : null;

        return $this->storeSample($serverId, $nodeId, $metric->value, $rate, ['counter' => (float) $counter, 'unit' => 'per_second']);
    }

    private function certificateDaysRemaining(Node $node): ?int
    {
        if ($node->scheme !== 'https' || ! function_exists('openssl_x509_parse')) {
            return null;
        }

        return Cache::remember("resourceusagealerts.ssl.{$node->id}", now()->addHours(6), function () use ($node): ?int {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true]]);
            $socket = @stream_socket_client("ssl://{$node->fqdn}:{$node->daemon_connect}", $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
            if (! is_resource($socket)) {
                return null;
            }
            $parameters = stream_context_get_params($socket);
            fclose($socket);
            $certificate = $parameters['options']['ssl']['peer_certificate'] ?? null;
            $parsed = $certificate ? openssl_x509_parse($certificate) : false;
            $expires = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;

            return is_numeric($expires)
                ? max(0, now()->diffInDays(Carbon::createFromTimestamp((int) $expires), false))
                : null;
        });
    }

    /** @return array<string, ResourceAlertSample> */
    public function collectPanelQueueSamples(): array
    {
        $failed = DB::getSchemaBuilder()->hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $oldest = DB::getSchemaBuilder()->hasTable('jobs') ? DB::table('jobs')->min('available_at') : null;

        return [
            AlertMetric::QUEUE_FAILED_JOBS->value => $this->storeSample(null, null, AlertMetric::QUEUE_FAILED_JOBS->value, $failed, ['source' => 'failed_jobs']),
            AlertMetric::QUEUE_OLDEST_JOB_AGE->value => $this->storeSample(null, null, AlertMetric::QUEUE_OLDEST_JOB_AGE->value, is_numeric($oldest) ? max(0, now()->timestamp - (int) $oldest) : 0, ['source' => 'jobs']),
        ];
    }
}
