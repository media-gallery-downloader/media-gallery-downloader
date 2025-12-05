<?php

namespace App\Livewire;

use App\Services\DownloadService;
use App\Services\UploadService;
use Filament\Notifications\Notification;
use Livewire\Component;

class QueueNavIcon extends Component
{
    public bool $wasActive = false;

    public function mount()
    {
        $downloadService = app(DownloadService::class);
        $downloadQueue = $downloadService->getQueue();
        $uploadQueue = app(UploadService::class)->getQueue();

        $this->wasActive = collect($downloadQueue)->contains('status', 'downloading')
            || collect($uploadQueue)->contains('status', 'processing');
    }

    public function render()
    {
        $downloadService = app(DownloadService::class);
        $downloadQueue = $downloadService->getQueue();

        // Check for failed downloads and notify
        foreach ($downloadQueue as $item) {
            if (($item['status'] ?? '') === 'failed') {
                Notification::make()
                    ->title('Download failed')
                    ->body($item['error'] ?? 'Unknown error')
                    ->danger()
                    ->send();

                $downloadService->removeFromQueue($item['id']);
            }
        }

        // Re-fetch queue to get updated state
        $downloadQueue = $downloadService->getQueue();
        $uploadQueue = app(UploadService::class)->getQueue();

        $hasActive = collect($downloadQueue)->contains('status', 'downloading')
            || collect($uploadQueue)->contains('status', 'processing');

        // If we were active and now we are not, it means a job finished
        if ($this->wasActive && ! $hasActive) {
            $this->dispatch('refresh-gallery');
            $this->dispatch('refresh-gallery')->to(\App\Filament\Pages\Home::class);
        }

        $this->wasActive = $hasActive;

        return view('livewire.queue-nav-icon', [
            'hasActive' => $hasActive,
        ]);
    }

    public function checkQueue()
    {
        $downloadService = app(DownloadService::class);
        $downloadQueue = $downloadService->getQueue();
        $uploadQueue = app(UploadService::class)->getQueue();

        return collect($downloadQueue)->merge($uploadQueue);
    }
}
