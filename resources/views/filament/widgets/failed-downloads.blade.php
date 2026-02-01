@php
$downloads = $this->getFailedDownloads();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Failed Downloads Log
        </x-slot>

        @if(count($downloads) > 0)
        <div class="space-y-2 max-h-48 overflow-y-auto">
            @foreach($downloads as $download)
            <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex-1 min-w-0">
                    <a href="{{ $download['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block">
                        {{ $download['url'] }}
                    </a>
                    @if($download['error_message'])
                    <div class="text-xs text-danger-500 mt-1 line-clamp-2">
                        {{ $download['error_message'] }}
                    </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <x-filament::icon-button
                        wire:click="retryDownload({{ $download['id'] }})"
                        icon="heroicon-m-arrow-path"
                        color="warning"
                        size="sm"
                        tooltip="Retry download" />
                    <x-filament::icon-button
                        wire:click="deleteDownload({{ $download['id'] }})"
                        icon="heroicon-m-trash"
                        color="danger"
                        size="sm"
                        tooltip="Delete" />
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
            <p>No failed downloads</p>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
