<?php

namespace Tests\Unit;

use App\Services\PostcodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostcodeServiceCoverageTest extends TestCase
{
    private PostcodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->service = new PostcodeService();
    }

    // ===================================================================
    // fetchFromPDOK — edge cases not yet covered
    // ===================================================================

    public function test_lookup_handles_missing_centroide_ll_key(): void
    {
        Cache::store('array')->flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Teststraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Teststad',
                        // centroide_ll key completely absent
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1234AB', '1');

        $this->assertNotNull($result);
        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
    }

    public function test_lookup_handles_invalid_centroide_ll_format(): void
    {
        Cache::store('array')->flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Teststraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Teststad',
                        'centroide_ll' => 'INVALID FORMAT',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('1234AB', '1');

        $this->assertNotNull($result);
        $this->assertNull($result['latitude']);
        $this->assertNull($result['longitude']);
    }

    public function test_format_address_with_house_letter_no_addition(): void
    {
        Cache::store('array')->flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Kerkstraat',
                        'huisnummer' => 5,
                        'huisletter' => 'B',
                        'huisnummertoevoeging' => null,
                        'postcode' => '5678CD',
                        'woonplaatsnaam' => 'Dorpstad',
                    ]],
                ],
            ]),
        ]);

        $result = $this->service->lookup('5678CD', '5');

        $this->assertNotNull($result);
        $this->assertEquals('Kerkstraat 5B, 5678CD Dorpstad', $result['full_address']);
    }

    public function test_lookup_trims_huisnummer(): void
    {
        Cache::store('array')->flush();

        Http::fake([
            'api.pdok.nl/*' => Http::response([
                'response' => [
                    'docs' => [[
                        'straatnaam' => 'Teststraat',
                        'huisnummer' => 1,
                        'postcode' => '1234AB',
                        'woonplaatsnaam' => 'Stad',
                    ]],
                ],
            ]),
        ]);

        // Huisnummer with whitespace
        $result = $this->service->lookup('1234AB', '  1  ');

        $this->assertNotNull($result);
    }
}
