<?php

namespace Havun\Core\Traits;

use Havun\Core\Services\APIContractRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Trait for validating API payloads against registered contracts
 *
 * Usage in Controller (Client side - Herdenkingsportaal):
 *
 *   use ValidatesAPIContract;
 *
 *   public function syncInvoice($memorial, $payment)
 *   {
 *       $payload = $this->preparePayload($memorial, $payment);
 *
 *       // Validate before sending
 *       $validation = $this->validateContract('invoice_sync', $payload);
 *
 *       if (!$validation['valid']) {
 *           Log::error('Invalid API payload', $validation['errors']);
 *           throw new \Exception('Payload does not match API contract');
 *       }
 *
 *       // Safe to send
 *       return $this->send($payload);
 *   }
 */
trait ValidatesAPIContract
{
    /**
     * Validate payload against API contract
     *
     * @param string $endpointId Contract identifier
     * @param array $payload Data to validate
     * @param bool $throwOnError Throw exception if validation fails
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    protected function validateContract(string $endpointId, array $payload, bool $throwOnError = false): array
    {
        $registry = app(APIContractRegistry::class);
        $result = $registry->validatePayload($endpointId, $payload);

        // Log warnings
        if (!empty($result['warnings'])) {
            Log::warning("[API Contract] Validation warnings for {$endpointId}", [
                'warnings' => $result['warnings'],
            ]);
        }

        // Log errors
        if (!$result['valid']) {
            Log::error("[API Contract] Validation failed for {$endpointId}", [
                'errors' => $result['errors'],
                'payload' => $payload,
            ]);

            if ($throwOnError) {
                throw new \RuntimeException(
                    "API payload validation failed for {$endpointId}: " .
                    implode(', ', $result['errors'])
                );
            }
        } else {
            Log::debug("[API Contract] Validation passed for {$endpointId}");
        }

        return $result;
    }

    /**
     * Assert that payload is valid (throws exception if not)
     */
    protected function assertValidContract(string $endpointId, array $payload): void
    {
        $this->validateContract($endpointId, $payload, throwOnError: true);
    }
}
