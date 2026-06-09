<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class AlertExportService
{
    /**
     * Export alerts as CSV string.
     */
    public function exportCsv(array $filters = []): string
    {
        $query = ResourceAlertEvent::query()
            ->with(['rule', 'server', 'node']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('triggered_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('triggered_at', '<=', $filters['to']);
        }

        $events = $query->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'ID', 'Status', 'Severity', 'Metric', 'Server', 'Node',
            'Value', 'Threshold', 'Triggered At', 'Acknowledged At', 'Resolved At',
            'Notification Count', 'Duration (min)',
        ]);

        foreach ($events as $event) {
            fputcsv($handle, [
                $event->id,
                $event->status->value,
                $event->severity->value,
                $event->metric->value,
                $event->server?->name ?? '-',
                $event->node?->name ?? '-',
                $event->value,
                $event->threshold,
                $event->triggered_at?->toIso8601String(),
                $event->acknowledged_at?->toIso8601String(),
                $event->resolved_at?->toIso8601String(),
                $event->notification_count,
                $event->triggered_at?->diffInMinutes($event->resolved_at ?? now()),
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Export samples as CSV string.
     */
    public function exportSamplesCsv(string $metric, ?int $serverId = null, ?int $nodeId = null, int $days = 7): string
    {
        $query = ResourceAlertSample::query()
            ->where('metric', $metric)
            ->where('sampled_at', '>=', now()->subDays($days));

        if ($serverId) {
            $query->where('server_id', $serverId);
        }
        if ($nodeId) {
            $query->where('node_id', $nodeId);
        }

        $samples = $query->orderBy('sampled_at')->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['ID', 'Metric', 'Value', 'Sampled At', 'Server ID', 'Node ID']);

        foreach ($samples as $sample) {
            fputcsv($handle, [
                $sample->id,
                $sample->metric->value,
                $sample->value,
                $sample->sampled_at?->toIso8601String(),
                $sample->server_id,
                $sample->node_id,
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Get CSV download headers.
     */
    public function csvDownloadHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];
    }
}