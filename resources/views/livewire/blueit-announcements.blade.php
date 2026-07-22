<div wire:init="refreshAnnouncements" wire:poll.10s="refreshAnnouncements">
    @if ($popupAnnouncements !== [])
        <div
            x-data="{ openAnnouncements: @js(array_values(array_column($popupAnnouncements, 'read_id'))) }"
            x-show="openAnnouncements.length > 0"
            style="position: fixed; inset: 0; z-index: 2147483000; background: rgba(0, 0, 0, 0.45);"
        >
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box;">
            <div style="display: flex; width: 100%; max-width: 48rem; max-height: calc(100vh - 2rem); flex-direction: column; gap: 0.75rem; overflow-y: auto;">
    @endif
    @foreach ($popupAnnouncements as $announcement)
        <div
            x-init="
                const closeDuplicate = () => document.querySelectorAll('.fi-no-notification').forEach((notification) => {
                    const title = notification.querySelector('.fi-no-notification-title')?.textContent?.trim();
                    const body = notification.querySelector('.fi-no-notification-body')?.textContent?.trim();
                    if (title === @js($announcement['title_text']) && body === @js($announcement['body_text'])) {
                        notification.querySelector('.fi-no-notification-close-btn')?.click();
                    }
                });
                window.dispatchEvent(new CustomEvent('close-notification', { detail: { id: @js($announcement['notification_id']) } }));
                closeDuplicate();
                setTimeout(closeDuplicate, 250);
                setTimeout(closeDuplicate, 1000);
            "
            x-show="openAnnouncements.includes(@js($announcement['read_id']))"
            class="rounded-xl border border-primary-500/30 bg-white p-4 shadow-2xl dark:bg-gray-900"
            style="width: 100%; box-sizing: border-box;"
        >
            @if (!empty($announcement['image_url']))
                <img src="{{ $announcement['image_url'] }}" alt="" loading="eager" onerror="this.style.display='none'" class="mb-3 rounded-lg" style="display: block; width: 100%; height: 14rem; max-height: 14rem; object-fit: cover;">
            @endif
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600">{{ $announcement['type'] === 'update' ? trans('resourceusagealerts::strings.announcements.update') : trans('resourceusagealerts::strings.announcements.normal') }}</p>
                    <h3 class="mt-1 text-base font-bold text-gray-950 dark:text-white">{{ $announcement['title_text'] }}</h3>
                </div>
                <button type="button" x-on:click="openAnnouncements = openAnnouncements.filter((id) => id !== @js($announcement['read_id'])); $wire.dismiss(@js($announcement['read_id']))" class="text-lg leading-none text-gray-500 hover:text-gray-900 dark:hover:text-white">×</button>
            </div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $announcement['body_text'] }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @if (!empty($announcement['button_url']))
                    <a href="{{ $announcement['button_url'] }}" target="{{ str_starts_with($announcement['button_url'], '/') ? '_self' : '_blank' }}" rel="noreferrer" class="rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white">
                        {{ $announcement['button_text'] }}
                    </a>
                @endif
                <button type="button" x-on:click="openAnnouncements = openAnnouncements.filter((id) => id !== @js($announcement['read_id'])); $wire.dismiss(@js($announcement['read_id']))" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                    {{ trans('resourceusagealerts::strings.announcements.dismiss') }}
                </button>
            </div>
        </div>
    @endforeach
    @if ($popupAnnouncements !== [])
            </div>
            </div>
        </div>
    @endif
</div>
