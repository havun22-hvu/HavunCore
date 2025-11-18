<?php

namespace Havun\Core\Services;

use Exception;

/**
 * Vault Service - Centralized Secrets Management
 *
 * Securely stores and distributes API keys, passwords, certificates, and other secrets
 * across all Havun projects. Encrypted at rest using AES-256-CBC.
 *
 * Usage in HavunCore:
 *   php artisan havun:vault:set mollie_api_key "live_xxx"
 *   php artisan havun:vault:get mollie_api_key
 *
 * Usage in other projects (via MCP):
 *   $vaultService = app(VaultService::class);
 *   $key = $vaultService->get('mollie_api_key');
 */
class VaultService
{
    private string $vaultPath;
    private string $encryptionKey;
    private array $cache = [];

    public function __construct()
    {
        $this->vaultPath = storage_path('vault/secrets.encrypted.json');
        $this->encryptionKey = $this->getEncryptionKey();

        // Ensure vault directory exists
        $vaultDir = dirname($this->vaultPath);
        if (!is_dir($vaultDir)) {
            mkdir($vaultDir, 0755, true);
        }
    }

    /**
     * Store a secret in the vault
     *
     * @param string $key Secret identifier (e.g., 'mollie_api_key', 'bunq_api_token')
     * @param mixed $value Secret value
     * @param array $metadata Optional metadata (project, description, expires_at)
     * @return bool Success
     */
    public function set(string $key, mixed $value, array $metadata = []): bool
    {
        try {
            $vault = $this->loadVault();

            $vault[$key] = [
                'value' => $value,
                'metadata' => array_merge([
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                    'created_by' => 'HavunCore',
                ], $metadata),
            ];

            $this->saveVault($vault);
            $this->cache[$key] = $value;

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to store secret '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Retrieve a secret from the vault
     *
     * @param string $key Secret identifier
     * @param mixed $default Default value if not found
     * @return mixed Secret value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $vault = $this->loadVault();

            if (!isset($vault[$key])) {
                return $default;
            }

            $value = $vault[$key]['value'];
            $this->cache[$key] = $value;

            return $value;
        } catch (Exception $e) {
            throw new Exception("Failed to retrieve secret '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Check if a secret exists
     */
    public function has(string $key): bool
    {
        $vault = $this->loadVault();
        return isset($vault[$key]);
    }

    /**
     * Delete a secret from the vault
     */
    public function delete(string $key): bool
    {
        try {
            $vault = $this->loadVault();

            if (!isset($vault[$key])) {
                return false;
            }

            unset($vault[$key]);
            unset($this->cache[$key]);

            $this->saveVault($vault);

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to delete secret '{$key}': " . $e->getMessage());
        }
    }

    /**
     * List all secrets (keys only, not values)
     *
     * @return array List of secret keys with metadata
     */
    public function list(): array
    {
        $vault = $this->loadVault();
        $secrets = [];

        foreach ($vault as $key => $data) {
            $secrets[$key] = $data['metadata'] ?? [];
        }

        return $secrets;
    }

    /**
     * Export secrets for a specific project
     *
     * @param string $project Project name (e.g., 'HavunAdmin', 'Herdenkingsportaal')
     * @return array Secrets filtered by project
     */
    public function exportForProject(string $project): array
    {
        $vault = $this->loadVault();
        $exported = [];

        foreach ($vault as $key => $data) {
            $metadata = $data['metadata'] ?? [];

            // Include if:
            // 1. No project specified (global secret)
            // 2. Project matches
            // 3. Project is in allowed_projects array
            if (
                !isset($metadata['project']) ||
                $metadata['project'] === $project ||
                (isset($metadata['allowed_projects']) && in_array($project, $metadata['allowed_projects']))
            ) {
                $exported[$key] = $data['value'];
            }
        }

        return $exported;
    }

    /**
     * Import secrets from another vault (for migration/backup)
     */
    public function import(array $secrets, bool $overwrite = false): int
    {
        $count = 0;

        foreach ($secrets as $key => $data) {
            if (!$overwrite && $this->has($key)) {
                continue;
            }

            $value = is_array($data) && isset($data['value']) ? $data['value'] : $data;
            $metadata = is_array($data) && isset($data['metadata']) ? $data['metadata'] : [];

            $this->set($key, $value, $metadata);
            $count++;
        }

        return $count;
    }

    /**
     * Initialize vault with default secrets structure
     */
    public function initialize(): bool
    {
        if (file_exists($this->vaultPath)) {
            throw new Exception("Vault already exists at: {$this->vaultPath}");
        }

        $defaultSecrets = [
            '_vault_version' => [
                'value' => '1.0.0',
                'metadata' => [
                    'description' => 'Vault format version',
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ];

        $this->saveVault($defaultSecrets);

        return true;
    }

    /**
     * Load and decrypt vault from disk
     */
    private function loadVault(): array
    {
        if (!file_exists($this->vaultPath)) {
            return [];
        }

        $encrypted = file_get_contents($this->vaultPath);

        if (empty($encrypted)) {
            return [];
        }

        $decrypted = $this->decrypt($encrypted);
        return json_decode($decrypted, true) ?? [];
    }

    /**
     * Encrypt and save vault to disk
     */
    private function saveVault(array $vault): void
    {
        $json = json_encode($vault, JSON_PRETTY_PRINT);
        $encrypted = $this->encrypt($json);

        file_put_contents($this->vaultPath, $encrypted);

        // Secure file permissions
        chmod($this->vaultPath, 0600);
    }

    /**
     * Encrypt data using AES-256-CBC
     */
    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data using AES-256-CBC
     */
    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Get encryption key from environment or generate one
     */
    private function getEncryptionKey(): string
    {
        $key = env('HAVUN_VAULT_KEY');

        if (!$key) {
            throw new Exception(
                "HAVUN_VAULT_KEY not set in .env file. " .
                "Generate one with: php artisan havun:vault:generate-key"
            );
        }

        // Ensure key is 32 bytes for AES-256
        return hash('sha256', $key, true);
    }

    /**
     * Generate a new encryption key
     */
    public static function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
