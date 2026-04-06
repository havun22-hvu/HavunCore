<?php

namespace Tests\Unit;

use App\Services\PostcodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostcodeServiceTest extends TestCase
{
    private PostcodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PostcodeService();
    }

    // -- Postcode Validation --

    public function test_valid_postcodes(): void
    {
        $this->assertTrue($this->service->isValidPostcode('1234AB'));
        $this->assertTrue($this->service->isValidPostcode('1234 AB'));
        $this->assertTrue($this->service->isValidPostcode('9999zz'));
        $this->assertTrue($this->service->isValidPostcode('1000AA'));
    }

    public function test_invalid_postcodes(): void
    {
        $this->assertFalse($this->service->isValidPostcode('0123AB')); // Starts with 0
        $this->assertFalse($this->service->isValidPostcode('12345')); // No letters
        $this->assertFalse($this->service->isValidPostcode('ABCD12')); // Letters first
        $this->assertFalse($this->service->isValidPostcode('')); // Empty
        $this->assertFalse($this->service->isValidPostcode(null)); // Null
        $this->assertFalse($this->service->isValidPostcode('123AB')); // Too short
        $this->assertFalse($this->service->isValidPostcode('1234ABC')); // Too many letters
    }

    // -- Postcode Normalization --

    public function test_normalize_removes_spaces(): void
    {
        $this->assertEquals('1234AB', $this->service->normalizePostcode('1234 AB'));
        $this->assertEquals('1234AB', $this->service->normalizePostcode('1234AB'));
    }

    public function test_normalize_uppercases(): void
    {
        $this->assertEquals('1234AB', $this->service->normalizePostcode('1234ab'));
        $this->assertEquals('1234AB', $this->service->normalizePostcode('1234 ab'));
    }

    // -- Lookup with HTTP Fake --

    public function test_lookup_returns_address_on_success(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Keizersgracht',
                        'huisnummer' => 10,
                        'huisletter' => null,
                        'huisnummertoevoeging' => null,
                        'postcode' => '1015AA',
                        'woonplaatsnaam' => 'Amsterdam',
                        'gemeentenaam' => 'Amsterdam',
                        'provincienaam' => 'Noord-Holland',
                        'centroide_ll' => 'POINT(4.889 52.375)',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1015AA', '10');

        $this->assertNotNull($result);
        $this->assertEquals('Keizersgracht', $result['street']);
        $this->assertEquals('Amsterdam', $result['city']);
        $this->assertEquals('Noord-Holland', $result['province']);
        $this->assertNotNull($result['latitude']);
        $this->assertNotNull($result['longitude']);
        $this->assertStringContainsString('Keizersgracht', $result['full_address']);
    }

    public function test_lookup_returns_null_for_invalid_postcode(): void
    {
        $result = $this->service->lookup('0000XX', '1');

        $this->assertNull($result);
    }

    public function test_lookup_returns_null_when_no_results(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => ['docs' => []],
            ]),
        ]);

        $result = $this->service->lookup('9999ZZ', '999');

        $this->assertNull($result);
    }

    public function test_lookup_returns_null_on_api_error(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->lookup('1015AA', '10');

        $this->assertNull($result);
    }

    public function test_lookup_caches_results(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Damstraat',
                        'huisnummer' => 1,
                        'postcode' => '1012JL',
                        'woonplaatsnaam' => 'Amsterdam',
                    ]],
                ],
            ]),
        ]);

        // First call - hits API
        $this->service->lookup('1012JL', '1');
        // Second call - should use cache
        $this->service->lookup('1012JL', '1');

        // HTTP should only be called once
        Http::assertSentCount(1);
    }
}
