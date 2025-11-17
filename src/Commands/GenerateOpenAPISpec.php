<?php

namespace Havun\Core\Commands;

use Havun\Core\Services\OpenAPIGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate OpenAPI/Swagger specification from API contracts
 *
 * This creates industry-standard API documentation that can be used with:
 * - Swagger UI (interactive API explorer)
 * - Postman (import collections)
 * - API client generators (PHP, JavaScript, etc.)
 * - CI/CD validation tools
 *
 * Usage:
 *   php artisan havun:openapi:generate
 *   php artisan havun:openapi:generate --output=public/openapi.yaml
 */
class GenerateOpenAPISpec extends Command
{
    protected $signature = 'havun:openapi:generate
                            {--output=storage/api/openapi.yaml : Output file path}
                            {--title=Havun API : API title}
                            {--version=1.0.0 : API version}
                            {--server=https://api.havun.nl : Server URL}';

    protected $description = 'Generate OpenAPI/Swagger specification from API contracts';

    public function handle(): int
    {
        $this->info('🔨 Generating OpenAPI specification...');
        $this->newLine();

        // Get contracts (in real implementation, load from config or database)
        $contracts = $this->getContracts();

        if (empty($contracts)) {
            $this->warn('⚠️  No API contracts found!');
            $this->line('');
            $this->line('Define contracts in config/api_contracts.php or register them via APIContractRegistry');
            return self::FAILURE;
        }

        $this->line("Found " . count($contracts) . " API contract(s):");
        foreach (array_keys($contracts) as $endpointId) {
            $this->line("  - {$endpointId}");
        }
        $this->newLine();

        // Create generator
        $generator = new OpenAPIGenerator(
            apiTitle: $this->option('title'),
            apiVersion: $this->option('version'),
            serverUrl: $this->option('server')
        );

        // Generate spec
        $spec = $generator->generateMultiple($contracts);

        // Save to file
        $outputPath = base_path($this->option('output'));
        $outputDir = dirname($outputPath);

        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $success = $generator->saveToFile($spec, $outputPath);

        if ($success) {
            $this->info('✅ OpenAPI specification generated successfully!');
            $this->newLine();
            $this->line("📄 File: {$outputPath}");
            $this->line("📏 Size: " . $this->formatBytes(File::size($outputPath)));
            $this->newLine();

            // Show next steps
            $this->showNextSteps($outputPath);

            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to generate OpenAPI specification');
            return self::FAILURE;
        }
    }

    /**
     * Get API contracts
     *
     * In production, this would load from:
     * - config/api_contracts.php
     * - Database (stored contracts)
     * - MCP registry
     */
    private function getContracts(): array
    {
        // Check if config file exists
        if (File::exists(config_path('api_contracts.php'))) {
            return config('api_contracts', []);
        }

        // Return example contracts
        return [
            'invoice_sync' => [
                'version' => '2.0',
                'endpoint' => 'POST /api/invoices/sync',
                'summary' => 'Sync invoice from Herdenkingsportaal to HavunAdmin',
                'description' => 'Creates or updates an invoice in HavunAdmin based on memorial payment data from Herdenkingsportaal.',
                'tags' => ['Invoices', 'Sync'],

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
                    'customer.address.street',
                    'customer.address.city',
                    'customer.address.postal_code',
                    'customer.address.country',
                    'invoice.due_date',
                    'invoice.description',
                    'payment.method',
                    'payment.paid_at',
                    'metadata',
                ],

                'field_types' => [
                    'memorial_reference' => 'string',
                    'customer' => 'object',
                    'customer.name' => 'string',
                    'customer.email' => 'string',
                    'customer.phone' => 'string',
                    'invoice' => 'object',
                    'invoice.number' => 'string',
                    'invoice.amount' => 'float',
                    'invoice.vat_amount' => 'float',
                    'invoice.total_amount' => 'float',
                    'invoice.due_date' => 'string',
                    'invoice.description' => 'string',
                    'payment' => 'object',
                    'payment.mollie_payment_id' => 'string',
                    'payment.status' => 'string',
                    'payment.method' => 'string',
                    'payment.paid_at' => 'string',
                ],

                'field_descriptions' => [
                    'memorial_reference' => 'Unique memorial reference (12 lowercase hex characters)',
                    'customer.name' => 'Full name of the customer',
                    'customer.email' => 'Customer email address',
                    'invoice.number' => 'Invoice number (format: INV-YYYY-NNNNN)',
                    'invoice.amount' => 'Invoice amount excluding VAT',
                    'invoice.vat_amount' => 'VAT amount (typically 21%)',
                    'invoice.total_amount' => 'Total amount including VAT',
                    'payment.mollie_payment_id' => 'Mollie payment transaction ID',
                    'payment.status' => 'Payment status (paid, pending, failed, refunded)',
                ],

                'examples' => [
                    'memorial_reference' => '550e8400e29b',
                    'customer.name' => 'Jan Jansen',
                    'customer.email' => 'jan@example.com',
                    'invoice.number' => 'INV-2025-00001',
                    'invoice.amount' => 19.95,
                    'invoice.vat_amount' => 4.19,
                    'invoice.total_amount' => 24.14,
                    'payment.mollie_payment_id' => 'tr_WDqYK6vllg',
                    'payment.status' => 'paid',
                ],

                'deprecated_fields' => [],
            ],
        ];
    }

    /**
     * Show next steps after generation
     */
    private function showNextSteps(string $filepath): void
    {
        $this->info('📋 Next Steps:');
        $this->newLine();

        $this->line('1️⃣  View the specification:');
        $this->line("   cat {$filepath}");
        $this->newLine();

        $this->line('2️⃣  Validate the spec:');
        $this->line('   npx @stoplight/spectral-cli lint ' . $filepath);
        $this->newLine();

        $this->line('3️⃣  View in Swagger UI:');
        $this->line('   Copy file to public/openapi.yaml');
        $this->line('   Visit: https://editor.swagger.io/');
        $this->line('   Or serve locally with Swagger UI');
        $this->newLine();

        $this->line('4️⃣  Generate client code:');
        $this->line('   npx @openapitools/openapi-generator-cli generate \\');
        $this->line('     -i ' . $filepath . ' \\');
        $this->line('     -g php \\');
        $this->line('     -o ./generated-client');
        $this->newLine();

        $this->line('5️⃣  Add to CI/CD:');
        $this->line('   See: HavunCore/.github/workflows/api-contract-check.yml');
        $this->newLine();
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }
}
