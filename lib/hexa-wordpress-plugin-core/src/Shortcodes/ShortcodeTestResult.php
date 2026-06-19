<?php

namespace Hexa\PluginCore\Shortcodes;

final class ShortcodeTestResult {
    public function __construct(
        public readonly string $shortcode,
        public readonly string $status,
        public readonly string $output,
        public readonly bool $registered = true
    ) {
    }
}

