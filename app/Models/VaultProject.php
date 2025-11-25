<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VaultProject extends Model
{
    protected $fillable = [
        'project',
        'secrets',
        'configs',
        'api_token',
        'is_active',
        'last_accessed_at',
    ];

    protected $casts = [
        'secrets' => 'array',
        'configs' => 'array',
        'is_active' => 'boolean',
        'last_accessed_at' => 'datetime',
    ];

    protected $hidden = [
        'api_token',
    ];

    /**
     * Generate a new API token
     */
    public static function generateToken(): string
    {
        return 'hvn_' . Str::random(48);
    }

    /**
     * Find project by API token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('api_token', $token)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all secrets this project has access to
     */
    public function getSecrets(): array
    {
        $secrets = [];

        foreach ($this->secrets as $key) {
            $secret = VaultSecret::where('key', $key)->first();
            if ($secret) {
                $secrets[$key] = $secret->getDecryptedValue();
            }
        }

        return $secrets;
    }

    /**
     * Get all configs this project has access to
     */
    public function getConfigs(): array
    {
        $configs = [];

        foreach ($this->configs as $name) {
            $config = VaultConfig::where('name', $name)->first();
            if ($config) {
                $configs[$name] = $config->config;
            }
        }

        return $configs;
    }

    /**
     * Check if project has access to a specific secret
     */
    public function hasSecretAccess(string $key): bool
    {
        return in_array($key, $this->secrets ?? []);
    }

    /**
     * Check if project has access to a specific config
     */
    public function hasConfigAccess(string $name): bool
    {
        return in_array($name, $this->configs ?? []);
    }

    /**
     * Update last accessed timestamp
     */
    public function touchAccess(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }
}
