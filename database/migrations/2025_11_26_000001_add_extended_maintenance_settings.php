<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Storage Cleanup Settings
        $this->migrator->add('maintenance.storage_cleanup_schedule_days', []);
        $this->migrator->add('maintenance.storage_cleanup_schedule_time', null);
        $this->migrator->add('maintenance.storage_cleanup_days_old', 90);

        // Database Backup Settings
        $this->migrator->add('maintenance.database_backup_schedule_days', []);
        $this->migrator->add('maintenance.database_backup_schedule_time', null);
        $this->migrator->add('maintenance.database_backup_retention_days', 30);

        // Log Rotation Settings
        $this->migrator->add('maintenance.log_rotation_schedule_days', []);
        $this->migrator->add('maintenance.log_rotation_schedule_time', null);
        $this->migrator->add('maintenance.log_retention_days', 14);

        // Thumbnail Regeneration Settings
        $this->migrator->add('maintenance.thumbnail_regen_schedule_days', []);
        $this->migrator->add('maintenance.thumbnail_regen_schedule_time', null);

        // Notification Settings
        $this->migrator->add('maintenance.notifications_enabled', false);
        $this->migrator->add('maintenance.notification_email', null);
        $this->migrator->add('maintenance.notification_webhook_url', null);
        $this->migrator->add('maintenance.notify_on_success', false);
        $this->migrator->add('maintenance.notify_on_failure', true);
    }

    public function down(): void
    {
        $this->migrator->delete('maintenance.storage_cleanup_schedule_days');
        $this->migrator->delete('maintenance.storage_cleanup_schedule_time');
        $this->migrator->delete('maintenance.storage_cleanup_days_old');

        $this->migrator->delete('maintenance.database_backup_schedule_days');
        $this->migrator->delete('maintenance.database_backup_schedule_time');
        $this->migrator->delete('maintenance.database_backup_retention_days');

        $this->migrator->delete('maintenance.log_rotation_schedule_days');
        $this->migrator->delete('maintenance.log_rotation_schedule_time');
        $this->migrator->delete('maintenance.log_retention_days');

        $this->migrator->delete('maintenance.thumbnail_regen_schedule_days');
        $this->migrator->delete('maintenance.thumbnail_regen_schedule_time');

        $this->migrator->delete('maintenance.notifications_enabled');
        $this->migrator->delete('maintenance.notification_email');
        $this->migrator->delete('maintenance.notification_webhook_url');
        $this->migrator->delete('maintenance.notify_on_success');
        $this->migrator->delete('maintenance.notify_on_failure');
    }
};
