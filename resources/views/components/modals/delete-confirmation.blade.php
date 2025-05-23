<div
    x-data="{ 
        open: false,
        id: null,
        name: '',
        isDeleting: false,
        async deleteItem() {
            this.isDeleting = true;
            try {
                const token = document.querySelector('meta[name=\'csrf-token\']').getAttribute('content');
                
                const response = await fetch(`/api/media/${this.id}`, {
                    method: 'DELETE',
                    headers: { 
                        'X-CSRF-TOKEN': token, 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    // Success - reload the page
                    window.location.reload();
                } else {
                    // Handle error
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Could not delete the item'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            } finally {
                this.isDeleting = false;
                this.open = false;
            }
        }
    }"
    x-on:open-delete-modal.window="
        open = true;
        id = $event.detail.id;
        name = $event.detail.name;
    "
    x-show="open"
    style="display: none"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 p-4"
    @keydown.escape.window="open = false">
    <div @click.away="open = false" class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden border border-white dark:border-gray-700">
        <!-- Modal header -->
        <div class="flex justify-between items-center p-3 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-gray-900">
            <h3 class="text-base font-medium text-gray-900 dark:text-white">Delete Media Item</h3>
            <button @click="open = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                <x-heroicon-m-x-mark class="w-5 h-5" />
            </button>
        </div>

        <!-- Modal content -->
        <div class="p-4">
            <div class="flex items-center mb-4 text-red-600">
                <x-heroicon-m-exclamation-triangle class="w-6 h-6 mr-2" />
                <span class="font-medium">Confirm Deletion</span>
            </div>

            <!-- Enhanced confirmation text -->
            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md mb-3">
                <p class="text-red-800 dark:text-red-200 font-medium text-base">
                    Are you sure you want to delete: <span class="font-bold" x-text="name"></span>?
                </p>
            </div>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This action cannot be undone. The file will be permanently deleted from the server.
            </p>
        </div>

        <!-- Modal footer -->
        <div class="flex justify-between p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
                @click.prevent.stop="open = false; id = null; name = '';"
                class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600 text-sm">
                Cancel
            </button>
            <button
                @click="deleteItem()"
                style="background-color:red;"
                :disabled="isDeleting"
                class="px-4 py-2 bg-red-700 text-white font-medium rounded-md hover:bg-red-800 shadow-sm disabled:bg-red-400 disabled:cursor-not-allowed text-sm flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span x-show="!isDeleting">Delete</span>
                <span x-show="isDeleting">
                    Deleting...
                </span>
            </button>
        </div>
    </div>
</div>