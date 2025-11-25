<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VaultAccessLog;
use App\Models\VaultConfig;
use App\Models\VaultProject;
use App\Models\VaultSecret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultController extends Controller
{
    /**
     * Authenticate project by API token
     */
    private function authenticateProject(Request $request): ?VaultProject
    {
        $token = $request->bearerToken() ?? $request->header('X-Vault-Token');

        if (!$token) {
            return null;
        }

        return VaultProject::findByToken($token);
    }

    /**
     * GET /api/vault/secrets
     * Get all secrets the project has access to
     */
    public function getSecrets(Request $request): JsonResponse
    {
        $project = $this->authenticateProject($request);

        if (!$project) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $project->touchAccess();

        VaultAccessLog::log(
            $project->project,
            'read',
            'secret',
            '*',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'project' => $project->project,
            'secrets' => $project->getSecrets(),
        ]);
    }

    /**
     * GET /api/vault/secrets/{key}
     * Get a specific secret
     */
    public function getSecret(Request $request, string $key): JsonResponse
    {
        $project = $this->authenticateProject($request);

        if (!$project) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$project->hasSecretAccess($key)) {
            return response()->json(['error' => 'Access denied to this secret'], 403);
        }

        $secret = VaultSecret::where('key', $key)->first();

        if (!$secret) {
            return response()->json(['error' => 'Secret not found'], 404);
        }

        $project->touchAccess();

        VaultAccessLog::log(
            $project->project,
            'read',
            'secret',
            $key,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'key' => $key,
            'value' => $secret->getDecryptedValue(),
            'category' => $secret->category,
        ]);
    }

    /**
     * GET /api/vault/configs
     * Get all configs the project has access to
     */
    public function getConfigs(Request $request): JsonResponse
    {
        $project = $this->authenticateProject($request);

        if (!$project) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $project->touchAccess();

        VaultAccessLog::log(
            $project->project,
            'read',
            'config',
            '*',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'project' => $project->project,
            'configs' => $project->getConfigs(),
        ]);
    }

    /**
     * GET /api/vault/configs/{name}
     * Get a specific config
     */
    public function getConfig(Request $request, string $name): JsonResponse
    {
        $project = $this->authenticateProject($request);

        if (!$project) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$project->hasConfigAccess($name)) {
            return response()->json(['error' => 'Access denied to this config'], 403);
        }

        $config = VaultConfig::where('name', $name)->first();

        if (!$config) {
            return response()->json(['error' => 'Config not found'], 404);
        }

        $project->touchAccess();

        VaultAccessLog::log(
            $project->project,
            'read',
            'config',
            $name,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'name' => $name,
            'type' => $config->type,
            'config' => $config->config,
        ]);
    }

    /**
     * GET /api/vault/bootstrap
     * Get everything needed to bootstrap a project (secrets + configs)
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $project = $this->authenticateProject($request);

        if (!$project) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $project->touchAccess();

        VaultAccessLog::log(
            $project->project,
            'read',
            'bootstrap',
            '*',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'project' => $project->project,
            'secrets' => $project->getSecrets(),
            'configs' => $project->getConfigs(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // ========================================
    // ADMIN ENDPOINTS (no auth for now, add later)
    // ========================================

    /**
     * GET /api/vault/admin/secrets
     * List all secrets (masked)
     */
    public function adminListSecrets(): JsonResponse
    {
        $secrets = VaultSecret::all()->map(function ($secret) {
            return [
                'id' => $secret->id,
                'key' => $secret->key,
                'category' => $secret->category,
                'description' => $secret->description,
                'masked_value' => $secret->getMaskedValue(),
                'is_sensitive' => $secret->is_sensitive,
                'updated_at' => $secret->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'secrets' => $secrets,
        ]);
    }

    /**
     * POST /api/vault/admin/secrets
     * Create a new secret
     */
    public function adminCreateSecret(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|unique:vault_secrets,key',
            'value' => 'required|string',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'is_sensitive' => 'nullable|boolean',
        ]);

        $secret = VaultSecret::create([
            'key' => $request->input('key'),
            'value' => $request->input('value'),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'is_sensitive' => $request->input('is_sensitive', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Secret created',
            'secret' => [
                'id' => $secret->id,
                'key' => $secret->key,
                'category' => $secret->category,
            ],
        ], 201);
    }

    /**
     * PUT /api/vault/admin/secrets/{key}
     * Update a secret
     */
    public function adminUpdateSecret(Request $request, string $key): JsonResponse
    {
        $secret = VaultSecret::where('key', $key)->first();

        if (!$secret) {
            return response()->json(['error' => 'Secret not found'], 404);
        }

        if ($request->has('value')) {
            $secret->value = $request->input('value');
        }
        if ($request->has('category')) {
            $secret->category = $request->input('category');
        }
        if ($request->has('description')) {
            $secret->description = $request->input('description');
        }

        $secret->save();

        return response()->json([
            'success' => true,
            'message' => 'Secret updated',
        ]);
    }

    /**
     * DELETE /api/vault/admin/secrets/{key}
     * Delete a secret
     */
    public function adminDeleteSecret(string $key): JsonResponse
    {
        $secret = VaultSecret::where('key', $key)->first();

        if (!$secret) {
            return response()->json(['error' => 'Secret not found'], 404);
        }

        $secret->delete();

        return response()->json([
            'success' => true,
            'message' => 'Secret deleted',
        ]);
    }

    /**
     * GET /api/vault/admin/projects
     * List all projects
     */
    public function adminListProjects(): JsonResponse
    {
        $projects = VaultProject::all()->map(function ($project) {
            return [
                'id' => $project->id,
                'project' => $project->project,
                'secrets' => $project->secrets,
                'configs' => $project->configs,
                'is_active' => $project->is_active,
                'last_accessed_at' => $project->last_accessed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'projects' => $projects,
        ]);
    }

    /**
     * POST /api/vault/admin/projects
     * Create a new project
     */
    public function adminCreateProject(Request $request): JsonResponse
    {
        $request->validate([
            'project' => 'required|string|unique:vault_projects,project',
            'secrets' => 'nullable|array',
            'configs' => 'nullable|array',
        ]);

        $token = VaultProject::generateToken();

        $project = VaultProject::create([
            'project' => $request->input('project'),
            'secrets' => $request->input('secrets', []),
            'configs' => $request->input('configs', []),
            'api_token' => $token,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created',
            'project' => $project->project,
            'api_token' => $token, // Only shown once!
        ], 201);
    }

    /**
     * PUT /api/vault/admin/projects/{project}
     * Update project permissions
     */
    public function adminUpdateProject(Request $request, string $projectName): JsonResponse
    {
        $project = VaultProject::where('project', $projectName)->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        if ($request->has('secrets')) {
            $project->secrets = $request->input('secrets');
        }
        if ($request->has('configs')) {
            $project->configs = $request->input('configs');
        }
        if ($request->has('is_active')) {
            $project->is_active = $request->input('is_active');
        }

        $project->save();

        return response()->json([
            'success' => true,
            'message' => 'Project updated',
        ]);
    }

    /**
     * POST /api/vault/admin/projects/{project}/regenerate-token
     * Regenerate API token for a project
     */
    public function adminRegenerateToken(string $projectName): JsonResponse
    {
        $project = VaultProject::where('project', $projectName)->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $token = VaultProject::generateToken();
        $project->update(['api_token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Token regenerated',
            'api_token' => $token, // Only shown once!
        ]);
    }

    /**
     * GET /api/vault/admin/logs
     * Get access logs
     */
    public function adminGetLogs(Request $request): JsonResponse
    {
        $query = VaultAccessLog::query()->orderBy('created_at', 'desc');

        if ($request->has('project')) {
            $query->forProject($request->input('project'));
        }

        $days = $request->input('days', 7);
        $query->recent($days);

        $logs = $query->limit(500)->get();

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }
}
