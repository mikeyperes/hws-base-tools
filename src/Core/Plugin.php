<?php

namespace HWS\BaseTools\Core;

final class Plugin {
    /**
     * @var Module[]
     */
    private $modules = [];

    /**
     * @var bool
     */
    private $booted = false;

    /**
     * Add a module to the structured bootstrap.
     *
     * @param Module $module Module instance.
     *
     * @return $this
     */
    public function add_module( Module $module ) {
        $this->modules[] = $module;

        return $this;
    }

    /**
     * Register hooks for all configured modules.
     */
    public function boot() {
        if ( $this->booted ) {
            return;
        }

        foreach ( $this->modules as $module ) {
            $module->register();
        }

        $this->booted = true;
    }
}
