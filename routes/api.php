<?php

use App\Http\Controllers\Api\AIProxyController;
use App\Http\Controllers\Api\ClaudeTaskController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MCPMessageController;
use App\Http\Controllers\Api\QrAuthController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\WebAuthnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'HavunCore',
        'version' => config('app.version', '1.0.0'),
    ]);
});

Route::get('/version', function () {
    return response()->json([
        'app' => 'HavunCore',
        'version' => config('app.version', '1.0.0'),
        'environment' => app()->environment(),
        'timestamp' => now()->toIso8601String(),
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

// QR Auth API - Passwordless authentication with device trust
Route::prefix('auth')->group(function () {
    // QR Login Flow
    Route::post('/qr/generate', [QrAuthController::class, 'generateQr'])->name('api.auth.qr.generate');
    Route::get('/qr/{code}/status', [QrAuthController::class, 'checkQrStatus'])->name('api.auth.qr.status');
    Route::post('/qr/{code}/approve', [QrAuthController::class, 'approveQr'])->name('api.auth.qr.approve');
    Route::post('/qr/{code}/send-email', [QrAuthController::class, 'sendEmail'])->name('api.auth.qr.send-email');

    // Email Login (approve via email link)
    Route::post('/email/approve', [QrAuthController::class, 'approveEmail'])->name('api.auth.email.approve');

    // Authenticated QR approve (from mobile app with device token)
    Route::post('/qr/approve-authenticated', [QrAuthController::class, 'approveAuthenticated'])->name('api.auth.qr.approve-authenticated');

    // QR approve from client app (email from trusted session)
    Route::post('/qr/approve-from-app', [QrAuthController::class, 'approveFromApp'])->name('api.auth.qr.approve-from-app');

    // Password Login (fallback)
    Route::post('/login', [QrAuthController::class, 'login'])->name('api.auth.login');
    Route::post('/logout', [QrAuthController::class, 'logout'])->name('api.auth.logout');

    // Token verification
    Route::post('/verify', [QrAuthController::class, 'verify'])->name('api.auth.verify');

    // User registration (first user or admin only)
    Route::post('/register', [QrAuthController::class, 'register'])->name('api.auth.register');

    // Device management (requires auth)
    Route::get('/devices', [DeviceController::class, 'index'])->name('api.auth.devices');
    Route::delete('/devices/{id}', [DeviceController::class, 'destroy'])->name('api.auth.devices.destroy');
    Route::post('/devices/revoke-all', [DeviceController::class, 'revokeAll'])->name('api.auth.devices.revoke-all');

    // Access logs
    Route::get('/logs', [DeviceController::class, 'logs'])->name('api.auth.logs');

    // WebAuthn / Passkey endpoints
    Route::prefix('webauthn')->group(function () {
        // Registration (requires auth)
        Route::get('/register-options', [WebAuthnController::class, 'registerOptions'])->name('api.auth.webauthn.register-options');
        Route::post('/register', [WebAuthnController::class, 'register'])->name('api.auth.webauthn.register');

        // Login (no auth required)
        Route::get('/login-options', [WebAuthnController::class, 'loginOptions'])->name('api.auth.webauthn.login-options');
        Route::post('/login', [WebAuthnController::class, 'login'])->name('api.auth.webauthn.login');
        Route::get('/available', [WebAuthnController::class, 'available'])->name('api.auth.webauthn.available');

        // Credential management (requires auth)
        Route::get('/credentials', [WebAuthnController::class, 'credentials'])->name('api.auth.webauthn.credentials');
        Route::delete('/credentials/{id}', [WebAuthnController::class, 'deleteCredential'])->name('api.auth.webauthn.credentials.delete');
    });
});

// AI Proxy API - Central Claude API proxy for all projects
Route::prefix('ai')->group(function () {
    Route::post('/chat', [AIProxyController::class, 'chat'])->name('api.ai.chat');
    Route::get('/usage', [AIProxyController::class, 'usage'])->name('api.ai.usage');
    Route::get('/health', [AIProxyController::class, 'health'])->name('api.ai.health');
});
