# Havun Local Backup Script
# Run manually or schedule via Task Scheduler
# Usage: .\local-backup.ps1 [-BackupPath "E:\Backups"]

param(
    [string]$BackupPath = "D:\Backups\Havun"
)

$ErrorActionPreference = "Stop"

# Config - pas aan indien nodig
$MySQLPath = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin"
$MySQLUser = "root"
$MySQLPass = ""  # Laragon default = no password

# Check if Laragon MySQL is running
$mysqlRunning = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
if (-not $mysqlRunning) {
    Write-Host "WARNING: MySQL (Laragon) is not running. Database backups will be skipped." -ForegroundColor Yellow
    Write-Host "Start Laragon first for database backups." -ForegroundColor Yellow
    Write-Host ""
}

$Date = Get-Date -Format "yyyy-MM-dd"
$BackupDir = "$BackupPath\$Date"

# Databases to backup
$Databases = @(
    "havunadmin",
    "herdenkingsportaal",
    "infosyst",
    "safehavun",
    "studieplanner",
    "judotoernooi"
)

# Project folders to backup (storage with important data)
$StorageFolders = @{
    "HavunAdmin" = "D:\GitHub\HavunAdmin\storage\invoices"
    "Herdenkingsportaal" = "D:\GitHub\Herdenkingsportaal\storage\app\public"
}

# GitHub folder (all code)
$GitHubFolder = "D:\GitHub"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Havun Local Backup - $Date" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Create backup directory
New-Item -ItemType Directory -Force -Path "$BackupDir\databases" | Out-Null
New-Item -ItemType Directory -Force -Path "$BackupDir\storage" | Out-Null

# Backup databases (only if MySQL is running)
Write-Host "Backing up databases..." -ForegroundColor Yellow
if ($mysqlRunning) {
    foreach ($db in $Databases) {
        Write-Host "  - $db... " -NoNewline
        try {
            $dumpFile = "$BackupDir\databases\$db.sql"
            $env:MYSQL_PWD = $MySQLPass
            $result = & "$MySQLPath\mysqldump.exe" -u $MySQLUser --single-transaction --routines --triggers $db 2>&1

            if ($LASTEXITCODE -eq 0 -and $result) {
                $result | Out-File -FilePath $dumpFile -Encoding UTF8
                # Compress
                Compress-Archive -Path $dumpFile -DestinationPath "$dumpFile.zip" -Force
                Remove-Item $dumpFile
                $size = (Get-Item "$dumpFile.zip").Length / 1KB
                Write-Host "OK ($([math]::Round($size, 1)) KB)" -ForegroundColor Green
            } else {
                Write-Host "SKIP (db not found)" -ForegroundColor Gray
            }
        } catch {
            Write-Host "FAILED: $_" -ForegroundColor Red
        }
    }
} else {
    Write-Host "  SKIPPED - MySQL not running" -ForegroundColor Gray
}

# Backup storage folders
Write-Host ""
Write-Host "Backing up storage folders..." -ForegroundColor Yellow
foreach ($project in $StorageFolders.Keys) {
    $folder = $StorageFolders[$project]
    Write-Host "  - $project... " -NoNewline
    if (Test-Path $folder) {
        try {
            $zipFile = "$BackupDir\storage\$project-storage.zip"
            Compress-Archive -Path $folder -DestinationPath $zipFile -Force
            $size = (Get-Item $zipFile).Length / 1MB
            Write-Host "OK ($([math]::Round($size, 2)) MB)" -ForegroundColor Green
        } catch {
            Write-Host "FAILED: $_" -ForegroundColor Red
        }
    } else {
        Write-Host "SKIP (folder not found)" -ForegroundColor Gray
    }
}

# Backup .env files (without secrets in filename)
Write-Host ""
Write-Host "Backing up .env files..." -ForegroundColor Yellow
New-Item -ItemType Directory -Force -Path "$BackupDir\env-files" | Out-Null
$projects = Get-ChildItem -Path $GitHubFolder -Directory | Where-Object { Test-Path "$($_.FullName)\.env" }
foreach ($project in $projects) {
    $envFile = "$($project.FullName)\.env"
    if (Test-Path $envFile) {
        Copy-Item $envFile "$BackupDir\env-files\$($project.Name).env"
        Write-Host "  - $($project.Name)" -ForegroundColor Green
    }
}

# Create checksums (compatible with older PowerShell)
Write-Host ""
Write-Host "Creating checksums..." -ForegroundColor Yellow
$checksums = @()
Get-ChildItem -Path $BackupDir -Recurse -File | ForEach-Object {
    try {
        $sha256 = [System.Security.Cryptography.SHA256]::Create()
        $stream = [System.IO.File]::OpenRead($_.FullName)
        $hashBytes = $sha256.ComputeHash($stream)
        $stream.Close()
        $hash = [BitConverter]::ToString($hashBytes) -replace '-', ''
        $relativePath = $_.FullName.Replace("$BackupDir\", "")
        $checksums += "$hash  $relativePath"
    } catch {
        # Skip files that can't be hashed
    }
}
if ($checksums.Count -gt 0) {
    $checksums | Out-File -FilePath "$BackupDir\checksums.sha256" -Encoding UTF8
    Write-Host "  Created checksums for $($checksums.Count) files" -ForegroundColor Green
}

# Summary
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
$totalSize = (Get-ChildItem -Path $BackupDir -Recurse -File | Measure-Object -Property Length -Sum).Sum / 1MB
Write-Host "  Backup complete: $([math]::Round($totalSize, 2)) MB" -ForegroundColor Green
Write-Host "  Location: $BackupDir" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

# Cleanup old backups (keep 30 days)
Write-Host ""
Write-Host "Cleaning up old backups..." -ForegroundColor Yellow
$cutoffDate = (Get-Date).AddDays(-30)
Get-ChildItem -Path $BackupPath -Directory | Where-Object {
    $_.Name -match '^\d{4}-\d{2}-\d{2}$' -and [datetime]::ParseExact($_.Name, 'yyyy-MM-dd', $null) -lt $cutoffDate
} | ForEach-Object {
    Write-Host "  Removing $($_.Name)..." -ForegroundColor Gray
    Remove-Item $_.FullName -Recurse -Force
}

Write-Host ""
Write-Host "Done!" -ForegroundColor Green
