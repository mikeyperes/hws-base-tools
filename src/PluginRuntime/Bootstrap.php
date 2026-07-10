<?php

namespace HWS\BaseTools\PluginRuntime;

final class Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        self::$booted = true;

        $legacy_runtime = PluginMetadata::root_path() . '/src/LegacyCompatibility/legacy-runtime.php';
        if ( ! is_readable( $legacy_runtime ) ) {
            throw new \RuntimeException( 'HWS Base Tools legacy compatibility runtime is missing.' );
        }

        require_once $legacy_runtime;
    }
}
