<div
    x-data="{ 
        open: false,
        id: null,
        name: '',
        file_name: '',
        mime_type: '',
        size: 0,
        url: '',
        created_at: '',
        path: '',
        source: '',
        formatSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString();
        },
        isUrl(str) {
            try {
                new URL(str);
                return true;
            } catch {
                return false;
            }
        }
    }"
    x-on:open-info-modal.window="
        open = true;
        id = $event.detail.id;
        name = $event.detail.name;
        file_name = $event.detail.file_name;
        mime_type = $event.detail.mime_type;
        size = $event.detail.size;
        url = $event.detail.url;
        created_at = $event.detail.created_at;
        path = $event.detail.path;
        source = $event.detail.source;
    "
    x-show="open"
    x-transition.opacity
    x-transition:enter.duration.300ms
    x-transition:leave.duration.200ms
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 p-4"
    x-cloak
    @keydown.escape.window="open = false">
    <div @click.away="open = false" class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden border border-white dark:border-gray-700">
        <!-- Modal header -->
        <div class="flex justify-between items-center p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <h3 class="text-base font-medium text-gray-900 dark:text-white">Media Properties</h3>
            <button
                @click="open = false"
                type="button"
                class="text-gray-400 hover:text-gray-500 focus:outline-none">
                <x-heroicon-m-x-mark class="w-5 h-5" />
            </button>
        </div>

        <!-- Modal content -->
        <div class="p-4 overflow-auto max-h-[70vh]">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" x-text="name"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">File Name</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" x-text="file_name"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">MIME Type</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" x-text="mime_type"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Size</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" x-text="formatSize(size)"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white" x-text="formatDate(created_at)"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Path</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white break-all" x-text="path"></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Source</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white break-all">
                        <template x-if="source && isUrl(source)">
                            <a :href="source" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline">
                                <span x-text="source"></span>
                                <svg class="inline-block w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </template>
                        <template x-if="!source || !isUrl(source)">
                            <span x-text="source || 'N/A'"></span>
                        </template>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Modal footer -->
        <div class="flex justify-end p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
                @click="open = false"
                type="button"
                class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600 text-sm">
                Close
            </button>
        </div>
    </div>
</div>