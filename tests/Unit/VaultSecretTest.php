<?php

namespace Tests\Unit;

use App\Models\VaultSecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor VaultSecret encryptie + masking + scope. Toegevoegd
 * 2026-04-20 voor HavunCore Unit-coverage push.
 */
class VaultSecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_is_encrypted_at_rest(): void
    {
        $secret = VaultSecret::create([
            'key' => 'TEST_KEY',
            'value' => 'plaintext-secret',
        ]);

        // Raw column value must NOT equal the plaintext.
        $raw = \DB::table('vault_secrets')->where('id', $secret->id)->value('value');
        $this->assertNotSame('plaintext-secret', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_get_decrypted_value_returns_original_plaintext(): void
    {
        $secret = VaultSecret::create([
            'key' => 'TEST_KEY',
            'value' => 'plaintext-secret',
        ]);

        $this->assertSame('plaintext-secret', $secret->getDecryptedValue());
    }

    public function test_value_is_hidden_from_array_serialization(): void
    {
        $secret = VaultSecret::create([
            'key' => 'TEST_KEY',
            'value' => 'plaintext-secret',
        ]);

        $this->assertArrayNotHasKey('value', $secret->toArray());
    }

    public function test_masked_value_shows_last_4_chars_only(): void
    {
        $secret = VaultSecret::create([
            'key' => 'TEST_KEY',
            'value' => 'sk_live_super_secret_1234',
        ]);

        $masked = $secret->getMaskedValue();
        $this->assertStringContainsString('•', $masked);
        $this->assertStringEndsWith('1234', $masked);
        $this->assertStringNotContainsString('super', $masked);
    }

    public function test_masked_value_returns_all_dots_for_short_secrets(): void
    {
        $secret = VaultSecret::create([
            'key' => 'TINY',
            'value' => 'abcd',
        ]);

        $this->assertSame('••••', $secret->getMaskedValue(),
            '4-char or shorter value: no suffix exposed.');
    }

    public function test_category_scope_filters_correctly(): void
    {
        VaultSecret::create(['key' => 'A', 'value' => 'v1', 'category' => 'api']);
        VaultSecret::create(['key' => 'B', 'value' => 'v2', 'category' => 'api']);
        VaultSecret::create(['key' => 'C', 'value' => 'v3', 'category' => 'database']);

        $this->assertSame(2, VaultSecret::category('api')->count());
        $this->assertSame(1, VaultSecret::category('database')->count());
    }
}
