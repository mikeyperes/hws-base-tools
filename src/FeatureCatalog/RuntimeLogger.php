<?php

namespace HWS\BaseTools\FeatureCatalog;

final class RuntimeLogger {
    public static function log( mixed $value, bool $full_debug = false, bool $display_stack = false ): void {
        if ( ! $full_debug || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
            return;
        }

        $message = is_scalar( $value ) || null === $value
            ? (string) $value
            : print_r( $value, true );

        if ( $display_stack ) {
            foreach ( array_slice( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), 2 ) as $index => $frame ) {
                $message .= sprintf(
                    "\nStack #%d -> %s%s() in %s on line %s",
                    $index + 1,
                    isset( $frame['class'] ) ? $frame['class'] . ( $frame['type'] ?? '' ) : '',
                    $frame['function'] ?? 'N/A',
                    $frame['file'] ?? 'N/A',
                    $frame['line'] ?? 'N/A'
                );
            }
        }

        error_log( '[HWS Base Tools] ' . $message );
    }
}
