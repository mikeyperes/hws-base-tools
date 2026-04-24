<?php

namespace HWS\BaseTools\Core;

final class Autoloader {
    /**
     * Register a PSR-4 style autoloader for the new structured plugin code.
     *
     * @param string $base_dir Absolute path to the src directory.
     */
    public static function register( $base_dir ) {
        static $registered = false;

        if ( $registered ) {
            return;
        }

        $base_dir = rtrim( (string) $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

        spl_autoload_register(
            static function( $class_name ) use ( $base_dir ) {
                $prefix = 'HWS\\BaseTools\\';

                if ( strpos( $class_name, $prefix ) !== 0 ) {
                    return;
                }

                $relative_class = substr( $class_name, strlen( $prefix ) );
                $relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
                $file_path      = $base_dir . $relative_path;

                if ( is_readable( $file_path ) ) {
                    require_once $file_path;
                }
            }
        );

        $registered = true;
    }
}
