<?php

use App\Filament\Pages\Settings;
use App\Models\Media;
use App\Services\MaintenanceService;
use App\Services\ThumbnailService;
use App\Services\UpdaterService;
use App\Settings\MaintenanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Settings Page', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can render the settings page', function () {
        $this->get(Settings::getUrl())
            ->assertSuccessful();
    });

    it('displays settings form', function () {
        $component = Livewire::test(Settings::class);

        $component->assertSuccessful();
    });

    it('loads settings on mount', function () {
        // Set some values in settings
        $settings = app(MaintenanceSettings::class);
        $settings->ytdlp_schedule_time = '03:00';
        $settings->save();

        $component = Livewire::test(Settings::class);

        $component->assertSet('data.ytdlp_schedule_time', '03:00');
    });

    it('can save ytdlp schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.ytdlp_schedule_time', '02:30')
            ->set('data.ytdlp_day_Mon', true)
            ->set('data.ytdlp_day_Fri', true)
            ->call('saveYtdlpSchedule');

        $component->assertSuccessful();

        $settings = app(MaintenanceSettings::class);
        expect($settings->ytdlp_schedule_time)->toBe('02:30');
        expect($settings->ytdlp_schedule_days)->toContain('Mon');
        expect($settings->ytdlp_schedule_days)->toContain('Fri');
    });

    it('can save duplicates schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.duplicates_schedule_time', '01:00')
            ->set('data.duplicates_day_Sun', true)
            ->call('saveDuplicatesSchedule');

        $component->assertSuccessful();

        $settings = app(MaintenanceSettings::class);
        expect($settings->duplicates_schedule_time)->toBe('01:00');
    });

    it('can save storage schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.storage_cleanup_schedule_time', '05:00')
            ->set('data.storage_cleanup_days_old', 60)
            ->call('saveStorageSchedule');

        $component->assertSuccessful();
    });

    it('can save backup schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.database_backup_schedule_time', '06:00')
            ->set('data.database_backup_retention_days', 14)
            ->call('saveBackupSchedule');

        $component->assertSuccessful();
    });

    it('can save log rotation schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.log_rotation_schedule_time', '07:00')
            ->set('data.log_retention_days', 7)
            ->call('saveLogRotationSchedule');

        $component->assertSuccessful();
    });

    it('can save thumbnail schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.thumbnail_regen_schedule_time', '08:00')
            ->call('saveThumbnailSchedule');

        $component->assertSuccessful();
    });

    it('can save notification settings', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.notifications_enabled', true)
            ->set('data.notification_email', 'test@example.com')
            ->set('data.notify_on_success', true)
            ->set('data.notify_on_failure', true)
            ->call('saveNotificationSettings');

        $component->assertSuccessful();
    });

    it('loads all day checkboxes for all schedules', function () {
        $settings = app(MaintenanceSettings::class);
        $settings->ytdlp_schedule_days = ['Mon', 'Wed', 'Fri'];
        $settings->duplicates_schedule_days = ['Sun'];
        $settings->storage_cleanup_schedule_days = ['Sat'];
        $settings->save();

        $component = Livewire::test(Settings::class);

        $component->assertSet('data.ytdlp_day_Mon', true);
        $component->assertSet('data.ytdlp_day_Wed', true);
        $component->assertSet('data.ytdlp_day_Fri', true);
        $component->assertSet('data.ytdlp_day_Sun', false);
        $component->assertSet('data.duplicates_day_Sun', true);
        $component->assertSet('data.storage_day_Sat', true);
    });

    // Note: The global save() method is a legacy method that requires all form fields
    // Individual schedule save methods (saveYtdlpSchedule, saveDuplicatesSchedule, etc.)
    // provide better coverage and are the recommended way to save settings
});

describe('Settings Page - Run Actions', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can run duplicate removal', function () {
        // Create some duplicates
        Media::factory()->create(['name' => 'duplicate.mp4', 'size' => 1000]);
        Media::factory()->create(['name' => 'duplicate.mp4', 'size' => 1000]);

        $component = Livewire::test(Settings::class)
            ->call('runDuplicateRemoval');

        $component->assertSuccessful();
        expect(Media::count())->toBe(1);
    });

    it('can run storage cleanup', function () {
        // Create orphaned file
        Storage::disk('public')->put('media/orphaned.mp4', 'content');

        $component = Livewire::test(Settings::class)
            ->call('runStorageCleanup');

        $component->assertSuccessful();
        Storage::disk('public')->assertMissing('media/orphaned.mp4');
    });

    it('can run log rotation', function () {
        // Mock the MaintenanceService to avoid deleting real log files
        $this->mock(MaintenanceService::class)
            ->shouldReceive('rotateLogs')
            ->once()
            ->andReturn(1);

        $component = Livewire::test(Settings::class)
            ->call('runLogRotation');

        $component->assertSuccessful();
    });

    it('can run ytdlp update', function () {
        // Mock the updater service
        $this->mock(UpdaterService::class)
            ->shouldReceive('checkAndUpdateYtdlp')
            ->once()
            ->andReturn(true);

        $component = Livewire::test(Settings::class)
            ->call('runYtdlpUpdate');

        $component->assertSuccessful();
    });

    it('handles ytdlp update failure', function () {
        // Mock the updater service to return false
        $this->mock(UpdaterService::class)
            ->shouldReceive('checkAndUpdateYtdlp')
            ->once()
            ->andReturn(false);

        $component = Livewire::test(Settings::class)
            ->call('runYtdlpUpdate');

        $component->assertSuccessful();
    });

    it('can run database backup', function () {
        $component = Livewire::test(Settings::class)
            ->call('runBackup');

        $component->assertSuccessful();
    });

    it('can run thumbnail regeneration', function () {
        // Mock the thumbnail service
        $this->mock(ThumbnailService::class)
            ->shouldReceive('generateThumbnail')
            ->andReturn('thumbnails/test.jpg');

        $component = Livewire::test(Settings::class)
            ->call('runThumbnailRegeneration');

        $component->assertSuccessful();
    });
});

describe('Settings Page - Schedule Day Configurations', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('saves multiple days for ytdlp schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.ytdlp_schedule_time', '03:00')
            ->set('data.ytdlp_day_Mon', true)
            ->set('data.ytdlp_day_Wed', true)
            ->set('data.ytdlp_day_Fri', true)
            ->call('saveYtdlpSchedule');

        $settings = app(MaintenanceSettings::class);
        expect($settings->ytdlp_schedule_days)->toHaveCount(3);
        expect($settings->ytdlp_schedule_days)->toContain('Mon');
        expect($settings->ytdlp_schedule_days)->toContain('Wed');
        expect($settings->ytdlp_schedule_days)->toContain('Fri');
    });

    it('saves multiple days for storage cleanup schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.storage_cleanup_schedule_time', '04:00')
            ->set('data.storage_day_Sat', true)
            ->set('data.storage_day_Sun', true)
            ->call('saveStorageSchedule');

        $settings = app(MaintenanceSettings::class);
        expect($settings->storage_cleanup_schedule_days)->toContain('Sat');
        expect($settings->storage_cleanup_schedule_days)->toContain('Sun');
    });

    it('saves multiple days for backup schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.database_backup_schedule_time', '02:00')
            ->set('data.backup_day_Mon', true)
            ->set('data.backup_day_Thu', true)
            ->call('saveBackupSchedule');

        $settings = app(MaintenanceSettings::class);
        expect($settings->database_backup_schedule_days)->toContain('Mon');
        expect($settings->database_backup_schedule_days)->toContain('Thu');
    });

    it('saves multiple days for log rotation schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.log_rotation_schedule_time', '01:00')
            ->set('data.log_day_Sun', true)
            ->call('saveLogRotationSchedule');

        $settings = app(MaintenanceSettings::class);
        expect($settings->log_rotation_schedule_days)->toContain('Sun');
    });

    it('saves multiple days for thumbnail regeneration schedule', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.thumbnail_regen_schedule_time', '05:00')
            ->set('data.thumbnail_day_Sat', true)
            ->call('saveThumbnailSchedule');

        $settings = app(MaintenanceSettings::class);
        expect($settings->thumbnail_regen_schedule_days)->toContain('Sat');
    });
});

describe('Settings Page - Notification Settings', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can save webhook url', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.notifications_enabled', true)
            ->set('data.notification_webhook_url', 'https://hooks.example.com/webhook')
            ->call('saveNotificationSettings');

        $component->assertSuccessful();

        $settings = app(MaintenanceSettings::class);
        expect($settings->notification_webhook_url)->toBe('https://hooks.example.com/webhook');
    });

    it('can disable notifications', function () {
        $settings = app(MaintenanceSettings::class);
        $settings->notifications_enabled = true;
        $settings->save();

        $component = Livewire::test(Settings::class)
            ->set('data.notifications_enabled', false)
            ->call('saveNotificationSettings');

        $settings = app(MaintenanceSettings::class);
        expect($settings->notifications_enabled)->toBeFalse();
    });

    it('can configure notify on success only', function () {
        $component = Livewire::test(Settings::class)
            ->set('data.notify_on_success', true)
            ->set('data.notify_on_failure', false)
            ->call('saveNotificationSettings');

        $settings = app(MaintenanceSettings::class);
        expect($settings->notify_on_success)->toBeTrue();
        expect($settings->notify_on_failure)->toBeFalse();
    });
});

describe('Settings Page - Error Handling', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('handles ytdlp update failure gracefully', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('updateYtDlp')->once()->andReturn(false);
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runYtdlpUpdate');

        $component->assertSuccessful();
    });

    it('handles ytdlp update exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('updateYtDlp')->once()->andThrow(new \Exception('Update failed'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runYtdlpUpdate');

        $component->assertSuccessful();
    });

    it('handles duplicate removal exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('removeDuplicates')->once()->andThrow(new \Exception('Database error'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runDuplicateRemoval');

        $component->assertSuccessful();
    });

    it('handles storage cleanup exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('cleanupOrphanedFiles')->once()->andThrow(new \Exception('Disk error'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runStorageCleanup');

        $component->assertSuccessful();
    });

    it('handles backup exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('createDatabaseBackup')->once()->andThrow(new \Exception('Backup failed'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runBackup');

        $component->assertSuccessful();
    });

    it('handles log rotation exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('rotateLogs')->once()->andThrow(new \Exception('Log rotation failed'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runLogRotation');

        $component->assertSuccessful();
    });

    it('handles thumbnail regeneration exception', function () {
        $mock = Mockery::mock(MaintenanceService::class);
        $mock->shouldReceive('regenerateThumbnails')->once()->andThrow(new \Exception('FFmpeg not found'));
        app()->instance(MaintenanceService::class, $mock);

        $component = Livewire::test(Settings::class)
            ->call('runThumbnailRegeneration');

        $component->assertSuccessful();
    });
});
