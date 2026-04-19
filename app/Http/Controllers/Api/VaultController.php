<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\AdminCreateProjectRequest;
use App\Http\Requests\Vault\AdminCreateSecretRequest;
use App\Http\Requests\Vault\AdminUpdateProjectRequest;
use App\Http\Requests\Vault\AdminUpdateSecretRequest;
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
    public function adminCreateSecret(AdminCreateSecretRequest $request): JsonResponse
    {
        $data = $request->validated();

        $secret = VaultSecret::create([
            'key' => $data['key'],
            'value' => $data['value'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'is_sensitive' => $data['is_sensitive'] ?? true,
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
    public function adminUpdateSecret(AdminUpdateSecretRequest $request, string $key): JsonResponse
    {
        $secret = VaultSecret::where('key', $key)->first();

        if (!$secret) {
            return response()->json(['error' => 'Secret not found'], 404);
        }

        $data = $request->validated();

        if (array_key_exists('value', $data)) {
            $secret->value = $data['value'];
        }
        if (array_key_exists('category', $data)) {
            $secret->category = $data['category'];
        }
        if (array_key_exists('description', $data)) {
            $secret->description = $data['description'];
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
    public function adminCreateProject(AdminCreateProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $token = VaultProject::generateToken();

        $project = VaultProject::create([
            'project' => $data['project'],
            'secrets' => $data['secrets'] ?? [],
            'configs' => $data['configs'] ?? [],
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
    public function adminUpdateProject(AdminUpdateProjectRequest $request, string $projectName): JsonResponse
    {
        $project = VaultProject::where('project', $projectName)->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $data = $request->validated();

        if (array_key_exists('secrets', $data)) {
            $project->secrets = $data['secrets'];
        }
        if (array_key_exists('configs', $data)) {
            $project->configs = $data['configs'];
        }
        if (array_key_exists('is_active', $data)) {
            $project->is_active = $data['is_active'];
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
