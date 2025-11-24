<?php

namespace App\Filament\Pages;

use App\Services\UpdaterService;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.settings';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
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
                ])->columnSpan(12),
        ])->statePath('data');
    }
}
