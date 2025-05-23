<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MediaController;

Route::get('/', function () {
    return redirect('/admin/home');
});

Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

Route::delete('/api/media/{media}', [MediaController::class, 'destroy'])
    ->middleware(['web', 'auth']);
