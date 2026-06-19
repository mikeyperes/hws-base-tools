<?php

namespace Hexa\PluginCore\Bootstrap;

use Hexa\PluginCore\Contracts\ModuleInterface;
use Hexa\PluginCore\Contracts\PluginContextInterface;

final class CoreBootstrap {
    private PluginContextInterface $context;

    /**
     * @var ModuleInterface[]
     */
    private array $modules = [];

    private bool $booted = false;

    public function __construct( PluginContextInterface $context ) {
        $this->context = $context;
    }

    public function context(): PluginContextInterface {
        return $this->context;
    }

    public function add_module( ModuleInterface $module ): self {
        $this->modules[] = $module;

        return $this;
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        foreach ( $this->modules as $module ) {
            $module->register();
        }

        $this->booted = true;
    }
}

