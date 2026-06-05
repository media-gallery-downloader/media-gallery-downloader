<?php

use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $services = [];
    $healthy = true;

    // Check database connection
    try {
        DB::connection()->getPdo();
        $services['database'] = 'ok';
    } catch (\Throwable $e) {
        $services['database'] = 'error: '.$e->getMessage();
        $healthy = false;
    }

    // Check the cache/queue backend (Redis/Valkey) - the component most likely
    // to take the app down, since sessions, cache and queues all depend on it.
    try {
        Redis::connection()->ping();
        $services['redis'] = 'ok';
    } catch (\Throwable $e) {
        $services['redis'] = 'error: '.$e->getMessage();
        $healthy = false;
    }

    // Check storage is writable
    if (is_writable(storage_path())) {
        $services['storage'] = 'ok';
    } else {
        $services['storage'] = 'error: not writable';
        $healthy = false;
    }

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toISOString(),
        'services' => $services,
    ], $healthy ? 200 : 503);
});

Route::get('/', function () {
    return redirect('/admin/home');
});

Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

// NOTE: This app ships with no built-in authentication (see README "Security").
// These routes are intentionally only behind the `web` group and rely on a
// network/reverse-proxy auth layer for protection.
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

// Backup download route (contains a full database dump - must be authenticated)
Route::get('/backup/download/{filename}', function (string $filename) {
    $safeName = basename($filename); // prevent directory traversal and header injection
    $backupDir = storage_path('app/data/backups');
    $filepath = $backupDir.'/'.$safeName;

    if (! file_exists($filepath)) {
        abort(404, 'Backup not found');
    }

    return response()->download($filepath, $safeName, [
        'Content-Type' => 'application/octet-stream',
    ]);
})->name('backup.download')->middleware(['web']);
