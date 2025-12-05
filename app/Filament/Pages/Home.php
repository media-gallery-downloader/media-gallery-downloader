<?php

namespace App\Filament\Pages;

use App\Services\DownloadService;
use App\Services\UploadService;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * @property \Filament\Forms\Form $form
 */
class Home extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = -5;

    protected static string $view = 'filament.pages.home';

    public ?array $data = [];

    #[Url]
    public $per_page = 100;

    #[Url]
    public $sort = 'newest';

    public function mount()
    {
        $this->form->fill();
    }

    #[On('refresh-gallery')]
    public function refreshGallery(): void
    {
        $this->dispatch('$refresh');
    }

    public function addToDownloadQueue(string $url): void
    {
        $downloadService = app(DownloadService::class);

        // Validate URL first
        if (! $downloadService->validateUrl($url)) {
            Notification::make()
                ->title('Invalid URL')
                ->body('Please enter a valid HTTP or HTTPS URL')
                ->danger()
                ->send();

            return;
        }

        // Generate unique download ID
        $downloadId = Str::uuid()->toString();

        // Add to queue and dispatch
        $downloadService->downloadFromUrl($url, $downloadId);
    }

    public function deleteMedia($id): void
    {
        $media = \App\Models\Media::find($id);

        if ($media) {
            // Delete database record (files will be deleted by model observer)
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

                                if (! filter_var($url, FILTER_VALIDATE_URL)) {
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
                                    ->body('URL: '.$url)
                                    ->info()
                                    ->send();
                            })
                            ->requiresConfirmation(false),
                    ])->alignRight(),
                ])->columnSpan(6)->collapsible(),

            // Upload section
            Section::make('Upload')
                ->description('Select video files or archives (zip, tar, 7z, rar) to upload.')
                ->schema([
                    Forms\Components\FileUpload::make('file')
                        ->label('File')
                        ->placeholder('Upload video or archive file')
                        ->acceptedFileTypes([
                            'video/*',
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/x-tar',
                            'application/gzip',
                            'application/x-gzip',
                            'application/x-bzip2',
                            'application/x-7z-compressed',
                            'application/x-rar-compressed',
                            'application/vnd.rar',
                        ])
                        ->live()
                        ->afterStateUpdated(function ($state, $set) use ($uploadService) {
                            if (! empty($state)) {
                                try {
                                    $uploadService->enqueueUpload($state);
                                    $set('file', null);

                                    Notification::make()
                                        ->title('Upload queued')
                                        ->body('File is being processed in the background')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Error queuing file')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        }),
                ])->columnSpan(6)->collapsible()->collapsed(),

            // Media Gallery section
            Section::make('Media Gallery')
                ->schema([
                    Forms\Components\ViewField::make('gallery')
                        ->view('components.media-gallery')
                        ->viewData([
                            'perPage' => $this->per_page,
                            'sort' => $this->sort,
                        ]),
                ])->columnSpan(12)->compact(true),
        ])->statePath('data')->columns(12);
    }

    public function getPollingInterval(): ?string
    {
        return '2s'; // Reduced from 1s to be less aggressive
    }
}
