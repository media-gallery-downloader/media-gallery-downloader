<x-filament-panels::page>
    <div class="flex flex-col" style="height: calc(100vh - 200px);">
        <!-- Header with download button -->
        <div class="flex items-center justify-end p-4 bg-white dark:bg-gray-900 rounded-t-xl border border-gray-200 dark:border-gray-700">
            <x-filament::button
                tag="a"
                href="{{ $this->media->url }}"
                download="{{ $this->media->file_name }}"
                icon="heroicon-m-arrow-down-tray"
                size="sm">
                Download
            </x-filament::button>
        </div>

        <!-- Media Content -->
        <div class="flex-1 flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-800 overflow-auto">
            @if(Str::contains($this->media->mime_type, 'video'))
            <video
                controls
                class="max-w-full max-h-full rounded-lg shadow-xl"
                style="max-height: calc(100vh - 350px);">
                <source src="{{ $this->media->url }}" type="{{ $this->media->mime_type }}">
                Your browser does not support video playback.
            </video>
            @elseif(Str::contains($this->media->mime_type, 'audio'))
            <div class="flex flex-col items-center gap-6">
                <div class="w-48 h-48 flex items-center justify-center bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl shadow-xl">
                    <x-heroicon-s-musical-note class="w-24 h-24 text-white opacity-80" />
                </div>
                <audio controls class="w-full max-w-md">
                    <source src="{{ $this->media->url }}" type="{{ $this->media->mime_type }}">
                    Your browser does not support audio playback.
                </audio>
            </div>
            @elseif(Str::contains($this->media->mime_type, 'image'))
            <img
                src="{{ $this->media->url }}"
                alt="{{ $this->media->name }}"
                class="max-w-full max-h-full object-contain rounded-lg shadow-xl"
                style="max-height: calc(100vh - 350px);">
            @elseif(Str::contains($this->media->mime_type, 'pdf'))
            <iframe
                src="{{ $this->media->url }}"
                class="w-full h-full rounded-lg bg-white"
                style="min-height: calc(100vh - 350px);">
            </iframe>
            @else
            <div class="text-center">
                <div class="w-32 h-32 mx-auto mb-4 flex items-center justify-center bg-gray-200 dark:bg-gray-700 rounded-2xl">
                    <x-heroicon-s-document class="w-16 h-16 text-gray-400" />
                </div>
                <p class="text-gray-500 dark:text-gray-400 mb-4">This file type cannot be previewed in the browser.</p>
                <x-filament::button
                    tag="a"
                    href="{{ $this->media->url }}"
                    download="{{ $this->media->file_name }}"
                    icon="heroicon-m-arrow-down-tray">
                    Download File
                </x-filament::button>
            </div>
            @endif
        </div>

        <!-- Footer with file info -->
        <div class="p-3 bg-white dark:bg-gray-900 rounded-b-xl border border-t-0 border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1">
                    <x-heroicon-m-document class="w-4 h-4" />
                    {{ $this->media->mime_type }}
                </span>
                <span class="flex items-center gap-1">
                    <x-heroicon-m-circle-stack class="w-4 h-4" />
                    {{ number_format($this->media->size / 1024 / 1024, 2) }} MB
                </span>
                <span class="flex items-center gap-1">
                    <x-heroicon-m-calendar class="w-4 h-4" />
                    {{ $this->media->created_at->format('M d, Y g:i A') }}
                </span>
                @if($this->media->source)
                <a href="{{ $this->media->source }}" target="_blank" class="flex items-center gap-1 text-primary-500 hover:text-primary-400">
                    <x-heroicon-m-link class="w-4 h-4" />
                    Source
                </a>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>