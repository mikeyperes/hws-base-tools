<?php

namespace Hexa\PluginCore\Tabs;

final class TabDefinition {
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly mixed $renderer,
        public readonly ?string $capability = null,
        public readonly bool $deprecated = false
    ) {
    }
}

