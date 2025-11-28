<?php

/**
 * HavunCore Auth Routes
 *
 * Add these routes to your routes/web.php file
 */

use App\Http\Controllers\HavunAuthController;
use Illuminate\Support\Facades\Route;

// HavunCore Authentication Routes
Route::prefix('auth')->group(function () {
    // Login page
    Route::get('/login', [HavunAuthController::class, 'showLogin'])->name('login');

    // Password login
    Route::post('/login', [HavunAuthController::class, 'login'])->name('havun.login');

    // QR login endpoints
    Route::post('/qr/generate', [HavunAuthController::class, 'generateQr'])->name('havun.qr.generate');
    Route::get('/qr/{code}/status', [HavunAuthController::class, 'checkQrStatus'])->name('havun.qr.status');
    Route::get('/qr/{code}/complete', [HavunAuthController::class, 'checkQrStatus'])->name('havun.qr.complete');

    // Logout
    Route::post('/logout', [HavunAuthController::class, 'logout'])->name('logout');
    Route::get('/logout', [HavunAuthController::class, 'logout'])->name('havun.logout');
});

// Protected routes - add 'havun.auth' middleware
// Route::middleware('havun.auth')->group(function () {
//     Route::get('/dashboard', ...);
//     Route::get('/admin', ...);
// });
