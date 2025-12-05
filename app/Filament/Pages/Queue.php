<?php

namespace App\Filament\Pages;

use App\Services\DownloadService;
use App\Services\UploadService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class Queue extends Page
{
    protected static ?string $navigationLabel = 'Queue';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.queue';

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return new HtmlString(Blade::render('<livewire:queue-nav-icon />'));
    }

    public array $downloadQueue = [];

    public array $uploadQueue = [];

    public array $importQueue = [];

    public ?string $currentDownloadId = null;

    public ?string $currentUploadId = null;

    public ?string $currentImportId = null;

    public function mount()
    {
        $this->refreshQueue();
    }

    public function hydrate()
    {
        $this->refreshQueue();
    }

    public function refreshQueue()
    {
        $downloadService = app(DownloadService::class);
        $this->downloadQueue = $downloadService->getQueue();

        // Find current downloading item
        $currentDownload = collect($this->downloadQueue)->firstWhere('status', 'downloading');
        $this->currentDownloadId = $currentDownload['id'] ?? null;

        $uploadService = app(UploadService::class);
        $this->uploadQueue = $uploadService->getQueue();

        // Find current uploading item
        $currentUpload = collect($this->uploadQueue)->firstWhere('status', 'processing');
        $this->currentUploadId = $currentUpload['id'] ?? null;
    }

    #[On('downloadCompleted')]
    public function handleDownloadCompleted($downloadId, $mediaId = null): void
    {
        $this->refreshQueue();
        $this->dispatch('refresh-download-queue');
    }

    #[On('downloadFailed')]
    public function handleDownloadFailed($downloadId, $error = 'Unknown error'): void
    {
        $this->refreshQueue();
        $this->dispatch('refresh-download-queue');
    }

    public function cancelDownload($downloadId): void
    {
        $downloadService = app(DownloadService::class);
        $downloadService->removeFromQueue($downloadId);

        Notification::make()
            ->title('Download cancelled')
            ->info()
            ->send();

        $this->refreshQueue();
        $this->dispatch('refresh-download-queue');
    }

    public function clearQueue(): void
    {
        $downloadService = app(DownloadService::class);
        $downloadService->clearQueue();

        $this->refreshQueue();

        Notification::make()
            ->title('Queue cleared')
            ->info()
            ->send();

        $this->dispatch('refresh-download-queue');
    }

    public function cancelUpload($uploadId): void
    {
        $uploadService = app(UploadService::class);
        $uploadService->removeFromQueue($uploadId);

        Notification::make()
            ->title('Upload cancelled')
            ->info()
            ->send();

        $this->refreshQueue();
        $this->dispatch('refresh-upload-queue');
    }

    public function clearUploadQueue(): void
    {
        $uploadService = app(UploadService::class);
        $uploadService->clearQueue();

        $this->refreshQueue();

        Notification::make()
            ->title('Upload queue cleared')
            ->info()
            ->send();

        $this->dispatch('refresh-upload-queue');
    }

    public function getPollingInterval(): ?string
    {
        return '2s';
    }
}
