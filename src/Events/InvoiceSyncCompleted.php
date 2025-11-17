<?php

namespace Havun\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an invoice sync completes (success or failure)
 *
 * This event automatically reports to MCP for monitoring across projects
 */
class InvoiceSyncCompleted
{
    use Dispatchable, SerializesModels;

    public string $memorialReference;
    public bool $success;
    public ?int $invoiceId;
    public ?string $error;
    public array $metadata;

    /**
     * @param string $memorialReference Memorial reference
     * @param bool $success Whether sync succeeded
     * @param int|null $invoiceId Created invoice ID (if success)
     * @param string|null $error Error message (if failed)
     * @param array $metadata Additional metadata
     */
    public function __construct(
        string $memorialReference,
        bool $success,
        ?int $invoiceId = null,
        ?string $error = null,
        array $metadata = []
    ) {
        $this->memorialReference = $memorialReference;
        $this->success = $success;
        $this->invoiceId = $invoiceId;
        $this->error = $error;
        $this->metadata = $metadata;
    }
}
