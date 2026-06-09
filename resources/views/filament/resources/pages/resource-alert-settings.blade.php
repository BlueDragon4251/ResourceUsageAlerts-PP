<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" wire:loading.remove>
                {{ trans('resourceusagealerts::strings.actions.saved') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>