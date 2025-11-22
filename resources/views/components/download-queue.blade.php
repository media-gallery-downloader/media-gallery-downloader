<div
    x-data="{ 
        downloadQueue: @js($downloadQueue),
        currentDownloadId: @js($currentDownloadId)
    }"
    @refresh-download-queue.window="
        downloadQueue = [...$wire.downloadQueue];
        currentDownloadId = $wire.currentDownloadId;
    "
    x-show="downloadQueue.length > 0"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95">

    <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-medium">Download Queue</h3>
        <div class="flex gap-2">
            <button
                @click="$wire.clearQueue()"
                class="text-xs px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                Clear All
            </button>
        </div>
    </div>

    @if(!empty($downloadQueue))
    <ul class="space-y-2">
        @foreach($downloadQueue as $index => $item)
        <li class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="flex justify-between items-start">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $item['url'] }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Method: {{ $item['method'] ?? 'unknown' }} •
                        Added: {{ \Carbon\Carbon::parse($item['added_at'])->diffForHumans() }}
                    </div>
                </div>
                <div class="ml-4 flex items-center gap-2">
                    @if($currentDownloadId === $item['id'])
                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded dark:bg-blue-900 dark:text-blue-300 animate-pulse">
                        <svg class="inline w-3 h-3 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Downloading
                    </span>
                    @else
                    <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded dark:bg-gray-600 dark:text-gray-300">
                        #{{ $index + 1 }}
                    </span>
                    @endif
                    <button
                        @click="$wire.cancelDownload('{{ $item['id'] }}')"
                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
    @endif
</div>