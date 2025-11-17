<?php

namespace Havun\Core\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Havun\Core\Events\InvoiceSyncCompleted;

class InvoiceSyncService
{
    private Client $client;
    private MemorialReferenceService $memorialService;

    public function __construct(
        string $apiUrl,
        string $apiToken,
        MemorialReferenceService $memorialService = null
    ) {
        $this->client = new Client([
            'base_uri' => $apiUrl,
            'headers' => [
                'Authorization' => "Bearer {$apiToken}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $this->memorialService = $memorialService ?? new MemorialReferenceService();
    }

    /**
     * Prepare invoice data from monument and payment
     */
    public function prepareInvoiceData($monument, $payment): array
    {
        return [
            'memorial_reference' => $monument->memorial_reference,
            'customer' => [
                'name' => $monument->customer_name,
                'email' => $monument->customer_email,
                'phone' => $monument->customer_phone ?? null,
                'address' => [
                    'street' => $monument->customer_street ?? null,
                    'city' => $monument->customer_city ?? null,
                    'postal_code' => $monument->customer_postal_code ?? null,
                    'country' => $monument->customer_country ?? 'NL',
                ],
            ],
            'invoice' => [
                'number' => $monument->invoice_number,
                'date' => $monument->created_at->format('Y-m-d'),
                'due_date' => $monument->created_at->addDays(14)->format('Y-m-d'),
                'amount' => (float) $payment->amount,
                'vat_amount' => (float) $payment->amount * 0.21,
                'total_amount' => (float) $payment->amount * 1.21,
                'description' => "Monument: {$monument->name}",
                'lines' => [
                    [
                        'description' => "Digitaal monument - {$monument->name}",
                        'quantity' => 1,
                        'unit_price' => (float) $payment->amount,
                        'vat_rate' => 21,
                        'total' => (float) $payment->amount,
                    ],
                ],
            ],
            'payment' => [
                'mollie_payment_id' => $payment->mollie_id,
                'status' => $payment->status,
                'method' => $payment->method ?? 'ideal',
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
            'metadata' => [
                'monument_id' => $monument->id,
                'monument_name' => $monument->name,
                'source' => 'herdenkingsportaal',
                'synced_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Send invoice to HavunAdmin API
     *
     * Automatically fires InvoiceSyncCompleted event for MCP monitoring
     */
    public function sendToHavunAdmin(array $invoiceData): InvoiceSyncResponse
    {
        $memorialReference = $invoiceData['memorial_reference'] ?? 'unknown';

        try {
            $response = $this->client->post('/api/invoices/sync', [
                'json' => $invoiceData,
            ]);

            $data = json_decode($response->getBody(), true);

            $syncResponse = new InvoiceSyncResponse(
                success: true,
                invoiceId: $data['invoice_id'] ?? null,
                memorialReference: $data['memorial_reference'] ?? null,
                message: 'Invoice synced successfully'
            );

            // Fire event for MCP monitoring
            event(new InvoiceSyncCompleted(
                memorialReference: $memorialReference,
                success: true,
                invoiceId: $syncResponse->invoiceId,
                metadata: [
                    'invoice_number' => $invoiceData['invoice']['number'] ?? null,
                    'amount' => $invoiceData['invoice']['total_amount'] ?? null,
                    'customer' => $invoiceData['customer']['name'] ?? null,
                ]
            ));

            return $syncResponse;

        } catch (RequestException $e) {
            $error = $e->getMessage();

            if ($e->hasResponse()) {
                $body = json_decode($e->getResponse()->getBody(), true);
                $error = $body['error'] ?? $error;
            }

            // Fire event for MCP monitoring (failure)
            event(new InvoiceSyncCompleted(
                memorialReference: $memorialReference,
                success: false,
                error: $error,
                metadata: [
                    'invoice_number' => $invoiceData['invoice']['number'] ?? null,
                ]
            ));

            return new InvoiceSyncResponse(
                success: false,
                error: $error
            );
        }
    }

    /**
     * Get invoice status from HavunAdmin
     */
    public function getInvoiceStatus(string $memorialReference): ?array
    {
        try {
            $response = $this->client->get("/api/invoices/{$memorialReference}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * Sync status from HavunAdmin back to Herdenkingsportaal
     */
    public function syncStatusFromHavunAdmin($monument): void
    {
        $invoiceData = $this->getInvoiceStatus($monument->memorial_reference);

        if (!$invoiceData) {
            return;
        }

        // Update monument based on HavunAdmin status
        if ($invoiceData['status'] === 'refunded') {
            $monument->update(['status' => 'refunded']);
        }
    }
}
