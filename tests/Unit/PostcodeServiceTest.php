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

    // -- Lookup with house letter and addition --

    public function test_lookup_returns_house_letter_and_addition(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Prinsengracht',
                        'huisnummer' => 263,
                        'huisletter' => 'A',
                        'huisnummertoevoeging' => 'boven',
                        'postcode' => '1016GV',
                        'woonplaatsnaam' => 'Amsterdam',
                        'gemeentenaam' => 'Amsterdam',
                        'provincienaam' => 'Noord-Holland',
                        'centroide_ll' => 'POINT(4.884 52.375)',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1016GV', '263');

        $this->assertNotNull($result);
        $this->assertEquals('A', $result['house_letter']);
        $this->assertEquals('boven', $result['addition']);
        // full_address should contain letter and addition
        $this->assertStringContainsString('263A-boven', $result['full_address']);
    }

    // -- Coordinate extraction --

    public function test_lookup_extracts_correct_lat_lon(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Teststraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Teststad',
                        'centroide_ll' => 'POINT(5.12345 52.67890)',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1234AB', '1');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(52.67890, $result['latitude'], 0.00001);
        $this->assertEqualsWithDelta(5.12345, $result['longitude'], 0.00001);
    }

    public function test_lookup_returns_null_lat_lon_when_no_point(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Teststraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Teststad',
                        // No centroide_ll
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1234AB', '1');

        $this->assertNotNull($result);
        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
    }

    // -- Distance calculation --

    public function test_get_distance_returns_km_between_two_addresses(): void
    {
        Cache::flush();

        // Fake two different postcodes with known coordinates
        Http::fake([
            'api.pdok.nl/*' => Http::sequence()
                ->push([
                    'response' => [
                        'docs' => [[
                            'straatnaam' => 'Straat A',
                            'huisnummer' => 1,
                            'postcode' => '1000AA',
                            'woonplaatsnaam' => 'Amsterdam',
                            'centroide_ll' => 'POINT(4.9041 52.3676)', // Amsterdam
                        ]],
                    ],
                ])
                ->push([
                    'response' => [
                        'docs' => [[
                            'straatnaam' => 'Straat B',
                            'huisnummer' => 1,
                            'postcode' => '3000AA',
                            'woonplaatsnaam' => 'Utrecht',
                            'centroide_ll' => 'POINT(5.1214 52.0907)', // Utrecht
                        ]],
                    ],
                ]),
        ]);

        $distance = $this->service->getDistance('1000AA', '1', '3000AA', '1');

        $this->assertNotNull($distance);
        // Amsterdam to Utrecht is roughly 35-40 km
        $this->assertGreaterThan(30.0, $distance);
        $this->assertLessThan(50.0, $distance);
    }

    public function test_get_distance_returns_zero_for_same_location(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Dezelfde Straat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Teststad',
                        'centroide_ll' => 'POINT(5.0 52.0)',
                    ]],
                ],
            ]),
        ]);

        $distance = $this->service->getDistance('1234AB', '1', '1234AB', '1');

        $this->assertNotNull($distance);
        $this->assertEqualsWithDelta(0.0, $distance, 0.01);
    }

    public function test_get_distance_returns_null_when_address_not_found(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => ['docs' => []],
            ]),
        ]);

        $distance = $this->service->getDistance('9999ZZ', '999', '1234AB', '1');

        $this->assertNull($distance);
    }

    public function test_get_distance_returns_null_when_no_coordinates(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Straat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Stad',
                        // No centroide_ll
                    ]],
                ],
            ]),
        ]);

        $distance = $this->service->getDistance('1234AB', '1', '1234AB', '1');

        $this->assertNull($distance);
    }

    // -- Exception handling --

    public function test_lookup_returns_null_on_exception(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => function () {
                throw new \Exception('Connection timeout');
            },
        ]);

        $result = $this->service->lookup('1234AB', '1');

        $this->assertNull($result);
    }

    // -- Postcode with spaces in lookup --

    public function test_lookup_normalizes_postcode_with_space(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Spacestraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Spacestad',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1234 ab', '1');

        $this->assertNotNull($result);
        $this->assertEquals('Spacestraat', $result['street']);
    }

    // -- Full address formatting --

    public function test_full_address_format_without_addition(): void
    {
        Cache::flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Dorpsstraat',
                        'huisnummer' => 42,
                        'huisletter' => null,
                        'huisnummertoevoeging' => null,
                        'postcode' => '5678CD',
                        'woonplaatsnaam' => 'Dorpstad',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('5678CD', '42');

        $this->assertNotNull($result);
        $this->assertEquals('Dorpsstraat 42, 5678CD Dorpstad', $result['full_address']);
    }
}
