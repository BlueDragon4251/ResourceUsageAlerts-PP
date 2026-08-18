<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\Notification;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertReportSubscription;
use PelicanPlugins\ResourceUsageAlerts\Notifications\ResourceAlertMailNotification;
use Throwable;

class AlertReportService
{
    public function sendDueReports(): int
    {
        if (! config('mail.default') || config('mail.default') === 'log') {
            return 0;
        }

        $sent = 0;
        ResourceAlertReportSubscription::query()->where('enabled', true)->where(function ($query): void {
            $query->whereNull('last_sent_at')->orWhere('last_sent_at', '<=', now()->subWeek());
        })->each(function (ResourceAlertReportSubscription $subscription) use (&$sent): void {
            try {
                Notification::route('mail', $subscription->email)->notify(new ResourceAlertMailNotification(
                    trans('resourceusagealerts::strings.reports.subject'),
                    $this->body($subscription)
                ));
                $subscription->forceFill(['last_sent_at' => now()])->save();
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return $sent;
    }

    public function body(ResourceAlertReportSubscription $subscription): string
    {
        $query = ResourceAlertEvent::query()->where('triggered_at', '>=', now()->subWeek());
        $filters = $subscription->filters ?? [];
        if (filled($filters['server_id'] ?? null)) {
            $query->where('server_id', (int) $filters['server_id']);
        }
        $total = (clone $query)->count();
        $open = (clone $query)->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])->count();
        $top = (clone $query)->selectRaw('metric, COUNT(*) AS aggregate')->groupBy('metric')->orderByDesc('aggregate')->limit(5)->pluck('aggregate', 'metric');

        return trans('resourceusagealerts::strings.reports.body', [
            'total' => $total,
            'open' => $open,
            'metrics' => $top->map(fn ($count, $metric) => $metric.': '.$count)->implode(', ') ?: '-',
        ]);
    }
}
