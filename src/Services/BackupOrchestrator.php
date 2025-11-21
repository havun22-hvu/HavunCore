<?php

namespace Havun\Core\Services;

use Havun\Core\Models\BackupLog;
use Havun\Core\Contracts\BackupStrategyInterface;
use Havun\Core\Strategies\LaravelAppBackupStrategy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class BackupOrchestrator
{
    protected array $strategies = [];

    public function __construct()
    {
        // Register backup strategies
        $this->strategies = [
            'laravel-app' => new LaravelAppBackupStrategy(),
            // Future: nodejs-app, laravel-package, etc.
        ];
    }

    /**
     * Run backup voor alle projecten of specifiek project
     */
    public function runBackup(?string $project = null): array
    {
        if ($project) {
            return [$project => $this->backupSingleProject($project)];
        }

        return $this->backupAllProjects();
    }

    /**
     * Backup alle enabled projecten
     */
    protected function backupAllProjects(): array
    {
        $results = [];
        $projects = config('havun.backup.projects', []);

        foreach ($projects as $projectName => $config) {
            if (!($config['enabled'] ?? false)) {
                Log::info("Skipping disabled project: {$projectName}");
                continue;
            }

            try {
                $results[$projectName] = $this->backupSingleProject($projectName);
            } catch (\Exception $e) {
                $results[$projectName] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::error("Backup failed for project: {$projectName}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Log failure to database
                $this->logBackupFailure($projectName, $e);
            }
        }

        return $results;
    }

    /**
     * Backup specifiek project
     */
    protected function backupSingleProject(string $project): array
    {
        $startTime = microtime(true);

        Log::info("========== Starting backup for: {$project} ==========");

        $config = config("havun.backup.projects.{$project}");
        if (!$config) {
            throw new \Exception("Project configuration not found: {$project}");
        }

        $config['name'] = $project;

        // 1. Get backup strategy
        $strategy = $this->getStrategy($config['type']);

        // 2. Execute backup
        $backupPath = $strategy->backup($config);

        if (!file_exists($backupPath)) {
            throw new \Exception("Backup file was not created: {$backupPath}");
        }

        $backupSize = filesize($backupPath);

        // 3. Calculate checksum
        Log::info("Calculating SHA256 checksum...");
        $checksum = hash_file('sha256', $backupPath);
        $checksumFile = $backupPath . '.sha256';
        file_put_contents($checksumFile, $checksum . '  ' . basename($backupPath));

        // 4. Upload to local storage
        $localPath = $this->uploadToLocal($backupPath, $checksumFile, $project);

        // 5. Upload to offsite storage (Hetzner Storage Box)
        $offsitePath = $this->uploadToOffsite($backupPath, $checksumFile, $project);

        // 6. Cleanup temp backup files
        @unlink($backupPath);
        @unlink($checksumFile);

        $duration = round(microtime(true) - $startTime, 2);

        // 7. Log to database
        $backupLog = BackupLog::create([
            'project' => $project,
            'project_type' => $config['type'],
            'backup_name' => basename($backupPath, '.zip'),
            'backup_date' => now(),
            'backup_size' => $backupSize,
            'backup_checksum' => $checksum,
            'disk_local' => (bool) $localPath,
            'disk_offsite' => (bool) $offsitePath,
            'offsite_path' => $offsitePath,
            'status' => 'success',
            'duration_seconds' => $duration,
            'is_encrypted' => $config['encryption']['enabled'] ?? false,
            'retention_years' => $config['retention']['archive_retention_years'],
            'can_auto_delete' => $config['retention']['auto_cleanup_archive'] ?? false,
        ]);

        Log::info("========== Backup completed for: {$project} ==========", [
            'duration' => $duration . 's',
            'size' => $backupLog->formatted_size,
            'checksum' => substr($checksum, 0, 16) . '...',
        ]);

        // 8. Cleanup old hot backups
        $this->cleanupHotBackups($project, $config['retention']['hot_retention_days']);

        return [
            'status' => 'success',
            'backup_name' => $backupLog->backup_name,
            'size' => $backupLog->formatted_size,
            'size_bytes' => $backupSize,
            'checksum' => $checksum,
            'duration' => $duration,
            'local' => (bool) $localPath,
            'offsite' => (bool) $offsitePath,
        ];
    }

    /**
     * Get backup strategy voor project type
     */
    protected function getStrategy(string $type): BackupStrategyInterface
    {
        if (!isset($this->strategies[$type])) {
            throw new \Exception("Unknown backup strategy: {$type}. Available: " . implode(', ', array_keys($this->strategies)));
        }

        return $this->strategies[$type];
    }

    /**
     * Upload naar local storage
     */
    protected function uploadToLocal(string $backupPath, string $checksumPath, string $project): ?string
    {
        try {
            $diskName = config('havun.backup.storage.local.disk', 'backups-local');
            $disk = Storage::disk($diskName);

            $filename = basename($backupPath);
            $destinationPath = "{$project}/hot/{$filename}";

            Log::info("Uploading to local storage: {$destinationPath}");

            // Ensure directory exists
            $dir = dirname($destinationPath);
            if (!$disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            // Upload backup file
            $disk->put($destinationPath, file_get_contents($backupPath));

            // Upload checksum file
            $disk->put($destinationPath . '.sha256', file_get_contents($checksumPath));

            Log::info("✅ Local upload successful");

            return $destinationPath;
        } catch (\Exception $e) {
            Log::error("❌ Local upload failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Upload naar offsite storage (Hetzner Storage Box)
     */
    protected function uploadToOffsite(string $backupPath, string $checksumPath, string $project): ?string
    {
        try {
            $diskName = config('havun.backup.storage.offsite.disk', 'hetzner-storage-box');
            $disk = Storage::disk($diskName);

            $filename = basename($backupPath);
            $year = date('Y');
            $month = date('m');

            // Archive path: /project/archive/YYYY/MM/filename.zip
            $destinationPath = "{$project}/archive/{$year}/{$month}/{$filename}";

            Log::info("Uploading to offsite storage: {$destinationPath}");

            // Ensure directory exists
            $dir = dirname($destinationPath);
            if (!$disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }

            // Upload backup file
            $disk->put($destinationPath, file_get_contents($backupPath));

            // Upload checksum file
            $disk->put($destinationPath . '.sha256', file_get_contents($checksumPath));

            Log::info("✅ Offsite upload successful");

            return $destinationPath;
        } catch (\Exception $e) {
            Log::error("❌ Offsite upload failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Cleanup oude hot backups
     */
    protected function cleanupHotBackups(string $project, int $retentionDays): void
    {
        try {
            $diskName = config('havun.backup.storage.local.disk', 'backups-local');
            $disk = Storage::disk($diskName);
            $hotPath = "{$project}/hot";

            if (!$disk->exists($hotPath)) {
                return;
            }

            $files = $disk->files($hotPath);
            $cutoffDate = now()->subDays($retentionDays);
            $deletedCount = 0;

            foreach ($files as $file) {
                $lastModified = $disk->lastModified($file);
                if ($lastModified < $cutoffDate->timestamp) {
                    $disk->delete($file);
                    $deletedCount++;
                    Log::info("Deleted old hot backup: {$file}");
                }
            }

            if ($deletedCount > 0) {
                Log::info("Cleanup completed", [
                    'project' => $project,
                    'deleted' => $deletedCount,
                    'retention_days' => $retentionDays,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Cleanup hot backups failed", [
                'project' => $project,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log backup failure to database
     */
    protected function logBackupFailure(string $project, \Exception $e): void
    {
        try {
            $config = config("havun.backup.projects.{$project}", []);

            BackupLog::create([
                'project' => $project,
                'project_type' => $config['type'] ?? 'unknown',
                'backup_name' => now()->format('Y-m-d-H-i-s') . '-' . $project . '-FAILED',
                'backup_date' => now(),
                'backup_size' => 0,
                'backup_checksum' => '',
                'disk_local' => false,
                'disk_offsite' => false,
                'offsite_path' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_seconds' => 0,
                'is_encrypted' => false,
                'retention_years' => $config['retention']['archive_retention_years'] ?? 7,
                'can_auto_delete' => false,
            ]);
        } catch (\Exception $logError) {
            Log::error("Failed to log backup failure", ['error' => $logError->getMessage()]);
        }
    }

    /**
     * Health check voor alle projecten
     */
    public function healthCheck(): array
    {
        $results = [];
        $projects = config('havun.backup.projects', []);

        foreach ($projects as $projectName => $config) {
            if (!($config['enabled'] ?? false)) {
                continue;
            }

            $lastBackup = BackupLog::latestByProject($projectName);

            $results[$projectName] = [
                'priority' => $config['priority'] ?? 'medium',
                'last_backup_date' => $lastBackup?->backup_date,
                'last_backup_age_hours' => $lastBackup?->age_hours,
                'last_backup_size' => $lastBackup?->formatted_size,
                'is_too_old' => $lastBackup ? $lastBackup->isTooOld() : true,
                'status' => $this->calculateHealthStatus($projectName, $lastBackup),
            ];
        }

        return $results;
    }

    /**
     * Calculate health status
     */
    protected function calculateHealthStatus(string $project, ?BackupLog $lastBackup): string
    {
        if (!$lastBackup) {
            return 'critical'; // No backup found
        }

        if ($lastBackup->status !== 'success') {
            return 'critical'; // Last backup failed
        }

        if ($lastBackup->isTooOld()) {
            return 'warning'; // Backup too old
        }

        if (!$lastBackup->disk_offsite) {
            return 'warning'; // Not uploaded to offsite
        }

        return 'healthy';
    }
}
