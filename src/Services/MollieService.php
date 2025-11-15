<?php

namespace Havun\Core\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Mollie Payment Service
 *
 * Shared Mollie integration for Herdenkingsportaal and HavunAdmin
 */
class MollieService
{
    protected string $apiKey;
    protected Client $client;
    protected MemorialReferenceService $memorialService;

    public function __construct(string $apiKey, MemorialReferenceService $memorialService = null)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.mollie.com/v2/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
        $this->memorialService = $memorialService ?? new MemorialReferenceService();
    }

    /**
     * Create payment with memorial reference in metadata
     *
     * @param float $amount
     * @param string $description
     * @param string|null $memorialReference
     * @param string|null $redirectUrl
     * @param string|null $webhookUrl
     * @return array
     */
    public function createPayment(
        float $amount,
        string $description,
        ?string $memorialReference = null,
        ?string $redirectUrl = null,
        ?string $webhookUrl = null
    ): array {
        $data = [
            'amount' => [
                'currency' => 'EUR',
                'value' => number_format($amount, 2, '.', ''),
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => [],
        ];

        if ($memorialReference) {
            $data['metadata']['memorial_reference'] = $memorialReference;
        }

        try {
            $response = $this->client->post('payments', [
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Mollie payment creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get payment details
     *
     * @param string $paymentId
     * @return array
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = $this->client->get('payments/' . $paymentId);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Mollie payment retrieval failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract memorial reference from payment metadata
     *
     * @param array $payment
     * @return string|null
     */
    public function extractMemorialReference(array $payment): ?string
    {
        return $payment['metadata']['memorial_reference'] ?? null;
    }

    /**
     * List recent payments
     *
     * @param int $limit
     * @return array
     */
    public function listPayments(int $limit = 50): array
    {
        try {
            $response = $this->client->get('payments', [
                'query' => ['limit' => $limit],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['_embedded']['payments'] ?? [];
        } catch (RequestException $e) {
            throw new \Exception('Mollie payment listing failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if payment is paid
     *
     * @param array $payment
     * @return bool
     */
    public function isPaid(array $payment): bool
    {
        return ($payment['status'] ?? '') === 'paid';
    }
}
