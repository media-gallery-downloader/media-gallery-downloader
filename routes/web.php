<?php

use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();

        // Check storage is writable
        if (! is_writable(storage_path())) {
            throw new Exception('Storage not writable');
        }

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'ok',
                'storage' => 'ok',
            ],
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => now()->toISOString(),
        ], 503);
    }
});

Route::get('/', function () {
    return redirect('/admin/home');
});

Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

Route::delete('/api/media/{media}', [MediaController::class, 'destroy'])
    ->middleware(['web']);

// API endpoint for smart refresh - returns media count and latest update timestamp
Route::get('/api/media/stats', function () {
    $count = \App\Models\Media::count();
    $latest = \App\Models\Media::latest()->first();

    return response()->json([
        'count' => $count,
        'latest_id' => $latest?->id,
        'latest_updated_at' => $latest?->updated_at?->timestamp,
    ]);
})->middleware(['web']);

// Backup download route
Route::get('/backup/download/{filename}', function (string $filename) {
    $backupDir = storage_path('app/data/backups');
    $filepath = $backupDir.'/'.basename($filename); // basename to prevent directory traversal

    if (! file_exists($filepath)) {
        abort(404, 'Backup not found');
    }

    return response()->download($filepath, $filename, [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ]);
})->name('backup.download')->middleware(['web']);
