<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\Server;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Pages\ResourceAlerts;
use Throwable;

class BlueItAnnouncementService
{
    private const PLUGIN_VERSION = '1.3.5';

    /** @return array<int, array<string, mixed>> */
    public function announcementsFor(User $user, ?Server $server = null): array
    {
        if (!$this->canReceive($user, $server)) {
            return [];
        }

        return collect($this->fetchAnnouncements())
            ->map(fn (array $announcement): array => $this->localize($announcement))
            ->filter(fn (array $announcement): bool => $this->isSafeAnnouncement($announcement))
            ->filter(fn (array $announcement): bool => $this->matchesPluginVersion($announcement))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function unreadFor(User $user, ?Server $server = null): array
    {
        $read = $this->readIds($user);

        return collect($this->announcementsFor($user, $server))
            ->reject(fn (array $announcement): bool => in_array($this->readKey($announcement), $read, true))
            ->values()
            ->all();
    }

    public function dismiss(User $user, string $announcementId): void
    {
        if (!Schema::hasTable('resource_usage_alerts_announcement_reads')) {
            return;
        }

        DB::table('resource_usage_alerts_announcement_reads')->updateOrInsert(
            ['user_id' => $user->id, 'announcement_id' => $announcementId],
            ['dismissed_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function markDelivered(User $user, string $announcementId): void
    {
        if (!Schema::hasTable('resource_usage_alerts_announcement_reads')) {
            return;
        }

        DB::table('resource_usage_alerts_announcement_reads')->updateOrInsert(
            ['user_id' => $user->id, 'announcement_id' => $announcementId],
            ['dismissed_at' => null, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function wasDelivered(User $user, string $announcementId): bool
    {
        if (!Schema::hasTable('resource_usage_alerts_announcement_reads')) {
            return false;
        }

        return DB::table('resource_usage_alerts_announcement_reads')
            ->where('user_id', $user->id)
            ->where('announcement_id', $announcementId)
            ->exists();
    }

    /** @param array<string, mixed> $announcement */
    public function sendToDatabase(User $user, array $announcement): void
    {
        $this->notification($announcement)->sendToDatabase($user);
    }

    /** @param array<int, array<string, mixed>> $announcements */
    public function removeInactiveDatabaseNotifications(User $user, array $announcements): void
    {
        if (!$this->announcementsRequestSucceeded()) {
            return;
        }

        $activeIds = collect($announcements)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->filter()
            ->all();
        $knownIds = $this->knownAnnouncementIds($user);

        $user->notifications()
            ->get()
            ->each(function ($notification) use ($activeIds, $knownIds): void {
                $data = is_array($notification->data) ? $notification->data : [];
                $viewData = is_array($data['viewData'] ?? null) ? $data['viewData'] : [];
                $pluginId = (string) ($viewData['blueit_plugin_id'] ?? '');
                $announcementId = (string) ($viewData['blueit_announcement_id'] ?? '');

                if ($announcementId === '') {
                    $announcementId = $this->legacyAnnouncementId($data);
                }

                $belongsToPlugin = $pluginId === 'resourceusagealerts'
                    || ($pluginId === '' && in_array($announcementId, $knownIds, true));

                if ($belongsToPlugin && !in_array($announcementId, $activeIds, true)) {
                    $notification->delete();
                }
            });
    }

    /** @param array<string, mixed> $announcement */
    private function notification(array $announcement): Notification
    {
        $buttonUrl = $this->safeUrl((string) ($announcement['button_url'] ?? ''));
        $buttonText = (string) ($announcement['button_text'] ?? trans('resourceusagealerts::strings.announcements.open'));

        $notification = Notification::make((string) $announcement['notification_id'])
            ->status(($announcement['type'] ?? 'normal') === 'update' ? 'warning' : 'info')
            ->title((string) $announcement['title_text'])
            ->body((string) $announcement['body_text'])
            ->viewData([
                'blueit_plugin_id' => 'resourceusagealerts',
                'blueit_announcement_id' => (string) $announcement['id'],
                'blueit_read_id' => (string) $announcement['read_id'],
            ]);

        if ($buttonUrl) {
            $notification->actions([
                Action::make('open_blueit_resourceusagealerts_announcement_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $announcement['id']))
                    ->button()
                    ->label($buttonText)
                    ->markAsRead()
                    ->url($buttonUrl),
            ]);
        }

        return $notification;
    }

    public function canReceive(?User $user, ?Server $server = null): bool
    {
        if (!$user || !(bool) config('resourceusagealerts.blueit_announcements_enabled', true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (!$server) {
            return $user->servers()->exists()
                || $user->subusers()
                    ->get()
                    ->contains(fn ($subuser): bool => in_array(
                        'alerts.announcements',
                        (array) $subuser->permissions,
                        true
                    ));
        }

        return $server->owner_id === $user->id || $user->can('alerts.announcements', $server);
    }

    /** @return array<int, string> */
    private function readIds(User $user): array
    {
        if (!Schema::hasTable('resource_usage_alerts_announcement_reads')) {
            return [];
        }

        return DB::table('resource_usage_alerts_announcement_reads')
            ->where('user_id', $user->id)
            ->whereNotNull('dismissed_at')
            ->pluck('announcement_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAnnouncements(): array
    {
        $result = $this->fetchAnnouncementsResult();

        return is_array($result['announcements'] ?? null) ? $result['announcements'] : [];
    }

    /** @return array{available: bool, announcements: array<int, array<string, mixed>>} */
    private function fetchAnnouncementsResult(): array
    {
        $url = (string) config('resourceusagealerts.blueit_announcements_url', '');
        if ($url === '') {
            return ['available' => false, 'announcements' => []];
        }

        return Cache::remember('resourceusagealerts.blueit_announcements', 10, function () use ($url): array {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->get($url, [
                        'plugin_id' => 'resourceusagealerts',
                        'version' => self::PLUGIN_VERSION,
                    ]);

                if (!$response->successful() || !$this->validSignature($response->body(), (string) $response->header('x-blueit-signature', ''))) {
                    return ['available' => false, 'announcements' => []];
                }

                $data = $response->json();

                return [
                    'available' => true,
                    'announcements' => is_array($data['announcements'] ?? null) ? $data['announcements'] : [],
                ];
            } catch (Throwable $exception) {
                Log::debug('Resource Usage Alerts BlueIT announcements unavailable.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return ['available' => false, 'announcements' => []];
            }
        });
    }

    private function announcementsRequestSucceeded(): bool
    {
        return (bool) ($this->fetchAnnouncementsResult()['available'] ?? false);
    }

    /** @return array<int, string> */
    private function knownAnnouncementIds(User $user): array
    {
        if (!Schema::hasTable('resource_usage_alerts_announcement_reads')) {
            return [];
        }

        return DB::table('resource_usage_alerts_announcement_reads')
            ->where('user_id', $user->id)
            ->pluck('announcement_id')
            ->map(fn (mixed $id): string => explode(':', (string) $id, 2)[0])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function legacyAnnouncementId(array $data): string
    {
        foreach ((array) ($data['actions'] ?? []) as $action) {
            $name = is_array($action) ? (string) ($action['name'] ?? '') : '';
            if (str_starts_with($name, 'open_blueit_announcement_')) {
                return substr($name, strlen('open_blueit_announcement_'));
            }
        }

        return '';
    }

    private function validSignature(string $body, string $signature): bool
    {
        $secret = (string) config('resourceusagealerts.blueit_announcements_secret', '');
        if ($secret === '') {
            return true;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $body, $secret), $signature);
    }

    /** @param array<string, mixed> $announcement */
    private function localize(array $announcement): array
    {
        $locale = str_starts_with(strtolower((string) app()->getLocale()), 'de') ? 'de' : 'en';

        $announcement['title_text'] = $this->localizedValue($announcement['title'] ?? [], $locale);
        $announcement['body_text'] = $this->localizedValue($announcement['body'] ?? [], $locale);
        $announcement['button_text'] = $this->localizedValue($announcement['button_label'] ?? [], $locale)
            ?: trans('resourceusagealerts::strings.announcements.open');
        $announcement['button_url'] = $this->safeUrl((string) ($announcement['button_url'] ?? ''));
        $announcement['image_url'] = $this->safeUrl((string) ($announcement['image_url'] ?? ''));
        $announcement['read_id'] = $this->readKey($announcement);
        $announcement['notification_id'] = 'blueit-resourceusagealerts-' . hash('sha256', $announcement['read_id']);

        $tenant = Filament::getTenant();
        if (($announcement['type'] ?? 'normal') === 'update' && !$announcement['button_url'] && $tenant instanceof Server) {
            $announcement['button_url'] = ResourceAlerts::getUrl(panel: 'server', tenant: $tenant);
        }

        return $announcement;
    }

    /** @param mixed $value */
    private function localizedValue(mixed $value, string $locale): string
    {
        if (!is_array($value)) {
            return '';
        }

        return trim((string) ($value[$locale] ?? $value['en'] ?? $value['de'] ?? ''));
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://') ? $url : null;
    }

    /** @param array<string, mixed> $announcement */
    private function readKey(array $announcement): string
    {
        $id = (string) ($announcement['id'] ?? '');
        $updatedAt = trim((string) ($announcement['updated_at'] ?? $announcement['updatedAt'] ?? ''));

        return ($updatedAt !== '' ? $id . ':' . $updatedAt : $id) . ':popup-v2';
    }

    /** @param array<string, mixed> $announcement */
    private function matchesPluginVersion(array $announcement): bool
    {
        if (($announcement['type'] ?? 'normal') === 'update') {
            $updateVersion = trim((string) ($announcement['update_version'] ?? $announcement['updateVersion'] ?? ''));

            return $updateVersion === '' || version_compare(self::PLUGIN_VERSION, $updateVersion, '<');
        }

        $versions = collect(explode(',', (string) ($announcement['plugin_versions'] ?? $announcement['pluginVersions'] ?? '')))
            ->map(fn (string $version): string => trim($version))
            ->filter()
            ->values()
            ->all();

        return $versions === [] || in_array(self::PLUGIN_VERSION, $versions, true);
    }

    /** @param array<string, mixed> $announcement */
    private function isSafeAnnouncement(array $announcement): bool
    {
        return (string) ($announcement['id'] ?? '') !== ''
            && in_array((string) ($announcement['type'] ?? 'normal'), ['normal', 'update'], true)
            && (string) ($announcement['title_text'] ?? '') !== ''
            && (string) ($announcement['body_text'] ?? '') !== '';
    }
}
