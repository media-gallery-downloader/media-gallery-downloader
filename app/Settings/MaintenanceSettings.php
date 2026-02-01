<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MaintenanceSettings extends Settings
{
    // yt-dlp Update Schedule
    public array $ytdlp_schedule_days;

    public ?string $ytdlp_schedule_time;

    // yt-dlp Extra Arguments
    public string $ytdlp_extra_args;

    // Deno Update Schedule
    public array $deno_schedule_days;

    public ?string $deno_schedule_time;

    // Duplicate Removal Schedule
    public array $duplicates_schedule_days;

    public ?string $duplicates_schedule_time;

    // Storage Cleanup Schedule
    public array $storage_cleanup_schedule_days;

    public ?string $storage_cleanup_schedule_time;

    public int $storage_cleanup_days_old; // Remove files older than X days

    // Database Backup Schedule
    public array $database_backup_schedule_days;

    public ?string $database_backup_schedule_time;

    public int $database_backup_retention_days; // Keep backups for X days

    // Log Rotation Schedule
    public array $log_rotation_schedule_days;

    public ?string $log_rotation_schedule_time;

    public int $log_retention_days; // Keep logs for X days

    // Thumbnail Regeneration Schedule
    public array $thumbnail_regen_schedule_days;

    public ?string $thumbnail_regen_schedule_time;

    // Bulk Import Schedule
    public array $import_scan_schedule_days;

    public ?string $import_scan_schedule_time;

    // Notification Settings
    public bool $notifications_enabled;

    public ?string $notification_email;

    public ?string $notification_webhook_url;

    public bool $notify_on_success;

    public bool $notify_on_failure;

    public static function group(): string
    {
        return 'maintenance';
    }
}
