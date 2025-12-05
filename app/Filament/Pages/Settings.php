<?php

namespace App\Filament\Pages;

use App\Services\MaintenanceService;
use App\Settings\MaintenanceSettings;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property \Filament\Forms\Form $form
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
            'ytdlp_schedule_time' => $settings->ytdlp_schedule_time,
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

    // YouTube cookies methods
    public function uploadCookies(): void
    {
        Log::info('uploadCookies called', ['data' => $this->data['cookies_file'] ?? 'null']);

        $cookiesFile = $this->data['cookies_file'] ?? null;

        if (empty($cookiesFile)) {
            Log::info('uploadCookies: No file in data');
            Notification::make()
                ->title('No file selected')
                ->body('Please select a cookies file first.')
                ->warning()
                ->send();

            return;
        }

        try {
            // Get the temporary file - Filament stores files in nested array structure
            $tempFile = $cookiesFile;

            // If it's an array, we need to extract the TemporaryUploadedFile
            if (is_array($tempFile)) {
                Log::info('uploadCookies: File is array', ['keys' => array_keys($tempFile)]);

                // Filament stores as [uuid => TemporaryUploadedFile] or [uuid => [class => path]]
                foreach ($tempFile as $key => $value) {
                    if ($value instanceof TemporaryUploadedFile) {
                        $tempFile = $value;
                        Log::info('uploadCookies: Found TemporaryUploadedFile in array');
                        break;
                    } elseif (is_array($value)) {
                        // Might be serialized format: [class => path]
                        foreach ($value as $class => $path) {
                            if (str_contains($class, 'TemporaryUploadedFile') && is_string($path)) {
                                // Read directly from the temp path
                                Log::info('uploadCookies: Found serialized temp file', ['path' => $path]);
                                if (file_exists($path)) {
                                    $content = file_get_contents($path);
                                    Log::info('uploadCookies: Read content from temp path', ['size' => strlen($content)]);
                                }
                                break 2;
                            }
                        }
                    }
                }
            }

            // If we already got content from the serialized path, skip this part
            if (! isset($content)) {
                if ($tempFile instanceof TemporaryUploadedFile) {
                    Log::info('uploadCookies: Reading from TemporaryUploadedFile object');
                    $content = $tempFile->get();
                } elseif (is_string($tempFile) && file_exists($tempFile)) {
                    Log::info('uploadCookies: Reading from string path', ['path' => $tempFile]);
                    $content = file_get_contents($tempFile);
                }
            }

            if (empty($content)) {
                Log::warning('uploadCookies: Could not read file content');
                Notification::make()
                    ->title('Could not read file')
                    ->body('The uploaded file could not be read. Please try again.')
                    ->danger()
                    ->send();

                return;
            }

            Log::info('uploadCookies: Got content', ['size' => strlen($content)]);

            // Basic validation - check if it contains YouTube cookies
            $hasYoutubeCookies = str_contains($content, '.youtube.com')
                || str_contains($content, 'youtube.com')
                || str_contains($content, '.googlevideo.com')
                || str_contains($content, '.google.com');

            if (! $hasYoutubeCookies) {
                Log::warning('uploadCookies: File does not contain YouTube cookies');
                Notification::make()
                    ->title('Invalid cookies file')
                    ->body('The file does not appear to contain YouTube cookies. Make sure you exported cookies while on youtube.com.')
                    ->danger()
                    ->send();

                return;
            }

            // Save the cookies file
            $cookiesPath = config('mgd.youtube.cookies_file', storage_path('app/cookies.txt'));
            file_put_contents($cookiesPath, $content);
            Log::info('uploadCookies: Saved cookies file', ['path' => $cookiesPath]);

            // Clean up the Livewire temp file if we have access to it
            if ($tempFile instanceof TemporaryUploadedFile) {
                try {
                    $tempPath = $tempFile->getRealPath();
                    if ($tempPath && file_exists($tempPath)) {
                        @unlink($tempPath);
                    }
                } catch (\Exception $e) {
                    // Livewire will clean it up eventually
                }
            }

            // Clear the upload field
            $this->data['cookies_file'] = null;

            Notification::make()
                ->title('Cookies uploaded successfully')
                ->body('Age-restricted videos should now be downloadable.')
                ->success()
                ->send();

            // Refresh the form to update the UI
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            Log::error('uploadCookies: Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            Notification::make()
                ->title('Upload failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteCookies(): void
    {
        try {
            $cookiesPath = config('mgd.youtube.cookies_file', storage_path('app/cookies.txt'));

            if (file_exists($cookiesPath)) {
                unlink($cookiesPath);
                Notification::make()
                    ->title('Cookies deleted')
                    ->body('YouTube authentication has been removed.')
                    ->success()
                    ->send();

                // Refresh the form to update the UI
                $this->dispatch('$refresh');
            } else {
                Notification::make()
                    ->title('No cookies file found')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Delete failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testCookies(): void
    {
        $cookiesPath = config('mgd.youtube.cookies_file', storage_path('app/cookies.txt'));

        if (! file_exists($cookiesPath)) {
            Notification::make()
                ->title('No cookies file')
                ->body('Please upload a cookies file first.')
                ->warning()
                ->send();

            return;
        }

        try {
            // Test with a known age-restricted video
            $testUrl = 'https://www.youtube.com/watch?v=UocjQ5uiucg';

            $process = new \Symfony\Component\Process\Process([
                'yt-dlp',
                '--cookies',
                $cookiesPath,
                '--simulate',
                '--no-warnings',
                $testUrl,
            ]);
            $process->setTimeout(30);
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();

            if ($process->isSuccessful()) {
                Notification::make()
                    ->title('Cookies are valid! ✅')
                    ->body('Age-restricted videos should download successfully.')
                    ->success()
                    ->send();
            } elseif (str_contains($output, 'cookies are no longer valid') || str_contains($output, 'rotated')) {
                Notification::make()
                    ->title('Cookies have expired ❌')
                    ->body('YouTube has invalidated these cookies. Please export fresh cookies from your browser. Tip: Use Firefox and don\'t open YouTube again after exporting.')
                    ->danger()
                    ->persistent()
                    ->send();
            } elseif (str_contains($output, 'Sign in to confirm your age')) {
                Notification::make()
                    ->title('Cookies are invalid ❌')
                    ->body('The cookies don\'t provide authentication. Make sure you\'re logged into YouTube when exporting cookies.')
                    ->danger()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Test failed')
                    ->body('Could not verify cookies: '.substr($output, 0, 200))
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Test error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function hasCookiesFile(): bool
    {
        $cookiesPath = config('mgd.youtube.cookies_file', storage_path('app/cookies.txt'));

        return file_exists($cookiesPath);
    }

    public function getCookiesFileAge(): ?string
    {
        $cookiesPath = config('mgd.youtube.cookies_file', storage_path('app/cookies.txt'));

        if (! file_exists($cookiesPath)) {
            return null;
        }

        $modified = filemtime($cookiesPath);

        return \Carbon\Carbon::createFromTimestamp($modified)->diffForHumans();
    }

    // Backup management methods
    public function getBackupFiles(): array
    {
        $backupDir = storage_path('app/data/backups');
        if (! \Illuminate\Support\Facades\File::exists($backupDir)) {
            return [];
        }

        $files = [];
        foreach (\Illuminate\Support\Facades\File::files($backupDir) as $file) {
            if ($file->getExtension() === 'sql') {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'date' => \Carbon\Carbon::createFromTimestamp($file->getMTime())->format('M j, Y g:i A'),
                    'path' => $file->getPathname(),
                ];
            }
        }

        // Sort by date descending (newest first)
        usort($files, fn ($a, $b) => filemtime($b['path']) - filemtime($a['path']));

        return $files;
    }

    public function downloadBackup(string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $backupDir = storage_path('app/data/backups');
        $filepath = $backupDir.'/'.basename($filename); // basename to prevent directory traversal

        if (! file_exists($filepath)) {
            abort(404, 'Backup not found');
        }

        return response()->download($filepath);
    }

    public function restoreBackup(string $content): void
    {
        if (empty($content)) {
            Notification::make()
                ->title('No file content')
                ->body('Please select a backup file to restore.')
                ->warning()
                ->send();

            return;
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
            Section::make('Maintenance')
                ->description('Schedule system maintenance tasks.')
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

            Section::make('YouTube Authentication')
                ->description('Upload cookies to download age-restricted YouTube videos.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('cookies_status')
                        ->label('Status')
                        ->content(fn () => $this->hasCookiesFile()
                            ? "✅ Cookies file installed (uploaded {$this->getCookiesFileAge()}) - Use 'Test Cookies' to verify they work"
                            : '❌ No cookies file - age-restricted videos will fail to download'),

                    Forms\Components\Placeholder::make('cookies_instructions')
                        ->label('How to export cookies')
                        ->content(new \Illuminate\Support\HtmlString('
                            <div class="text-sm space-y-3">
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold">1</span>
                                    <span>Use <strong>Firefox</strong> <span class="text-gray-500 dark:text-gray-400">(recommended - cookies are more stable than Chrome)</span></span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold">2</span>
                                    <span>Install the <a href="https://addons.mozilla.org/en-US/firefox/addon/get-cookies-txt-locally/" target="_blank" class="text-primary-600 dark:text-primary-400 underline hover:no-underline"><strong>"Get cookies.txt LOCALLY"</strong></a> browser extension</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold">3</span>
                                    <span>Log into YouTube with your Google account</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold">4</span>
                                    <span>Click the extension icon and export cookies for youtube.com</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center text-xs font-bold">5</span>
                                    <span>Upload the exported file below</span>
                                </div>
                                <div class="mt-4 p-3 rounded-lg bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20">
                                    <div class="flex gap-2">
                                        <span class="text-warning-600 dark:text-warning-400">⚠️</span>
                                        <span class="text-warning-700 dark:text-warning-300"><strong>Important:</strong> After exporting, don\'t use YouTube in that browser again. YouTube may invalidate cookies when it detects they\'re being used elsewhere.</span>
                                    </div>
                                </div>
                            </div>
                        '))
                        ->hidden(fn () => $this->hasCookiesFile()),

                    Forms\Components\FileUpload::make('cookies_file')
                        ->label('Upload Cookies File')
                        ->acceptedFileTypes(['text/plain', '.txt'])
                        ->maxSize(1024) // 1MB max
                        ->helperText('Select a cookies.txt file to upload it automatically.')
                        ->hidden(fn () => $this->hasCookiesFile())
                        ->storeFiles(false)
                        ->afterStateUpdated(fn () => $this->uploadCookies()),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('test_cookies')
                            ->label('Test Cookies')
                            ->icon('heroicon-m-beaker')
                            ->color('info')
                            ->action('testCookies')
                            ->visible(fn () => $this->hasCookiesFile()),

                        Forms\Components\Actions\Action::make('delete_cookies')
                            ->label('Delete Cookies')
                            ->icon('heroicon-m-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Delete YouTube Cookies')
                            ->modalDescription('Are you sure you want to delete the cookies file? Age-restricted videos will no longer be downloadable.')
                            ->action('deleteCookies')
                            ->visible(fn () => $this->hasCookiesFile()),
                    ])->alignRight(),
                ])
                ->columnSpanFull(),
        ])->statePath('data');
    }
}
