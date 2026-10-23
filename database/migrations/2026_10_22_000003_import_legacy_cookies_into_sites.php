<?php

use App\Services\Sites\LegacyCookieImporter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Split the old single cookies file (formerly YTDLP_COOKIES_FILE,
     * default storage/app/cookies.txt) into per-site cookies. The importer
     * logs and keeps the file on failure instead of throwing, so a bad file
     * can never stop the container from starting.
     */
    public function up(): void
    {
        app(LegacyCookieImporter::class)->import(env('YTDLP_COOKIES_FILE') ?: storage_path('app/cookies.txt'));
    }

    public function down(): void
    {
        // Not reversible: the sites it created can be edited or deleted in Settings.
    }
};
