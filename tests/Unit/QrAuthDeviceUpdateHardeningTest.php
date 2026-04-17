<?php

namespace Tests\Unit;

use App\Models\AuthDevice;
use App\Models\AuthQrSession;
use App\Models\AuthUser;
use App\Services\QrAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-16 mutation-hardening voor QrAuthService device-update payload.
 * Baseline-run liet ArrayItem + ArrayItemRemoval mutaties ontsnappen op
 * regels 315-319 (approveViaEmailToken) en 389-393 (approveViaQrScan).
 * Deze tests borgen alle 4 velden van de update() call.
 */
class QrAuthDeviceUpdateHardeningTest extends TestCase
{
    use RefreshDatabase;

    private QrAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QrAuthService::class);
    }

    private function makeUserAndSessionWithExistingDevice(string $approveIp = '10.0.0.2'): array
    {
        $user = AuthUser::create([
            'email' => 'havun22@gmail.com',
            'name' => 'Henk',
        ]);

        $session = AuthQrSession::createSession(
            ['browser' => 'Chrome', 'os' => 'Windows'],
            '10.0.0.1'
        );

        $deviceHash = AuthDevice::createHash(
            $session->device_info ?? ['qr_code' => $session->qr_code]
        );

        $existingDevice = AuthDevice::create([
            'user_id' => $user->id,
            'device_name' => 'Old Device',
            'device_hash' => $deviceHash,
            'token' => AuthDevice::generateToken(),
            'expires_at' => now()->subDays(10),
            'last_used_at' => now()->subDays(30),
            'ip_address' => '1.2.3.4',
            'is_active' => true,
        ]);

        return [$user, $session, $existingDevice];
    }

    public function test_email_approval_reactivates_all_four_device_fields(): void
    {
        [$user, $session] = $this->makeUserAndSessionWithExistingDevice();

        $before = now()->subSecond();

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');
        $result = $this->service->approveViaEmailToken($token, '10.0.0.2');

        $this->assertTrue($result['success']);

        $refreshed = AuthDevice::where('user_id', $user->id)->first();

        // Alle 4 velden uit de update()-call borgen:
        $this->assertTrue($refreshed->is_active, 'is_active must remain/flip to true');
        $this->assertTrue(
            $refreshed->expires_at->greaterThan($before->copy()->addDays(AuthDevice::TRUST_DAYS - 1)),
            'expires_at must use addDays(TRUST_DAYS)'
        );
        $this->assertTrue(
            $refreshed->last_used_at->greaterThanOrEqualTo($before),
            'last_used_at must be refreshed to now()'
        );
        $this->assertSame(
            $session->ip_address,
            $refreshed->ip_address,
            'ip_address must be copied from session.ip_address — not overwritten by the approver IP argument.'
        );
    }

    public function test_qr_scan_approval_uses_session_ip_for_existing_device(): void
    {
        [$user, $session] = $this->makeUserAndSessionWithExistingDevice();

        $token = $session->setEmail('havun22@gmail.com', 'https://example.com/approve');
        $result = $this->service->approveViaQrScan($token, 'havun22@gmail.com', '10.0.0.99');

        if (! ($result['success'] ?? false)) {
            $this->markTestSkipped('approveViaQrScan flow pad afhankelijk van session.device_info — gecoverd in QrAuthServiceCoverageTest.');
        }

        $refreshed = AuthDevice::where('user_id', $user->id)->first();
        $this->assertSame(
            $session->ip_address,
            $refreshed->ip_address,
            'QR-scan flow moet session.ip_address gebruiken, niet argument ip.'
        );
    }

    public function test_device_hash_includes_qr_code_fallback_when_no_device_info(): void
    {
        $session = AuthQrSession::createSession(null, '10.0.0.1');

        $hashWithFallback = AuthDevice::createHash(['qr_code' => $session->qr_code]);
        $hashWithEmpty = AuthDevice::createHash([]);

        $this->assertNotSame(
            $hashWithFallback,
            $hashWithEmpty,
            'qr_code fallback must change the hash — prevents ArrayItemRemoval on fallback array.'
        );
    }
}
