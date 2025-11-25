<?php

use App\Http\Controllers\Api\ClaudeTaskController;
use App\Http\Controllers\Api\MCPMessageController;
use App\Http\Controllers\Api\VaultController;
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

// Vault API - Project authentication via Bearer token
Route::prefix('vault')->group(function () {
    // Project endpoints (require API token)
    Route::get('/secrets', [VaultController::class, 'getSecrets'])->name('api.vault.secrets');
    Route::get('/secrets/{key}', [VaultController::class, 'getSecret'])->name('api.vault.secret');
    Route::get('/configs', [VaultController::class, 'getConfigs'])->name('api.vault.configs');
    Route::get('/configs/{name}', [VaultController::class, 'getConfig'])->name('api.vault.config');
    Route::get('/bootstrap', [VaultController::class, 'bootstrap'])->name('api.vault.bootstrap');

    // Admin endpoints (TODO: add admin auth middleware)
    Route::prefix('admin')->group(function () {
        // Secrets management
        Route::get('/secrets', [VaultController::class, 'adminListSecrets'])->name('api.vault.admin.secrets');
        Route::post('/secrets', [VaultController::class, 'adminCreateSecret'])->name('api.vault.admin.secrets.create');
        Route::put('/secrets/{key}', [VaultController::class, 'adminUpdateSecret'])->name('api.vault.admin.secrets.update');
        Route::delete('/secrets/{key}', [VaultController::class, 'adminDeleteSecret'])->name('api.vault.admin.secrets.delete');

        // Projects management
        Route::get('/projects', [VaultController::class, 'adminListProjects'])->name('api.vault.admin.projects');
        Route::post('/projects', [VaultController::class, 'adminCreateProject'])->name('api.vault.admin.projects.create');
        Route::put('/projects/{project}', [VaultController::class, 'adminUpdateProject'])->name('api.vault.admin.projects.update');
        Route::post('/projects/{project}/regenerate-token', [VaultController::class, 'adminRegenerateToken'])->name('api.vault.admin.projects.regenerate');

        // Logs
        Route::get('/logs', [VaultController::class, 'adminGetLogs'])->name('api.vault.admin.logs');
    });
});
