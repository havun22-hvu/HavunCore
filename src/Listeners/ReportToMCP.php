<?php

namespace Havun\Core\Listeners;

use Havun\Core\Events\InvoiceSyncCompleted;
use Havun\Core\Events\HavunCoreDeployed;
use Havun\Core\Services\MCPService;
use Illuminate\Support\Facades\Log;

/**
 * Listener that automatically reports events to MCP
 *
 * This enables cross-project communication and monitoring
 */
class ReportToMCP
{
    private MCPService $mcp;

    public function __construct(MCPService $mcp)
    {
        $this->mcp = $mcp;
    }

    /**
     * Handle InvoiceSyncCompleted event
     */
    public function handleInvoiceSync(InvoiceSyncCompleted $event): void
    {
        $details = [
            'Invoice ID' => $event->invoiceId ?? 'N/A',
            'Success' => $event->success ? 'Yes' : 'No',
        ];

        if ($event->error) {
            $details['Error'] = $event->error;
        }

        if (!empty($event->metadata)) {
            foreach ($event->metadata as $key => $value) {
                $details[$key] = $value;
            }
        }

        $this->mcp->reportInvoiceSync(
            $event->memorialReference,
            $event->success,
            $details
        );

        Log::info('[MCP] Invoice sync reported', [
            'memorial_reference' => $event->memorialReference,
            'success' => $event->success,
        ]);
    }

    /**
     * Handle HavunCoreDeployed event
     */
    public function handleDeployment(HavunCoreDeployed $event): void
    {
        // Notify all projects
        $this->mcp->notifyDeployment(
            $event->version,
            $event->changes
        );

        // If breaking changes, send urgent notification
        if ($event->breakingChanges && !empty($event->requiredActions)) {
            $this->mcp->reportBreakingChange(
                "HavunCore {$event->version}",
                "HavunCore has been updated with breaking changes.",
                $event->requiredActions
            );
        }

        Log::info('[MCP] Deployment reported', [
            'version' => $event->version,
            'breaking' => $event->breakingChanges,
        ]);
    }

    /**
     * Register event listeners
     */
    public function subscribe($events): array
    {
        return [
            InvoiceSyncCompleted::class => 'handleInvoiceSync',
            HavunCoreDeployed::class => 'handleDeployment',
        ];
    }
}
