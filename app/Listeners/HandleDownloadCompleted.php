<?php

namespace App\Listeners;

use App\Events\DownloadCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleDownloadCompleted
{
    public function handle(DownloadCompleted $event): void
    {
        // Dispatch Livewire event to update UI
        \Livewire\Livewire::dispatch('downloadCompleted', [
            'downloadId' => $event->downloadId,
            'mediaId' => $event->mediaId
        ]);
    }
}
