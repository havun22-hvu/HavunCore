<?php

namespace Havun\Core\Services;

/**
 * Response object for InvoiceSyncService operations
 */
class InvoiceSyncResponse
{
    public function __construct(
        public bool $success,
        public ?int $invoiceId = null,
        public ?string $memorialReference = null,
        public ?string $message = null,
        public ?string $error = null
    ) {}

    /**
     * Check if the sync was successful
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if the sync failed
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Get error message if failed
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'invoice_id' => $this->invoiceId,
            'memorial_reference' => $this->memorialReference,
            'message' => $this->message,
            'error' => $this->error,
        ];
    }
}
