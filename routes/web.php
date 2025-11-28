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

// Central approve page for email login
Route::get('/auth/approve', [AuthApproveController::class, 'show'])->name('auth.approve');
Route::post('/auth/approve', [AuthApproveController::class, 'process'])->name('auth.approve.process');
