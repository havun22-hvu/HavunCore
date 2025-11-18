<?php

namespace Havun\Core\Services;

use Exception;

/**
 * Snippet Library - Reusable Code Templates
 *
 * Centralized library of reusable code snippets that can be attached to
 * orchestrated tasks and copied to any project.
 *
 * Directory structure:
 *   storage/snippets/
 *     ├── payments/
 *     │   ├── mollie-payment-controller.php
 *     │   ├── mollie-webhook-handler.php
 *     │   └── bunq-payment-flow.php
 *     ├── invoices/
 *     │   ├── invoice-generator.php
 *     │   └── invoice-pdf-template.blade.php
 *     ├── api/
 *     │   ├── rest-controller-template.php
 *     │   └── api-response-formatter.php
 *     └── utilities/
 *         ├── memorial-reference-validator.php
 *         └── email-sender.php
 *
 * Usage:
 *   php artisan havun:snippet:add payments/mollie-setup
 *   php artisan havun:snippet:get payments/mollie-setup
 *   php artisan havun:snippet:list
 */
class SnippetLibrary
{
    private string $snippetsPath;

    public function __construct()
    {
        $this->snippetsPath = storage_path('snippets');

        // Ensure snippets directory exists
        if (!is_dir($this->snippetsPath)) {
            mkdir($this->snippetsPath, 0755, true);
        }
    }

    /**
     * Store a code snippet
     *
     * @param string $path Snippet path (e.g., 'payments/mollie-setup')
     * @param string $code The actual code
     * @param array $metadata Optional metadata (description, language, tags, dependencies)
     * @return bool Success
     */
    public function add(string $path, string $code, array $metadata = []): bool
    {
        try {
            $fullPath = $this->getFullPath($path);

            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Save code file
            file_put_contents($fullPath, $code);

            // Save metadata if provided
            if (!empty($metadata)) {
                $metadataPath = $fullPath . '.meta.json';
                file_put_contents(
                    $metadataPath,
                    json_encode(array_merge([
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ], $metadata), JSON_PRETTY_PRINT)
                );
            }

            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to add snippet '{$path}': " . $e->getMessage());
        }
    }

    /**
     * Retrieve a code snippet
     *
     * @param string $path Snippet path
     * @return array ['code' => string, 'metadata' => array] or null if not found
     */
    public function get(string $path): ?array
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $code = file_get_contents($fullPath);
        $metadata = [];

        $metadataPath = $fullPath . '.meta.json';
        if (file_exists($metadataPath)) {
            $metadata = json_decode(file_get_contents($metadataPath), true) ?? [];
        }

        return [
            'code' => $code,
            'metadata' => $metadata,
        ];
    }

    /**
     * Check if a snippet exists
     */
    public function has(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * Delete a snippet
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        unlink($fullPath);

        // Delete metadata if exists
        $metadataPath = $fullPath . '.meta.json';
        if (file_exists($metadataPath)) {
            unlink($metadataPath);
        }

        return true;
    }

    /**
     * List all snippets
     *
     * @param string|null $category Filter by category (e.g., 'payments', 'invoices')
     * @return array Snippet paths with metadata
     */
    public function list(?string $category = null): array
    {
        $searchPath = $category ? $this->snippetsPath . '/' . $category : $this->snippetsPath;

        if (!is_dir($searchPath)) {
            return [];
        }

        $snippets = [];
        $this->scanDirectory($searchPath, $snippets);

        return $snippets;
    }

    /**
     * Get all categories
     */
    public function categories(): array
    {
        $categories = [];

        if (!is_dir($this->snippetsPath)) {
            return [];
        }

        $dirs = scandir($this->snippetsPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullPath = $this->snippetsPath . '/' . $dir;
            if (is_dir($fullPath)) {
                $categories[] = $dir;
            }
        }

        return $categories;
    }

    /**
     * Initialize snippet library with default templates
     */
    public function initialize(): bool
    {
        $defaultSnippets = $this->getDefaultSnippets();

        foreach ($defaultSnippets as $path => $data) {
            if (!$this->has($path)) {
                $this->add($path, $data['code'], $data['metadata']);
            }
        }

        return true;
    }

    /**
     * Export snippet for use in orchestrated tasks
     */
    public function export(string $path): ?array
    {
        $snippet = $this->get($path);

        if (!$snippet) {
            return null;
        }

        return [
            'path' => $path,
            'code' => $snippet['code'],
            'metadata' => $snippet['metadata'],
            'usage' => $snippet['metadata']['usage'] ?? 'Copy this code to your project',
        ];
    }

    /**
     * Search snippets by tag
     */
    public function searchByTag(string $tag): array
    {
        $allSnippets = $this->list();
        $results = [];

        foreach ($allSnippets as $path => $metadata) {
            $tags = $metadata['tags'] ?? [];
            if (in_array($tag, $tags)) {
                $results[$path] = $metadata;
            }
        }

        return $results;
    }

    /**
     * Get full file system path for a snippet
     */
    private function getFullPath(string $path): string
    {
        // Remove any directory traversal attempts
        $path = str_replace(['..', '\\'], ['', '/'], $path);

        return $this->snippetsPath . '/' . $path;
    }

    /**
     * Recursively scan directory for snippets
     */
    private function scanDirectory(string $dir, array &$snippets, string $prefix = ''): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            $relativePath = $prefix ? $prefix . '/' . $file : $file;

            if (is_dir($fullPath)) {
                $this->scanDirectory($fullPath, $snippets, $relativePath);
            } elseif (!str_ends_with($file, '.meta.json')) {
                $metadata = [];
                $metadataPath = $fullPath . '.meta.json';

                if (file_exists($metadataPath)) {
                    $metadata = json_decode(file_get_contents($metadataPath), true) ?? [];
                }

                $snippets[$relativePath] = $metadata;
            }
        }
    }

    /**
     * Get default snippets to initialize library
     */
    private function getDefaultSnippets(): array
    {
        return [
            'payments/mollie-payment-setup.php' => [
                'code' => $this->getMollieSetupSnippet(),
                'metadata' => [
                    'description' => 'Complete Mollie payment setup with webhook handling',
                    'language' => 'php',
                    'tags' => ['mollie', 'payment', 'webhook'],
                    'dependencies' => ['mollie/mollie-api-php'],
                    'usage' => 'Copy to your controller and configure webhook URL',
                ],
            ],
            'api/rest-response-formatter.php' => [
                'code' => $this->getRestResponseSnippet(),
                'metadata' => [
                    'description' => 'Standardized REST API response formatter',
                    'language' => 'php',
                    'tags' => ['api', 'rest', 'response'],
                    'usage' => 'Use in all API controllers for consistent responses',
                ],
            ],
            'utilities/memorial-reference-service.php' => [
                'code' => $this->getMemorialReferenceSnippet(),
                'metadata' => [
                    'description' => 'Memorial reference generation and validation',
                    'language' => 'php',
                    'tags' => ['memorial', 'validation', 'reference'],
                    'usage' => 'Use Havun\Core\Services\MemorialReferenceService instead',
                ],
            ],
        ];
    }

    private function getMollieSetupSnippet(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Controllers;

use Mollie\Laravel\Facades\Mollie;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $payment = Mollie::api()->payments->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => '24.14',
            ],
            'description' => 'Memorial Payment',
            'redirectUrl' => route('payment.success'),
            'webhookUrl' => route('payment.webhook'),
            'metadata' => [
                'memorial_reference' => $request->memorial_reference,
                'order_id' => $request->order_id,
            ],
        ]);

        return redirect($payment->getCheckoutUrl());
    }

    public function webhook(Request $request)
    {
        $paymentId = $request->input('id');
        $payment = Mollie::api()->payments->get($paymentId);

        if ($payment->isPaid() && !$payment->hasRefunds()) {
            // Payment successful
            $metadata = $payment->metadata;
            // Process order...
        } elseif ($payment->isFailed()) {
            // Payment failed
        }

        return response('', 200);
    }
}
PHP;
    }

    private function getRestResponseSnippet(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Responses;

trait RestResponse
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function created($data = null, string $message = 'Created')
    {
        return $this->success($data, $message, 201);
    }

    protected function notFound(string $message = 'Not found')
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }

    protected function validationError($errors, string $message = 'Validation failed')
    {
        return $this->error($message, 422, $errors);
    }
}
PHP;
    }

    private function getMemorialReferenceSnippet(): string
    {
        return <<<'PHP'
<?php

// This snippet shows how to use the MemorialReferenceService from HavunCore
// Instead of copying this code, use: composer require havun/core

use Havun\Core\Services\MemorialReferenceService;

class YourController
{
    private MemorialReferenceService $memorialService;

    public function __construct(MemorialReferenceService $memorialService)
    {
        $this->memorialService = $memorialService;
    }

    public function createMemorial()
    {
        // Generate new reference
        $reference = $this->memorialService->generate();
        // Example: "550e8400e29b"

        // Validate reference
        $isValid = $this->memorialService->validate($reference);

        // Format for display
        $formatted = $this->memorialService->format($reference);
        // Example: "550E-8400-E29B"

        // Extract metadata
        $metadata = $this->memorialService->extractMetadata($reference);
        // ['timestamp' => ..., 'random' => ...]
    }
}
PHP;
    }
}
