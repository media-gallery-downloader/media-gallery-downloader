@php
$data = $this->getQueueData();
$hasCompleted = ($data['stats']['completed'] ?? 0) > 0;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Download Queue</span>
                @if($hasCompleted)
                <x-filament::button
                    wire:click="clearCompleted"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-trash">
                    Clear Completed
                </x-filament::button>
                @endif
            </div>
        </x-slot>

        {{-- Stats Bar --}}
        <div class="flex gap-4 mb-4 text-sm">
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                <span class="text-gray-600 dark:text-gray-400">Pending: {{ $data['stats']['pending'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                <span class="text-gray-600 dark:text-gray-400">Downloading: {{ $data['stats']['downloading'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Completed: {{ $data['stats']['completed'] }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-danger-500"></span>
                <span class="text-gray-600 dark:text-gray-400">Failed: {{ $data['stats']['failed'] }}</span>
            </div>
        </div>

        @if(count($data['items']) > 0)
        <div class="space-y-2">
            @foreach($data['items'] as $item)
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $item['url'] }}
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>Method: {{ $item['method'] ?? 'auto' }}</span>
                        @if(isset($item['added_at']))
                        <span>•</span>
                        <span>{{ \Carbon\Carbon::parse($item['added_at'])->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    @switch($item['status'] ?? 'queued')
                    @case('queued')
                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        Queued
                    </span>
                    @break
                    @case('downloading')
                    <span class="px-2 py-1 text-xs rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 animate-pulse">
                        Downloading...
                    </span>
                    @break
                    @case('completed')
                    <span class="px-2 py-1 text-xs rounded-full bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300">
                        <x-heroicon-s-check class="w-3 h-3 inline" /> Completed
                    </span>
                    @break
                    @case('failed')
                    <span class="px-2 py-1 text-xs rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-300">
                        <x-heroicon-s-x-mark class="w-3 h-3 inline" /> Failed
                    </span>
                    <x-filament::button
                        wire:click="retryFailed('{{ $item['id'] }}')"
                        size="xs"
                        color="warning"
                        icon="heroicon-m-arrow-path">
                        Retry
                    </x-filament::button>
                    @break
                    @default
                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        {{ $item['status'] ?? 'Unknown' }}
                    </span>
                    @endswitch
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No downloads in queue</p>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
