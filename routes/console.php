<?php

use App\Services\MaintenanceService;
use App\Settings\MaintenanceSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

try {
    $settings = app(MaintenanceSettings::class);
    $dayMap = [
        'Sun' => 0,
        'Mon' => 1,
        'Tue' => 2,
        'Wed' => 3,
        'Thu' => 4,
        'Fri' => 5,
        'Sat' => 6,
    ];

    // Helper function to schedule a task
    $scheduleTask = function (array $scheduleDays, ?string $scheduleTime, string $name, callable $callback) use ($dayMap) {
        if (empty($scheduleDays) || empty($scheduleTime)) {
            return;
        }

        $days = array_map(fn ($day) => $dayMap[$day] ?? null, $scheduleDays);
        $days = array_filter($days, fn ($d) => ! is_null($d));

        if (! empty($days)) {
            Schedule::call($callback)
                ->days($days)
                ->at($scheduleTime)
                ->name($name);
        }
    };

    // Schedule yt-dlp update
    $scheduleTask(
        $settings->ytdlp_schedule_days ?? [],
        $settings->ytdlp_schedule_time,
        'update-ytdlp',
        function () {
            app(MaintenanceService::class)->updateYtDlp();
        }
    );

    // Schedule Deno update
    $scheduleTask(
        $settings->deno_schedule_days ?? [],
        $settings->deno_schedule_time,
        'update-deno',
        function () {
            app(MaintenanceService::class)->updateDeno();
        }
    );

    // Schedule duplicate removal
    $scheduleTask(
        $settings->duplicates_schedule_days ?? [],
        $settings->duplicates_schedule_time,
        'remove-duplicates',
        function () {
            app(MaintenanceService::class)->removeDuplicates();
        }
    );

    // Schedule storage cleanup
    $scheduleTask(
        $settings->storage_cleanup_schedule_days ?? [],
        $settings->storage_cleanup_schedule_time ?? null,
        'storage-cleanup',
        function () {
            app(MaintenanceService::class)->cleanupOrphanedFiles();
        }
    );

    // Schedule database backup
    $scheduleTask(
        $settings->database_backup_schedule_days ?? [],
        $settings->database_backup_schedule_time ?? null,
        'database-backup',
        function () {
            app(MaintenanceService::class)->createDatabaseBackup();
        }
    );

    // Schedule log rotation
    $scheduleTask(
        $settings->log_rotation_schedule_days ?? [],
        $settings->log_rotation_schedule_time ?? null,
        'log-rotation',
        function () {
            app(MaintenanceService::class)->rotateLogs();
        }
    );

    // Schedule thumbnail regeneration
    $scheduleTask(
        $settings->thumbnail_regen_schedule_days ?? [],
        $settings->thumbnail_regen_schedule_time ?? null,
        'thumbnail-regeneration',
        function () {
            app(MaintenanceService::class)->regenerateThumbnails();
        }
    );

    // Schedule bulk import scan
    $scheduleTask(
        $settings->import_scan_schedule_days ?? [],
        $settings->import_scan_schedule_time ?? null,
        'import-scan',
        function () {
            app(MaintenanceService::class)->scanAndQueueImports();
        }
    );

    // Retry failed downloads every hour
    Schedule::call(function () {
        app(MaintenanceService::class)->retryFailedDownloads();
    })
        ->hourly()
        ->name('retry-failed-downloads');
} catch (\Throwable $e) {
    // Settings might not be migrated yet or other issues
}
