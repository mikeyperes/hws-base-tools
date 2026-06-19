<?php

namespace Hexa\PluginCore\Activity;

final class ActivityLogEntry {
    public function __construct(
        public readonly string $message,
        public readonly array $context = [],
        public readonly ?string $actor = null,
        public readonly ?string $source = null,
        public readonly ?string $timestamp = null
    ) {
    }

    public function to_array(): array {
        return [
            'message'   => $this->message,
            'context'   => $this->context,
            'actor'     => $this->actor,
            'source'    => $this->source,
            'timestamp' => $this->timestamp,
        ];
    }
}

