<x-filament-panels::page>
    {{-- Failed Downloads Section --}}
    <x-filament::section collapsible>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <span>Failed Downloads Log</span>
                <div class="flex gap-2">
                    @php $stats = $this->getFailedStats(); @endphp
                    @if($stats['pending'] > 0)
                    <x-filament::button
                        wire:click="retryAllPending"
                        size="sm"
                        color="warning"
                        icon="heroicon-m-arrow-path">
                        Retry All Pending ({{ $stats['pending'] }})
                    </x-filament::button>
                    @endif
                    @if($stats['resolved'] > 0)
                    <x-filament::button
                        wire:click="clearResolved"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-trash">
                        Clear Resolved
                    </x-filament::button>
                    @endif
                </div>
            </div>
        </x-slot>

        {{-- Stats Bar --}}
        <div class="flex gap-4 mb-4 text-sm">
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-warning-400"></span>
                <span class="text-gray-600 dark:text-gray-400">Pending: {{ $stats['pending'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                <span class="text-gray-600 dark:text-gray-400">Retrying: {{ $stats['retrying'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-danger-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Failed: {{ $stats['failed'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Resolved: {{ $stats['resolved'] }}</span>
            </div>
        </div>

        @php $downloads = $this->getFailedDownloads(); @endphp
        @if(count($downloads) > 0)
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @foreach($downloads as $download)
            <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $download['url'] }}
                    </div>
                    @if($download['error_message'])
                    <div class="text-xs text-danger-500 mt-1 line-clamp-2">
                        {{ $download['error_message'] }}
                    </div>
                    @endif
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <span>Attempts: {{ $download['retry_count'] }}</span>
                        @if($download['last_attempt_at'])
                        <span>•</span>
                        <span>Last: {{ \Carbon\Carbon::parse($download['last_attempt_at'])->diffForHumans() }}</span>
                        @endif
                        @if($download['next_retry_at'])
                        <span>•</span>
                        <span>Next: {{ \Carbon\Carbon::parse($download['next_retry_at'])->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    @switch($download['status'])
                    @case('pending')
                    <span class="px-2 py-1 text-xs rounded-full bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                        Pending Retry
                    </span>
                    @break
                    @case('retrying')
                    <span class="px-2 py-1 text-xs rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 animate-pulse">
                        Retrying...
                    </span>
                    @break
                    @case('failed')
                    <span class="px-2 py-1 text-xs rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-300">
                        Permanently Failed
                    </span>
                    @break
                    @case('resolved')
                    <span class="px-2 py-1 text-xs rounded-full bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300">
                        <x-heroicon-s-check class="w-3 h-3 inline" /> Resolved
                    </span>
                    @break
                    @endswitch

                    @if($download['status'] !== 'retrying')
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
                    @endif
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

    {{-- Failed Uploads Section --}}
    <x-filament::section collapsible>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <span>Failed Uploads Log</span>
                <div class="flex gap-2">
                    @php $uploadStats = $this->getFailedUploadStats(); @endphp
                    @if($uploadStats['resolved'] > 0)
                    <x-filament::button
                        wire:click="clearResolvedUploads"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-trash">
                        Clear Resolved
                    </x-filament::button>
                    @endif
                </div>
            </div>
        </x-slot>

        {{-- Stats Bar --}}
        <div class="flex gap-4 mb-4 text-sm">
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-warning-400"></span>
                <span class="text-gray-600 dark:text-gray-400">Pending: {{ $uploadStats['pending'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-danger-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Failed: {{ $uploadStats['failed'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Resolved: {{ $uploadStats['resolved'] }}</span>
            </div>
        </div>

        @php $uploads = $this->getFailedUploads(); @endphp
        @if(count($uploads) > 0)
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @foreach($uploads as $upload)
            <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $upload['filename'] }}
                    </div>
                    @if($upload['error_message'])
                    <div class="text-xs text-danger-500 mt-1 line-clamp-2">
                        {{ $upload['error_message'] }}
                    </div>
                    @endif
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <span>Attempts: {{ $upload['retry_count'] }}</span>
                        @if($upload['last_attempt_at'])
                        <span>•</span>
                        <span>Last: {{ \Carbon\Carbon::parse($upload['last_attempt_at'])->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    @switch($upload['status'])
                    @case('pending')
                    <span class="px-2 py-1 text-xs rounded-full bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                        Pending
                    </span>
                    @break
                    @case('failed')
                    <span class="px-2 py-1 text-xs rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-300">
                        Permanently Failed
                    </span>
                    @break
                    @case('resolved')
                    <span class="px-2 py-1 text-xs rounded-full bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300">
                        <x-heroicon-s-check class="w-3 h-3 inline" /> Resolved
                    </span>
                    @break
                    @endswitch

                    <x-filament::icon-button
                        wire:click="deleteUpload({{ $upload['id'] }})"
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
            <p>No failed uploads</p>
        </div>
        @endif
    </x-filament::section>

    {{-- System Logs Form --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>