<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // yt-dlp extra command line arguments
        $this->migrator->add('maintenance.ytdlp_extra_args', '');
    }

    public function down(): void
    {
        $this->migrator->delete('maintenance.ytdlp_extra_args');
    }
};
