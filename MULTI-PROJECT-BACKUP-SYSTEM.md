# 🏢 HavunCore Multi-Project Backup System

**Versie:** 1.0.0
**Datum:** 21 november 2025
**Doel:** Centrale backup oplossing voor ALLE Havun projecten

---

## 🎯 Scope

### Projecten die gebackupt worden:

| Project | Type | Backup Type | Priority | Compliance |
|---------|------|-------------|----------|------------|
| **HavunAdmin** | Laravel App | DB + Files | 🔴 Kritiek | 7 jaar (fiscaal) |
| **Herdenkingsportaal** | Laravel App | DB + Files | 🔴 Kritiek | 7 jaar (GDPR) |
| **HavunCore** | Laravel Package | Code + Config | 🟡 Belangrijk | 3 jaar |
| **havun-mcp** | Node.js Server | Code + Config | 🟢 Medium | 1 jaar |
| **Toekomstige Host Sites** | Laravel Apps | DB + Files | 🟡 Variabel | Per klant |

**Total Projects (nu):** 4 actief + toekomstige klanten

---

## 🏗️ Centrale Architectuur

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      HAVUNCORE BACKUP ORCHESTRATOR                       │
│                          (Centrale Coordinator)                           │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
                    ▼               ▼               ▼
          ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
          │ HavunAdmin   │ │Herdenkings-  │ │  HavunCore   │
          │   Backup     │ │ portaal      │ │   Backup     │
          │              │ │   Backup     │ │              │
          │ • Database   │ │ • Database   │ │ • Source     │
          │ • Invoices   │ │ • Uploads    │ │ • Vault      │
          │ • Exports    │ │ • Monuments  │ │ • Config     │
          └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
                 │                │                │
                 └────────────────┼────────────────┘
                                  │
                                  ▼
                    ┌──────────────────────────┐
                    │   Backup Aggregator      │
                    │                          │
                    │  • Compress & Encrypt    │
                    │  • SHA256 Checksums      │
                    │  • Backup Manifest       │
                    └────────────┬─────────────┘
                                 │
                 ┌───────────────┼───────────────┐
                 │               │               │
                 ▼               ▼               ▼
       ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
       │Local Storage │ │Hetzner Box   │ │ BackupLog    │
       │ (Hot - 30d)  │ │(Archive-7yr) │ │  Database    │
       └──────────────┘ └──────────────┘ └──────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         UNIFIED MONITORING                               │
│  • Centraal Dashboard (HavunCore Web UI)                                │
│  • Slack/Discord Notifications                                          │
│  • Email Alerts (havun22@gmail.com)                                     │
│  • Health Checks (alle projecten tegelijk)                              │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 📦 Hetzner Storage Box Structuur

**Eén centrale Storage Box voor alle projecten**

```
/havun-backups/                              # Root
├── havunadmin/
│   ├── hot/                                 # Last 30 days
│   │   ├── 2025-11-21-havunadmin.zip
│   │   ├── 2025-11-21-havunadmin.zip.sha256
│   │   └── ...
│   └── archive/                             # 7 years
│       ├── 2025/
│       │   ├── 11/
│       │   └── 12/
│       ├── 2024/ ... 2018/
│
├── herdenkingsportaal/
│   ├── hot/
│   │   ├── 2025-11-21-herdenkingsportaal.zip
│   │   └── ...
│   └── archive/
│       └── 2025/ ... 2018/
│
├── havuncore/
│   ├── hot/
│   │   ├── 2025-11-21-havuncore.zip
│   │   └── ...
│   └── archive/
│       └── 2025/ ... 2022/  (3 jaar)
│
├── havun-mcp/
│   └── hot/  (geen archive - niet compliance vereist)
│
└── klant-sites/                             # Toekomstige klanten
    ├── klant-a-website/
    ├── klant-b-webshop/
    └── ...
```

---

## 🛠️ HavunCore BackupOrchestrator

### Service Architectuur

```php
// src/Services/BackupOrchestrator.php

namespace Havun\Core\Services;

class BackupOrchestrator
{
    /**
     * Run backup voor alle projecten of specifiek project
     */
    public function runBackup(?string $project = null): BackupResult
    {
        if ($project) {
            return $this->backupSingleProject($project);
        }

        return $this->backupAllProjects();
    }

    /**
     * Backup alle geregistreerde projecten
     */
    protected function backupAllProjects(): BackupResult
    {
        $results = [];

        foreach (config('havun.backup.projects') as $project => $config) {
            if (!$config['enabled']) {
                continue;
            }

            $results[$project] = $this->backupSingleProject($project);
        }

        return new BackupResult($results);
    }

    /**
     * Backup specifiek project
     */
    protected function backupSingleProject(string $project): ProjectBackupResult
    {
        $config = config("havun.backup.projects.{$project}");

        // 1. Determine backup strategy based on project type
        $strategy = $this->getBackupStrategy($config['type']);

        // 2. Execute backup
        $backupPath = $strategy->backup($config);

        // 3. Generate checksum
        $checksum = hash_file('sha256', $backupPath);
        file_put_contents($backupPath . '.sha256', $checksum . '  ' . basename($backupPath));

        // 4. Upload to storage locations
        $this->uploadToLocalStorage($backupPath, $project);
        $this->uploadToOffsite($backupPath, $project);

        // 5. Log backup
        $this->logBackup($project, $backupPath, $checksum);

        // 6. Cleanup old hot backups (per project retention policy)
        $this->cleanupHotBackups($project, $config['hot_retention_days']);

        // 7. Send notification
        $this->notifyBackupSuccess($project, filesize($backupPath));

        return new ProjectBackupResult([
            'project' => $project,
            'status' => 'success',
            'backup_path' => $backupPath,
            'checksum' => $checksum,
            'size' => filesize($backupPath),
        ]);
    }

    /**
     * Health check voor alle projecten
     */
    public function healthCheck(): HealthCheckResult
    {
        $results = [];

        foreach (config('havun.backup.projects') as $project => $config) {
            $results[$project] = [
                'last_backup_age' => $this->getLastBackupAge($project),
                'last_backup_size' => $this->getLastBackupSize($project),
                'checksum_valid' => $this->verifyLastChecksum($project),
                'offsite_accessible' => $this->testOffsiteConnection($project),
                'status' => $this->calculateHealthStatus($project),
            ];
        }

        return new HealthCheckResult($results);
    }

    /**
     * Restore specifiek project
     */
    public function restore(string $project, string $backupFile, array $options = []): RestoreResult
    {
        // 1. Verify checksum
        if (!$this->verifyChecksum($backupFile)) {
            throw new ChecksumMismatchException("Backup file corrupted!");
        }

        // 2. Get restore strategy
        $config = config("havun.backup.projects.{$project}");
        $strategy = $this->getRestoreStrategy($config['type']);

        // 3. Execute restore
        $result = $strategy->restore($backupFile, $config, $options);

        // 4. Log restore event
        $this->logRestore($project, $backupFile, $result);

        // 5. Send notification
        $this->notifyRestoreComplete($project);

        return $result;
    }
}
```

---

## 📋 Project Configuration

### havun.php Config

```php
// config/havun.php

return [
    'backup' => [
        // Centrale storage configuratie
        'storage' => [
            'local' => [
                'disk' => 'backups-local',
                'path' => env('BACKUP_LOCAL_PATH', '/backups'),
            ],
            'offsite' => [
                'disk' => 'hetzner-storage-box',
                'path' => '/havun-backups',
            ],
        ],

        // Project configuraties
        'projects' => [
            'havunadmin' => [
                'enabled' => true,
                'type' => 'laravel-app',
                'priority' => 'critical',
                'schedule' => '0 3 * * *',  // Daily 03:00

                'paths' => [
                    'root' => env('HAVUNADMIN_PATH', '/var/www/havunadmin/production'),
                    'database' => 'havunadmin_production',
                ],

                'include' => [
                    'database' => true,
                    'files' => [
                        'storage/app/invoices',
                        'storage/app/exports',
                    ],
                    'config' => true,  // .env backup
                ],

                'retention' => [
                    'hot_retention_days' => 30,
                    'archive_retention_years' => 7,  // Compliance!
                    'auto_cleanup_archive' => false,  // NEVER delete archive!
                ],

                'compliance' => [
                    'required' => true,
                    'type' => 'belastingdienst',  // Dutch tax law
                    'data_classification' => 'financial',
                ],

                'encryption' => [
                    'enabled' => true,
                    'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
                ],

                'notifications' => [
                    'email' => ['havun22@gmail.com'],
                    'slack' => env('SLACK_BACKUP_WEBHOOK'),
                    'on_success' => 'daily-digest',
                    'on_failure' => 'immediate',
                ],
            ],

            'herdenkingsportaal' => [
                'enabled' => true,
                'type' => 'laravel-app',
                'priority' => 'critical',
                'schedule' => '0 4 * * *',  // Daily 04:00

                'paths' => [
                    'root' => env('HERDENKINGSPORTAAL_PATH', '/var/www/herdenkingsportaal/production'),
                    'database' => 'herdenkingsportaal_production',
                ],

                'include' => [
                    'database' => true,
                    'files' => [
                        'storage/app/public/monuments',  // Monument photos
                        'storage/app/public/profiles',   // User profiles
                        'storage/app/uploads',
                    ],
                    'config' => true,
                ],

                'retention' => [
                    'hot_retention_days' => 30,
                    'archive_retention_years' => 7,  // GDPR + compliance
                    'auto_cleanup_archive' => false,
                ],

                'compliance' => [
                    'required' => true,
                    'type' => 'gdpr',
                    'data_classification' => 'personal-data',
                ],

                'encryption' => [
                    'enabled' => true,
                    'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
                ],
            ],

            'havuncore' => [
                'enabled' => true,
                'type' => 'laravel-package',
                'priority' => 'high',
                'schedule' => '0 5 * * 0',  // Weekly (Sunday 05:00)

                'paths' => [
                    'root' => env('HAVUNCORE_PATH', 'D:/GitHub/HavunCore'),
                ],

                'include' => [
                    'database' => false,  // No database
                    'files' => [
                        'src',
                        'config',
                        'storage/vault',
                        'storage/snippets',
                        'storage/orchestrations',
                        '*.md',  // Documentation
                        'composer.json',
                    ],
                    'config' => true,  // .env (vault keys!)
                    'git' => true,  // Include .git folder
                ],

                'retention' => [
                    'hot_retention_days' => 90,
                    'archive_retention_years' => 3,
                    'auto_cleanup_archive' => true,  // OK to cleanup after 3 years
                ],

                'compliance' => [
                    'required' => false,
                    'data_classification' => 'internal',
                ],

                'encryption' => [
                    'enabled' => true,  // Vault is encrypted anyway, but safe!
                ],
            ],

            'havun-mcp' => [
                'enabled' => true,
                'type' => 'nodejs-app',
                'priority' => 'medium',
                'schedule' => '0 6 * * 0',  // Weekly (Sunday 06:00)

                'paths' => [
                    'root' => env('HAVUN_MCP_PATH', 'D:/GitHub/havun-mcp'),
                ],

                'include' => [
                    'database' => true,  // clients.json, messages.json
                    'files' => [
                        'src',
                        '*.js',
                        '*.json',
                        '*.md',
                    ],
                    'config' => true,
                ],

                'retention' => [
                    'hot_retention_days' => 30,
                    'archive_retention_years' => 1,  // Kort - niet compliance
                    'auto_cleanup_archive' => true,
                ],

                'compliance' => [
                    'required' => false,
                ],

                'encryption' => [
                    'enabled' => false,  // Not sensitive
                ],
            ],
        ],

        // Monitoring
        'monitoring' => [
            'health_check_schedule' => '0 * * * *',  // Hourly
            'max_backup_age_hours' => 25,
            'min_backup_size_bytes' => 1024,  // 1 KB (not empty)
        ],

        // Notifications
        'notifications' => [
            'channels' => ['mail', 'slack'],
            'mail' => [
                'to' => env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com'),
                'from' => env('MAIL_FROM_ADDRESS'),
            ],
            'slack' => [
                'webhook' => env('SLACK_BACKUP_WEBHOOK'),
                'channel' => '#backups',
            ],
        ],
    ],
];
```

---

## 🎨 Backup Strategies (Per Project Type)

### Laravel App Strategy

```php
// src/Strategies/LaravelAppBackupStrategy.php

class LaravelAppBackupStrategy implements BackupStrategyInterface
{
    public function backup(array $config): string
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $projectName = $config['name'];
        $backupName = "{$timestamp}-{$projectName}";
        $tempDir = storage_path("backups/temp/{$backupName}");

        // 1. Create temp directory
        File::makeDirectory($tempDir, 0755, true);

        // 2. Dump database
        if ($config['include']['database']) {
            $this->dumpDatabase(
                $config['paths']['database'],
                $tempDir . '/database.sql'
            );
        }

        // 3. Copy files
        foreach ($config['include']['files'] as $path) {
            $sourcePath = $config['paths']['root'] . '/' . $path;
            $destPath = $tempDir . '/files/' . $path;
            File::copyDirectory($sourcePath, $destPath);
        }

        // 4. Backup .env
        if ($config['include']['config']) {
            File::copy(
                $config['paths']['root'] . '/.env',
                $tempDir . '/env-backup.txt'
            );
        }

        // 5. Create manifest
        $manifest = [
            'project' => $projectName,
            'timestamp' => $timestamp,
            'type' => 'laravel-app',
            'database' => $config['include']['database'],
            'files' => $config['include']['files'],
            'laravel_version' => $this->getLaravelVersion($config['paths']['root']),
            'php_version' => PHP_VERSION,
        ];
        File::put($tempDir . '/backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // 6. Compress
        $zipPath = storage_path("backups/{$backupName}.zip");
        $this->compress($tempDir, $zipPath, $config['encryption']);

        // 7. Cleanup temp
        File::deleteDirectory($tempDir);

        return $zipPath;
    }

    protected function dumpDatabase(string $database, string $outputPath): void
    {
        // Plain SQL dump (niet binary!)
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg(config('database.connections.mysql.username')),
            escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.host')),
            escapeshellarg($database),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new BackupException("Database dump failed: " . implode("\n", $output));
        }
    }

    protected function compress(string $sourceDir, string $zipPath, array $encryption): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException("Failed to create ZIP file");
        }

        // Add all files recursively
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Encryption (optional)
        if ($encryption['enabled'] && !empty($encryption['password'])) {
            $zip->setPassword($encryption['password']);
            $zip->setEncryptionName('*', ZipArchive::EM_AES_256);
        }

        $zip->close();
    }
}
```

---

### Laravel Package Strategy (HavunCore)

```php
// src/Strategies/LaravelPackageBackupStrategy.php

class LaravelPackageBackupStrategy implements BackupStrategyInterface
{
    public function backup(array $config): string
    {
        // Similar to Laravel App, maar:
        // - Geen database dump
        // - Include .git folder (complete git history)
        // - Include composer dependencies (vendor/ optioneel)
        // - Focus op source code + documentation

        $timestamp = now()->format('Y-m-d-H-i-s');
        $backupName = "{$timestamp}-havuncore";
        $tempDir = storage_path("backups/temp/{$backupName}");

        File::makeDirectory($tempDir, 0755, true);

        // Copy source, config, storage, docs
        foreach ($config['include']['files'] as $path) {
            $sourcePath = $config['paths']['root'] . '/' . $path;
            $destPath = $tempDir . '/' . $path;

            if (File::isDirectory($sourcePath)) {
                File::copyDirectory($sourcePath, $destPath);
            } else {
                File::copy($sourcePath, $destPath);
            }
        }

        // Include git history (IMPORTANT!)
        if ($config['include']['git']) {
            File::copyDirectory(
                $config['paths']['root'] . '/.git',
                $tempDir . '/.git'
            );
        }

        // Manifest
        $manifest = [
            'project' => 'havuncore',
            'timestamp' => $timestamp,
            'type' => 'laravel-package',
            'git_commit' => $this->getGitCommit($config['paths']['root']),
            'version' => $this->getPackageVersion($config['paths']['root']),
        ];
        File::put($tempDir . '/backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // Compress
        $zipPath = storage_path("backups/{$backupName}.zip");
        $this->compress($tempDir, $zipPath, $config['encryption']);

        File::deleteDirectory($tempDir);

        return $zipPath;
    }
}
```

---

### Node.js App Strategy (havun-mcp)

```php
// src/Strategies/NodejsAppBackupStrategy.php

class NodejsAppBackupStrategy implements BackupStrategyInterface
{
    public function backup(array $config): string
    {
        // Voor Node.js MCP server:
        // - Backup clients.json, messages.json
        // - Backup source code
        // - Backup package.json, package-lock.json
        // - Backup configuration

        $timestamp = now()->format('Y-m-d-H-i-s');
        $backupName = "{$timestamp}-havun-mcp";
        $tempDir = storage_path("backups/temp/{$backupName}");

        File::makeDirectory($tempDir, 0755, true);

        // Copy all configured files
        foreach ($config['include']['files'] as $pattern) {
            $this->copyByPattern(
                $config['paths']['root'],
                $tempDir,
                $pattern
            );
        }

        // Copy JSON databases
        if ($config['include']['database']) {
            File::copy(
                $config['paths']['root'] . '/clients.json',
                $tempDir . '/clients.json'
            );
            File::copy(
                $config['paths']['root'] . '/messages.json',
                $tempDir . '/messages.json'
            );
        }

        // Manifest
        $manifest = [
            'project' => 'havun-mcp',
            'timestamp' => $timestamp,
            'type' => 'nodejs-app',
            'node_version' => $this->getNodeVersion(),
        ];
        File::put($tempDir . '/backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // Compress (no encryption for MCP - not sensitive)
        $zipPath = storage_path("backups/{$backupName}.zip");
        $this->compress($tempDir, $zipPath, ['enabled' => false]);

        File::deleteDirectory($tempDir);

        return $zipPath;
    }
}
```

---

## 📡 Artisan Commands

### havun:backup:run

```bash
# Backup alle projecten
php artisan havun:backup:run

# Backup specifiek project
php artisan havun:backup:run --project=havunadmin
php artisan havun:backup:run --project=herdenkingsportaal

# Dry run (test zonder upload)
php artisan havun:backup:run --dry-run

# Force (ook al draait backup al)
php artisan havun:backup:run --force
```

---

### havun:backup:health

```bash
# Health check alle projecten
php artisan havun:backup:health

# Output example:
✅ havunadmin (Critical)
   Last backup: 18 hours ago (OK)
   Size: 52.5 MB (OK)
   Checksum: Verified (OK)
   Offsite: Accessible (OK)

✅ herdenkingsportaal (Critical)
   Last backup: 19 hours ago (OK)
   Size: 128.3 MB (OK)
   Checksum: Verified (OK)
   Offsite: Accessible (OK)

✅ havuncore (High)
   Last backup: 3 days ago (OK)
   Size: 2.1 MB (OK)
   Checksum: Verified (OK)
   Offsite: Accessible (OK)

❌ havun-mcp (Medium)
   Last backup: 9 days ago (WARNING - Weekly expected!)
   Action: Check cron job
```

---

### havun:backup:restore

```bash
# List available backups for project
php artisan havun:backup:list --project=havunadmin

# Restore latest backup
php artisan havun:backup:restore --project=havunadmin --latest

# Restore specific backup
php artisan havun:backup:restore --project=havunadmin --backup=2025-11-21-03-00-00

# Restore from archive (7 jaar geleden)
php artisan havun:backup:restore --project=havunadmin --date=2019-05-15

# Test restore (naar test environment)
php artisan havun:backup:restore --project=havunadmin --latest --test
```

---

### havun:backup:test

```bash
# Quarterly test restore for project
php artisan havun:backup:test --project=havunadmin

# Test alle projecten (quarterly routine)
php artisan havun:backup:test --all

# Output:
Running quarterly backup test for: havunadmin

✅ Download backup from offsite storage
✅ Verify SHA256 checksum
✅ Extract backup
✅ Restore database to test environment
✅ Verify table counts
✅ Test file integrity
✅ Cleanup test environment

Test Result: SUCCESS
Report saved to: /backups/test-restores/2025-Q4-havunadmin-test.log
```

---

### havun:backup:cleanup

```bash
# Cleanup oude hot backups (per project retention policy)
php artisan havun:backup:cleanup --project=havunadmin

# Cleanup alle projecten
php artisan havun:backup:cleanup --all

# Dry run (zie wat verwijderd zou worden)
php artisan havun:backup:cleanup --all --dry-run

# Output:
Cleanup for: havunadmin (Retention: 30 days)
  Found 45 backups in hot storage
  Keeping: 30 (last 30 days)
  Deleting: 15 (older than 30 days)

  ⚠️ ARCHIVE backups worden NOOIT verwijderd (7 jaar compliance)!
```

---

## 📊 Centrale BackupLog Database

### Schema

```sql
CREATE TABLE backup_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Project info
    project VARCHAR(50) NOT NULL,              -- havunadmin, herdenkingsportaal, etc.
    project_type VARCHAR(50) NOT NULL,         -- laravel-app, nodejs-app, etc.

    -- Backup info
    backup_name VARCHAR(255) NOT NULL,         -- 2025-11-21-03-00-00-havunadmin
    backup_date DATETIME NOT NULL,
    backup_size BIGINT UNSIGNED NOT NULL,      -- bytes
    backup_checksum VARCHAR(64) NOT NULL,      -- SHA256

    -- Storage locations
    disk_local BOOLEAN NOT NULL DEFAULT 1,
    disk_offsite BOOLEAN NOT NULL DEFAULT 1,
    offsite_path VARCHAR(500) NULL,            -- /havun-backups/havunadmin/hot/...

    -- Status
    status ENUM('success', 'failed', 'partial') NOT NULL,
    error_message TEXT NULL,
    duration_seconds INT UNSIGNED NULL,

    -- Compliance
    is_encrypted BOOLEAN NOT NULL DEFAULT 0,
    retention_years INT UNSIGNED NOT NULL,     -- 7 voor fiscaal, 3 voor internal
    can_auto_delete BOOLEAN NOT NULL DEFAULT 0,-- FALSE voor compliance data

    -- Notifications
    notification_sent BOOLEAN NOT NULL DEFAULT 0,
    notified_at DATETIME NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_project (project),
    INDEX idx_backup_date (backup_date),
    INDEX idx_status (status),
    INDEX idx_project_date (project, backup_date)
);

-- Restore logs (audit trail)
CREATE TABLE restore_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    project VARCHAR(50) NOT NULL,
    backup_name VARCHAR(255) NOT NULL,
    restore_date DATETIME NOT NULL,
    restore_type ENUM('production', 'test', 'archive') NOT NULL,
    restored_by VARCHAR(255) NULL,             -- User/system
    restore_reason TEXT NULL,

    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_project (project),
    INDEX idx_restore_date (restore_date)
);

-- Test restore logs (quarterly tests)
CREATE TABLE backup_test_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    project VARCHAR(50) NOT NULL,
    test_quarter VARCHAR(10) NOT NULL,         -- 2025-Q4
    test_date DATETIME NOT NULL,
    backup_tested VARCHAR(255) NOT NULL,

    test_result ENUM('pass', 'fail') NOT NULL,
    test_report TEXT NULL,                     -- Full test output

    checked_items JSON NULL,                   -- {database: true, files: true, ...}

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_project (project),
    INDEX idx_test_quarter (test_quarter)
);
```

---

## 🔔 Unified Notifications

### Daily Digest Email

**Subject:** `[HavunCore] Daily Backup Report - 2025-11-21`

```
Daily Backup Report
Date: 21 November 2025

────────────────────────────────────────

✅ ALL BACKUPS SUCCESSFUL

Projects backed up today:

1. HavunAdmin (Critical)
   Time: 03:00 AM
   Size: 52.5 MB
   Status: ✅ Success
   Offsite: ✅ Uploaded
   Checksum: ✅ Verified

2. Herdenkingsportaal (Critical)
   Time: 04:00 AM
   Size: 128.3 MB
   Status: ✅ Success
   Offsite: ✅ Uploaded
   Checksum: ✅ Verified

────────────────────────────────────────

Storage Status:
  Local (Hot): 12.5 GB used
  Offsite (Archive): 245.8 GB used (7 years)

Health Check: ✅ ALL SYSTEMS HEALTHY

────────────────────────────────────────

Automated email from HavunCore Backup Orchestrator
```

---

### Failure Alert (Immediate)

**Subject:** `🚨 [HavunCore] BACKUP FAILED - HavunAdmin - 2025-11-21`

```
🚨 BACKUP FAILURE ALERT

Project: HavunAdmin (CRITICAL)
Date: 21 November 2025 03:00 AM
Status: ❌ FAILED

Error:
  Database dump failed: Connection refused to mysql:3306

Impact:
  - No backup created today
  - Last successful backup: 20 November 2025 (24h ago)
  - ⚠️ COMPLIANCE RISK if not resolved within 24h

Action Required:
  1. Check MySQL service status
  2. Verify database credentials
  3. Manually run: php artisan havun:backup:run --project=havunadmin
  4. Check logs: storage/logs/laravel.log

────────────────────────────────────────

IMMEDIATE ACTION REQUIRED - Critical Project
```

---

### Slack Notification

```json
{
  "text": "🚨 Backup Failed: HavunAdmin",
  "attachments": [
    {
      "color": "danger",
      "fields": [
        {
          "title": "Project",
          "value": "HavunAdmin (Critical)",
          "short": true
        },
        {
          "title": "Status",
          "value": "❌ Failed",
          "short": true
        },
        {
          "title": "Error",
          "value": "Database connection refused",
          "short": false
        },
        {
          "title": "Last Successful Backup",
          "value": "24 hours ago",
          "short": true
        },
        {
          "title": "Action",
          "value": "Check MySQL service",
          "short": true
        }
      ],
      "footer": "HavunCore Backup Orchestrator",
      "ts": 1732161600
    }
  ]
}
```

---

## ⚙️ Cron Schedule (Centrale Server)

```bash
# crontab -e

# HavunAdmin - Daily 03:00
0 3 * * * cd /var/www/havuncore && php artisan havun:backup:run --project=havunadmin

# Herdenkingsportaal - Daily 04:00
0 4 * * * cd /var/www/havuncore && php artisan havun:backup:run --project=herdenkingsportaal

# HavunCore - Weekly Sunday 05:00
0 5 * * 0 cd /var/www/havuncore && php artisan havun:backup:run --project=havuncore

# havun-mcp - Weekly Sunday 06:00
0 6 * * 0 cd /var/www/havuncore && php artisan havun:backup:run --project=havun-mcp

# Health Check - Hourly
0 * * * * cd /var/www/havuncore && php artisan havun:backup:health

# Cleanup Hot Backups - Daily 07:00
0 7 * * * cd /var/www/havuncore && php artisan havun:backup:cleanup --all

# Weekly Report - Sunday 08:00
0 8 * * 0 cd /var/www/havuncore && php artisan havun:backup:report --weekly
```

---

## 💰 Cost Breakdown

### Hetzner Storage Box

**Geschatte totale storage over 7 jaar:**

| Project | Daily Size | 7 Years Total | Archive Only |
|---------|------------|---------------|--------------|
| HavunAdmin | 50 MB | ~130 GB | ~130 GB |
| Herdenkingsportaal | 150 MB | ~385 GB | ~385 GB |
| HavunCore | 3 MB (weekly) | ~1.1 GB | ~1.1 GB |
| havun-mcp | 5 MB (weekly) | ~1.9 GB | (1 jaar = 260 MB) |
| **Totaal** | - | **~518 GB** | **~516 GB** |

**Aanbevolen Storage Box:**
- **BX30 (5 TB):** €19,04/maand
- Ruimte voor 9x current capacity (future growth!)
- **7-Year Total Cost:** €19,04 × 12 × 7 = **€1.599,36**

**Per project per year:**
- €1.599,36 / 7 / 4 projects = **~€57/project/jaar** voor 7 jaar compliance

---

## 📚 Implementation Roadmap

### Fase 1: Core Infrastructure (Week 1)

- [ ] Maak BackupOrchestrator service
- [ ] Implementeer backup strategies (Laravel, Node.js)
- [ ] Database migrations (backup_logs, restore_logs, test_logs)
- [ ] Basic Artisan commands (run, health, list)

### Fase 2: Storage & Upload (Week 2)

- [ ] Hetzner Storage Box account aanmaken
- [ ] SFTP driver configureren
- [ ] Upload mechanisme implementeren
- [ ] Checksum verificatie

### Fase 3: Monitoring & Notifications (Week 2-3)

- [ ] Email notifications
- [ ] Slack integration (optional)
- [ ] Health check command
- [ ] Daily/weekly reports

### Fase 4: Restore & Testing (Week 3)

- [ ] Restore command implementeren
- [ ] Test restore procedure
- [ ] Quarterly test automation
- [ ] Documentatie restore procedures

### Fase 5: Production Deployment (Week 4)

- [ ] Deploy naar HavunCore productie
- [ ] Setup cron jobs
- [ ] Eerste backups van alle projecten
- [ ] Test restore van elk project
- [ ] Documentatie voor team

---

## 🎯 Next Steps

1. **Review architectuur** met team
2. **Approve budget** (€19/maand Hetzner Storage Box)
3. **Start implementatie** Fase 1
4. **Test op staging** servers eerst
5. **Deploy naar productie** na testing

---

**Multi-Project Backup System**
**Powered by HavunCore Orchestration** 🚀
**Compliance-Ready for All Havun Projects**

---

📧 **Questions?** havun22@gmail.com
📂 **Documentation:** D:\GitHub\HavunCore\*.md
