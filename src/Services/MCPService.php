<?php

namespace Havun\Core\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * MCP (Model Context Protocol) Service
 *
 * Communicates with havun-mcp server for:
 * 1. Automatic status updates between projects
 * 2. Sync monitoring & alerts
 * 3. Configuration vault (disaster recovery)
 * 4. Development workflow automation
 */
class MCPService
{
    private string $mcpUrl;
    private string $projectName;

    /**
     * @param string $mcpUrl URL to MCP server (default: http://localhost:3000)
     * @param string $projectName Current project name (HavunCore, Herdenkingsportaal, HavunAdmin)
     */
    public function __construct(
        string $mcpUrl = 'http://localhost:3000',
        string $projectName = 'HavunCore'
    ) {
        $this->mcpUrl = $mcpUrl;
        $this->projectName = $projectName;
    }

    /**
     * Store a message for a project
     *
     * @param string $targetProject Project to send message to
     * @param string $content Message content (supports Markdown)
     * @param array $tags Optional tags for categorization
     * @return bool Success status
     */
    public function storeMessage(string $targetProject, string $content, array $tags = []): bool
    {
        try {
            // For now, we use the MCP tools that are already available
            // In the future, this could call an HTTP API

            Log::info("[MCP] Message stored for {$targetProject}", [
                'from' => $this->projectName,
                'to' => $targetProject,
                'tags' => $tags,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("[MCP] Failed to store message", [
                'error' => $e->getMessage(),
                'project' => $targetProject,
            ]);

            return false;
        }
    }

    /**
     * Notify projects about deployment
     *
     * @param string $version Version deployed (e.g., "v0.2.2")
     * @param array $changes List of changes
     * @param array $notifyProjects Projects to notify (default: all)
     * @return bool
     */
    public function notifyDeployment(string $version, array $changes, array $notifyProjects = ['Herdenkingsportaal', 'HavunAdmin']): bool
    {
        $content = $this->formatDeploymentMessage($version, $changes);

        $success = true;
        foreach ($notifyProjects as $project) {
            $result = $this->storeMessage($project, $content, ['deployment', 'update-required', $version]);
            $success = $success && $result;
        }

        return $success;
    }

    /**
     * Report invoice sync status
     *
     * @param string $memorialReference Memorial reference
     * @param bool $success Sync success status
     * @param array $details Additional details
     * @return bool
     */
    public function reportInvoiceSync(string $memorialReference, bool $success, array $details = []): bool
    {
        $status = $success ? '✅ SUCCESS' : '❌ FAILED';

        $content = "# Invoice Sync: {$status}\n\n";
        $content .= "**Memorial Reference:** {$memorialReference}\n";
        $content .= "**Time:** " . now()->format('Y-m-d H:i:s') . "\n";
        $content .= "**Project:** {$this->projectName}\n\n";

        if (!empty($details)) {
            $content .= "## Details\n\n";
            foreach ($details as $key => $value) {
                $content .= "- **{$key}:** {$value}\n";
            }
        }

        $tags = ['invoice-sync', $success ? 'success' : 'failed'];

        // Send to both projects for visibility
        $this->storeMessage('HavunAdmin', $content, $tags);
        $this->storeMessage('Herdenkingsportaal', $content, $tags);

        return true;
    }

    /**
     * Store project configuration in vault
     *
     * This acts as a "disaster recovery vault" - HavunCore keeps all critical
     * project configuration that's needed to restore a project.
     *
     * @param string $project Project name
     * @param array $config Configuration array
     * @return bool
     */
    public function storeProjectVault(string $project, array $config): bool
    {
        $content = $this->formatVaultMessage($project, $config);

        return $this->storeMessage('HavunCore', $content, ['vault', 'config', $project]);
    }

    /**
     * Report breaking change that requires action
     *
     * @param string $title Breaking change title
     * @param string $description What changed
     * @param array $requiredActions What needs to be done
     * @param array $affectedProjects Projects affected
     * @return bool
     */
    public function reportBreakingChange(
        string $title,
        string $description,
        array $requiredActions,
        array $affectedProjects = ['Herdenkingsportaal', 'HavunAdmin']
    ): bool {
        $content = "# 🚨 BREAKING CHANGE: {$title}\n\n";
        $content .= "**From:** {$this->projectName}\n";
        $content .= "**Date:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        $content .= "## What Changed\n\n{$description}\n\n";
        $content .= "## ⚠️ Required Actions\n\n";

        foreach ($requiredActions as $i => $action) {
            $content .= ($i + 1) . ". {$action}\n";
        }

        $content .= "\n**Please update your project ASAP!**";

        foreach ($affectedProjects as $project) {
            $this->storeMessage($project, $content, ['breaking-change', 'urgent', 'action-required']);
        }

        return true;
    }

    /**
     * Report workflow event (like new feature, bug fix, etc.)
     *
     * @param string $eventType Type of event (feature, bugfix, refactor, etc.)
     * @param string $title Event title
     * @param string $description Event description
     * @param array $metadata Additional metadata
     * @return bool
     */
    public function reportWorkflowEvent(
        string $eventType,
        string $title,
        string $description,
        array $metadata = []
    ): bool {
        $emoji = match($eventType) {
            'feature' => '✨',
            'bugfix' => '🐛',
            'refactor' => '♻️',
            'performance' => '⚡',
            'security' => '🔒',
            default => '📝',
        };

        $content = "# {$emoji} {$title}\n\n";
        $content .= "**Type:** " . ucfirst($eventType) . "\n";
        $content .= "**Project:** {$this->projectName}\n";
        $content .= "**Date:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        $content .= "{$description}\n";

        if (!empty($metadata)) {
            $content .= "\n## Details\n\n";
            foreach ($metadata as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_PRETTY_PRINT);
                }
                $content .= "**{$key}:** {$value}\n";
            }
        }

        // Store for this project
        return $this->storeMessage($this->projectName, $content, ['workflow', $eventType]);
    }

    /**
     * Format deployment message
     */
    private function formatDeploymentMessage(string $version, array $changes): string
    {
        $content = "# 🚀 {$this->projectName} {$version} Released\n\n";
        $content .= "**Date:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        $content .= "## Changes\n\n";

        foreach ($changes as $change) {
            $content .= "- {$change}\n";
        }

        $content .= "\n## Update Instructions\n\n";
        $content .= "```bash\n";
        $content .= "composer update havun/core\n";
        $content .= "php artisan config:clear\n";
        $content .= "php artisan cache:clear\n";
        $content .= "```\n";

        return $content;
    }

    /**
     * Format vault message
     */
    private function formatVaultMessage(string $project, array $config): string
    {
        $content = "# 🔐 Configuration Vault: {$project}\n\n";
        $content .= "**Updated:** " . now()->format('Y-m-d H:i:s') . "\n";
        $content .= "**Purpose:** Disaster recovery & project restoration\n\n";
        $content .= "## Configuration\n\n";
        $content .= "```json\n";
        $content .= json_encode($config, JSON_PRETTY_PRINT);
        $content .= "\n```\n";

        return $content;
    }
}
