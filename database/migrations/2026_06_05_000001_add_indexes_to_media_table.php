<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gallery and widgets sort/filter the media table by these columns
     * (created_at for "newest/oldest" and activity charts, size for sorting,
     * mime_type for the stats widget). Without indexes these become full table
     * scans as the library grows.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('size');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['size']);
            $table->dropIndex(['mime_type']);
        });
    }
};
