<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backing store for the in-app download/upload queues. Replaces the previous
     * single-cache-key approach, where concurrent read-modify-write from Horizon
     * workers + the polling UI raced and lost updates. Each job is one row, so
     * status updates are atomic single-row writes.
     *
     * The auto-increment `id` gives a stable insertion order; `queue_id` is the
     * externally-visible UUID used by jobs and the UI.
     */
    public function up(): void
    {
        Schema::create('queue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('queue_id')->unique();
            $table->string('type')->index();              // download | upload
            $table->string('status')->default('queued')->index();
            $table->string('url', 2048)->nullable();      // downloads
            $table->string('filename')->nullable();       // uploads
            $table->string('mime_type')->nullable();      // uploads
            $table->string('method')->nullable();         // auto | yt-dlp | direct
            $table->json('meta')->nullable();             // progress, error, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_items');
    }
};
