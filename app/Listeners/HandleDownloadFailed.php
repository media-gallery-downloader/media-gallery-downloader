<?php

namespace App\Listeners;

use App\Events\DownloadFailed;
use Illuminate\Support\Facades\Cache;

class HandleDownloadFailed
{
    public function handle(DownloadFailed $event): void
    {
        // Store event data in cache for Livewire polling to pick up
        // This approach works reliably from queue workers
        $cacheKey = 'download_failed_'.$event->downloadId;
        Cache::put($cacheKey, [
            'downloadId' => $event->downloadId,
            'error' => $event->error,
            'timestamp' => now()->timestamp,
        ], 300); // Keep for 5 minutes

        // Also clear the pending count cache
        Cache::forget('queue_pending_count');
    }
}
