<x-filament-panels::page class="fi-dashboard-page">
    <div>
        <x-filament-panels::form wire:submit="submit">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>
</x-filament-panels::page>