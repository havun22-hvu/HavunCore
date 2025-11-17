<?php

namespace Havun\Core\Testing;

/**
 * Pact Contract Builder
 *
 * Consumer-Driven Contract Testing - Industry standard used by:
 * - ING Bank
 * - Atlassian
 * - Soundcloud
 * - RedHat
 *
 * How it works:
 * 1. Consumer (Herdenkingsportaal) defines what it expects
 * 2. Generates a "pact" file
 * 3. Provider (HavunAdmin) validates it can meet expectations
 * 4. If provider changes API → pact verification fails in CI
 *
 * Installation:
 *   composer require --dev pact-foundation/pact-php
 */
class PactContractBuilder
{
    private string $consumer;
    private string $provider;
    private array $interactions = [];

    public function __construct(string $consumer, string $provider)
    {
        $this->consumer = $consumer;
        $this->provider = $provider;
    }

    /**
     * Add an interaction (API call expectation)
     *
     * @param string $description Human-readable description
     * @param string $state Provider state (e.g., "invoice exists")
     * @param array $request Expected request
     * @param array $response Expected response
     * @return self
     */
    public function addInteraction(
        string $description,
        string $state,
        array $request,
        array $response
    ): self {
        $this->interactions[] = [
            'description' => $description,
            'providerState' => $state,
            'request' => $request,
            'response' => $response,
        ];

        return $this;
    }

    /**
     * Build pact specification
     *
     * @return array Pact file content
     */
    public function build(): array
    {
        return [
            'consumer' => [
                'name' => $this->consumer,
            ],
            'provider' => [
                'name' => $this->provider,
            ],
            'interactions' => $this->interactions,
            'metadata' => [
                'pactSpecification' => [
                    'version' => '3.0.0',
                ],
                'client' => [
                    'name' => 'HavunCore PactBuilder',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    /**
     * Save pact to file
     *
     * @param string $directory Pact directory (usually ./pacts)
     * @return string File path
     */
    public function save(string $directory = './pacts'): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = strtolower($this->consumer) . '-' . strtolower($this->provider) . '.json';
        $filepath = rtrim($directory, '/') . '/' . $filename;

        file_put_contents($filepath, json_encode($this->build(), JSON_PRETTY_PRINT));

        return $filepath;
    }

    /**
     * Create invoice sync interaction (example)
     *
     * This is what Herdenkingsportaal expects from HavunAdmin API
     */
    public static function invoiceSyncExample(): self
    {
        $builder = new self('Herdenkingsportaal', 'HavunAdmin');

        $builder->addInteraction(
            description: 'A request to sync a memorial invoice',
            state: 'an invoice does not exist for memorial 550e8400e29b',
            request: [
                'method' => 'POST',
                'path' => '/api/invoices/sync',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer test_token',
                ],
                'body' => [
                    'memorial_reference' => '550e8400e29b',
                    'customer' => [
                        'name' => 'Jan Jansen',
                        'email' => 'jan@example.com',
                    ],
                    'invoice' => [
                        'number' => 'INV-2025-00001',
                        'amount' => 19.95,
                        'vat_amount' => 4.19,
                        'total_amount' => 24.14,
                    ],
                    'payment' => [
                        'mollie_payment_id' => 'tr_test123',
                        'status' => 'paid',
                    ],
                ],
            ],
            response: [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'success' => true,
                    'invoice_id' => 501,
                    'memorial_reference' => '550e8400e29b',
                ],
            ]
        );

        return $builder;
    }

    /**
     * Create VPD data fetch interaction (example)
     *
     * This is what HavunAdmin expects from VPDUpdate API
     */
    public static function vpdDataFetchExample(): self
    {
        $builder = new self('HavunAdmin', 'VPDUpdate');

        $builder->addInteraction(
            description: 'A request to fetch VPD data',
            state: 'VPD data exists for version 1.0',
            request: [
                'method' => 'GET',
                'path' => '/api/vpd/data',
                'query' => [
                    'version' => '1.0',
                    'limit' => '100',
                ],
                'headers' => [
                    'Authorization' => 'Bearer test_vpd_token',
                ],
            ],
            response: [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'data' => [
                        // VPD data structure
                    ],
                    'version' => '1.0',
                    'count' => 100,
                ],
            ]
        );

        return $builder;
    }
}
