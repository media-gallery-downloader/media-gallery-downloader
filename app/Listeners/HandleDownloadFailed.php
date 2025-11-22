<?php

namespace App\Listeners;

use App\Events\DownloadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleDownloadFailed
{
    public function handle(DownloadFailed $event): void
    {
        // Dispatch Livewire event to update UI
        \Livewire\Livewire::dispatch('downloadFailed', [
            'downloadId' => $event->downloadId,
            'error' => $event->error
        ]);
    }
}
