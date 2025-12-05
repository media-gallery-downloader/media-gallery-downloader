<?php

namespace App\Filament\Pages;

use App\Models\FailedDownload;
use App\Models\FailedUpload;
use App\Services\DownloadService;
use App\Services\MaintenanceService;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

/**
 * @property \Filament\Forms\Form $form
 */
class Logs extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.logs';

    protected static ?int $navigationSort = 50;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'log_lines' => 100,
        ]);
    }

    // Failed downloads methods
    public function getFailedDownloads(): array
    {
        return FailedDownload::orderByDesc('created_at')
            ->take(20)
            ->get()
            ->toArray();
    }

    public function getFailedStats(): array
    {
        return [
            'pending' => FailedDownload::where('status', 'pending')->count(),
            'retrying' => FailedDownload::where('status', 'retrying')->count(),
            'failed' => FailedDownload::where('status', 'failed')->count(),
            'resolved' => FailedDownload::where('status', 'resolved')->count(),
        ];
    }

    public function retryDownload(int $id): void
    {
        $failed = FailedDownload::find($id);
        if (! $failed) {
            return;
        }

        $downloadService = app(DownloadService::class);

        $failed->markRetrying();

        try {
            $downloadService->downloadFromUrl($failed->url, uniqid('retry_'));
            $failed->markResolved();

            Notification::make()
                ->title('Download retried')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $failed->markFailed($e->getMessage());

            Notification::make()
                ->title('Retry failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteDownload(int $id): void
    {
        FailedDownload::destroy($id);

        Notification::make()
            ->title('Failed download removed')
            ->success()
            ->send();
    }

    public function clearResolved(): void
    {
        FailedDownload::where('status', 'resolved')->delete();

        Notification::make()
            ->title('Resolved downloads cleared')
            ->success()
            ->send();
    }

    public function clearAllFailed(): void
    {
        FailedDownload::where('status', 'failed')->delete();

        Notification::make()
            ->title('Failed downloads cleared')
            ->success()
            ->send();
    }

    public function retryAllPending(): void
    {
        $service = app(MaintenanceService::class);
        $count = $service->retryFailedDownloads();

        Notification::make()
            ->title("Retrying {$count} downloads")
            ->success()
            ->send();
    }

    // Failed uploads methods
    public function getFailedUploads(): array
    {
        return FailedUpload::orderByDesc('created_at')
            ->take(20)
            ->get()
            ->toArray();
    }

    public function getFailedUploadStats(): array
    {
        return [
            'pending' => FailedUpload::where('status', 'pending')->count(),
            'failed' => FailedUpload::where('status', 'failed')->count(),
            'resolved' => FailedUpload::where('status', 'resolved')->count(),
        ];
    }

    public function deleteUpload(int $id): void
    {
        FailedUpload::destroy($id);

        Notification::make()
            ->title('Failed upload removed')
            ->success()
            ->send();
    }

    public function clearResolvedUploads(): void
    {
        FailedUpload::where('status', 'resolved')->delete();

        Notification::make()
            ->title('Resolved uploads cleared')
            ->success()
            ->send();
    }

    public function clearAllFailedUploads(): void
    {
        FailedUpload::where('status', 'failed')->delete();

        Notification::make()
            ->title('Failed uploads cleared')
            ->success()
            ->send();
    }

    // System log methods
    protected function getLogFiles(): array
    {
        $files = File::glob(storage_path('logs/*.log'));
        $options = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $options[$filename] = $filename;
        }
        krsort($options);

        return $options;
    }

    protected function getLogContent(string $filename, int $lines = 100): string
    {
        $path = storage_path('logs/'.$filename);
        if (! File::exists($path)) {
            return 'File not found.';
        }

        $command = "tail -n {$lines} ".escapeshellarg($path);
        $output = shell_exec($command);

        return $output ?: 'Log is empty or could not be read.';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('System Logs')
                ->description('View and download system logs.')
                ->collapsible()
                ->schema([
                    Forms\Components\Select::make('log_file')
                        ->label('Select Log File')
                        ->options($this->getLogFiles())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $set('log_content', $this->getLogContent($state));
                                $set('log_lines', 100);
                            }
                        }),

                    Forms\Components\Textarea::make('log_content')
                        ->label('Log Content')
                        ->rows(20)
                        ->readOnly()
                        ->extraAttributes(['class' => 'font-mono text-xs']),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('refresh')
                            ->label('Refresh')
                            ->icon('heroicon-m-arrow-path')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $file = $get('log_file');
                                $lines = $get('log_lines') ?? 100;
                                if ($file) {
                                    $set('log_content', $this->getLogContent($file, $lines));
                                }
                            }),

                        Forms\Components\Actions\Action::make('load_more')
                            ->label('Load More (+100 lines)')
                            ->icon('heroicon-m-plus')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $file = $get('log_file');
                                $lines = ($get('log_lines') ?? 100) + 100;
                                $set('log_lines', $lines);
                                if ($file) {
                                    $set('log_content', $this->getLogContent($file, $lines));
                                }
                            }),

                        Forms\Components\Actions\Action::make('download')
                            ->label('Download Log')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->action(function (Forms\Get $get) {
                                $file = $get('log_file');
                                if ($file) {
                                    return response()->download(storage_path('logs/'.$file));
                                }
                            }),
                    ])->alignRight(),

                    Forms\Components\Hidden::make('log_lines')->default(100),
                ])->columnSpanFull(),
        ])->statePath('data');
    }
}
