<?php

namespace App\Filament\Pages;

use App\Services\UploadService;
use App\Services\DownloadService;
use App\Services\UpdaterService;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

class Home extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Home';
    protected static ?int $navigationSort = -5;
    protected static string $view = 'filament.pages.home';

    public ?array $data = [];
    public array $downloadQueue = [];
    public ?string $currentDownloadId = null;

    public function mount()
    {
        $this->form->fill();
    }

    #[On('downloadCompleted')]
    public function handleDownloadCompleted($downloadId, $mediaId = null): void
    {
        // Remove completed download from queue
        $this->downloadQueue = collect($this->downloadQueue)
            ->reject(fn($item) => $item['id'] === $downloadId)
            ->values()
            ->toArray();

        // Reset current download if this was it
        if ($this->currentDownloadId === $downloadId) {
            $this->currentDownloadId = null;
        }

        // Process next item in queue
        $this->processQueue();

        // Show success notification
        Notification::make()
            ->title('Download completed')
            ->body('Media has been successfully downloaded')
            ->success()
            ->send();

        Log::info('Download completed', [
            'downloadId' => $downloadId,
            'mediaId' => $mediaId,
            'remaining' => count($this->downloadQueue)
        ]);

        // Refresh the UI
        $this->dispatch('refresh-download-queue');
    }

    #[On('downloadFailed')]
    public function handleDownloadFailed($downloadId, $error = 'Unknown error'): void
    {
        // Remove failed download from queue
        $this->downloadQueue = collect($this->downloadQueue)
            ->reject(fn($item) => $item['id'] === $downloadId)
            ->values()
            ->toArray();

        // Reset current download if this was it
        if ($this->currentDownloadId === $downloadId) {
            $this->currentDownloadId = null;
        }

        // Process next item in queue
        $this->processQueue();

        // Show error notification
        Notification::make()
            ->title('Download failed')
            ->body('Error: ' . $error)
            ->danger()
            ->send();

        Log::error('Download failed', [
            'downloadId' => $downloadId,
            'error' => $error,
            'remaining' => count($this->downloadQueue)
        ]);

        // Refresh the UI
        $this->dispatch('refresh-download-queue');
    }

    public function addToDownloadQueue(string $url): void
    {
        $downloadService = app(\App\Services\DownloadService::class);

        // Validate URL first
        if (!$downloadService->validateUrl($url)) {
            Notification::make()
                ->title('Invalid URL')
                ->body('Please enter a valid HTTP or HTTPS URL')
                ->danger()
                ->send();
            return;
        }

        // Generate unique download ID
        $downloadId = \Illuminate\Support\Str::uuid()->toString();

        // Add to internal queue for UI tracking
        $this->downloadQueue[] = [
            'id' => $downloadId,
            'url' => $url,
            'method' => $downloadService->getDownloadMethod($url),
            'added_at' => now()->toISOString(),
            'status' => 'queued'
        ];

        // Start processing if nothing is currently downloading
        if (empty($this->currentDownloadId)) {
            $this->processQueue();
        }

        Log::info('Added to download queue', [
            'downloadId' => $downloadId,
            'url' => $url,
            'queueSize' => count($this->downloadQueue)
        ]);
    }

    private function processQueue(): void
    {
        // Find next queued item
        $nextItem = collect($this->downloadQueue)
            ->where('status', 'queued')
            ->first();

        if (!$nextItem) {
            $this->currentDownloadId = null;
            return;
        }

        // Mark as downloading
        $this->currentDownloadId = $nextItem['id'];

        // Update status in queue
        $this->downloadQueue = collect($this->downloadQueue)
            ->map(function ($item) use ($nextItem) {
                if ($item['id'] === $nextItem['id']) {
                    $item['status'] = 'downloading';
                    $item['started_at'] = now()->toISOString();
                }
                return $item;
            })
            ->toArray();

        // Dispatch the actual download
        $downloadService = app(\App\Services\DownloadService::class);
        $downloadService->downloadFromUrl($nextItem['url'], $nextItem['id']);

        Log::info('Started processing download', [
            'downloadId' => $nextItem['id'],
            'url' => $nextItem['url']
        ]);
    }

    public function cancelDownload($downloadId): void
    {
        // Remove from queue
        $this->downloadQueue = collect($this->downloadQueue)
            ->reject(fn($item) => $item['id'] === $downloadId)
            ->values()
            ->toArray();

        // If this was the current download, reset and process next
        if ($this->currentDownloadId === $downloadId) {
            $this->currentDownloadId = null;
            $this->processQueue();
        }

        Notification::make()
            ->title('Download cancelled')
            ->info()
            ->send();

        // Refresh the UI
        $this->dispatch('refresh-download-queue');
    }

    public function clearQueue(): void
    {
        $this->downloadQueue = [];
        $this->currentDownloadId = null;

        Notification::make()
            ->title('Queue cleared')
            ->info()
            ->send();

        // Refresh the UI
        $this->dispatch('refresh-download-queue');
    }

    public function deleteMedia($id): void
    {
        $media = \App\Models\Media::find($id);

        if ($media) {
            // Delete thumbnail if exists
            if ($media->thumbnail_path) {
                $thumbnailService = app(\App\Services\ThumbnailService::class);
                $thumbnailService->deleteThumbnail($media->thumbnail_path);
            }

            // Delete original file
            if (Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }

            // Delete database record
            $media->delete();

            Notification::make()
                ->title('Media deleted')
                ->success()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        $uploadService = app(UploadService::class);

        return $form->schema([
            Section::make('Download')
                ->description('Enter the URL of the video you want to download.')
                ->schema([
                    Forms\Components\TextInput::make('url')
                        ->label('URL')
                        ->placeholder('Enter video URL')
                        ->url()
                        ->required(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('download')
                            ->icon('heroicon-m-cloud-arrow-down')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $url = $get('url');

                                // Validate URL
                                if (empty($url)) {
                                    Notification::make()
                                        ->title('Error')
                                        ->body('URL is required')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                                    Notification::make()
                                        ->title('Error')
                                        ->body('Please enter a valid URL')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                // Add to download queue
                                $this->addToDownloadQueue($url);

                                // Clear the input field immediately
                                $set('url', null);

                                // Notify user
                                Notification::make()
                                    ->title('Added to download queue')
                                    ->body('URL: ' . $url)
                                    ->info()
                                    ->send();

                                // Force UI update to show download queue section
                                $this->dispatch('refresh-download-queue');
                            })
                            ->requiresConfirmation(false)
                    ])->alignRight(),

                    // Display download queue
                    Forms\Components\Section::make('Download Queue')
                        ->hidden(fn() => empty($this->downloadQueue))
                        ->schema([
                            Forms\Components\ViewField::make('downloadQueue')
                                ->view('components.download-queue')
                                ->viewData(['downloadQueue' => $this->downloadQueue, 'currentDownloadId' => $this->currentDownloadId])
                        ])
                ])->columnSpan(6)->collapsible()->collapsed(),

            // Upload section
            Section::make('Upload')
                ->description('Select the file you want to upload.')
                ->schema([
                    Forms\Components\FileUpload::make('file')
                        ->label('File')
                        ->placeholder('Upload media file')
                        ->disk('public')
                        ->directory('media')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/*', 'video/*', 'audio/*', 'application/pdf'])
                        ->live()
                        ->afterStateUpdated(function ($state, $set) use ($uploadService) {
                            if (!empty($state)) {
                                try {
                                    $uploadService->processUpload($state);
                                    $set('file', null);
                                    redirect(self::getUrl());
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Error processing file')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        })
                ])->columnSpan(6)->collapsible()->collapsed(),

            // Media Gallery section
            Section::make('Media Gallery')
                ->schema([
                    Forms\Components\ViewField::make('gallery')
                        ->view('components.media-gallery')
                ])->columnSpan(12)->compact(true),

            // Maintenance section
            Section::make('Maintenance')
                ->description('System maintenance options.')
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('checkForUpdates')
                            ->label('Check for yt-dlp Updates')
                            ->icon('heroicon-m-arrow-path')
                            ->color('gray')
                            ->action(function () {
                                $updaterService = app(UpdaterService::class);

                                try {
                                    $result = $updaterService->checkAndUpdateYtdlp();

                                    if ($result) {
                                        Notification::make()
                                            ->title('yt-dlp is up to date')
                                            ->body('The latest version is installed.')
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Update check failed')
                                            ->body('Unable to check or update yt-dlp. Check logs for details.')
                                            ->warning()
                                            ->send();
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Error checking for updates')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                    ])->alignCenter()
                ])->columnSpan(12)->collapsible()->collapsed(),
        ])->statePath('data')->columns(12);
    }

    public function getPollingInterval(): ?string
    {
        return '2s'; // Reduced from 1s to be less aggressive
    }
}
