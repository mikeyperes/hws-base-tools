<?php

namespace Hexa\PluginCore\Activity;

final class ActivityLogger {
    /**
     * @var ActivityLogEntry[]
     */
    private array $entries = [];

    public function add( ActivityLogEntry $entry ): void {
        $this->entries[] = $entry;
    }

    /**
     * @return ActivityLogEntry[]
     */
    public function all(): array {
        return $this->entries;
    }
}

