<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Postcode Service - Dutch Address Lookup
 *
 * Uses PDOK (Publieke Dienstverlening Op de Kaart) - free Dutch government API
 * for postcode + huisnummer → full address lookup.
 *
 * Usage:
 *   $service = new PostcodeService();
 *   $address = $service->lookup('1234AB', '10');
 *   // Returns: ['street' => 'Hoofdstraat', 'city' => 'Amsterdam', ...]
 */
class PostcodeService
{
    private const PDOK_URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';
    private const CACHE_TTL = 86400 * 30; // 30 days

    /**
     * Lookup address by postcode and house number
     *
     * @param string $postcode Dutch postcode (e.g., "1234AB" or "1234 AB")
     * @param string $huisnummer House number (e.g., "10" or "10a")
     * @return array|null Address data or null if not found
     */
    public function lookup(string $postcode, string $huisnummer): ?array
    {
        $postcode = $this->normalizePostcode($postcode);
        $huisnummer = trim($huisnummer);

        if (!$this->isValidPostcode($postcode)) {
            return null;
        }

        $cacheKey = "postcode:{$postcode}:{$huisnummer}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($postcode, $huisnummer) {
            return $this->fetchFromPDOK($postcode, $huisnummer);
        });
    }

    /**
     * Validate Dutch postcode format
     */
    public function isValidPostcode(?string $postcode): bool
    {
        if (empty($postcode)) {
            return false;
        }
        return preg_match('/^[1-9][0-9]{3}\s?[a-zA-Z]{2}$/', $postcode) === 1;
    }

    /**
     * Normalize postcode to uppercase without spaces
     */
    public function normalizePostcode(string $postcode): string
    {
        return strtoupper(str_replace(' ', '', $postcode));
    }

    /**
     * Fetch address from PDOK API
     */
    private function fetchFromPDOK(string $postcode, string $huisnummer): ?array
    {
        try {
            $query = "postcode:{$postcode} AND huisnummer:{$huisnummer}";

            $response = Http::timeout(5)
                ->get(self::PDOK_URL, [
                    'q' => $query,
                    'fq' => 'type:adres',
                    'rows' => 1,
                ]);

            if (!$response->successful()) {
                Log::warning("PDOK lookup failed", [
                    'postcode' => $postcode,
                    'huisnummer' => $huisnummer,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            $docs = $data['response']['docs'] ?? [];

            if (empty($docs)) {
                return null;
            }

            $doc = $docs[0];

            return [
                'street' => $doc['straatnaam'] ?? null,
                'house_number' => $doc['huisnummer'] ?? null,
                'house_letter' => $doc['huisletter'] ?? null,
                'addition' => $doc['huisnummertoevoeging'] ?? null,
                'postcode' => $doc['postcode'] ?? null,
                'city' => $doc['woonplaatsnaam'] ?? null,
                'municipality' => $doc['gemeentenaam'] ?? null,
                'province' => $doc['provincienaam'] ?? null,
                'latitude' => $this->extractLat($doc['centroide_ll'] ?? null),
                'longitude' => $this->extractLon($doc['centroide_ll'] ?? null),
                'full_address' => $this->formatAddress($doc),
            ];
        } catch (\Exception $e) {
            Log::error("PDOK lookup exception", [
                'postcode' => $postcode,
                'huisnummer' => $huisnummer,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract latitude from POINT string
     */
    private function extractLat(?string $point): ?float
    {
        if (!$point || !preg_match('/POINT\(([\d.]+) ([\d.]+)\)/', $point, $matches)) {
            return null;
        }
        return (float) $matches[2];
    }

    /**
     * Extract longitude from POINT string
     */
    private function extractLon(?string $point): ?float
    {
        if (!$point || !preg_match('/POINT\(([\d.]+) ([\d.]+)\)/', $point, $matches)) {
            return null;
        }
        return (float) $matches[1];
    }

    /**
     * Format full address string
     */
    private function formatAddress(array $doc): string
    {
        $street = $doc['straatnaam'] ?? '';
        $number = $doc['huisnummer'] ?? '';
        $letter = $doc['huisletter'] ?? '';
        $addition = $doc['huisnummertoevoeging'] ?? '';
        $postcode = $doc['postcode'] ?? '';
        $city = $doc['woonplaatsnaam'] ?? '';

        $houseNumber = $number . $letter;
        if ($addition) {
            $houseNumber .= '-' . $addition;
        }

        return trim("{$street} {$houseNumber}, {$postcode} {$city}");
    }

    /**
     * Calculate distance between two postcodes in kilometers (Haversine)
     */
    public function getDistance(string $postcode1, string $huisnummer1, string $postcode2, string $huisnummer2): ?float
    {
        $addr1 = $this->lookup($postcode1, $huisnummer1);
        $addr2 = $this->lookup($postcode2, $huisnummer2);

        if (!$addr1 || !$addr2 || !$addr1['latitude'] || !$addr2['latitude']) {
            return null;
        }

        return $this->haversine(
            $addr1['latitude'], $addr1['longitude'],
            $addr2['latitude'], $addr2['longitude']
        );
    }

    /**
     * Haversine formula for distance calculation
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) ** 2 +
             cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
