<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-site download settings: the domains a site covers, its yt-dlp
        // arguments, and (in a file, see Site::cookiesPath) its cookies.
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('domains');
            $table->text('extra_args')->nullable();
            $table->boolean('combine_args')->default(true);
            $table->timestamp('cookies_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
