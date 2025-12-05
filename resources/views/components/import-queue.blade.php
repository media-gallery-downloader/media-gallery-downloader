<div
    x-data="{ 
        importQueue: @js($importQueue),
        currentImportId: @js($currentImportId)
    }"
    @refresh-import-queue.window="
        importQueue = [...$wire.importQueue];
        currentImportId = $wire.currentImportId;
    "
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95">

    <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-medium">Import Queue</h3>
        <div class="flex gap-2" x-show="importQueue.length > 0">
            <button
                @click="$wire.clearImportQueue()"
                title="Clear all imports"
                class="text-xs px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                Clear All
            </button>
        </div>
    </div>

    <div x-show="importQueue.length === 0" class="text-sm text-gray-500 dark:text-gray-400 italic">
        Import queue is empty.
    </div>

    <ul class="space-y-2" x-show="importQueue.length > 0">
        <template x-for="(item, index) in importQueue" :key="item.id">
            <li class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="flex justify-between items-start">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.path"></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Type: <span x-text="item.type?.replace('_', ' ') || 'unknown'"></span> •
                            Added: <span x-text="new Date(item.added_at).toLocaleString()"></span>
                            <template x-if="item.total_files > 0">
                                <span> • Files: <span x-text="item.processed_files + '/' + item.total_files"></span></span>
                            </template>
                        </div>
                    </div>
                    <div class="ml-4 flex items-center gap-2">
                        <template x-if="currentImportId === item.id">
                            <div class="flex flex-col items-end">
                                <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded dark:bg-purple-900 dark:text-purple-300 animate-pulse flex items-center">
                                    <svg class="inline w-3 h-3 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Importing <span x-show="item.progress" x-text="'(' + Math.round(item.progress) + '%)'" class="ml-1"></span>
                                </span>
                                <div class="w-24 h-1 bg-gray-200 rounded-full mt-1 dark:bg-gray-600 overflow-hidden" x-show="item.progress">
                                    <div class="h-full bg-purple-500 rounded-full transition-all duration-500" :style="'width: ' + (item.progress || 0) + '%'"></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="item.status === 'failed'">
                            <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded dark:bg-red-900 dark:text-red-300" title="Error" x-text="'Failed'"></span>
                        </template>
                        <template x-if="currentImportId !== item.id && item.status !== 'failed'">
                            <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded dark:bg-gray-600 dark:text-gray-300" x-text="'#' + (index + 1)"></span>
                        </template>
                        <button
                            @click="$wire.cancelImport(item.id)"
                            title="Cancel Import"
                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </li>
        </template>
    </ul>
</div>
