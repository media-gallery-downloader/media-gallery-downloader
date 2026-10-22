<?php

namespace App\Filament\Pages;

use App\Livewire\ManageSites;
use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpArguments;
use App\Services\MaintenanceService;
use App\Settings\MaintenanceSettings;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @property Form $form
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount()
    {
        $settings = app(MaintenanceSettings::class);
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $data = [
            'ytdlp_extra_args' => $settings->ytdlp_extra_args ?? '',
            'ytdlp_schedule_time' => $settings->ytdlp_schedule_time,
            'deno_schedule_time' => $settings->deno_schedule_time ?? null,
            'duplicates_schedule_time' => $settings->duplicates_schedule_time,
            'storage_cleanup_schedule_time' => $settings->storage_cleanup_schedule_time ?? null,
            'database_backup_schedule_time' => $settings->database_backup_schedule_time ?? null,
            'log_rotation_schedule_time' => $settings->log_rotation_schedule_time ?? null,
            'thumbnail_regen_schedule_time' => $settings->thumbnail_regen_schedule_time ?? null,
            'import_scan_schedule_time' => $settings->import_scan_schedule_time ?? null,
            'storage_cleanup_days_old' => $settings->storage_cleanup_days_old ?? 90,
            'database_backup_retention_days' => $settings->database_backup_retention_days ?? 30,
            'log_retention_days' => $settings->log_retention_days ?? 14,
            'notifications_enabled' => $settings->notifications_enabled ?? false,
            'notification_email' => $settings->notification_email ?? null,
            'notification_webhook_url' => $settings->notification_webhook_url ?? null,
            'notify_on_success' => $settings->notify_on_success ?? false,
            'notify_on_failure' => $settings->notify_on_failure ?? true,
            'log_lines' => 100,
        ];

        foreach ($days as $day) {
            $data["ytdlp_day_{$day}"] = in_array($day, $settings->ytdlp_schedule_days ?? []);
            $data["deno_day_{$day}"] = in_array($day, $settings->deno_schedule_days ?? []);
            $data["duplicates_day_{$day}"] = in_array($day, $settings->duplicates_schedule_days ?? []);
            $data["storage_day_{$day}"] = in_array($day, $settings->storage_cleanup_schedule_days ?? []);
            $data["backup_day_{$day}"] = in_array($day, $settings->database_backup_schedule_days ?? []);
            $data["log_day_{$day}"] = in_array($day, $settings->log_rotation_schedule_days ?? []);
            $data["thumbnail_day_{$day}"] = in_array($day, $settings->thumbnail_regen_schedule_days ?? []);
            $data["import_day_{$day}"] = in_array($day, $settings->import_scan_schedule_days ?? []);
        }

        $this->form->fill($data);
    }

    public function save()
    {
        $data = $this->form->getState();
        $settings = app(MaintenanceSettings::class);
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $settings->ytdlp_schedule_days = array_values(array_filter($days, fn ($day) => $data["ytdlp_day_{$day}"] ?? false));
        $settings->ytdlp_schedule_time = $data['ytdlp_schedule_time'];
        $settings->duplicates_schedule_days = array_values(array_filter($days, fn ($day) => $data["duplicates_day_{$day}"] ?? false));
        $settings->duplicates_schedule_time = $data['duplicates_schedule_time'];

        $settings->save();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    // yt-dlp methods
    public function saveYtdlpSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->ytdlp_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["ytdlp_day_{$day}"] ?? false));
        $settings->ytdlp_schedule_time = $this->data['ytdlp_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('yt-dlp schedule saved')->success()->send();
    }

    // Configuration methods
    public function saveConfiguration(): void
    {
        $extraArgs = (string) ($this->data['ytdlp_extra_args'] ?? '');
        $this->resetErrorBag('data.ytdlp_extra_args');

        // Checked with yt-dlp's own option parser; they're checked again before every run.
        try {
            app(YtDlpArguments::class)->validateText($extraArgs);
        } catch (InvalidYtDlpArgumentsException $e) {
            throw ValidationException::withMessages(['data.ytdlp_extra_args' => $e->getMessage()]);
        }

        $settings = app(MaintenanceSettings::class);

        $settings->ytdlp_extra_args = $extraArgs;
        $settings->save();

        Notification::make()->title('Configuration saved')->success()->send();
    }

    public function runYtdlpUpdate(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $result = $service->updateYtDlp();
            if ($result) {
                Notification::make()->title('yt-dlp is up to date')->success()->send();
            } else {
                Notification::make()->title('Update check failed')->warning()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Deno methods
    public function saveDenoSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->deno_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["deno_day_{$day}"] ?? false));
        $settings->deno_schedule_time = $this->data['deno_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Deno schedule saved')->success()->send();
    }

    public function runDenoUpdate(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $result = $service->updateDeno();
            if ($result) {
                Notification::make()->title('Deno is up to date')->success()->send();
            } else {
                Notification::make()->title('Update check failed')->warning()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Duplicate removal methods
    public function saveDuplicatesSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->duplicates_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["duplicates_day_{$day}"] ?? false));
        $settings->duplicates_schedule_time = $this->data['duplicates_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Duplicate removal schedule saved')->success()->send();
    }

    public function runDuplicateRemoval(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $count = $service->removeDuplicates();
            if ($count > 0) {
                Notification::make()->title("Removed {$count} duplicates")->success()->send();
            } else {
                Notification::make()->title('No duplicates found')->success()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Storage cleanup methods
    public function saveStorageSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->storage_cleanup_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["storage_day_{$day}"] ?? false));
        $settings->storage_cleanup_schedule_time = $this->data['storage_cleanup_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Storage cleanup schedule saved')->success()->send();
    }

    public function runStorageCleanup(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $count = $service->cleanupOrphanedFiles();
            Notification::make()->title("Removed {$count} orphaned files")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Database backup methods
    public function saveBackupSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->database_backup_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["backup_day_{$day}"] ?? false));
        $settings->database_backup_schedule_time = $this->data['database_backup_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Database backup schedule saved')->success()->send();
    }

    public function runBackup(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $filepath = $service->createDatabaseBackup();
            if ($filepath) {
                Notification::make()->title('Backup created successfully')->success()->send();
            } else {
                Notification::make()->title('Backup failed')->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Log rotation methods
    public function saveLogRotationSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->log_rotation_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["log_day_{$day}"] ?? false));
        $settings->log_rotation_schedule_time = $this->data['log_rotation_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Log rotation schedule saved')->success()->send();
    }

    public function runLogRotation(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $count = $service->rotateLogs();
            Notification::make()->title("Removed {$count} old log files")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Thumbnail regeneration methods
    public function saveThumbnailSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->thumbnail_regen_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["thumbnail_day_{$day}"] ?? false));
        $settings->thumbnail_regen_schedule_time = $this->data['thumbnail_regen_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Thumbnail regeneration schedule saved')->success()->send();
    }

    public function runThumbnailRegeneration(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $results = $service->regenerateThumbnails();
            Notification::make()
                ->title('Thumbnails regenerated')
                ->body("{$results['success']} success, {$results['failed']} failed")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Bulk import methods
    public function saveImportSchedule(): void
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $settings = app(MaintenanceSettings::class);

        $settings->import_scan_schedule_days = array_values(array_filter($days, fn ($day) => $this->data["import_day_{$day}"] ?? false));
        $settings->import_scan_schedule_time = $this->data['import_scan_schedule_time'] ?? null;
        $settings->save();

        Notification::make()->title('Bulk import schedule saved')->success()->send();
    }

    public function runImportScan(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $results = $service->scanAndQueueImports();

            if ($results['queued'] > 0) {
                Notification::make()
                    ->title('Import scan completed')
                    ->body("Queued {$results['queued']} files for import".($results['skipped'] > 0 ? ", skipped {$results['skipped']}" : ''))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('No files to import')
                    ->body('No video files found in the incoming directory.')
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function getImportStatus(): array
    {
        return app(MaintenanceService::class)->getImportStatus();
    }

    public function clearFailedImports(): void
    {
        try {
            $service = app(MaintenanceService::class);
            $count = $service->clearFailedImports();

            Notification::make()
                ->title('Failed imports cleared')
                ->body("Deleted {$count} files from failed directory.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // Notification settings
    public function saveNotificationSettings(): void
    {
        $settings = app(MaintenanceSettings::class);

        $settings->notifications_enabled = $this->data['notifications_enabled'] ?? false;
        $settings->notification_email = $this->data['notification_email'] ?? null;
        $settings->notification_webhook_url = $this->data['notification_webhook_url'] ?? null;
        $settings->notify_on_success = $this->data['notify_on_success'] ?? false;
        $settings->notify_on_failure = $this->data['notify_on_failure'] ?? true;
        $settings->save();

        Notification::make()->title('Notification settings saved')->success()->send();
    }

    // Backup management methods
    public function getBackupFiles(): array
    {
        $backupDir = storage_path('app/data/backups');
        if (! File::exists($backupDir)) {
            return [];
        }

        $files = [];
        foreach (File::files($backupDir) as $file) {
            // Accept both .sql (legacy/MySQL) and .sqlite (SQLite) extensions
            if (in_array($file->getExtension(), ['sql', 'sqlite'], true)) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'date' => Carbon::createFromTimestamp($file->getMTime())->format('M j, Y g:i A'),
                    'path' => $file->getPathname(),
                ];
            }
        }

        // Sort by date descending (newest first)
        usort($files, fn ($a, $b) => filemtime($b['path']) - filemtime($a['path']));

        return $files;
    }

    public function downloadBackup(string $filename): BinaryFileResponse
    {
        $backupDir = storage_path('app/data/backups');
        $filepath = $backupDir.'/'.basename($filename); // basename to prevent directory traversal

        if (! file_exists($filepath)) {
            abort(404, 'Backup not found');
        }

        return response()->download($filepath);
    }

    public function restoreBackup(string $content, bool $isBase64 = false): void
    {
        if (empty($content)) {
            Notification::make()
                ->title('No file content')
                ->body('Please select a backup file to restore.')
                ->warning()
                ->send();

            return;
        }

        // Decode Base64 if needed (for binary SQLite files)
        if ($isBase64) {
            $content = base64_decode($content, true);
            if ($content === false) {
                Notification::make()
                    ->title('Invalid file')
                    ->body('The file could not be decoded. Please ensure it is a valid backup file.')
                    ->danger()
                    ->send();

                return;
            }
        }

        try {
            $service = app(MaintenanceService::class);
            $results = $service->restoreFromBackup($content);

            $message = "Imported {$results['imported']} records";
            if ($results['skipped'] > 0) {
                $message .= ", skipped {$results['skipped']} duplicates";
            }
            if ($results['queued'] > 0) {
                $message .= ", queued {$results['queued']} for download";
            }

            Notification::make()
                ->title('Restore completed')
                ->body($message)
                ->success()
                ->send();

            // Show info for duplicate records
            $duplicates = $results['duplicates'] ?? [];
            if (! empty($duplicates)) {
                $count = count($duplicates);
                $recordList = implode(', ', array_slice($duplicates, 0, 10));
                if ($count > 10) {
                    $recordList .= ' ... and '.($count - 10).' more';
                }

                Notification::make()
                    ->title("Skipped {$count} duplicate records")
                    ->body("The following records already exist and were not imported: {$recordList}")
                    ->info()
                    ->persistent()
                    ->send();
            }

            // Show warning for records without source URLs
            $noSource = $results['no_source'] ?? [];
            if (! empty($noSource)) {
                $count = count($noSource);
                $recordList = implode(', ', array_slice($noSource, 0, 10));
                if ($count > 10) {
                    $recordList .= ' ... and '.($count - 10).' more';
                }

                Notification::make()
                    ->title("Skipped {$count} records without source URLs")
                    ->body("The following records were not imported because they have no source URL and cannot be re-downloaded: {$recordList}")
                    ->warning()
                    ->persistent()
                    ->send();
            }

            // Close the modal
            $this->dispatch('close-modal', id: 'restore-backup-modal');
        } catch (\Exception $e) {
            Log::error('Backup restore failed', ['error' => $e->getMessage()]);
            Notification::make()
                ->title('Restore failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Configuration')
                ->description('Configure download settings.')
                ->collapsible()
                ->schema([
                    Forms\Components\Textarea::make('ytdlp_extra_args')
                        ->label('yt-dlp Extra Arguments')
                        ->helperText(new HtmlString('Additional command line arguments passed to yt-dlp for every download, including the metadata lookup. Put one or more per line and quote values that contain spaces, as in a shell (e.g. --add-headers "Referer: https://example.com"); lines starting with # are comments. Options that run commands, read or write local files, change where files are saved, or update yt-dlp are rejected. See the <a href="https://github.com/yt-dlp/yt-dlp#usage-and-options" target="_blank" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">yt-dlp documentation</a> for available options.'))
                        ->placeholder("--limit-rate 1M\n--add-headers \"Referer: https://example.com\"")
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('save_configuration')
                            ->label('Save Configuration')
                            ->icon('heroicon-m-check')
                            ->color('success')
                            ->action('saveConfiguration'),
                    ])->alignRight(),
                ])
                ->columnSpanFull(),

            Section::make('Sites')
                ->description('Cookies and extra yt-dlp arguments for specific sites. A download uses the site whose domain matches its URL, subdomains included; URLs no site covers get no cookies.')
                ->collapsible()
                ->schema([
                    Forms\Components\Livewire::make(ManageSites::class)->key('manage-sites'),
                    Forms\Components\Placeholder::make('cookies_instructions')
                        ->label('How to export cookies')
                        ->content(new HtmlString('
                            <ol class="text-sm list-decimal ps-5 space-y-1">
                                <li>Use <strong>Firefox</strong> (its cookies stay valid longer than Chrome\'s) and log into the site.</li>
                                <li>With the <a href="https://addons.mozilla.org/en-US/firefox/addon/get-cookies-txt-locally/" target="_blank" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">Get cookies.txt LOCALLY</a> extension, export the cookies for that site.</li>
                                <li>Edit the site here and paste the export (or upload the file).</li>
                            </ol>
                            <p class="mt-2 text-sm text-warning-700 dark:text-warning-300">Sites such as YouTube may invalidate exported cookies once that browser session keeps being used, so export from a browser or profile you leave alone.</p>
                        ')),
                ])
                ->columnSpanFull(),

            Section::make('Maintenance')
                ->description('Run or schedule system maintenance tasks.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\View::make('filament.components.maintenance-schedule')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Notifications')
                ->description('Configure notifications for maintenance tasks.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('notifications_enabled')
                        ->label('Enable Notifications')
                        ->live(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('notification_email')
                                ->label('Email Address')
                                ->email()
                                ->placeholder('admin@example.com'),

                            Forms\Components\TextInput::make('notification_webhook_url')
                                ->label('Webhook URL')
                                ->url()
                                ->placeholder('https://hooks.slack.com/...'),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('notify_on_success')
                                ->label('Notify on Success'),

                            Forms\Components\Toggle::make('notify_on_failure')
                                ->label('Notify on Failure'),
                        ]),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('save_notifications')
                            ->label('Save Notification Settings')
                            ->icon('heroicon-m-check')
                            ->color('success')
                            ->action('saveNotificationSettings'),
                    ])->alignRight(),
                ])
                ->columnSpanFull(),

        ])->statePath('data');
    }
}
