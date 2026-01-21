<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Deno Update Schedule
        $this->migrator->add('maintenance.deno_schedule_days', []);
        $this->migrator->add('maintenance.deno_schedule_time', null);
    }

    public function down(): void
    {
        $this->migrator->delete('maintenance.deno_schedule_days');
        $this->migrator->delete('maintenance.deno_schedule_time');
    }
};
