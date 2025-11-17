<?php

namespace Havun\Core\Services;

use Illuminate\Support\Facades\File;

/**
 * OpenAPI/Swagger Generator
 *
 * Generates OpenAPI 3.0 specifications from API contracts.
 * This is the PROFESSIONAL standard used by Google, Stripe, Twilio, etc.
 *
 * Benefits:
 * - Single source of truth for API documentation
 * - Auto-generate client libraries
 * - Interactive API explorer (Swagger UI)
 * - Validation tools
 * - CI/CD integration
 */
class OpenAPIGenerator
{
    private string $apiTitle;
    private string $apiVersion;
    private string $serverUrl;

    public function __construct(
        string $apiTitle = 'Havun API',
        string $apiVersion = '1.0.0',
        string $serverUrl = 'https://api.havun.nl'
    ) {
        $this->apiTitle = $apiTitle;
        $this->apiVersion = $apiVersion;
        $this->serverUrl = $serverUrl;
    }

    /**
     * Generate OpenAPI spec from API contract
     *
     * @param string $endpointId Endpoint identifier
     * @param array $contract API contract
     * @return array OpenAPI specification
     */
    public function generateFromContract(string $endpointId, array $contract): array
    {
        $spec = $this->createBaseSpec();

        // Add endpoint
        $method = $this->extractMethod($contract['endpoint'] ?? 'POST');
        $path = $this->extractPath($contract['endpoint'] ?? '/api/unknown');

        $spec['paths'][$path] = [
            strtolower($method) => $this->generateOperation($endpointId, $contract),
        ];

        // Add schemas
        $spec['components']['schemas'] = $this->generateSchemas($contract);

        return $spec;
    }

    /**
     * Generate complete OpenAPI spec for multiple endpoints
     *
     * @param array $contracts Array of contracts ['endpoint_id' => contract]
     * @return array Complete OpenAPI specification
     */
    public function generateMultiple(array $contracts): array
    {
        $spec = $this->createBaseSpec();

        foreach ($contracts as $endpointId => $contract) {
            $method = $this->extractMethod($contract['endpoint'] ?? 'POST');
            $path = $this->extractPath($contract['endpoint'] ?? '/api/unknown');

            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            $spec['paths'][$path][strtolower($method)] = $this->generateOperation($endpointId, $contract);

            // Merge schemas
            $schemas = $this->generateSchemas($contract);
            foreach ($schemas as $schemaName => $schema) {
                $spec['components']['schemas'][$schemaName] = $schema;
            }
        }

        return $spec;
    }

    /**
     * Save OpenAPI spec to file (YAML format)
     *
     * @param array $spec OpenAPI specification
     * @param string $filepath File path
     * @return bool Success
     */
    public function saveToFile(array $spec, string $filepath): bool
    {
        $yaml = $this->arrayToYaml($spec);
        return File::put($filepath, $yaml) !== false;
    }

    /**
     * Create base OpenAPI 3.0 structure
     */
    private function createBaseSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->apiTitle,
                'description' => 'API documentation generated from HavunCore contracts',
                'version' => $this->apiVersion,
                'contact' => [
                    'name' => 'Havun Development Team',
                ],
            ],
            'servers' => [
                [
                    'url' => $this->serverUrl,
                    'description' => 'Production server',
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Enter your API token',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
    }

    /**
     * Generate operation (endpoint) specification
     */
    private function generateOperation(string $endpointId, array $contract): array
    {
        $operation = [
            'operationId' => $endpointId,
            'summary' => $contract['summary'] ?? ucfirst(str_replace('_', ' ', $endpointId)),
            'description' => $contract['description'] ?? '',
            'tags' => $contract['tags'] ?? ['API'],
        ];

        $method = $this->extractMethod($contract['endpoint'] ?? 'POST');

        // Add request body for POST/PUT/PATCH
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $this->getSchemaName($endpointId, 'Request'),
                        ],
                    ],
                ],
            ];
        }

        // Add responses
        $operation['responses'] = [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $this->getSchemaName($endpointId, 'Response'),
                        ],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Bad request - validation failed',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorResponse',
                        ],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Unauthorized - invalid API token',
            ],
            '422' => [
                'description' => 'Unprocessable entity - invalid data',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ValidationErrorResponse',
                        ],
                    ],
                ],
            ],
        ];

        return $operation;
    }

    /**
     * Generate JSON Schema from contract
     */
    private function generateSchemas(array $contract): array
    {
        $schemas = [];

        // Request schema
        $requestSchema = [
            'type' => 'object',
            'required' => $this->getTopLevelRequired($contract['required_fields'] ?? []),
            'properties' => $this->generateProperties($contract),
        ];

        $schemas[$this->getSchemaName('unknown', 'Request')] = $requestSchema;

        // Response schema (generic for now)
        $schemas[$this->getSchemaName('unknown', 'Response')] = [
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'example' => true,
                ],
                'data' => [
                    'type' => 'object',
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'Operation completed successfully',
                ],
            ],
        ];

        // Error schemas (standard)
        $schemas['ErrorResponse'] = [
            'type' => 'object',
            'properties' => [
                'error' => [
                    'type' => 'string',
                    'example' => 'Invalid request',
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'The request could not be processed',
                ],
            ],
        ];

        $schemas['ValidationErrorResponse'] = [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'example' => 'The given data was invalid.',
                ],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                    'example' => [
                        'email' => ['The email field is required.'],
                    ],
                ],
            ],
        ];

        return $schemas;
    }

    /**
     * Generate properties from contract fields
     */
    private function generateProperties(array $contract): array
    {
        $properties = [];
        $allFields = array_merge(
            $contract['required_fields'] ?? [],
            $contract['optional_fields'] ?? []
        );

        foreach ($allFields as $field) {
            $fieldPath = explode('.', $field);
            $this->addNestedProperty($properties, $fieldPath, $contract);
        }

        return $properties;
    }

    /**
     * Add nested property to schema
     */
    private function addNestedProperty(array &$properties, array $fieldPath, array $contract): void
    {
        $field = implode('.', $fieldPath);
        $fieldName = array_shift($fieldPath);

        if (empty($fieldPath)) {
            // Leaf node
            $type = $this->getFieldType($field, $contract);
            $properties[$fieldName] = [
                'type' => $type,
            ];

            // Add example if available
            if (isset($contract['examples'][$field])) {
                $properties[$fieldName]['example'] = $contract['examples'][$field];
            }

            // Add description if available
            if (isset($contract['field_descriptions'][$field])) {
                $properties[$fieldName]['description'] = $contract['field_descriptions'][$field];
            }

            // Add deprecated flag
            if (in_array($field, $contract['deprecated_fields'] ?? [])) {
                $properties[$fieldName]['deprecated'] = true;
            }
        } else {
            // Nested object
            if (!isset($properties[$fieldName])) {
                $properties[$fieldName] = [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            $this->addNestedProperty($properties[$fieldName]['properties'], $fieldPath, $contract);
        }
    }

    /**
     * Get OpenAPI type from contract field type
     */
    private function getFieldType(string $field, array $contract): string
    {
        $contractType = $contract['field_types'][$field] ?? 'string';

        return match($contractType) {
            'integer', 'int' => 'integer',
            'float', 'double' => 'number',
            'boolean', 'bool' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    /**
     * Get top-level required fields (without nested paths)
     */
    private function getTopLevelRequired(array $requiredFields): array
    {
        $topLevel = [];
        foreach ($requiredFields as $field) {
            $parts = explode('.', $field);
            $topLevel[] = $parts[0];
        }
        return array_unique($topLevel);
    }

    /**
     * Extract HTTP method from endpoint string
     */
    private function extractMethod(string $endpoint): string
    {
        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+/', $endpoint, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'POST'; // Default
    }

    /**
     * Extract path from endpoint string
     */
    private function extractPath(string $endpoint): string
    {
        if (preg_match('/^(?:GET|POST|PUT|PATCH|DELETE)\s+(.+)$/', $endpoint, $matches)) {
            return $matches[1];
        }
        return $endpoint;
    }

    /**
     * Get schema name for endpoint
     */
    private function getSchemaName(string $endpointId, string $suffix = ''): string
    {
        $name = str_replace('_', '', ucwords($endpointId, '_'));
        return $name . $suffix;
    }

    /**
     * Convert array to YAML (simple implementation)
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if indexed array
                if (array_keys($value) === range(0, count($value) - 1)) {
                    $yaml .= $prefix . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $prefix . "  -\n";
                            $yaml .= $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $prefix . "  - " . $this->formatYamlValue($item) . "\n";
                        }
                    }
                } else {
                    // Associative array
                    $yaml .= $prefix . $key . ":\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $prefix . $key . ': ' . $this->formatYamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format value for YAML
     */
    private function formatYamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        // Quote strings with special characters
        if (preg_match('/[:\{\}\[\],&*#\?|\-<>=!%@`"]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }
}
