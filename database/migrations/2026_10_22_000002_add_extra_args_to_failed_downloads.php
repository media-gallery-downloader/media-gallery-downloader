<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one-off yt-dlp arguments a download was started with, so every
        // retry of it asks for the same (see DownloadOptions).
        Schema::table('failed_downloads', function (Blueprint $table) {
            $table->text('extra_args')->nullable();
            $table->boolean('combine_args')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('failed_downloads', function (Blueprint $table) {
            $table->dropColumn(['extra_args', 'combine_args']);
        });
    }
};
