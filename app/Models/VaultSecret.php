<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class VaultSecret extends Model
{
    protected $fillable = [
        'key',
        'value',
        'category',
        'description',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    protected $hidden = [
        'value', // Never expose encrypted value directly
    ];

    /**
     * Set the value (automatically encrypts)
     */
    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = Crypt::encryptString($value);
    }

    /**
     * Get the decrypted value
     */
    public function getDecryptedValue(): string
    {
        return Crypt::decryptString($this->attributes['value']);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get masked value for display (shows only last 4 chars)
     */
    public function getMaskedValue(): string
    {
        $decrypted = $this->getDecryptedValue();
        $length = strlen($decrypted);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4) . substr($decrypted, -4);
    }
}
