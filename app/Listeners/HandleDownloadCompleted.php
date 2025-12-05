<?php

namespace App\Listeners;

use App\Events\DownloadCompleted;
use Illuminate\Support\Facades\Cache;

class HandleDownloadCompleted
{
    public function handle(DownloadCompleted $event): void
    {
        // Store event data in cache for Livewire polling to pick up
        // This approach works reliably from queue workers
        $cacheKey = 'download_completed_'.$event->downloadId;
        Cache::put($cacheKey, [
            'downloadId' => $event->downloadId,
            'mediaId' => $event->mediaId,
            'timestamp' => now()->timestamp,
        ], 300); // Keep for 5 minutes

        // Also increment a counter for the queue nav icon
        Cache::forget('queue_pending_count');
    }
}
