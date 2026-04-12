<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Error Log
 *
 * Structured error tracking with deduplication via fingerprint.
 */
class ErrorLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'severity',
        'url',
        'method',
        'ip_address',
        'user_id',
        'context',
        'fingerprint',
        'occurrence_count',
        'last_occurred_at',
        'created_at',
    ];

    protected $casts = [
        'line' => 'integer',
        'context' => 'array',
        'occurrence_count' => 'integer',
        'last_occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Capture an exception with deduplication.
     */
    public static function capture(\Throwable $e, ?Request $request = null): void
    {
        try {
            $fingerprint = hash('sha256', get_class($e) . $e->getFile() . $e->getLine());
            $maxTrace = config('observability.error_trace_max_length', 5000);

            // Try to deduplicate: increment if same fingerprint exists within last hour
            $existing = static::where('fingerprint', $fingerprint)
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if ($existing) {
                $existing->update([
                    'occurrence_count' => $existing->occurrence_count + 1,
                    'last_occurred_at' => now(),
                ]);

                return;
            }

            static::create([
                'exception_class' => get_class($e),
                'message' => mb_substr($e->getMessage(), 0, 65535),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, $maxTrace),
                'severity' => static::determineSeverity($e),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'ip_address' => $request?->ip(),
                'user_id' => $request?->user()?->id,
                'context' => $request ? [
                    'route' => $request->route()?->getName(),
                    'input_keys' => array_keys($request->except(['password', 'token', 'secret', 'api_key'])),
                ] : null,
                'fingerprint' => $fingerprint,
                'last_occurred_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let error logging cause secondary errors
        }
    }

    /**
     * Determine severity based on exception type.
     */
    protected static function determineSeverity(\Throwable $e): string
    {
        if ($e instanceof \Error) {
            return 'critical';
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode() >= 500 ? 'error' : 'warning';
        }

        return 'error';
    }

    /**
     * Scope recent errors
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope by severity
     */
    public function scopeForSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}
