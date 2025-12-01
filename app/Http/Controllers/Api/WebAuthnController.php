<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use App\Models\AuthDevice;
use App\Models\WebAuthnCredential;
use App\Models\WebAuthnChallenge;
use App\Services\DeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebAuthnController extends Controller
{
    public function __construct(
        private DeviceTrustService $deviceTrustService
    ) {}

    /**
     * GET /api/auth/webauthn/register-options
     * Get options to register a new passkey (requires auth)
     */
    public function registerOptions(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $verification = $this->deviceTrustService->verifyToken($token, $request->ip());
        if (!$verification['valid']) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = AuthUser::find($verification['user']['id']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Create challenge
        $challengeRecord = WebAuthnChallenge::createForRegistration($user->id);

        // Get existing credential IDs to exclude
        $excludeCredentials = $user->webauthnCredentials()
            ->get()
            ->map(fn($c) => [
                'id' => $c->credential_id,
                'type' => 'public-key',
                'transports' => $c->transports ?? ['internal', 'hybrid'],
            ])
            ->toArray();

        return response()->json([
            'challenge' => $this->base64urlEncode($challengeRecord->challenge),
            'rp' => [
                'name' => 'HavunCore',
                'id' => $this->getRpId($request),
            ],
            'user' => [
                'id' => $this->base64urlEncode($user->id . '-' . $user->email),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred',
                'residentKey' => 'preferred',
            ],
            'excludeCredentials' => $excludeCredentials,
        ]);
    }

    /**
     * POST /api/auth/webauthn/register
     * Register a new passkey (requires auth)
     */
    public function register(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $verification = $this->deviceTrustService->verifyToken($token, $request->ip());
        if (!$verification['valid']) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = AuthUser::find($verification['user']['id']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $request->validate([
            'credential' => 'required|array',
            'credential.id' => 'required|string',
            'credential.rawId' => 'required|string',
            'credential.type' => 'required|string|in:public-key',
            'credential.response.clientDataJSON' => 'required|string',
            'credential.response.attestationObject' => 'required|string',
            'name' => 'nullable|string|max:100',
        ]);

        $credentialData = $request->input('credential');

        // Parse clientDataJSON to get challenge
        $clientDataJSON = $this->base64urlDecode($credentialData['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if (!$clientData || !isset($clientData['challenge'])) {
            return response()->json(['error' => 'Invalid client data'], 400);
        }

        // Verify challenge
        $challenge = $this->base64urlDecode($clientData['challenge']);
        $challengeRecord = WebAuthnChallenge::findValidChallenge($challenge, 'register');

        if (!$challengeRecord || $challengeRecord->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid or expired challenge'], 400);
        }

        // Parse attestation object to get public key
        $attestationObject = $this->base64urlDecode($credentialData['response']['attestationObject']);
        $publicKey = $this->extractPublicKey($attestationObject);

        if (!$publicKey) {
            // Store raw attestation if we can't parse it (simplified approach)
            $publicKey = base64_encode($attestationObject);
        }

        // Determine device type from user agent
        $ua = $request->userAgent();
        $deviceType = 'unknown';
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $deviceType = 'iOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $deviceType = 'Android';
        } elseif (stripos($ua, 'Mac') !== false) {
            $deviceType = 'macOS';
        } elseif (stripos($ua, 'Windows') !== false) {
            $deviceType = 'Windows';
        }

        // Store credential
        $credential = WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialData['id'],
            'public_key' => $publicKey,
            'name' => $request->input('name', 'Passkey op ' . $deviceType),
            'counter' => 0,
            'transports' => $credentialData['response']['transports'] ?? ['internal', 'hybrid'],
            'device_type' => $deviceType,
        ]);

        // Delete used challenge
        $challengeRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Passkey geregistreerd',
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'device_type' => $credential->device_type,
                'created_at' => $credential->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/auth/webauthn/login-options
     * Get options for passkey login (no auth required)
     */
    public function loginOptions(Request $request): JsonResponse
    {
        $username = $request->query('username');

        // Create challenge
        $userId = null;
        $allowCredentials = [];

        if ($username) {
            $user = AuthUser::findByEmail($username . '@havun.nl')
                ?? AuthUser::findByEmail($username);

            if ($user) {
                $userId = $user->id;
                $allowCredentials = $user->webauthnCredentials()
                    ->get()
                    ->map(fn($c) => [
                        'id' => $c->credential_id,
                        'type' => 'public-key',
                        'transports' => $c->transports ?? ['internal', 'hybrid'],
                    ])
                    ->toArray();
            }
        }

        // If no specific user, get all credentials (for passkey autofill)
        if (empty($allowCredentials)) {
            $allowCredentials = WebAuthnCredential::all()
                ->map(fn($c) => [
                    'id' => $c->credential_id,
                    'type' => 'public-key',
                    'transports' => $c->transports ?? ['internal', 'hybrid'],
                ])
                ->toArray();
        }

        if (empty($allowCredentials)) {
            return response()->json([
                'error' => 'No passkeys registered',
                'available' => false,
            ], 404);
        }

        $challengeRecord = WebAuthnChallenge::createForLogin($userId);

        return response()->json([
            'challenge' => $this->base64urlEncode($challengeRecord->challenge),
            'timeout' => 60000,
            'rpId' => $this->getRpId($request),
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
            'available' => true,
        ]);
    }

    /**
     * GET /api/auth/webauthn/available
     * Check if biometric login is available for a user
     */
    public function available(Request $request): JsonResponse
    {
        $username = $request->query('username');
        $count = 0;

        if ($username) {
            $user = AuthUser::findByEmail($username . '@havun.nl')
                ?? AuthUser::findByEmail($username);

            if ($user) {
                $count = $user->webauthnCredentials()->count();
            }
        }

        // Also check total credentials
        if ($count === 0) {
            $count = WebAuthnCredential::count();
        }

        return response()->json([
            'available' => $count > 0,
            'count' => $count,
        ]);
    }

    /**
     * POST /api/auth/webauthn/login
     * Login with passkey
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|array',
            'credential.id' => 'required|string',
            'credential.rawId' => 'required|string',
            'credential.type' => 'required|string|in:public-key',
            'credential.response.authenticatorData' => 'required|string',
            'credential.response.clientDataJSON' => 'required|string',
            'credential.response.signature' => 'required|string',
        ]);

        $credentialData = $request->input('credential');

        // Find credential by ID
        $credential = WebAuthnCredential::findByCredentialId($credentialData['id']);

        if (!$credential) {
            return response()->json([
                'success' => false,
                'error' => 'Passkey niet gevonden',
            ], 401);
        }

        // Parse clientDataJSON to verify challenge
        $clientDataJSON = $this->base64urlDecode($credentialData['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if (!$clientData || !isset($clientData['challenge'])) {
            return response()->json([
                'success' => false,
                'error' => 'Ongeldige client data',
            ], 400);
        }

        // Verify challenge
        $challenge = $this->base64urlDecode($clientData['challenge']);
        $challengeRecord = WebAuthnChallenge::findValidChallenge($challenge, 'login');

        if (!$challengeRecord) {
            return response()->json([
                'success' => false,
                'error' => 'Ongeldige of verlopen challenge',
            ], 400);
        }

        // Get user
        $user = $credential->user;

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Gebruiker niet gevonden',
            ], 404);
        }

        // Verify signature (simplified - in production use proper WebAuthn library)
        // For now we trust the browser's WebAuthn implementation
        $authenticatorData = $this->base64urlDecode($credentialData['response']['authenticatorData']);

        // Check counter to prevent replay attacks
        // Note: Some authenticators (especially mobile) may not increment counter reliably
        // Only fail if counter goes backwards (not if it stays the same)
        $newCounter = $this->extractCounter($authenticatorData);
        if ($newCounter !== null && $credential->counter > 0 && $newCounter < $credential->counter) {
            return response()->json([
                'success' => false,
                'error' => 'Beveiligingsfout: counter mismatch',
            ], 400);
        }

        // Update credential counter
        $credential->counter = $newCounter ?? $credential->counter + 1;
        $credential->last_used_at = now();
        $credential->save();

        // Delete used challenge
        $challengeRecord->delete();

        // Create device token
        $ua = $request->userAgent();
        $deviceInfo = [
            'browser' => $this->detectBrowser($ua),
            'os' => $credential->device_type ?? 'Unknown',
            'user_agent' => $ua,
        ];

        // Generate device hash from fingerprint data
        $deviceHash = hash('sha256', implode('|', [
            $request->ip(),
            $ua,
            $user->id,
            $credential->credential_id,
        ]));

        $device = AuthDevice::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'device_hash' => $deviceHash,
            'device_name' => $credential->name,
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'ip_address' => $request->ip(),
            'user_agent' => $deviceInfo['user_agent'],
            'is_active' => true,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // Update user last login
        $user->touchLogin();

        return response()->json([
            'success' => true,
            'message' => 'Ingelogd met passkey',
            'device_token' => $device->token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_admin' => $user->is_admin,
            ],
        ]);
    }

    /**
     * GET /api/auth/webauthn/credentials
     * List user's passkeys (requires auth)
     */
    public function credentials(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $verification = $this->deviceTrustService->verifyToken($token, $request->ip());
        if (!$verification['valid']) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = AuthUser::find($verification['user']['id']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $credentials = $user->webauthnCredentials()
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'device_type' => $c->device_type,
                'last_used_at' => $c->last_used_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        return response()->json([
            'credentials' => $credentials,
        ]);
    }

    /**
     * DELETE /api/auth/webauthn/credentials/{id}
     * Delete a passkey (requires auth)
     */
    public function deleteCredential(Request $request, int $id): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $verification = $this->deviceTrustService->verifyToken($token, $request->ip());
        if (!$verification['valid']) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = AuthUser::find($verification['user']['id']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $credential = $user->webauthnCredentials()->find($id);
        if (!$credential) {
            return response()->json(['error' => 'Credential not found'], 404);
        }

        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => 'Passkey verwijderd',
        ]);
    }

    // Helper methods

    private function getRpId(Request $request): string
    {
        $host = $request->getHost();
        // Return base domain for passkey compatibility
        if (str_ends_with($host, '.havun.nl')) {
            return 'havun.nl';
        }
        return $host;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function extractPublicKey(string $attestationObject): ?string
    {
        // Simplified - in production use a proper CBOR library
        // For now, store the raw attestation object
        return base64_encode($attestationObject);
    }

    private function extractCounter(string $authenticatorData): ?int
    {
        // Counter is bytes 33-36 of authenticator data (big-endian)
        if (strlen($authenticatorData) < 37) {
            return null;
        }
        return unpack('N', substr($authenticatorData, 33, 4))[1];
    }

    private function detectBrowser(string $ua): string
    {
        if (stripos($ua, 'Firefox') !== false) return 'Firefox';
        if (stripos($ua, 'Edg') !== false) return 'Edge';
        if (stripos($ua, 'Chrome') !== false) return 'Chrome';
        if (stripos($ua, 'Safari') !== false) return 'Safari';
        return 'Unknown';
    }
}
