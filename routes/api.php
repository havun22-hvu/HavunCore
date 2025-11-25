<?php

use App\Http\Controllers\Api\ClaudeTaskController;
use App\Http\Controllers\Api\MCPMessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'HavunCore',
        'version' => '1.0.0',
    ]);
});

// Claude Task Queue API
Route::prefix('claude/tasks')->group(function () {
    Route::get('/', [ClaudeTaskController::class, 'index'])->name('api.claude.tasks.index');
    Route::post('/', [ClaudeTaskController::class, 'store'])->name('api.claude.tasks.store');
    Route::get('/pending/{project}', [ClaudeTaskController::class, 'pending'])->name('api.claude.tasks.pending');
    Route::get('/{id}', [ClaudeTaskController::class, 'show'])->name('api.claude.tasks.show');
    Route::delete('/{id}', [ClaudeTaskController::class, 'destroy'])->name('api.claude.tasks.destroy');
    Route::post('/{id}/start', [ClaudeTaskController::class, 'start'])->name('api.claude.tasks.start');
    Route::post('/{id}/complete', [ClaudeTaskController::class, 'complete'])->name('api.claude.tasks.complete');
    Route::post('/{id}/fail', [ClaudeTaskController::class, 'fail'])->name('api.claude.tasks.fail');
});

// MCP Messages API
Route::prefix('mcp/messages')->group(function () {
    Route::get('/', [MCPMessageController::class, 'index'])->name('api.mcp.messages.index');
    Route::post('/', [MCPMessageController::class, 'store'])->name('api.mcp.messages.store');
    Route::post('/sync', [MCPMessageController::class, 'sync'])->name('api.mcp.messages.sync');
    Route::get('/{project}', [MCPMessageController::class, 'show'])->name('api.mcp.messages.show');
    Route::delete('/{id}', [MCPMessageController::class, 'destroy'])->name('api.mcp.messages.destroy');
});
