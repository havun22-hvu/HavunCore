<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Guards HavunCore's session-cookie defaults — part of the cross-project
 * security-headers contract. If these regress we lose cookie-level CSRF
 * protection, XSS-exfiltration protection, and cleartext-transport
 * protection, regardless of what nginx is doing.
 *
 * The actual Set-Cookie response-header behaviour is Laravel's job once
 * the config is right; what we assert here is the config-level contract
 * (so a reviewer / auditor reads config/session.php and knows these are
 * the enforced defaults).
 */
class SessionConfigTest extends TestCase
{
    public function test_secure_flag_defaults_to_true(): void
    {
        $raw = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "env('SESSION_SECURE_COOKIE', true)",
            $raw,
            'secure-cookie default must be true so the Set-Cookie header refuses HTTP transport'
        );
    }

    public function test_http_only_defaults_to_true(): void
    {
        $raw = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "env('SESSION_HTTP_ONLY', true)",
            $raw,
            'http_only default must be true so JavaScript cannot read the session cookie'
        );
    }

    public function test_same_site_defaults_to_lax(): void
    {
        $raw = file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString(
            "env('SESSION_SAME_SITE', 'lax')",
            $raw,
            'same_site default must be "lax" for baseline CSRF protection'
        );
    }

    public function test_loaded_session_config_honours_defaults_in_non_local(): void
    {
        // When env vars are absent, the defaults above should be the
        // values that actually flow through to session()->getCookieConfig().
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        // `secure` may be null in the local test env if SESSION_SECURE_COOKIE
        // is explicitly unset; what we guard is the default literal in the
        // file, asserted by the three tests above.
    }
}
