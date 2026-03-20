<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';

// SSO Authentication Routes (public — accessed via Apache /peminjaman-ruang-api/auth/sso)
Route::prefix('auth/sso')->group(function () {
    Route::get('/', [App\Http\Controllers\Auth\SsoAuthController::class, 'authenticate']);
    Route::get('/redirect', [App\Http\Controllers\Auth\SsoAuthController::class, 'redirect']);
});
