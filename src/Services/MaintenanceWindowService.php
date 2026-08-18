<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertMaintenanceWindow;

class MaintenanceWindowService
{
    public function activeFor(?int $serverId, ?int $nodeId, ?int $userId, ?Carbon $at = null): bool
    {
        if (! Schema::hasTable('resource_alert_maintenance_windows')) {
            return false;
        }

        $at ??= now();

        return ResourceAlertMaintenanceWindow::query()
            ->where('enabled', true)
            ->where(function ($query) use ($serverId, $nodeId, $userId): void {
                $query->where('scope', 'global');
                if ($serverId !== null) {
                    $query->orWhere(fn ($scope) => $scope->where('scope', 'server')->where('server_id', $serverId));
                }
                if ($nodeId !== null) {
                    $query->orWhere(fn ($scope) => $scope->where('scope', 'node')->where('node_id', $nodeId));
                }
                if ($userId !== null) {
                    $query->orWhere(fn ($scope) => $scope->where('scope', 'user')->where('user_id', $userId));
                }
            })
            ->get()
            ->contains(fn (ResourceAlertMaintenanceWindow $window): bool => $this->contains($window, $at));
    }

    public function contains(ResourceAlertMaintenanceWindow $window, Carbon $at): bool
    {
        if ($at->betweenIncluded($window->starts_at, $window->ends_at)) {
            return true;
        }

        $recurrence = $window->recurrence;
        if (! is_array($recurrence) || ($recurrence['type'] ?? null) !== 'weekly') {
            return false;
        }

        $timezone = is_string($recurrence['timezone'] ?? null) ? $recurrence['timezone'] : config('app.timezone', 'UTC');
        $local = $at->copy()->setTimezone($timezone);
        $days = array_map('intval', (array) ($recurrence['days'] ?? []));
        if (! in_array($local->dayOfWeekIso, $days, true)) {
            return false;
        }

        $start = (string) ($recurrence['start'] ?? '00:00');
        $end = (string) ($recurrence['end'] ?? '23:59');
        $time = $local->format('H:i');

        return $start <= $end ? $time >= $start && $time <= $end : $time >= $start || $time <= $end;
    }
}
