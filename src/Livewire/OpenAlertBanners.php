<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Livewire;

use App\Livewire\AlertBanner;
use App\Livewire\AlertBannerCollection;
use App\Models\Server;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertMessageFormatter;
use PelicanPlugins\ResourceUsageAlerts\Services\PermissionService;
use Throwable;

class OpenAlertBanners extends Component
{
    public string $panelId;

    public AlertBannerCollection $alertBanners;

    public function mount(string $panelId): void
    {
        $this->panelId = $panelId;
        $this->alertBanners = new AlertBannerCollection();
        $this->loadBanners();
    }

    public function remove(string $id): void
    {
        $this->alertBanners->forget($id);
    }

    public function render(): View
    {
        return view('resourceusagealerts::livewire.open-alert-banners');
    }

    private function loadBanners(): void
    {
        if (!$this->enabled() || !Schema::hasTable('resource_alert_events') || !auth()->check()) {
            return;
        }

        try {
            $query = ResourceAlertEvent::query()
                ->open()
                ->with(['rule', 'server', 'node'])
                ->latest('triggered_at')
                ->limit(5);

            if ($this->panelId === 'server') {
                $server = Filament::getTenant();
                if (!$server instanceof Server || !app(PermissionService::class)->canViewServerAlerts(user(), $server)) {
                    return;
                }
                $query->where('server_id', $server->id);
            } elseif ($this->panelId === 'admin') {
                if (!user()?->can('receive resourceAlertEvent') && !user()?->isRootAdmin()) {
                    return;
                }
            } else {
                return;
            }

            $formatter = app(AlertMessageFormatter::class);
            foreach ($query->get() as $event) {
                $banner = AlertBanner::make("resource_alert_{$event->id}")
                    ->title($formatter->triggeredTitle($event))
                    ->body($formatter->triggeredBody($event))
                    ->status($event->severity->filamentStatus())
                    ->closable();

                $this->alertBanners->put($banner->getId(), $banner);
            }
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts could not render alert banners.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        return filter_var(
            config('resourceusagealerts.enabled', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }
}
