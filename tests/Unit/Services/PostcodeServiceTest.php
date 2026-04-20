<?php

namespace Tests\Unit\Services;

use App\Services\PostcodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage voor de PDOK-postcode lookup: validation + normalization +
 * cache + HTTP-fallback. Toegevoegd 2026-04-20 om HavunCore CI-coverage
 * richting 80 % te tillen.
 */
class PostcodeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_normalize_postcode_uppercases_and_strips_spaces(): void
    {
        $service = new PostcodeService();

        $this->assertSame('1234AB', $service->normalizePostcode('1234ab'));
        $this->assertSame('1234AB', $service->normalizePostcode('1234 ab'));
        $this->assertSame('1234AB', $service->normalizePostcode(' 1234 AB '));
    }

    public function test_is_valid_postcode_accepts_standard_dutch_format(): void
    {
        $service = new PostcodeService();

        $this->assertTrue($service->isValidPostcode('1234AB'));
        $this->assertTrue($service->isValidPostcode('1234 AB'));
        $this->assertTrue($service->isValidPostcode('9999ZZ'));
    }

    public function test_is_valid_postcode_rejects_invalid_inputs(): void
    {
        $service = new PostcodeService();

        $this->assertFalse($service->isValidPostcode(''));
        $this->assertFalse($service->isValidPostcode(null));
        $this->assertFalse($service->isValidPostcode('0234AB'), 'First digit cannot be 0.');
        $this->assertFalse($service->isValidPostcode('1234'));
        $this->assertFalse($service->isValidPostcode('1234ABC'));
        $this->assertFalse($service->isValidPostcode('AB1234'));
    }

    public function test_lookup_returns_null_for_invalid_postcode_without_http_call(): void
    {
        // No Http::fake → if any HTTP call escapes, the test will throw.
        $service = new PostcodeService();

        $this->assertNull($service->lookup('invalid', '10'));
    }

    public function test_lookup_caches_pdok_response(): void
    {
        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => ['docs' => [[
                    'straatnaam' => 'Hoofdstraat',
                    'woonplaatsnaam' => 'Amsterdam',
                    'huisnummer' => 10,
                    'postcode' => '1234AB',
                ]]],
            ], 200),
        ]);

        $service = new PostcodeService();
        $first = $service->lookup('1234AB', '10');
        $this->assertNotNull($first, 'PDOK lookup must hit the fake.');

        // Second call must come from cache — clear http fake to prove it.
        Http::fake([
            'api.pdok.nl/*' => fn () => $this->fail('Cache miss — service called PDOK twice.'),
        ]);

        $second = $service->lookup('1234AB', '10');
        $this->assertSame($first, $second);
    }
}
