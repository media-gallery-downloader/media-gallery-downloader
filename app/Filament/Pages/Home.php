<?php

namespace App\Filament\Pages;

use App\Services\UploadService;
use App\Services\DownloadService;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;

class Home extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = -5;

    protected static string $view = 'filament.pages.home';

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $uploadService = app(UploadService::class);
        $downloadService = app(DownloadService::class);

        return $form->schema([
            Section::make('Download')
                ->description('Enter the URL of the video you want to download.')
                ->schema([
                    Forms\Components\TextInput::make('url')->label('URL')->placeholder('Enter video URL')->url(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('download')
                            ->icon('heroicon-m-cloud-arrow-down')
                            ->action(function (array $data, $set) use ($downloadService) {
                                if (!empty($data['url'])) {
                                    try {
                                        $downloadService->downloadFromUrl($data['url']);
                                        $set('url', null);
                                        redirect(self::getUrl());
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title('Error downloading file')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }
                            })
                    ])->alignRight()
                ])->columnSpan(6)->collapsible()->collapsed(),

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

            Section::make('Media Gallery')
                ->schema([
                    Forms\Components\ViewField::make('gallery')
                        ->view('components.media-gallery')
                ])->columnSpan(12)->compact(true),

        ])->statePath('data')->columns(12);
    }
}
