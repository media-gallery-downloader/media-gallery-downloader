<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\MediaController;

Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();

        // Check storage is writable
        if (!is_writable(storage_path())) {
            throw new Exception('Storage not writable');
        }

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'ok',
                'storage' => 'ok',
            ]
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
