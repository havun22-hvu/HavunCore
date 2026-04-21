<?php

namespace Tests\Unit\Services;

use App\Models\AuthAccessLog;
use App\Models\AuthDevice;
use App\Models\AuthUser;
use App\Services\DeviceTrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage voor DeviceTrustService — bearer-token verify, revoke,
 * logout, en getUserDevices/getAccessLogs. Toegevoegd 2026-04-20 om
 * de gap richting 80 % HavunCore Unit-coverage te dichten.
 *
 * Eén van de meest gebruikte services (admin middleware, QrAuth,
 * WebAuthn, Vault) — verdiende eigen tests al lang.
 */
class DeviceTrustServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithDevice(array $deviceOverrides = []): array
    {
        $user = AuthUser::create([
            'email' => 'test-' . Str::random(8) . '@havun.nl',
            'name' => 'Test User',
            'password' => bcrypt('test-password'),
            'is_admin' => false,
        ]);

        $token = Str::random(64);
        $device = AuthDevice::create(array_merge([
            'user_id' => $user->id,
            'token' => $token,
            'device_hash' => hash('sha256', 'device-' . Str::random(16)),
            'device_name' => 'Test Device',
            'browser' => 'PHPUnit',
            'os' => 'Test',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'is_active' => true,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ], $deviceOverrides));

        return [$user, $device, $token];
    }

    public function test_verify_token_returns_invalid_for_unknown_token(): void
    {
        $result = (new DeviceTrustService())->verifyToken('unknown-token-string');

        $this->assertFalse($result['valid']);
    }

    public function test_verify_token_returns_user_and_user_model_when_valid(): void
    {
        [, , $token] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->verifyToken($token);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('user_model', $result);
        $this->assertInstanceOf(AuthUser::class, $result['user_model']);
    }

    public function test_verify_token_extends_trust_when_close_to_expiry(): void
    {
        [, $device, $token] = $this->makeUserWithDevice([
            'expires_at' => now()->addDays(3), // < 7 day window
        ]);

        (new DeviceTrustService())->verifyToken($token);

        $device->refresh();
        $this->assertGreaterThan(now()->addDays(7), $device->expires_at,
            'Verify must extend trust when within 7 days of expiry.');
    }

    public function test_revoke_device_marks_existing_device_inactive(): void
    {
        [$user, $device] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->revokeDevice($user, $device->id, '127.0.0.1');

        $this->assertTrue($result['success']);
        $device->refresh();
        $this->assertFalse((bool) $device->is_active);
    }

    public function test_revoke_device_returns_error_for_unknown_id(): void
    {
        [$user] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->revokeDevice($user, 999_999);

        $this->assertFalse($result['success']);
        $this->assertSame('Device not found', $result['message']);
    }

    public function test_revoke_all_devices_can_keep_one_active(): void
    {
        [$user, $deviceKeep] = $this->makeUserWithDevice();
        [, $deviceRevoke] = $this->makeUserWithDevice();
        // Reassign the second device to the same user so we have two.
        $deviceRevoke->update(['user_id' => $user->id]);

        $result = (new DeviceTrustService())->revokeAllDevices($user, $deviceKeep->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['revoked_count']);
        $this->assertTrue((bool) $deviceKeep->fresh()->is_active);
        $this->assertFalse((bool) $deviceRevoke->fresh()->is_active);
    }

    public function test_logout_revokes_device_with_matching_token(): void
    {
        [, $device, $token] = $this->makeUserWithDevice();

        $result = (new DeviceTrustService())->logout($token);

        $this->assertTrue($result['success']);
        $this->assertFalse((bool) $device->fresh()->is_active);
    }

    public function test_logout_returns_error_for_unknown_token(): void
    {
        $result = (new DeviceTrustService())->logout('unknown-token');

        $this->assertFalse($result['success']);
        $this->assertSame('Device not found', $result['message']);
    }

    public function test_get_user_devices_lists_only_active_devices_sorted_by_last_used(): void
    {
        [$user, $active] = $this->makeUserWithDevice([
            'device_name' => 'Recent',
            'last_used_at' => now(),
        ]);
        // Second active device, used earlier (should come after "Recent")
        AuthDevice::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'device_hash' => hash('sha256', 'old'),
            'device_name' => 'Older',
            'browser' => 'PHPUnit',
            'os' => 'Test',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'is_active' => true,
            'last_used_at' => now()->subDays(3),
            'expires_at' => now()->addDays(20),
        ]);
        // Revoked device — must NOT appear.
        AuthDevice::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'device_hash' => hash('sha256', 'revoked'),
            'device_name' => 'Revoked',
            'browser' => 'PHPUnit',
            'os' => 'Test',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'is_active' => false,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);

        $devices = (new DeviceTrustService())->getUserDevices($user);

        $this->assertCount(2, $devices, 'Revoked device must not appear in the list');
        $names = array_column($devices, 'name');
        $this->assertSame(['Recent', 'Older'], $names, 'Sorted by last_used_at descending');
        // Shape-check: every entry has the fields the controller expects.
        foreach ($devices as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('ip_address', $entry);
            $this->assertArrayHasKey('last_used_at', $entry);
            $this->assertArrayHasKey('expires_at', $entry);
            $this->assertFalse($entry['is_current'], 'is_current is set by the controller, not the service');
        }
    }

    public function test_get_access_logs_returns_recent_log_entries_for_user(): void
    {
        [$user] = $this->makeUserWithDevice();

        \App\Models\AuthAccessLog::log(
            \App\Models\AuthAccessLog::ACTION_LOGIN,
            $user->id,
            'Chrome op Mac',
            '10.0.0.1',
        );
        \App\Models\AuthAccessLog::log(
            \App\Models\AuthAccessLog::ACTION_LOGOUT,
            $user->id,
            'Chrome op Mac',
            '10.0.0.1',
        );

        $logs = (new DeviceTrustService())->getAccessLogs($user, limit: 10);

        $this->assertCount(2, $logs);
        $actions = array_column($logs, 'action');
        $this->assertContains(\App\Models\AuthAccessLog::ACTION_LOGIN, $actions);
        $this->assertContains(\App\Models\AuthAccessLog::ACTION_LOGOUT, $actions);
        // Shape-check.
        foreach ($logs as $entry) {
            $this->assertArrayHasKey('action', $entry);
            $this->assertArrayHasKey('device_name', $entry);
            $this->assertArrayHasKey('ip_address', $entry);
            $this->assertArrayHasKey('created_at', $entry);
        }
    }

    // -----------------------------------------------------------------
    // MSI-hardening (pad 4, Infection 21-04-2026 → 83 % baseline).
    // De onderstaande tests sluiten de 11 geesels die Infection op de
    // DeviceTrustService vond. Elke test voldoet aan test-quality-
    // policy §3: echt contract, strict ===, geen padding.
    // -----------------------------------------------------------------

    public function test_verify_token_calls_touch_used_updating_ip_and_last_used_at(): void
    {
        // Kills: Line 26 MethodCallRemoval op `$device->touchUsed($ipAddress)`.
        // Contract: verify MOET last_used_at bijwerken naar "nu" en ip_address
        // vervangen door de aanroep-IP. Zonder touchUsed blijft de oude
        // waarde staan.
        [, $device, $token] = $this->makeUserWithDevice([
            'last_used_at' => now()->subDays(5),
            'ip_address' => '8.8.8.8',
        ]);
        $before = $device->last_used_at;

        (new DeviceTrustService())->verifyToken($token, '203.0.113.42');

        $device->refresh();
        $this->assertSame('203.0.113.42', $device->ip_address,
            'touchUsed MOET ip_address bijwerken naar de verify-aanroep IP');
        $this->assertTrue($device->last_used_at->gt($before),
            'touchUsed MOET last_used_at vooruit zetten');
    }

    public function test_verify_token_extend_boundary_at_exactly_seven_days_does_not_extend(): void
    {
        // Kills: Line 29 LessThan (`< 7` → `<= 7`).
        // Contract: `diffInDays(now()) < 7` betekent "strict minder dan 7".
        // Bij expires_at exact op 7 dagen (diffInDays == 7) MOET extend
        // NIET gebeuren. Een `<= 7` mutant zou hier wel extenden.
        $fixedNow = Carbon::parse('2026-04-21 12:00:00');
        Carbon::setTestNow($fixedNow);

        [, $device, $token] = $this->makeUserWithDevice([
            // Exact 7 dagen in de toekomst → diffInDays == 7.
            'expires_at' => $fixedNow->copy()->addDays(7),
        ]);
        $originalExpiry = $device->expires_at->copy();

        (new DeviceTrustService())->verifyToken($token);

        $device->refresh();
        $this->assertTrue($device->expires_at->equalTo($originalExpiry),
            'Bij diffInDays == 7 MOET extendTrust NIET worden aangeroepen');

        Carbon::setTestNow();
    }

    public function test_verify_token_extend_boundary_at_six_days_does_extend(): void
    {
        // Kills: samen met bovenstaande test de `< 7` → `<= 7` mutatie definitief.
        // Contract: < 7 dagen MOET extenden. 6 dagen is binnen window.
        $fixedNow = Carbon::parse('2026-04-21 12:00:00');
        Carbon::setTestNow($fixedNow);

        [, $device, $token] = $this->makeUserWithDevice([
            'expires_at' => $fixedNow->copy()->addDays(6),
        ]);
        $originalExpiry = $device->expires_at->copy();

        (new DeviceTrustService())->verifyToken($token);

        $device->refresh();
        $this->assertTrue($device->expires_at->gt($originalExpiry),
            'Bij diffInDays < 7 MOET extendTrust wel worden aangeroepen');

        Carbon::setTestNow();
    }

    public function test_verify_token_response_contains_expires_at_as_iso_string_key(): void
    {
        // Kills: Line 49 ArrayItem (`'expires_at' =>` → `'expires_at' >`).
        // Contract: expires_at MOET in de device-payload zitten als ISO-string
        // op key 'expires_at'. Een mutatie die `=>` naar `>` wijzigt zou de
        // key laten verdwijnen.
        [, $device, $token] = $this->makeUserWithDevice([
            'expires_at' => now()->addDays(20),
        ]);

        $result = (new DeviceTrustService())->verifyToken($token);

        $this->assertArrayHasKey('expires_at', $result['device']);
        $this->assertSame(
            $device->fresh()->expires_at->toISOString(),
            $result['device']['expires_at'],
        );
    }

    public function test_get_user_devices_handles_null_last_used_at_without_crashing(): void
    {
        // Kills: Line 66 NullSafeMethodCall (`?->` → `->`).
        // Contract: last_used_at MAG null zijn (nieuw device zonder gebruik).
        // De service MOET dat afhandelen met `?->toISOString()` → null in
        // de output. Zonder `?->` zou `null->toISOString()` een TypeError/
        // Error gooien.
        [$user] = $this->makeUserWithDevice(['last_used_at' => null]);

        $devices = (new DeviceTrustService())->getUserDevices($user);

        $this->assertCount(1, $devices);
        $this->assertNull($devices[0]['last_used_at'],
            'null last_used_at MOET als null doorkomen via ?->');
    }

    public function test_revoke_device_writes_access_log_with_device_id_metadata(): void
    {
        // Kills: Line 91 MethodCallRemoval (verwijderen van AuthAccessLog::log).
        // Kills: Line 97 ArrayItemRemoval (verwijderen van revoked_device_id).
        // Contract: elke device-revoke MOET een audit-log entry schrijven
        // met action=device_revoke en metadata.revoked_device_id = X.
        [$user, $device] = $this->makeUserWithDevice();
        $logsBefore = AuthAccessLog::count();

        (new DeviceTrustService())->revokeDevice($user, $device->id, '198.51.100.9');

        $this->assertSame($logsBefore + 1, AuthAccessLog::count(),
            'revokeDevice MOET exact 1 audit-log schrijven');

        $log = AuthAccessLog::where('user_id', $user->id)
            ->where('action', AuthAccessLog::ACTION_DEVICE_REVOKE)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'audit-log met action=device_revoke MOET bestaan');
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($device->device_name, $log->device_name);
        $this->assertSame('198.51.100.9', $log->ip_address);
        $this->assertIsArray($log->metadata);
        $this->assertSame($device->id, $log->metadata['revoked_device_id'] ?? null,
            'metadata MOET revoked_device_id exact gelijk aan device->id bevatten');
    }

    public function test_revoke_all_devices_writes_access_log_with_revoked_count_metadata(): void
    {
        // Kills: Line 120 MethodCallRemoval (verwijderen AuthAccessLog::log).
        // Kills: Line 126 ArrayItemRemoval (verwijderen revoked_count).
        // Contract: revokeAll MOET een audit-log schrijven met exact het
        // aantal gerevokete devices in metadata.revoked_count. Zonder log
        // of zonder revoked_count is audit-spoor gebroken.
        [$user, $deviceKeep] = $this->makeUserWithDevice();
        [, $deviceDrop1] = $this->makeUserWithDevice();
        [, $deviceDrop2] = $this->makeUserWithDevice();
        $deviceDrop1->update(['user_id' => $user->id]);
        $deviceDrop2->update(['user_id' => $user->id]);
        $logsBefore = AuthAccessLog::count();

        (new DeviceTrustService())->revokeAllDevices($user, $deviceKeep->id, '10.1.2.3');

        $this->assertSame($logsBefore + 1, AuthAccessLog::count(),
            'revokeAllDevices MOET exact 1 audit-log schrijven');

        $log = AuthAccessLog::where('user_id', $user->id)
            ->where('action', AuthAccessLog::ACTION_DEVICE_REVOKE)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('All devices', $log->device_name);
        $this->assertSame('10.1.2.3', $log->ip_address);
        $this->assertIsArray($log->metadata);
        $this->assertSame(2, $log->metadata['revoked_count'] ?? null,
            'metadata MOET revoked_count exact gelijk aan gerevokete aantal bevatten');
        $this->assertSame($deviceKeep->id, $log->metadata['except_device_id'] ?? null);
    }

    public function test_logout_writes_access_log_with_logout_action(): void
    {
        // Kills: Line 155 MethodCallRemoval (verwijderen AuthAccessLog::log).
        // Contract: elke logout MOET een audit-log-entry schrijven met
        // action=logout en device_name van het gerevokete device.
        [$user, $device, $token] = $this->makeUserWithDevice();
        $logsBefore = AuthAccessLog::count();

        (new DeviceTrustService())->logout($token, '192.0.2.77');

        $this->assertSame($logsBefore + 1, AuthAccessLog::count(),
            'logout MOET exact 1 audit-log schrijven');

        $log = AuthAccessLog::where('user_id', $user->id)
            ->where('action', AuthAccessLog::ACTION_LOGOUT)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'audit-log met action=logout MOET bestaan');
        $this->assertSame($device->device_name, $log->device_name);
        $this->assertSame('192.0.2.77', $log->ip_address);
    }

    public function test_get_access_logs_default_limit_is_exactly_twenty(): void
    {
        // Kills: Line 171 Increment/Decrement op `int $limit = 20`.
        // Contract: default limit = 20. Bij 25 logs MOET getAccessLogs()
        // er exact 20 teruggeven. Mutaties naar 19 of 21 geven een
        // verschillend aantal.
        [$user] = $this->makeUserWithDevice();

        for ($i = 0; $i < 25; $i++) {
            AuthAccessLog::log(
                AuthAccessLog::ACTION_LOGIN,
                $user->id,
                "dev-{$i}",
                '10.0.0.1',
            );
        }

        $logs = (new DeviceTrustService())->getAccessLogs($user);

        $this->assertCount(20, $logs,
            'Default limit MOET exact 20 zijn; 19 of 21 breken dit contract');
    }
}
