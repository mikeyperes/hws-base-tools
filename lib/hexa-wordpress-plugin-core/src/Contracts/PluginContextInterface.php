<?php

namespace Hexa\PluginCore\Contracts;

interface PluginContextInterface {
    public function get( string $key, mixed $default = null ): mixed;

    public function all(): array;
}

