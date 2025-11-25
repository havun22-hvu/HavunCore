<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultConfig extends Model
{
    protected $fillable = [
        'name',
        'type',
        'config',
        'description',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    /**
     * Scope by type
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get config value by dot notation
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Merge config with secrets to create full configuration
     */
    public function mergeWithSecrets(array $secretKeys): array
    {
        $config = $this->config;

        foreach ($secretKeys as $key) {
            $secret = VaultSecret::where('key', $key)->first();
            if ($secret) {
                data_set($config, $key, $secret->getDecryptedValue());
            }
        }

        return $config;
    }
}
