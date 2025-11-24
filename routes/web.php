<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'HavunCore',
        'version' => '1.0.0',
        'description' => 'Central orchestration and shared services',
    ]);
});
