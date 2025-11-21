<?php

namespace Havun\Core\Contracts;

interface BackupStrategyInterface
{
    /**
     * Execute backup for given project configuration
     *
     * @param array $config Project configuration
     * @return string Path to created backup file
     * @throws \Exception on backup failure
     */
    public function backup(array $config): string;

    /**
     * Restore backup for given project
     *
     * @param string $backupPath Path to backup file
     * @param array $config Project configuration
     * @param array $options Restore options
     * @return array Restore result
     * @throws \Exception on restore failure
     */
    public function restore(string $backupPath, array $config, array $options = []): array;
}
