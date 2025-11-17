<?php

namespace Havun\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when HavunCore is deployed/updated
 *
 * Automatically notifies all projects that use HavunCore
 */
class HavunCoreDeployed
{
    use Dispatchable;

    public string $version;
    public array $changes;
    public bool $breakingChanges;
    public array $requiredActions;

    /**
     * @param string $version New version (e.g., "v0.2.2")
     * @param array $changes List of changes
     * @param bool $breakingChanges Whether there are breaking changes
     * @param array $requiredActions Actions required by consuming projects
     */
    public function __construct(
        string $version,
        array $changes,
        bool $breakingChanges = false,
        array $requiredActions = []
    ) {
        $this->version = $version;
        $this->changes = $changes;
        $this->breakingChanges = $breakingChanges;
        $this->requiredActions = $requiredActions;
    }
}
