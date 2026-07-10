<?php

namespace HWS\BaseTools\AdminDashboard;

final class DashboardModuleDefinition {
    /**
     * @param string[] $files
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly mixed $renderer,
        public readonly array $files = [],
        public readonly bool $deprecated = false,
        public readonly mixed $visible = true
    ) {
    }

    public function is_visible(): bool {
        return is_callable( $this->visible )
            ? (bool) call_user_func( $this->visible )
            : (bool) $this->visible;
    }
}
