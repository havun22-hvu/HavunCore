<?php

namespace Havun\Core\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Push Notifier Service
 *
 * Sends real-time push notifications to other Claude instances via file-based system.
 * Uses Node.js file watcher for instant delivery (< 100ms latency).
 *
 * Usage:
 *   app(PushNotifier::class)->send('Herdenkingsportaal', [
 *       'type' => 'api_change',
 *       'message' => 'API updated to nested structure',
 *   ]);
 *
 * @package Havun\Core\Services
 * @version 1.0.0
 */
class PushNotifier
{
    private string $notificationsBasePath;
    private string $projectName;

    /**
     * @param string $notificationsBasePath Base path for notifications directory
     * @param string $projectName Current project name
     */
    public function __construct(
        string $notificationsBasePath = 'D:\GitHub\havun-mcp\notifications',
        string $projectName = 'HavunCore'
    ) {
        $this->notificationsBasePath = rtrim($notificationsBasePath, '\\/');
        $this->projectName = $projectName;
    }

    /**
     * Send push notification to target project
     *
     * @param string $targetProject Project name (Herdenkingsportaal, HavunAdmin, etc.)
     * @param array $data Notification data
     * @return bool Success status
     *
     * @throws Exception If notification cannot be sent
     */
    public function send(string $targetProject, array $data): bool
    {
        try {
            // Build notification
            $notification = $this->buildNotification($targetProject, $data);

            // Write to file
            $filePath = $this->writeNotificationFile($targetProject, $notification);

            Log::info("[PushNotifier] Notification sent to {$targetProject}", [
                'notification_id' => $notification['id'],
                'type' => $notification['type'],
                'file' => $filePath,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("[PushNotifier] Failed to send notification to {$targetProject}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Broadcast notification to multiple projects
     *
     * @param array $targetProjects List of project names
     * @param array $data Notification data
     * @return array Results per project ['ProjectName' => true/false]
     */
    public function broadcast(array $targetProjects, array $data): array
    {
        $results = [];

        foreach ($targetProjects as $project) {
            $results[$project] = $this->send($project, $data);
        }

        Log::info("[PushNotifier] Broadcast notification sent", [
            'targets' => count($targetProjects),
            'successful' => count(array_filter($results)),
            'failed' => count($targetProjects) - count(array_filter($results)),
        ]);

        return $results;
    }

    /**
     * Send notification with high priority
     *
     * @param string $targetProject Project name
     * @param array $data Notification data
     * @return bool Success status
     */
    public function sendUrgent(string $targetProject, array $data): bool
    {
        $data['priority'] = 'urgent';
        $data['action_required'] = true;

        return $this->send($targetProject, $data);
    }

    /**
     * Send API change notification
     *
     * @param string $targetProject Project name
     * @param string $message Change description
     * @param bool $breaking Whether this is a breaking change
     * @param string|null $deadline Deadline for migration (Y-m-d format)
     * @return bool Success status
     */
    public function notifyAPIChange(
        string $targetProject,
        string $message,
        bool $breaking = false,
        ?string $deadline = null
    ): bool {
        return $this->send($targetProject, [
            'type' => $breaking ? 'breaking_change' : 'api_change',
            'message' => $message,
            'priority' => $breaking ? 'urgent' : 'high',
            'action_required' => $breaking,
            'deadline' => $deadline,
        ]);
    }

    /**
     * Send test result notification
     *
     * @param string $targetProject Project name
     * @param bool $success Test success status
     * @param string $message Test result details
     * @param array $metadata Additional test data
     * @return bool Success status
     */
    public function notifyTestResult(
        string $targetProject,
        bool $success,
        string $message,
        array $metadata = []
    ): bool {
        return $this->send($targetProject, [
            'type' => 'test_result',
            'message' => ($success ? '✅ ' : '❌ ') . $message,
            'priority' => $success ? 'low' : 'high',
            'metadata' => array_merge($metadata, ['success' => $success]),
        ]);
    }

    /**
     * Send deployment notification
     *
     * @param string $targetProject Project name
     * @param string $version Version deployed
     * @param array $changes List of changes
     * @return bool Success status
     */
    public function notifyDeployment(
        string $targetProject,
        string $version,
        array $changes = []
    ): bool {
        $message = "# 🚀 Deployment: {$this->projectName} {$version}\n\n";

        if (!empty($changes)) {
            $message .= "## Changes\n\n";
            foreach ($changes as $change) {
                $message .= "- {$change}\n";
            }
        }

        return $this->send($targetProject, [
            'type' => 'deployment',
            'message' => $message,
            'priority' => 'normal',
            'metadata' => [
                'version' => $version,
                'deployed_by' => $this->projectName,
            ],
        ]);
    }

    /**
     * Request action from target project
     *
     * @param string $targetProject Project name
     * @param string $message Request description
     * @param string|null $deadline Optional deadline
     * @return bool Success status
     */
    public function requestAction(
        string $targetProject,
        string $message,
        ?string $deadline = null
    ): bool {
        return $this->send($targetProject, [
            'type' => 'request',
            'message' => $message,
            'priority' => 'high',
            'action_required' => true,
            'deadline' => $deadline,
        ]);
    }

    /**
     * Build notification array
     *
     * @param string $targetProject Target project name
     * @param array $data Notification data
     * @return array Complete notification
     */
    private function buildNotification(string $targetProject, array $data): array
    {
        return [
            'id' => uniqid('msg_', true),
            'from' => $data['from'] ?? $this->projectName,
            'to' => $targetProject,
            'type' => $data['type'] ?? 'info',
            'message' => $data['message'] ?? '',
            'timestamp' => now()->toIso8601String(),
            'priority' => $data['priority'] ?? 'normal',
            'action_required' => $data['action_required'] ?? false,
            'deadline' => $data['deadline'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    /**
     * Write notification to file
     *
     * @param string $targetProject Target project name
     * @param array $notification Notification data
     * @return string File path
     *
     * @throws Exception If directory cannot be created or file cannot be written
     */
    private function writeNotificationFile(string $targetProject, array $notification): string
    {
        // Build directory path
        $notificationDir = $this->notificationsBasePath . DIRECTORY_SEPARATOR
                         . $targetProject . DIRECTORY_SEPARATOR . 'new';

        // Create directory if not exists
        if (!is_dir($notificationDir)) {
            if (!mkdir($notificationDir, 0755, true)) {
                throw new Exception("Failed to create notification directory: {$notificationDir}");
            }
        }

        // Build file path
        $filename = $notification['id'] . '.json';
        $filePath = $notificationDir . DIRECTORY_SEPARATOR . $filename;

        // Write JSON file
        $json = json_encode($notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($filePath, $json) === false) {
            throw new Exception("Failed to write notification file: {$filePath}");
        }

        return $filePath;
    }

    /**
     * Get notification history for current project
     *
     * @param int $limit Number of notifications to retrieve
     * @return array List of notifications
     */
    public function getHistory(int $limit = 50): array
    {
        $readDir = $this->notificationsBasePath . DIRECTORY_SEPARATOR
                 . $this->projectName . DIRECTORY_SEPARATOR . 'read';

        if (!is_dir($readDir)) {
            return [];
        }

        $files = glob($readDir . DIRECTORY_SEPARATOR . '*.json');

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Limit results
        $files = array_slice($files, 0, $limit);

        // Read notifications
        $notifications = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $notification = json_decode($content, true);
                if ($notification !== null) {
                    $notifications[] = $notification;
                }
            }
        }

        return $notifications;
    }

    /**
     * Get pending notifications count
     *
     * @return int Number of unread notifications
     */
    public function getPendingCount(): int
    {
        $newDir = $this->notificationsBasePath . DIRECTORY_SEPARATOR
                . $this->projectName . DIRECTORY_SEPARATOR . 'new';

        if (!is_dir($newDir)) {
            return 0;
        }

        $files = glob($newDir . DIRECTORY_SEPARATOR . '*.json');
        return count($files);
    }
}
