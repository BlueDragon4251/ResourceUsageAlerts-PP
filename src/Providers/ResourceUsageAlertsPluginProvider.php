<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Providers;

use App\Enums\TabPosition;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Role;
use App\Models\Subuser;
use App\Models\User;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PelicanPlugins\ResourceUsageAlerts\Jobs\CleanupOldAlertSamplesJob;
use PelicanPlugins\ResourceUsageAlerts\Jobs\CollectResourceSamplesJob;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Policies\ResourceAlertChannelPolicy;
use PelicanPlugins\ResourceUsageAlerts\Policies\ResourceAlertEventPolicy;
use PelicanPlugins\ResourceUsageAlerts\Policies\ResourceAlertRulePolicy;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertMessageFormatter;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertNotificationService;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;
use PelicanPlugins\ResourceUsageAlerts\Services\PermissionService;
use PelicanPlugins\ResourceUsageAlerts\Services\ResourceSampleService;
use PelicanPlugins\ResourceUsageAlerts\Services\WebPushNotificationService;

class ResourceUsageAlertsPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceSampleService::class);
        $this->app->singleton(AlertRuleEvaluator::class);
        $this->app->singleton(AlertMessageFormatter::class);
        $this->app->singleton(AlertNotificationService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(WebPushNotificationService::class);

        Role::registerCustomDefaultPermissions('resourceAlertRule');
        Role::registerCustomPermissions([
            'resourceAlertEvent' => ['viewList', 'view', 'update', 'delete', 'receive'],
            'resourceAlertChannel' => ['viewList', 'view', 'create', 'update', 'delete'],
        ]);
        Role::registerCustomModelIcon('resourceAlertRule', 'tabler-bell-ringing');
        Role::registerCustomModelIcon('resourceAlertEvent', 'tabler-alert-triangle');

        Subuser::registerCustomPermissions(
            'alerts',
            ['view', 'create', 'update', 'delete', 'channels', 'receive'],
            'resourceusagealerts::permissions',
            'tabler-bell-ringing'
        );
    }

    public function boot(): void
    {
        EditProfile::registerCustomTabs(
            TabPosition::After,
            Tab::make('resource_alert_push')
                ->label(trans('resourceusagealerts::strings.push.profile_tab'))
                ->icon('tabler-bell-ringing')
                ->schema([
                    View::make('resourceusagealerts::filament.profile.push-notifications')
                        ->columnSpanFull(),
                ])
        );

        Gate::policy(ResourceAlertRule::class, ResourceAlertRulePolicy::class);
        Gate::policy(ResourceAlertEvent::class, ResourceAlertEventPolicy::class);
        Gate::policy(ResourceAlertChannel::class, ResourceAlertChannelPolicy::class);

        Gate::before(fn (User $user): ?bool => $user->isRootAdmin() ? true : null);

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $enabled = filter_var(
                config('resourceusagealerts.enabled', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;

            if (!$enabled) {
                return;
            }

            $interval = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5));

            $schedule->job(new CollectResourceSamplesJob())
                ->cron("*/{$interval} * * * *")
                ->name('resource-usage-alerts:collect')
                ->withoutOverlapping();
            $schedule->job(new CleanupOldAlertSamplesJob())
                ->daily()
                ->name('resource-usage-alerts:cleanup')
                ->withoutOverlapping();
        });
    }
}
