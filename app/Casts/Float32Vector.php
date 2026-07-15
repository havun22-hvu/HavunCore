<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * An embedding stored as raw float32 rather than JSON text.
 *
 * The model already computes in float32, so JSON only ever wrote a wider
 * format than the data needed: 15,222 bytes per vector against 3,072 packed,
 * and every read had to parse that text back into floats. Verified lossless
 * over 300 vectors — max deviation exactly 0.
 *
 * Only real vectors live here; the word-frequency fallback is a word => weight
 * map that cannot be packed, and doc_chunks deliberately stores no fallbacks.
 * See DocIndexer::indexChunks().
 */
class Float32Vector implements CastsAttributes
{
    /** @return array<int, float>|null */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return array_values(unpack('f*', $value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException(
                'A chunk vector must be a list of floats; the word-frequency fallback does not belong here.'
            );
        }

        return pack('f*', ...$value);
    }
}
