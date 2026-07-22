<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Livewire;

use App\Models\Server;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use PelicanPlugins\ResourceUsageAlerts\Services\BlueItAnnouncementService;
use Throwable;

class BlueItAnnouncements extends Component
{
    public string $panelId;

    /** @var array<int, array<string, mixed>> */
    public array $popupAnnouncements = [];

    /** @var array<int, string> */
    public array $sentAnnouncementIds = [];

    public int $pollSeconds = 10;

    public function mount(string $panelId): void
    {
        $this->panelId = $panelId;
        $this->pollSeconds = max(5, (int) config('resourceusagealerts.blueit_announcements_poll_seconds', 10));
    }

    public function refreshAnnouncements(): void
    {
        try {
            if (!auth()->check()) {
                return;
            }

            $service = app(BlueItAnnouncementService::class);
            $server = $this->server();
            if (!$service->canReceive(user(), $server)) {
                $this->popupAnnouncements = [];

                return;
            }

            $activeAnnouncements = $service->announcementsFor(user(), $server);
            $service->removeInactiveDatabaseNotifications(user(), $activeAnnouncements);

            $visibleIds = collect($this->popupAnnouncements)
                ->pluck('read_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();
            $this->popupAnnouncements = collect($service->unreadFor(user(), $server))
                ->filter(fn (array $announcement): bool =>
                    in_array((string) $announcement['read_id'], $visibleIds, true)
                    || !in_array((string) $announcement['read_id'], $this->sentAnnouncementIds, true)
                )
                ->values()
                ->all();

            foreach ($this->popupAnnouncements as $announcement) {
                $delivered = false;
                $readId = (string) $announcement['read_id'];

                try {
                    if (!$service->wasDelivered(user(), $readId)) {
                        $service->sendToDatabase(user(), $announcement);
                        $service->markDelivered(user(), $readId);
                    }
                    $delivered = true;
                } catch (Throwable $exception) {
                    Log::debug('Resource Usage Alerts BlueIT announcement database notification failed.', [
                        'announcement_id' => $announcement['id'] ?? null,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                }

                if ($delivered) {
                    $this->sentAnnouncementIds[] = $readId;
                }
            }
        } catch (Throwable $exception) {
            Log::debug('Resource Usage Alerts BlueIT announcement component failed to refresh.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->popupAnnouncements = [];
        }
    }

    public function dismiss(string $announcementId): void
    {
        if (!auth()->check()) {
            return;
        }

        app(BlueItAnnouncementService::class)->dismiss(user(), $announcementId);
        $this->popupAnnouncements = collect($this->popupAnnouncements)
            ->reject(fn (array $announcement): bool => (string) $announcement['read_id'] === $announcementId)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('resourceusagealerts::livewire.blueit-announcements');
    }

    private function server(): ?Server
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Server ? $tenant : null;
    }
}
