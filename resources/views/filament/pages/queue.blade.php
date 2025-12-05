<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6" wire:poll.2s="refreshQueue">
        <!-- Download Queue -->
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @include('components.download-queue', [
            'downloadQueue' => $downloadQueue,
            'currentDownloadId' => $currentDownloadId
            ])
        </div>

        <!-- Uploads in Progress -->
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @include('components.upload-queue', [
            'uploadQueue' => $uploadQueue,
            'currentUploadId' => $currentUploadId
            ])
        </div>

        <!-- Import Queue -->
        <div class="p-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @include('components.import-queue', [
            'importQueue' => $importQueue,
            'currentImportId' => $currentImportId
            ])
        </div>
    </div>
</x-filament-panels::page>