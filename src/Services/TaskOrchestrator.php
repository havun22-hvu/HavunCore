<?php

namespace Havun\Core\Services;

use Exception;
use Illuminate\Support\Str;

/**
 * Task Orchestrator - Multi-Claude Task Delegation System
 *
 * Analyzes high-level user requests, splits them into specific tasks,
 * and delegates them to Claude instances in different projects via MCP.
 *
 * Usage:
 *   php artisan havun:orchestrate "Add installment payments"
 *
 * Flow:
 *   1. User gives high-level command
 *   2. Orchestrator analyzes and splits into tasks
 *   3. Tasks delegated via MCP to project-specific Claudes
 *   4. Monitor progress and verify integration
 */
class TaskOrchestrator
{
    private MCPService $mcp;
    private VaultService $vault;
    private SnippetLibrary $snippets;
    private string $orchestrationsPath;

    public function __construct(
        MCPService $mcp,
        VaultService $vault,
        SnippetLibrary $snippets
    ) {
        $this->mcp = $mcp;
        $this->vault = $vault;
        $this->snippets = $snippets;
        $this->orchestrationsPath = storage_path('orchestrations');

        if (!is_dir($this->orchestrationsPath)) {
            mkdir($this->orchestrationsPath, 0755, true);
        }
    }

    /**
     * Orchestrate a high-level request into delegated tasks
     *
     * @param string $description User's high-level request
     * @param array $options Additional options (projects, priority, etc.)
     * @return array Orchestration details with tasks
     */
    public function orchestrate(string $description, array $options = []): array
    {
        // Generate orchestration ID
        $orchestrationId = 'orch_' . date('Ymd_His') . '_' . Str::random(4);

        // Analyze the request
        $analysis = $this->analyzeRequest($description);

        // Create tasks based on analysis
        $tasks = $this->createTasks($orchestrationId, $description, $analysis);

        // Resolve dependencies
        $tasks = $this->resolveDependencies($tasks);

        // Create orchestration record
        $orchestration = [
            'id' => $orchestrationId,
            'description' => $description,
            'status' => 'pending',
            'analysis' => $analysis,
            'tasks' => $tasks,
            'total_tasks' => count($tasks),
            'completed_tasks' => 0,
            'estimated_duration_minutes' => $this->calculateEstimatedDuration($tasks),
            'created_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => null,
        ];

        // Save orchestration
        $this->saveOrchestration($orchestration);

        // Delegate tasks via MCP
        $this->delegateTasks($orchestration);

        return $orchestration;
    }

    /**
     * Analyze user request to determine scope and requirements
     */
    private function analyzeRequest(string $description): array
    {
        $analysis = [
            'components' => [],
            'projects' => [],
            'required_secrets' => [],
            'relevant_snippets' => [],
            'api_changes' => false,
            'database_changes' => false,
            'complexity' => 'medium',
        ];

        $descLower = strtolower($description);

        // Detect components
        if (str_contains($descLower, 'payment') || str_contains($descLower, 'mollie') || str_contains($descLower, 'betaal')) {
            $analysis['components'][] = 'payment_system';
            $analysis['required_secrets'][] = 'mollie_api_key';
            $analysis['relevant_snippets'][] = 'payments/mollie-payment-setup.php';
        }

        if (str_contains($descLower, 'invoice') || str_contains($descLower, 'factuur')) {
            $analysis['components'][] = 'invoicing';
            $analysis['relevant_snippets'][] = 'invoices/invoice-generator.php';
        }

        if (str_contains($descLower, 'memorial') || str_contains($descLower, 'gedenk')) {
            $analysis['components'][] = 'memorial_system';
            $analysis['relevant_snippets'][] = 'utilities/memorial-reference-service.php';
        }

        if (str_contains($descLower, 'api') || str_contains($descLower, 'endpoint')) {
            $analysis['components'][] = 'api';
            $analysis['api_changes'] = true;
            $analysis['relevant_snippets'][] = 'api/rest-response-formatter.php';
        }

        if (str_contains($descLower, 'email') || str_contains($descLower, 'mail')) {
            $analysis['components'][] = 'email';
            $analysis['required_secrets'][] = 'gmail_oauth_credentials';
        }

        // Detect affected projects
        if (str_contains($descLower, 'admin') || in_array('api', $analysis['components'])) {
            $analysis['projects'][] = 'HavunAdmin';
        }

        if (str_contains($descLower, 'herdenkingsportaal') || str_contains($descLower, 'portal')) {
            $analysis['projects'][] = 'Herdenkingsportaal';
        }

        if (str_contains($descLower, 'vpd') || str_contains($descLower, 'update')) {
            $analysis['projects'][] = 'VPDUpdate';
        }

        // If no specific project mentioned, assume both HavunAdmin and Herdenkingsportaal
        if (empty($analysis['projects'])) {
            $analysis['projects'][] = 'HavunAdmin';
            if (in_array('payment_system', $analysis['components']) || in_array('memorial_system', $analysis['components'])) {
                $analysis['projects'][] = 'Herdenkingsportaal';
            }
        }

        // Detect database changes
        if (str_contains($descLower, 'new') || str_contains($descLower, 'create') ||
            str_contains($descLower, 'add') || str_contains($descLower, 'table')) {
            $analysis['database_changes'] = true;
        }

        // Determine complexity
        $complexityScore = 0;
        $complexityScore += count($analysis['projects']) * 10;
        $complexityScore += count($analysis['components']) * 5;
        $complexityScore += $analysis['database_changes'] ? 10 : 0;
        $complexityScore += $analysis['api_changes'] ? 15 : 0;

        if ($complexityScore < 20) {
            $analysis['complexity'] = 'low';
        } elseif ($complexityScore > 40) {
            $analysis['complexity'] = 'high';
        }

        return $analysis;
    }

    /**
     * Create specific tasks based on analysis
     */
    private function createTasks(string $orchestrationId, string $description, array $analysis): array
    {
        $tasks = [];
        $taskCounter = 1;

        // If API changes needed, create backend task
        if ($analysis['api_changes'] && in_array('HavunAdmin', $analysis['projects'])) {
            $tasks[] = $this->createTask(
                id: sprintf('task_%03d', $taskCounter++),
                orchestrationId: $orchestrationId,
                project: 'HavunAdmin',
                priority: 'high',
                description: "Backend API implementation for: {$description}",
                instructions: $this->generateBackendInstructions($description, $analysis),
                secrets: $this->resolveSecrets($analysis['required_secrets']),
                snippets: $this->resolveSnippets(array_filter($analysis['relevant_snippets'], fn($s) => str_contains($s, 'api/') || str_contains($s, 'payments/'))),
                estimatedMinutes: $analysis['complexity'] === 'high' ? 45 : 30
            );
        }

        // If database changes needed, create migration task (can be combined with backend)
        if ($analysis['database_changes'] && !empty($tasks)) {
            $tasks[0]['instructions'] = array_merge(
                ['1. Create database migration for required schema changes'],
                $tasks[0]['instructions']
            );
            $tasks[0]['estimated_duration_minutes'] += 15;
        }

        // If Herdenkingsportaal involved, create frontend task
        if (in_array('Herdenkingsportaal', $analysis['projects'])) {
            $dependsOn = !empty($tasks) ? [$tasks[0]['id']] : [];

            $tasks[] = $this->createTask(
                id: sprintf('task_%03d', $taskCounter++),
                orchestrationId: $orchestrationId,
                project: 'Herdenkingsportaal',
                priority: empty($dependsOn) ? 'high' : 'medium',
                description: "Frontend implementation for: {$description}",
                instructions: $this->generateFrontendInstructions($description, $analysis),
                secrets: [],
                snippets: $this->resolveSnippets(array_filter($analysis['relevant_snippets'], fn($s) => !str_contains($s, 'api/'))),
                estimatedMinutes: 30,
                dependencies: $dependsOn
            );
        }

        // If VPDUpdate involved, create sync task
        if (in_array('VPDUpdate', $analysis['projects'])) {
            $tasks[] = $this->createTask(
                id: sprintf('task_%03d', $taskCounter++),
                orchestrationId: $orchestrationId,
                project: 'VPDUpdate',
                priority: 'medium',
                description: "VPD sync implementation for: {$description}",
                instructions: $this->generateVPDInstructions($description, $analysis),
                secrets: $this->resolveSecrets(['havunadmin_api_token']),
                snippets: [],
                estimatedMinutes: 25
            );
        }

        // Add testing task if complexity is high
        if ($analysis['complexity'] === 'high' && count($tasks) > 1) {
            $allPreviousTasks = array_column($tasks, 'id');

            $tasks[] = $this->createTask(
                id: sprintf('task_%03d', $taskCounter++),
                orchestrationId: $orchestrationId,
                project: 'HavunAdmin',
                priority: 'low',
                description: "Integration testing for: {$description}",
                instructions: [
                    '1. Create integration tests that verify all components work together',
                    '2. Test API endpoints with real data',
                    '3. Verify error handling',
                    '4. Check API contract compliance',
                    '5. Run test suite and ensure all tests pass',
                ],
                secrets: [],
                snippets: [],
                estimatedMinutes: 20,
                dependencies: $allPreviousTasks
            );
        }

        return $tasks;
    }

    /**
     * Create a single task
     */
    private function createTask(
        string $id,
        string $orchestrationId,
        string $project,
        string $priority,
        string $description,
        array $instructions,
        array $secrets,
        array $snippets,
        int $estimatedMinutes,
        array $dependencies = []
    ): array {
        return [
            'id' => $id,
            'orchestration_id' => $orchestrationId,
            'project' => $project,
            'priority' => $priority,
            'status' => 'pending',
            'description' => $description,
            'dependencies' => $dependencies,
            'instructions' => $instructions,
            'secrets' => $secrets,
            'snippets' => $snippets,
            'estimated_duration_minutes' => $estimatedMinutes,
            'actual_duration_minutes' => null,
            'started_at' => null,
            'completed_at' => null,
            'result' => null,
        ];
    }

    /**
     * Generate backend implementation instructions
     */
    private function generateBackendInstructions(string $description, array $analysis): array
    {
        $instructions = [];

        if ($analysis['database_changes']) {
            $instructions[] = '1. Create database migration for required tables/columns';
            $instructions[] = '2. Create or update Eloquent models';
        }

        if ($analysis['api_changes']) {
            $instructions[] = '3. Create API controller with required endpoints';
            $instructions[] = '4. Add routes to api.php';
            $instructions[] = '5. Implement request validation';
            $instructions[] = '6. Implement business logic';
        }

        if (in_array('payment_system', $analysis['components'])) {
            $instructions[] = '7. Integrate with Mollie API using provided snippet';
            $instructions[] = '8. Set up webhook handling';
        }

        $instructions[] = '9. Write unit and feature tests';
        $instructions[] = '10. Update API documentation/OpenAPI spec if applicable';

        return array_values($instructions);
    }

    /**
     * Generate frontend implementation instructions
     */
    private function generateFrontendInstructions(string $description, array $analysis): array
    {
        $instructions = [
            '1. Update relevant Blade templates or Vue components',
            '2. Add form fields and validation',
            '3. Implement API calls to backend endpoints',
            '4. Add error handling and user feedback',
            '5. Update email templates if needed',
            '6. Test user flow end-to-end',
        ];

        return $instructions;
    }

    /**
     * Generate VPD sync instructions
     */
    private function generateVPDInstructions(string $description, array $analysis): array
    {
        return [
            '1. Update sync service to handle new data fields',
            '2. Add API calls to HavunAdmin endpoints',
            '3. Implement data mapping and transformation',
            '4. Add error handling and retry logic',
            '5. Test sync with HavunAdmin API',
        ];
    }

    /**
     * Resolve secrets from vault
     */
    private function resolveSecrets(array $secretKeys): array
    {
        $secrets = [];

        foreach ($secretKeys as $key) {
            if ($this->vault->has($key)) {
                $secrets[$key] = $this->vault->get($key);
            }
        }

        return $secrets;
    }

    /**
     * Resolve snippets from library
     */
    private function resolveSnippets(array $snippetPaths): array
    {
        $snippets = [];

        foreach ($snippetPaths as $path) {
            $snippet = $this->snippets->get($path);
            if ($snippet) {
                $snippets[] = [
                    'path' => $path,
                    'code' => $snippet['code'],
                    'metadata' => $snippet['metadata'],
                    'usage' => $snippet['metadata']['usage'] ?? 'Use this code in your implementation',
                ];
            }
        }

        return $snippets;
    }

    /**
     * Resolve task dependencies
     */
    private function resolveDependencies(array $tasks): array
    {
        // Dependencies are already set in createTasks, but we can add more logic here
        // For now, just validate that dependencies exist
        $taskIds = array_column($tasks, 'id');

        foreach ($tasks as &$task) {
            foreach ($task['dependencies'] as $depId) {
                if (!in_array($depId, $taskIds)) {
                    throw new Exception("Task {$task['id']} depends on non-existent task {$depId}");
                }
            }
        }

        return $tasks;
    }

    /**
     * Calculate total estimated duration
     */
    private function calculateEstimatedDuration(array $tasks): int
    {
        // Sequential duration
        $sequential = array_sum(array_column($tasks, 'estimated_duration_minutes'));

        // Parallel duration (longest chain of dependencies)
        $parallel = $this->calculateParallelDuration($tasks);

        return $parallel;
    }

    /**
     * Calculate parallel execution duration (critical path)
     */
    private function calculateParallelDuration(array $tasks): int
    {
        $taskDurations = [];
        foreach ($tasks as $task) {
            $taskDurations[$task['id']] = $task['estimated_duration_minutes'];
        }

        $taskDeps = [];
        foreach ($tasks as $task) {
            $taskDeps[$task['id']] = $task['dependencies'];
        }

        // Calculate longest path for each task
        $longestPaths = [];
        foreach ($tasks as $task) {
            $longestPaths[$task['id']] = $this->getLongestPath($task['id'], $taskDurations, $taskDeps);
        }

        return max($longestPaths);
    }

    /**
     * Get longest path to a task (critical path calculation)
     */
    private function getLongestPath(string $taskId, array $durations, array $dependencies, array &$memo = []): int
    {
        if (isset($memo[$taskId])) {
            return $memo[$taskId];
        }

        $deps = $dependencies[$taskId] ?? [];

        if (empty($deps)) {
            $memo[$taskId] = $durations[$taskId];
            return $durations[$taskId];
        }

        $maxDepPath = 0;
        foreach ($deps as $depId) {
            $maxDepPath = max($maxDepPath, $this->getLongestPath($depId, $durations, $dependencies, $memo));
        }

        $memo[$taskId] = $maxDepPath + $durations[$taskId];
        return $memo[$taskId];
    }

    /**
     * Delegate tasks to projects via MCP
     */
    private function delegateTasks(array $orchestration): void
    {
        foreach ($orchestration['tasks'] as $task) {
            // Only delegate tasks with no dependencies or whose dependencies are met
            if (empty($task['dependencies'])) {
                $this->delegateTask($task);
            }
        }
    }

    /**
     * Delegate a single task via MCP
     */
    private function delegateTask(array $task): bool
    {
        $message = $this->formatTaskMessage($task);

        return $this->mcp->storeMessage(
            project: $task['project'],
            content: $message,
            tags: ['task', 'orchestration', $task['orchestration_id'], $task['id']]
        );
    }

    /**
     * Format task as MCP message
     */
    private function formatTaskMessage(array $task): string
    {
        $message = "# 🎯 New Task from HavunCore\n\n";
        $message .= "**Task ID:** {$task['id']}\n";
        $message .= "**Orchestration:** {$task['orchestration_id']}\n";
        $message .= "**Priority:** " . strtoupper($task['priority']) . "\n";
        $message .= "**Estimated Duration:** {$task['estimated_duration_minutes']} minutes\n\n";

        $message .= "## Description\n\n";
        $message .= $task['description'] . "\n\n";

        if (!empty($task['dependencies'])) {
            $message .= "## Dependencies\n\n";
            $message .= "⚠️ This task depends on: " . implode(', ', $task['dependencies']) . "\n";
            $message .= "Wait for those tasks to complete before starting.\n\n";
        }

        $message .= "## Instructions\n\n";
        foreach ($task['instructions'] as $instruction) {
            $message .= "- {$instruction}\n";
        }
        $message .= "\n";

        if (!empty($task['secrets'])) {
            $message .= "## Secrets Provided\n\n";
            foreach (array_keys($task['secrets']) as $key) {
                $message .= "✓ {$key}\n";
            }
            $message .= "\n";
            $message .= "```json\n";
            $message .= json_encode($task['secrets'], JSON_PRETTY_PRINT);
            $message .= "\n```\n\n";
        }

        if (!empty($task['snippets'])) {
            $message .= "## Code Snippets\n\n";
            foreach ($task['snippets'] as $snippet) {
                $message .= "### {$snippet['path']}\n\n";
                $message .= "**Usage:** {$snippet['usage']}\n\n";
                $message .= "```php\n";
                $message .= $snippet['code'];
                $message .= "\n```\n\n";
            }
        }

        $message .= "---\n\n";
        $message .= "When complete, report back with:\n";
        $message .= "`php artisan havun:tasks:complete {$task['id']}`\n";

        return $message;
    }

    /**
     * Get orchestration status
     */
    public function getStatus(string $orchestrationId): ?array
    {
        return $this->loadOrchestration($orchestrationId);
    }

    /**
     * List all orchestrations
     */
    public function listOrchestrations(bool $includeCompleted = false): array
    {
        $files = glob($this->orchestrationsPath . '/*.json');
        $orchestrations = [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);

            if (!$includeCompleted && $data['status'] === 'completed') {
                continue;
            }

            $orchestrations[] = $data;
        }

        // Sort by created_at descending
        usort($orchestrations, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $orchestrations;
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(string $orchestrationId, string $taskId, string $status, array $result = null): bool
    {
        $orchestration = $this->loadOrchestration($orchestrationId);

        if (!$orchestration) {
            return false;
        }

        foreach ($orchestration['tasks'] as &$task) {
            if ($task['id'] === $taskId) {
                $task['status'] = $status;

                if ($status === 'in_progress' && !$task['started_at']) {
                    $task['started_at'] = now()->toIso8601String();
                }

                if ($status === 'completed' || $status === 'failed') {
                    $task['completed_at'] = now()->toIso8601String();

                    if ($task['started_at']) {
                        $start = strtotime($task['started_at']);
                        $end = strtotime($task['completed_at']);
                        $task['actual_duration_minutes'] = round(($end - $start) / 60);
                    }

                    if ($result) {
                        $task['result'] = $result;
                    }
                }

                break;
            }
        }

        // Update orchestration status
        $orchestration['completed_tasks'] = count(array_filter(
            $orchestration['tasks'],
            fn($t) => $t['status'] === 'completed'
        ));

        if ($orchestration['completed_tasks'] === $orchestration['total_tasks']) {
            $orchestration['status'] = 'completed';
            $orchestration['completed_at'] = now()->toIso8601String();
        } elseif ($orchestration['status'] === 'pending') {
            $orchestration['status'] = 'in_progress';
            $orchestration['started_at'] = now()->toIso8601String();
        }

        $this->saveOrchestration($orchestration);

        // Check if any dependent tasks can now be delegated
        if ($status === 'completed') {
            $this->delegateDependentTasks($orchestration, $taskId);
        }

        return true;
    }

    /**
     * Delegate tasks that were waiting for dependencies
     */
    private function delegateDependentTasks(array $orchestration, string $completedTaskId): void
    {
        foreach ($orchestration['tasks'] as $task) {
            if ($task['status'] !== 'pending') {
                continue;
            }

            // Check if all dependencies are met
            $allDepsMet = true;
            foreach ($task['dependencies'] as $depId) {
                $depTask = $this->findTask($orchestration['tasks'], $depId);
                if (!$depTask || $depTask['status'] !== 'completed') {
                    $allDepsMet = false;
                    break;
                }
            }

            if ($allDepsMet) {
                $this->delegateTask($task);
            }
        }
    }

    /**
     * Find task by ID
     */
    private function findTask(array $tasks, string $taskId): ?array
    {
        foreach ($tasks as $task) {
            if ($task['id'] === $taskId) {
                return $task;
            }
        }
        return null;
    }

    /**
     * Save orchestration to disk
     */
    private function saveOrchestration(array $orchestration): void
    {
        $filePath = $this->orchestrationsPath . '/' . $orchestration['id'] . '.json';
        file_put_contents($filePath, json_encode($orchestration, JSON_PRETTY_PRINT));
    }

    /**
     * Load orchestration from disk
     */
    private function loadOrchestration(string $orchestrationId): ?array
    {
        $filePath = $this->orchestrationsPath . '/' . $orchestrationId . '.json';

        if (!file_exists($filePath)) {
            return null;
        }

        return json_decode(file_get_contents($filePath), true);
    }
}
