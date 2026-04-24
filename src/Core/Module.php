<?php

namespace HWS\BaseTools\Core;

interface Module {
    /**
     * Register WordPress hooks for the module.
     */
    public function register();
}
