<?php

use App\Http\Controllers\Web\AuthApproveController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'HavunCore',
        'version' => '1.0.0',
        'description' => 'Central orchestration and shared services',
    ]);
});

// Central approve page for QR/email login
Route::get('/approve', [AuthApproveController::class, 'show'])->name('auth.approve');
Route::post('/approve', [AuthApproveController::class, 'process'])->name('auth.approve.process');
