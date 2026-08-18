<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Services\RuntimeHealthService;

class AlertSetupChecklist extends Widget
{
    protected string $view = 'resourceusagealerts::widgets.alert-setup-checklist';

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, array{label: string, ready: bool, hint: string}> */
    public function checks(): array
    {
        $collection = app(RuntimeHealthService::class)->status('collection');

        return [
            ['label' => trans('resourceusagealerts::strings.setup.migrations'), 'ready' => Schema::hasTable('resource_alert_events') && Schema::hasTable('resource_alert_samples'), 'hint' => 'php artisan migrate'],
            ['label' => trans('resourceusagealerts::strings.setup.queue'), 'ready' => (string) config('queue.default', 'sync') !== 'sync', 'hint' => 'php artisan queue:work'],
            ['label' => trans('resourceusagealerts::strings.setup.scheduler'), 'ready' => $collection['completed_at']?->isAfter(now()->subMinutes(15)) ?? false, 'hint' => 'php artisan schedule:work'],
            ['label' => trans('resourceusagealerts::strings.setup.push'), 'ready' => filled(config('resourceusagealerts.vapid_public_key')) && filled(config('resourceusagealerts.vapid_private_key')), 'hint' => 'php artisan resource-alerts:generate-push-keys'],
            ['label' => trans('resourceusagealerts::strings.setup.mail'), 'ready' => filled(config('mail.default')) && config('mail.default') !== 'log', 'hint' => trans('resourceusagealerts::strings.setup.mail_hint')],
            ['label' => trans('resourceusagealerts::strings.setup.doctor'), 'ready' => true, 'hint' => 'php artisan resource-alerts:doctor'],
            ['label' => trans('resourceusagealerts::strings.setup.test'), 'ready' => Schema::hasTable('resource_alert_delivery_attempts') && DB::table('resource_alert_delivery_attempts')->where('status', 'sent')->exists(), 'hint' => trans('resourceusagealerts::strings.setup.test_hint')],
        ];
    }
}
