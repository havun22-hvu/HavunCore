<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware — Mozilla Observatory compliant.
 *
 * IMPORTANT: This is the ONLY place security headers should be set.
 * Do NOT add security headers in nginx — that causes duplicate headers.
 *
 * CSP Checklist (all must pass Mozilla Observatory):
 * - default-src 'none'          → deny by default
 * - script-src with nonce       → NO unsafe-inline, NO unsafe-eval
 * - style-src with nonce        → NO unsafe-inline (refactor style="" to CSS classes)
 * - object-src 'none'           → block plugins
 * - base-uri 'self'             → prevent base tag injection
 * - form-action 'self'          → restrict form submissions
 * - frame-ancestors 'none'      → prevent clickjacking
 * - manifest-src 'self'         → allow PWA manifest
 *
 * External scripts MUST have SRI (integrity + crossorigin attributes).
 * Use @nonce on all <script> and <style> tags in Blade templates.
 *
 * @see docs/kb/runbooks/security-headers-check.md
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate CSP nonce for this request
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        $isLocal = app()->environment('local');

        $csp = implode('; ', [
            "default-src 'none'",

            // Scripts: nonce-based only — NO unsafe-inline, NO unsafe-eval
            // Add CDN domains with https:// prefix as needed
            $isLocal
                ? "script-src 'self' 'nonce-{$nonce}' http://localhost:* ws://localhost:*"
                : "script-src 'self' 'nonce-{$nonce}'",

            // Styles: nonce-based — NO unsafe-inline
            // All <style> tags must have @nonce, all style="" must be refactored to CSS classes
            $isLocal
                ? "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://fonts.bunny.net http://localhost:*"
                : "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://fonts.bunny.net",

            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:",

            $isLocal
                ? "connect-src 'self' http://localhost:* ws://localhost:* wss://localhost:*"
                : "connect-src 'self'",

            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
            "manifest-src 'self'",

            ...($isLocal ? [] : ["upgrade-insecure-requests"]),
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
