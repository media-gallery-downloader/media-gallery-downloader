<?php

namespace App\Filament\Pages;

use App\Services\MaintenanceService;
use App\Settings\MaintenanceSettings;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

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
        }

        $this->form->fill($data);
    }

    public function save()
    {
        $data = $this->form->getState();
        $settings = app(MaintenanceSettings::class);
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $settings->ytdlp_schedule_days = array_values(array_filter($days, fn($day) => $data["ytdlp_day_{$day}"] ?? false));
        $settings->ytdlp_schedule_time = $data['ytdlp_schedule_time'];
        $settings->duplicates_schedule_days = array_values(array_filter($days, fn($day) => $data["duplicates_day_{$day}"] ?? false));
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

        $settings->ytdlp_schedule_days = array_values(array_filter($days, fn($day) => $this->data["ytdlp_day_{$day}"] ?? false));
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

        $settings->duplicates_schedule_days = array_values(array_filter($days, fn($day) => $this->data["duplicates_day_{$day}"] ?? false));
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

        $settings->storage_cleanup_schedule_days = array_values(array_filter($days, fn($day) => $this->data["storage_day_{$day}"] ?? false));
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

        $settings->database_backup_schedule_days = array_values(array_filter($days, fn($day) => $this->data["backup_day_{$day}"] ?? false));
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

        $settings->log_rotation_schedule_days = array_values(array_filter($days, fn($day) => $this->data["log_day_{$day}"] ?? false));
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

        $settings->thumbnail_regen_schedule_days = array_values(array_filter($days, fn($day) => $this->data["thumbnail_day_{$day}"] ?? false));
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

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Maintenance')
                ->description('Schedule system maintenance tasks.')
                ->collapsible()
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
