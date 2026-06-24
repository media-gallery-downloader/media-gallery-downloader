<?php

namespace App\Filament\Pages;

use App\Models\Media;
use App\Services\DownloadService;
use App\Services\UploadService;
use Filament\Actions\Action;
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
use Spatie\Tags\Tag;

/**
 * @property Form $form
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

    #[Url]
    public ?string $search = null;

    /** @var array<int, string> Tag names the gallery is filtered by (match all). */
    #[Url]
    public array $tags = [];

    public function mount()
    {
        $this->form->fill();
    }

    /**
     * Edit a media item's title, source and tags. Mounted from the gallery's
     * info ("i") button via wire:click="mountAction('editMediaInfo', { id })".
     */
    public function editMediaInfoAction(): Action
    {
        return Action::make('editMediaInfo')
            ->modalHeading('Edit media')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments): array {
                $media = Media::findOrFail($arguments['id']);

                return [
                    'name' => $media->name,
                    'source' => $media->source,
                    'tags' => $media->tags->map(fn (Tag $tag) => $tag->name)->all(),
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                    'created_at' => $media->created_at?->toDayDateTimeString(),
                ];
            })
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('source')
                    ->label('Source')
                    ->maxLength(2048)
                    ->helperText('Where this came from — a URL or a note.'),
                Forms\Components\TagsInput::make('tags')
                    ->label('Tags')
                    ->suggestions($this->tagSuggestions())
                    ->helperText('Type to search existing tags or add a new one.'),
                Forms\Components\Placeholder::make('details')
                    ->label('Details')
                    ->content(fn (Forms\Get $get): string => trim(sprintf(
                        '%s · %s · %s',
                        $get('file_name') ?: '—',
                        $this->humanSize((int) $get('size')),
                        $get('created_at') ?: '—',
                    ))),
            ])
            ->action(function (array $data, array $arguments): void {
                $media = Media::findOrFail($arguments['id']);
                $media->update([
                    'name' => $data['name'],
                    'source' => (string) ($data['source'] ?? ''), // column is NOT NULL
                ]);
                $media->syncTags($data['tags'] ?? []);

                Notification::make()->title('Media updated')->success()->send();
            });
    }

    /** Existing tag names, most-recent first, for the tags-input autocomplete. */
    private function tagSuggestions(): array
    {
        return Tag::query()->latest('id')->get()->map(fn (Tag $tag) => $tag->name)->unique()->values()->all();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }

    /**
     * The page is rendered inside `<x-filament-panels::form wire:submit="submit">`,
     * so pressing Enter in any field (e.g. the search box) submits the form.
     * There is no whole-form action — downloads/uploads run from their own
     * buttons / afterStateUpdated — so this is intentionally a no-op. Without it,
     * the form submit throws a MethodNotFoundException (500).
     */
    public function submit(): void
    {
        //
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
        $media = Media::find($id);

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
                ->description('Select a video file or archive (zip, tar, 7z, rar) to upload.')
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
                ])->columnSpan(6)->collapsible(),

            // Media Gallery section
            Section::make('Media Gallery')
                ->schema([
                    Forms\Components\ViewField::make('gallery')
                        ->view('components.media-gallery')
                        ->viewData([
                            'perPage' => $this->per_page,
                            'sort' => $this->sort,
                            'search' => $this->search,
                            'tags' => $this->tags,
                        ]),
                ])->columnSpan(12)->compact(true),
        ])->statePath('data')->columns(12);
    }

    public function getPollingInterval(): ?string
    {
        // Each poll re-runs the full gallery query, so only poll quickly while
        // there is active work; back off substantially when the queues are idle.
        $items = array_merge(
            app(DownloadService::class)->getQueue(),
            app(UploadService::class)->getQueue(),
        );

        foreach ($items as $item) {
            if (in_array($item['status'] ?? '', ['queued', 'downloading', 'processing'], true)) {
                return '3s';
            }
        }

        return '15s';
    }
}
