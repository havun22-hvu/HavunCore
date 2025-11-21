<?php

namespace Havun\Core\Strategies;

use Havun\Core\Contracts\BackupStrategyInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class LaravelAppBackupStrategy implements BackupStrategyInterface
{
    /**
     * Execute backup for Laravel application
     */
    public function backup(array $config): string
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $projectName = $config['name'];
        $backupName = "{$timestamp}-{$projectName}";

        // Create temp directory for backup
        $tempDir = storage_path("backups/temp/{$backupName}");

        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        Log::info("Creating backup for {$projectName}", ['temp_dir' => $tempDir]);

        // 1. Backup database (if enabled)
        if ($config['include']['database'] ?? false) {
            $this->backupDatabase($config, $tempDir);
        }

        // 2. Backup files
        if (!empty($config['include']['files'])) {
            $this->backupFiles($config, $tempDir);
        }

        // 3. Backup .env (if enabled)
        if ($config['include']['config'] ?? false) {
            $this->backupConfig($config, $tempDir);
        }

        // 4. Create manifest
        $this->createManifest($config, $tempDir, $timestamp);

        // 5. Compress to ZIP
        $zipPath = storage_path("backups/{$backupName}.zip");
        $this->compressBackup($tempDir, $zipPath, $config['encryption'] ?? []);

        // 6. Cleanup temp directory
        File::deleteDirectory($tempDir);

        Log::info("Backup created successfully", [
            'project' => $projectName,
            'size' => filesize($zipPath),
            'path' => $zipPath,
        ]);

        return $zipPath;
    }

    /**
     * Backup database
     */
    protected function backupDatabase(array $config, string $tempDir): void
    {
        $database = $config['paths']['database'] ?? null;

        if (!$database) {
            throw new \Exception("Database name not configured for project: {$config['name']}");
        }

        Log::info("Backing up database: {$database}");

        // Get database connection type from .env
        $envPath = $config['paths']['root'] . '/.env';
        $dbConnection = $this->parseDatabaseConnection($envPath);

        if ($dbConnection === 'sqlite') {
            $this->backupSQLiteDatabase($config, $tempDir);
        } else {
            $this->backupMySQLDatabase($config, $tempDir, $database);
        }
    }

    /**
     * Backup SQLite database (just copy the file)
     */
    protected function backupSQLiteDatabase(array $config, string $tempDir): void
    {
        $rootPath = $config['paths']['root'];
        $dbPath = $rootPath . '/database/database.sqlite';

        if (!file_exists($dbPath)) {
            throw new \Exception("SQLite database file not found: {$dbPath}");
        }

        $outputPath = $tempDir . '/database.sqlite';
        File::copy($dbPath, $outputPath);

        Log::info("SQLite database backup completed", [
            'size' => filesize($outputPath),
        ]);
    }

    /**
     * Backup MySQL database using mysqldump
     */
    protected function backupMySQLDatabase(array $config, string $tempDir, string $database): void
    {
        $outputPath = $tempDir . '/database.sql';

        // Get database credentials from .env of the target project
        $envPath = $config['paths']['root'] . '/.env';
        $dbCredentials = $this->parseDatabaseCredentials($envPath);

        // MySQL dump command
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --single-transaction --quick --lock-tables=false %s > %s 2>&1',
            escapeshellarg($dbCredentials['username']),
            escapeshellarg($dbCredentials['password']),
            escapeshellarg($dbCredentials['host'] ?? 'localhost'),
            escapeshellarg($database),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            Log::error("Database dump failed", [
                'database' => $database,
                'error' => $error,
                'exit_code' => $exitCode,
            ]);
            throw new \Exception("Database dump failed for {$database}: {$error}");
        }

        // Verify dump file exists and has content
        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new \Exception("Database dump file is empty or missing: {$outputPath}");
        }

        Log::info("MySQL database backup completed", [
            'database' => $database,
            'size' => filesize($outputPath),
        ]);
    }

    /**
     * Parse database connection type from .env file
     */
    protected function parseDatabaseConnection(string $envPath): string
    {
        if (!file_exists($envPath)) {
            throw new \Exception(".env file not found: {$envPath}");
        }

        $env = file_get_contents($envPath);

        // Parse DB_CONNECTION
        if (preg_match('/DB_CONNECTION=(.*)/', $env, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return 'mysql'; // Default
    }

    /**
     * Parse database credentials from .env file
     */
    protected function parseDatabaseCredentials(string $envPath): array
    {
        if (!file_exists($envPath)) {
            throw new \Exception(".env file not found: {$envPath}");
        }

        $env = file_get_contents($envPath);
        $credentials = [];

        // Parse DB_USERNAME
        if (preg_match('/DB_USERNAME=(.*)/', $env, $matches)) {
            $credentials['username'] = trim($matches[1]);
        }

        // Parse DB_PASSWORD
        if (preg_match('/DB_PASSWORD=(.*)/', $env, $matches)) {
            $credentials['password'] = trim($matches[1]);
        }

        // Parse DB_HOST
        if (preg_match('/DB_HOST=(.*)/', $env, $matches)) {
            $credentials['host'] = trim($matches[1]);
        }

        return $credentials;
    }

    /**
     * Backup files
     */
    protected function backupFiles(array $config, string $tempDir): void
    {
        $rootPath = $config['paths']['root'];
        $filesDir = $tempDir . '/files';

        File::makeDirectory($filesDir, 0755, true);

        foreach ($config['include']['files'] as $path) {
            $sourcePath = $rootPath . '/' . $path;

            if (!file_exists($sourcePath)) {
                Log::warning("Backup path does not exist, skipping", ['path' => $sourcePath]);
                continue;
            }

            $destPath = $filesDir . '/' . $path;

            if (is_dir($sourcePath)) {
                Log::info("Backing up directory: {$path}");
                File::copyDirectory($sourcePath, $destPath);
            } else {
                Log::info("Backing up file: {$path}");
                File::ensureDirectoryExists(dirname($destPath));
                File::copy($sourcePath, $destPath);
            }
        }
    }

    /**
     * Backup .env configuration
     */
    protected function backupConfig(array $config, string $tempDir): void
    {
        $envPath = $config['paths']['root'] . '/.env';

        if (!file_exists($envPath)) {
            Log::warning(".env file not found, skipping config backup", ['path' => $envPath]);
            return;
        }

        $destPath = $tempDir . '/env-backup.txt';
        File::copy($envPath, $destPath);

        Log::info("Config backed up (.env)");
    }

    /**
     * Create backup manifest
     */
    protected function createManifest(array $config, string $tempDir, string $timestamp): void
    {
        $manifest = [
            'project' => $config['name'],
            'timestamp' => $timestamp,
            'backup_date' => now()->toIso8601String(),
            'type' => 'laravel-app',
            'includes' => [
                'database' => $config['include']['database'] ?? false,
                'files' => $config['include']['files'] ?? [],
                'config' => $config['include']['config'] ?? false,
            ],
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        $manifestPath = $tempDir . '/backup-manifest.json';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        Log::info("Backup manifest created");
    }

    /**
     * Compress backup to ZIP
     */
    protected function compressBackup(string $sourceDir, string $zipPath, array $encryption = []): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \Exception("ZipArchive extension not available");
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Failed to create ZIP file: {$zipPath}");
        }

        // Add all files recursively
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);

                $zip->addFile($filePath, $relativePath);
                $fileCount++;
            }
        }

        // Optional: Set password for encryption
        if (($encryption['enabled'] ?? false) && !empty($encryption['password'])) {
            $zip->setPassword($encryption['password']);

            // Encrypt all files
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256);
            }

            Log::info("Backup encrypted with AES-256");
        }

        $zip->close();

        Log::info("Backup compressed", [
            'files' => $fileCount,
            'size' => filesize($zipPath),
        ]);
    }

    /**
     * Restore backup (basic implementation)
     */
    public function restore(string $backupPath, array $config, array $options = []): array
    {
        // TODO: Implement restore logic
        // This would:
        // 1. Extract ZIP
        // 2. Verify manifest
        // 3. Restore database (mysql import)
        // 4. Restore files (rsync/copy)
        // 5. Restore .env (if needed)
        // 6. Run post-restore commands (clear cache, etc.)

        throw new \Exception("Restore functionality not yet implemented");
    }
}
