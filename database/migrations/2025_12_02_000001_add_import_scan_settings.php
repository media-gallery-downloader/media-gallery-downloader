<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Bulk Import Scan Settings
        $this->migrator->add('maintenance.import_scan_schedule_days', []);
        $this->migrator->add('maintenance.import_scan_schedule_time', null);
    }

    public function down(): void
    {
        $this->migrator->delete('maintenance.import_scan_schedule_days');
        $this->migrator->delete('maintenance.import_scan_schedule_time');
    }
};
