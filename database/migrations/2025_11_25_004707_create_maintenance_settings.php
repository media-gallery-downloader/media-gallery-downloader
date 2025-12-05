<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('maintenance.ytdlp_schedule_days', []);
        $this->migrator->add('maintenance.ytdlp_schedule_time', '00:00');
        $this->migrator->add('maintenance.duplicates_schedule_days', []);
        $this->migrator->add('maintenance.duplicates_schedule_time', '00:00');
    }
};
