# 🚀 Backup System Implementation Guide

**Voor:** HavunCore Multi-Project Backup System
**Versie:** 1.0.0
**Geschatte tijd:** 3-4 dagen implementatie + 1 dag testing

---

## 📋 Prerequisites Checklist

Voordat je begint, zorg dat je het volgende hebt:

- [ ] **Hetzner Storage Box** account (zie `HETZNER-STORAGE-BOX-SETUP.md`)
- [ ] **SFTP credentials** voor Storage Box
- [ ] **Backup encryption password** (32+ characters, random)
- [ ] **MySQL database** access voor alle projecten
- [ ] **Server toegang** tot alle project directories
- [ ] **Email credentials** voor notificaties
- [ ] **Slack webhook** (optioneel)

---

## 🏗️ Fase 1: Core Infrastructure (Dag 1)

### Stap 1.1: Database Migrations

```bash
cd D:/GitHub/HavunCore

# Maak migrations voor backup logging
php artisan make:migration create_backup_logs_table
php artisan make:migration create_restore_logs_table
php artisan make:migration create_backup_test_logs_table
```

**Migration 1: backup_logs**
```php
// database/migrations/YYYY_MM_DD_create_backup_logs_table.php

public function up()
{
    Schema::create('backup_logs', function (Blueprint $table) {
        $table->id();

        // Project info
        $table->string('project', 50);
        $table->string('project_type', 50);

        // Backup info
        $table->string('backup_name');
        $table->dateTime('backup_date');
        $table->unsignedBigInteger('backup_size');
        $table->string('backup_checksum', 64);

        // Storage locations
        $table->boolean('disk_local')->default(true);
        $table->boolean('disk_offsite')->default(true);
        $table->string('offsite_path', 500)->nullable();

        // Status
        $table->enum('status', ['success', 'failed', 'partial']);
        $table->text('error_message')->nullable();
        $table->unsignedInteger('duration_seconds')->nullable();

        // Compliance
        $table->boolean('is_encrypted')->default(false);
        $table->unsignedInteger('retention_years');
        $table->boolean('can_auto_delete')->default(false);

        // Notifications
        $table->boolean('notification_sent')->default(false);
        $table->dateTime('notified_at')->nullable();

        $table->timestamps();

        // Indexes
        $table->index('project');
        $table->index('backup_date');
        $table->index('status');
        $table->index(['project', 'backup_date']);
    });
}
```

**Migration 2: restore_logs**
```php
// database/migrations/YYYY_MM_DD_create_restore_logs_table.php

public function up()
{
    Schema::create('restore_logs', function (Blueprint $table) {
        $table->id();

        $table->string('project', 50);
        $table->string('backup_name');
        $table->dateTime('restore_date');
        $table->enum('restore_type', ['production', 'test', 'archive']);
        $table->string('restored_by')->nullable();
        $table->text('restore_reason')->nullable();

        $table->enum('status', ['success', 'failed']);
        $table->text('error_message')->nullable();

        $table->timestamp('created_at')->useCurrent();

        $table->index('project');
        $table->index('restore_date');
    });
}
```

**Migration 3: backup_test_logs**
```php
// database/migrations/YYYY_MM_DD_create_backup_test_logs_table.php

public function up()
{
    Schema::create('backup_test_logs', function (Blueprint $table) {
        $table->id();

        $table->string('project', 50);
        $table->string('test_quarter', 10); // 2025-Q4
        $table->dateTime('test_date');
        $table->string('backup_tested');

        $table->enum('test_result', ['pass', 'fail']);
        $table->text('test_report')->nullable();

        $table->json('checked_items')->nullable();

        $table->timestamp('created_at')->useCurrent();

        $table->index('project');
        $table->index('test_quarter');
    });
}
```

**Run migrations:**
```bash
php artisan migrate
```

---

### Stap 1.2: Models

**BackupLog Model**
```php
// src/Models/BackupLog.php

namespace Havun\Core\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $fillable = [
        'project',
        'project_type',
        'backup_name',
        'backup_date',
        'backup_size',
        'backup_checksum',
        'disk_local',
        'disk_offsite',
        'offsite_path',
        'status',
        'error_message',
        'duration_seconds',
        'is_encrypted',
        'retention_years',
        'can_auto_delete',
        'notification_sent',
        'notified_at',
    ];

    protected $casts = [
        'backup_date' => 'datetime',
        'disk_local' => 'boolean',
        'disk_offsite' => 'boolean',
        'is_encrypted' => 'boolean',
        'can_auto_delete' => 'boolean',
        'notification_sent' => 'boolean',
        'notified_at' => 'datetime',
    ];

    /**
     * Scope: Laatste backup per project
     */
    public function scopeLatestByProject($query, string $project)
    {
        return $query->where('project', $project)
            ->orderBy('backup_date', 'desc')
            ->first();
    }

    /**
     * Scope: Succesvolle backups
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Vandaag
     */
    public function scopeToday($query)
    {
        return $query->whereDate('backup_date', today());
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->backup_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get backup age in hours
     */
    public function getAgeHoursAttribute(): float
    {
        return now()->diffInHours($this->backup_date);
    }

    /**
     * Check if backup is too old
     */
    public function isTooOld(int $maxHours = 25): bool
    {
        return $this->age_hours > $maxHours;
    }
}
```

**RestoreLog Model** (similar pattern)
**BackupTestLog Model** (similar pattern)

---

### Stap 1.3: Config File

**config/havun.php** (backup sectie)
```php
// config/havun.php

return [
    'backup' => [
        // Storage
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

        // Encryption
        'encryption' => [
            'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
            'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
        ],

        // Projects (zie MULTI-PROJECT-BACKUP-SYSTEM.md voor complete config)
        'projects' => [
            'havunadmin' => [
                'enabled' => env('BACKUP_HAVUNADMIN_ENABLED', true),
                'type' => 'laravel-app',
                'priority' => 'critical',
                'schedule' => '0 3 * * *',

                'paths' => [
                    'root' => env('HAVUNADMIN_PATH'),
                    'database' => env('HAVUNADMIN_DATABASE', 'havunadmin_production'),
                ],

                'include' => [
                    'database' => true,
                    'files' => [
                        'storage/app/invoices',
                        'storage/app/exports',
                    ],
                    'config' => true,
                ],

                'retention' => [
                    'hot_retention_days' => 30,
                    'archive_retention_years' => 7,
                    'auto_cleanup_archive' => false,
                ],

                'compliance' => [
                    'required' => true,
                    'type' => 'belastingdienst',
                ],

                'notifications' => [
                    'email' => [env('BACKUP_NOTIFICATION_EMAIL')],
                    'on_success' => 'daily-digest',
                    'on_failure' => 'immediate',
                ],
            ],

            // ... andere projecten (herdenkingsportaal, havuncore, havun-mcp)
        ],

        // Monitoring
        'monitoring' => [
            'health_check_schedule' => '0 * * * *',
            'max_backup_age_hours' => 25,
            'min_backup_size_bytes' => 1024,
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

### Stap 1.4: Filesystem Disks

**config/filesystems.php** (add disks)
```php
'disks' => [
    // ... existing disks

    'backups-local' => [
        'driver' => 'local',
        'root' => env('BACKUP_LOCAL_PATH', storage_path('backups')),
    ],

    'hetzner-storage-box' => [
        'driver' => 'sftp',
        'host' => env('HETZNER_STORAGE_HOST'),
        'username' => env('HETZNER_STORAGE_USERNAME'),
        'password' => env('HETZNER_STORAGE_PASSWORD'),
        'root' => '/havun-backups',
        'timeout' => 30,
        'directoryPerm' => 0755,
    ],
],
```

---

### Stap 1.5: Environment Variables

**.env additions**
```env
# Backup Configuration
BACKUP_LOCAL_PATH=/backups
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=your-super-secure-random-32-char-password-here-xyz

# Hetzner Storage Box
HETZNER_STORAGE_HOST=uXXXXXX.your-storagebox.de
HETZNER_STORAGE_USERNAME=uXXXXXX
HETZNER_STORAGE_PASSWORD=your-storage-box-password

# Project Paths
HAVUNADMIN_PATH=/var/www/havunadmin/production
HAVUNADMIN_DATABASE=havunadmin_production
BACKUP_HAVUNADMIN_ENABLED=true

HERDENKINGSPORTAAL_PATH=/var/www/herdenkingsportaal/production
HERDENKINGSPORTAAL_DATABASE=herdenkingsportaal_production
BACKUP_HERDENKINGSPORTAAL_ENABLED=true

HAVUNCORE_PATH=D:/GitHub/HavunCore
BACKUP_HAVUNCORE_ENABLED=true

HAVUN_MCP_PATH=D:/GitHub/havun-mcp
BACKUP_HAVUN_MCP_ENABLED=true

# Notifications
BACKUP_NOTIFICATION_EMAIL=havun22@gmail.com
SLACK_BACKUP_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

---

## 🏗️ Fase 2: Backup Service (Dag 2)

### Stap 2.1: BackupOrchestrator Service

```bash
# Create service file
touch src/Services/BackupOrchestrator.php
```

```php
// src/Services/BackupOrchestrator.php

namespace Havun\Core\Services;

use Havun\Core\Models\BackupLog;
use Havun\Core\Contracts\BackupStrategyInterface;
use Havun\Core\Strategies\LaravelAppBackupStrategy;
use Havun\Core\Strategies\LaravelPackageBackupStrategy;
use Havun\Core\Strategies\NodejsAppBackupStrategy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BackupOrchestrator
{
    protected array $strategies = [];

    public function __construct()
    {
        // Register backup strategies
        $this->strategies = [
            'laravel-app' => new LaravelAppBackupStrategy(),
            'laravel-package' => new LaravelPackageBackupStrategy(),
            'nodejs-app' => new NodejsAppBackupStrategy(),
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

        $config = config("havun.backup.projects.{$project}");
        if (!$config) {
            throw new \Exception("Project configuration not found: {$project}");
        }

        $config['name'] = $project;

        // 1. Get backup strategy
        $strategy = $this->getStrategy($config['type']);

        // 2. Execute backup
        Log::info("Starting backup for project: {$project}");
        $backupPath = $strategy->backup($config);

        // 3. Calculate checksum
        $checksum = hash_file('sha256', $backupPath);
        $checksumFile = $backupPath . '.sha256';
        file_put_contents($checksumFile, $checksum . '  ' . basename($backupPath));

        // 4. Upload to local storage
        $localPath = $this->uploadToLocal($backupPath, $checksumFile, $project);

        // 5. Upload to offsite storage
        $offsitePath = $this->uploadToOffsite($backupPath, $checksumFile, $project);

        // 6. Cleanup temp backup
        @unlink($backupPath);
        @unlink($checksumFile);

        $duration = round(microtime(true) - $startTime, 2);

        // 7. Log to database
        $backupLog = BackupLog::create([
            'project' => $project,
            'project_type' => $config['type'],
            'backup_name' => basename($backupPath, '.zip'),
            'backup_date' => now(),
            'backup_size' => filesize($backupPath),
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

        Log::info("Backup completed for project: {$project}", [
            'duration' => $duration . 's',
            'size' => $backupLog->formatted_size,
        ]);

        // 8. Send notification
        $this->sendNotification($project, $backupLog, 'success');

        // 9. Cleanup old hot backups
        $this->cleanupHotBackups($project, $config['retention']['hot_retention_days']);

        return [
            'status' => 'success',
            'backup_name' => $backupLog->backup_name,
            'size' => $backupLog->formatted_size,
            'checksum' => $checksum,
            'duration' => $duration,
        ];
    }

    /**
     * Get backup strategy voor project type
     */
    protected function getStrategy(string $type): BackupStrategyInterface
    {
        if (!isset($this->strategies[$type])) {
            throw new \Exception("Unknown backup strategy: {$type}");
        }

        return $this->strategies[$type];
    }

    /**
     * Upload naar local storage
     */
    protected function uploadToLocal(string $backupPath, string $checksumPath, string $project): ?string
    {
        try {
            $disk = Storage::disk(config('havun.backup.storage.local.disk'));

            $filename = basename($backupPath);
            $destinationPath = "{$project}/hot/{$filename}";

            $disk->put($destinationPath, file_get_contents($backupPath));
            $disk->put($destinationPath . '.sha256', file_get_contents($checksumPath));

            return $destinationPath;
        } catch (\Exception $e) {
            Log::error("Failed to upload to local storage", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Upload naar offsite storage (Hetzner Storage Box)
     */
    protected function uploadToOffsite(string $backupPath, string $checksumPath, string $project): ?string
    {
        try {
            $disk = Storage::disk(config('havun.backup.storage.offsite.disk'));

            $filename = basename($backupPath);
            $year = date('Y');
            $month = date('m');

            // Archive path: /project/archive/YYYY/MM/filename.zip
            $destinationPath = "{$project}/archive/{$year}/{$month}/{$filename}";

            $disk->put($destinationPath, file_get_contents($backupPath));
            $disk->put($destinationPath . '.sha256', file_get_contents($checksumPath));

            return $destinationPath;
        } catch (\Exception $e) {
            Log::error("Failed to upload to offsite storage", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cleanup oude hot backups
     */
    protected function cleanupHotBackups(string $project, int $retentionDays): void
    {
        try {
            $disk = Storage::disk(config('havun.backup.storage.local.disk'));
            $hotPath = "{$project}/hot";

            $files = $disk->files($hotPath);
            $cutoffDate = now()->subDays($retentionDays);

            foreach ($files as $file) {
                $lastModified = $disk->lastModified($file);
                if ($lastModified < $cutoffDate->timestamp) {
                    $disk->delete($file);
                    Log::info("Deleted old hot backup", ['file' => $file]);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Cleanup hot backups failed", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send notification
     */
    protected function sendNotification(string $project, BackupLog $backupLog, string $type): void
    {
        $config = config("havun.backup.projects.{$project}.notifications");

        if (!$config) {
            return;
        }

        // Email notification
        if (isset($config['email'])) {
            // TODO: Implement email notification
        }

        // Slack notification
        if (isset($config['slack'])) {
            // TODO: Implement Slack notification
        }

        $backupLog->update([
            'notification_sent' => true,
            'notified_at' => now(),
        ]);
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
                'last_backup_date' => $lastBackup?->backup_date,
                'last_backup_age_hours' => $lastBackup?->age_hours,
                'last_backup_size' => $lastBackup?->formatted_size,
                'is_too_old' => $lastBackup ? $lastBackup->isTooOld() : true,
                'checksum_valid' => $lastBackup ? $this->verifyChecksum($lastBackup) : false,
                'status' => $this->calculateHealthStatus($projectName, $lastBackup),
            ];
        }

        return $results;
    }

    /**
     * Verify checksum van laatste backup
     */
    protected function verifyChecksum(BackupLog $backupLog): bool
    {
        try {
            $disk = Storage::disk(config('havun.backup.storage.local.disk'));
            $backupPath = "{$backupLog->project}/hot/{$backupLog->backup_name}.zip";

            if (!$disk->exists($backupPath)) {
                return false;
            }

            $actualChecksum = hash('sha256', $disk->get($backupPath));
            return $actualChecksum === $backupLog->backup_checksum;
        } catch (\Exception $e) {
            Log::error("Checksum verification failed", ['error' => $e->getMessage()]);
            return false;
        }
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
```

Dit is ongeveer 25% van de complete implementation guide. Zal ik doorgaan met:
- Fase 3: Backup Strategies implementatie
- Fase 4: Artisan Commands
- Fase 5: Testing
- Fase 6: Deployment

Of wil je eerst feedback op wat ik tot nu toe heb gemaakt?