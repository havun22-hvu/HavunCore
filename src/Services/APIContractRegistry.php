<?php

namespace Havun\Core\Services;

use Illuminate\Support\Facades\Log;

/**
 * API Contract Registry
 *
 * Registers and validates API contracts between Havun projects.
 * Ensures both sides of an API (client and server) stay in sync.
 *
 * Example:
 *   Server side (HavunAdmin) registers what it expects:
 *     APIContractRegistry::registerEndpoint('invoice_sync', $contract)
 *
 *   Client side (Herdenkingsportaal) validates before sending:
 *     APIContractRegistry::validatePayload('invoice_sync', $data)
 *
 * MCP is used to share contracts between projects.
 */
class APIContractRegistry
{
    private MCPService $mcp;
    private string $projectName;

    public function __construct(MCPService $mcp, string $projectName)
    {
        $this->mcp = $mcp;
        $this->projectName = $projectName;
    }

    /**
     * Register an API endpoint contract
     *
     * Call this on the SERVER side (e.g., HavunAdmin)
     *
     * @param string $endpointId Unique identifier (e.g., 'invoice_sync')
     * @param array $contract Contract specification
     * @return bool
     */
    public function registerEndpoint(string $endpointId, array $contract): bool
    {
        $content = $this->formatContractMessage($endpointId, $contract);

        // Store in MCP for ALL projects to see
        $success = $this->mcp->storeMessage('HavunCore', $content, [
            'api-contract',
            'endpoint',
            $endpointId,
            'provider:' . $this->projectName
        ]);

        if ($success) {
            Log::info("[API Contract] Registered endpoint: {$endpointId}", [
                'provider' => $this->projectName,
                'version' => $contract['version'] ?? '1.0',
            ]);
        }

        return $success;
    }

    /**
     * Validate payload against registered contract
     *
     * Call this on the CLIENT side (e.g., Herdenkingsportaal) before sending API request
     *
     * @param string $endpointId Endpoint identifier
     * @param array $payload Data to send
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function validatePayload(string $endpointId, array $payload): array
    {
        // In a real implementation, this would fetch the contract from MCP
        // For now, we'll use hardcoded contracts (can be loaded from config)
        $contract = $this->getContract($endpointId);

        if (!$contract) {
            return [
                'valid' => false,
                'errors' => ["Contract not found for endpoint: {$endpointId}"],
                'warnings' => [],
            ];
        }

        return $this->validateAgainstContract($payload, $contract);
    }

    /**
     * Validate that payload matches contract
     */
    private function validateAgainstContract(array $payload, array $contract): array
    {
        $errors = [];
        $warnings = [];

        // Check required fields
        foreach ($contract['required_fields'] ?? [] as $field) {
            if (!$this->hasField($payload, $field)) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Check field types
        foreach ($contract['field_types'] ?? [] as $field => $expectedType) {
            if ($this->hasField($payload, $field)) {
                $actualValue = $this->getField($payload, $field);
                if (!$this->isCorrectType($actualValue, $expectedType)) {
                    $errors[] = "Field '{$field}' has wrong type. Expected: {$expectedType}, got: " . gettype($actualValue);
                }
            }
        }

        // Check deprecated fields
        foreach ($contract['deprecated_fields'] ?? [] as $field) {
            if ($this->hasField($payload, $field)) {
                $warnings[] = "Field '{$field}' is deprecated and will be removed in future versions";
            }
        }

        // Check for unknown fields (if strict mode)
        if ($contract['strict_mode'] ?? false) {
            $allowedFields = array_merge(
                $contract['required_fields'] ?? [],
                $contract['optional_fields'] ?? []
            );

            foreach ($this->getAllFields($payload) as $field) {
                if (!in_array($field, $allowedFields)) {
                    $warnings[] = "Unknown field: {$field}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get contract for endpoint
     *
     * In production, this would fetch from MCP or cache
     * For now, returns hardcoded contracts
     */
    private function getContract(string $endpointId): ?array
    {
        // These contracts would be stored in MCP and fetched dynamically
        $contracts = [
            'invoice_sync' => [
                'version' => '2.0',
                'provider' => 'HavunAdmin',
                'endpoint' => 'POST /api/invoices/sync',
                'required_fields' => [
                    'memorial_reference',
                    'customer',
                    'customer.name',
                    'customer.email',
                    'invoice',
                    'invoice.number',
                    'invoice.amount',
                    'invoice.vat_amount',
                    'invoice.total_amount',
                    'payment',
                    'payment.mollie_payment_id',
                    'payment.status',
                ],
                'optional_fields' => [
                    'customer.phone',
                    'customer.address',
                    'invoice.due_date',
                    'invoice.description',
                    'payment.method',
                    'payment.paid_at',
                    'metadata',
                ],
                'field_types' => [
                    'memorial_reference' => 'string',
                    'customer' => 'array',
                    'customer.name' => 'string',
                    'customer.email' => 'string',
                    'invoice.amount' => 'float',
                    'invoice.vat_amount' => 'float',
                    'invoice.total_amount' => 'float',
                    'payment.status' => 'string',
                ],
                'deprecated_fields' => [
                    // Example: 'old_field_name'
                ],
                'strict_mode' => false,
            ],

            'vpd_update' => [
                'version' => '1.0',
                'provider' => 'VPDUpdate',
                'endpoint' => 'GET /api/vpd/data',
                'required_fields' => [
                    'version',  // ← Nieuwe required field sinds v1.0
                ],
                'optional_fields' => [
                    'filter',
                    'limit',
                ],
                'field_types' => [
                    'version' => 'string',
                    'limit' => 'integer',
                ],
                'deprecated_fields' => [],
                'strict_mode' => true,
            ],
        ];

        return $contracts[$endpointId] ?? null;
    }

    /**
     * Check if contract has changed (breaking change detection)
     */
    public function detectBreakingChanges(string $endpointId, array $oldContract, array $newContract): array
    {
        $breakingChanges = [];

        // New required fields = breaking change
        $oldRequired = $oldContract['required_fields'] ?? [];
        $newRequired = $newContract['required_fields'] ?? [];
        $addedRequired = array_diff($newRequired, $oldRequired);

        if (!empty($addedRequired)) {
            $breakingChanges[] = [
                'type' => 'new_required_field',
                'fields' => $addedRequired,
                'severity' => 'high',
                'message' => 'New required fields added: ' . implode(', ', $addedRequired),
            ];
        }

        // Removed fields = breaking change
        $allOldFields = array_merge($oldRequired, $oldContract['optional_fields'] ?? []);
        $allNewFields = array_merge($newRequired, $newContract['optional_fields'] ?? []);
        $removedFields = array_diff($allOldFields, $allNewFields);

        if (!empty($removedFields)) {
            $breakingChanges[] = [
                'type' => 'removed_field',
                'fields' => $removedFields,
                'severity' => 'high',
                'message' => 'Fields removed: ' . implode(', ', $removedFields),
            ];
        }

        // Changed field types = breaking change
        foreach ($newContract['field_types'] ?? [] as $field => $newType) {
            $oldType = $oldContract['field_types'][$field] ?? null;
            if ($oldType && $oldType !== $newType) {
                $breakingChanges[] = [
                    'type' => 'type_changed',
                    'field' => $field,
                    'old_type' => $oldType,
                    'new_type' => $newType,
                    'severity' => 'high',
                    'message' => "Field '{$field}' type changed from {$oldType} to {$newType}",
                ];
            }
        }

        return $breakingChanges;
    }

    /**
     * Report contract breaking changes to all consumers
     */
    public function reportBreakingChanges(string $endpointId, array $breakingChanges, array $consumers): bool
    {
        if (empty($breakingChanges)) {
            return true;
        }

        $content = "# 🚨 API BREAKING CHANGE: {$endpointId}\n\n";
        $content .= "**Provider:** {$this->projectName}\n";
        $content .= "**Date:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $content .= "## Breaking Changes\n\n";
        foreach ($breakingChanges as $change) {
            $content .= "### {$change['message']}\n";
            $content .= "- **Type:** {$change['type']}\n";
            $content .= "- **Severity:** {$change['severity']}\n\n";
        }

        $content .= "## ⚠️ Action Required\n\n";
        $content .= "Update your API client to match the new contract.\n";
        $content .= "See contract details in MCP (tag: api-contract, endpoint: {$endpointId})\n";

        foreach ($consumers as $consumer) {
            $this->mcp->storeMessage($consumer, $content, [
                'breaking-change',
                'api-contract',
                'urgent',
                'action-required',
                $endpointId
            ]);
        }

        return true;
    }

    /**
     * Format contract as MCP message
     */
    private function formatContractMessage(string $endpointId, array $contract): string
    {
        $content = "# 📋 API Contract: {$endpointId}\n\n";
        $content .= "**Provider:** {$this->projectName}\n";
        $content .= "**Version:** " . ($contract['version'] ?? '1.0') . "\n";
        $content .= "**Endpoint:** " . ($contract['endpoint'] ?? 'N/A') . "\n";
        $content .= "**Updated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $content .= "## Required Fields\n\n";
        foreach ($contract['required_fields'] ?? [] as $field) {
            $type = $contract['field_types'][$field] ?? 'any';
            $content .= "- `{$field}` ({$type})\n";
        }

        if (!empty($contract['optional_fields'])) {
            $content .= "\n## Optional Fields\n\n";
            foreach ($contract['optional_fields'] as $field) {
                $type = $contract['field_types'][$field] ?? 'any';
                $content .= "- `{$field}` ({$type})\n";
            }
        }

        if (!empty($contract['deprecated_fields'])) {
            $content .= "\n## ⚠️ Deprecated Fields\n\n";
            foreach ($contract['deprecated_fields'] as $field) {
                $content .= "- `{$field}` - Will be removed in future version\n";
            }
        }

        $content .= "\n## Contract Specification\n\n";
        $content .= "```json\n";
        $content .= json_encode($contract, JSON_PRETTY_PRINT);
        $content .= "\n```\n";

        return $content;
    }

    // Helper methods for nested field checking
    private function hasField(array $data, string $field): bool
    {
        if (strpos($field, '.') === false) {
            return isset($data[$field]);
        }

        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    private function getField(array $data, string $field)
    {
        if (strpos($field, '.') === false) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function getAllFields(array $data, string $prefix = ''): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $fieldName = $prefix ? "{$prefix}.{$key}" : $key;
            $fields[] = $fieldName;

            if (is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                // Nested associative array
                $fields = array_merge($fields, $this->getAllFields($value, $fieldName));
            }
        }

        return $fields;
    }

    private function isCorrectType($value, string $expectedType): bool
    {
        return match($expectedType) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value), // int is acceptable for float
            'boolean', 'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            default => true, // 'any' or unknown types
        };
    }
}
