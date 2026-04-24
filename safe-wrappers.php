<?php namespace hws_base_tools;

/**
 * Safe Wrappers for AJAX, Shell Commands, and Error Handling
 * 
 * Provides:
 * - Safe AJAX handler with try-catch, nonce, and capability checks
 * - Safe shell_exec that checks for disabled functions
 * - Safe constant/ini access
 * 
 * @since 8.9.5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Nonce action name for HWS AJAX calls
 */
define( __NAMESPACE__ . '\\HWS_AJAX_NONCE', 'hws_base_tools_ajax_nonce' );

/**
 * Create a nonce for AJAX calls
 * 
 * @return string Nonce value
 */
function hws_create_nonce() {
    return wp_create_nonce( HWS_AJAX_NONCE );
}

/**
 * Verify AJAX nonce
 * 
 * @param string $nonce Nonce to verify (defaults to $_POST['nonce'] or $_REQUEST['_wpnonce'])
 * @return bool True if valid, false otherwise
 */
function hws_verify_nonce( $nonce = null ) {
    if ( $nonce === null ) {
        $nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
        if ( empty( $nonce ) ) {
            $nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
        }
    }

    if ( ! is_string( $nonce ) ) {
        return false;
    }

    $nonce = sanitize_text_field( $nonce );

    return wp_verify_nonce( $nonce, HWS_AJAX_NONCE );
}

/**
 * Require a valid AJAX nonce and stop with a consistent JSON error if invalid.
 *
 * @param string $field Preferred nonce field in the request body.
 * @return void
 */
function hws_require_ajax_nonce_or_error( $field = 'nonce' ) {
    $nonce = null;

    if ( isset( $_POST[ $field ] ) ) {
        $nonce = wp_unslash( $_POST[ $field ] );
    } elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
        $nonce = wp_unslash( $_REQUEST['_wpnonce'] );
    }

    if ( ! hws_verify_nonce( $nonce ) ) {
        wp_send_json_error( [
            'message' => 'Security check failed. Please refresh the page and try again.',
            'code'    => 'invalid_nonce',
        ] );
    }
}

/**
 * Safe AJAX handler wrapper
 * 
 * Wraps an AJAX callback with:
 * - Try-catch for error handling
 * - Optional nonce verification
 * - Capability checking
 * - Proper JSON response
 * 
 * Usage:
 *   add_action( 'wp_ajax_my_action', function() {
 *       hws_safe_ajax_handler( 'my_actual_handler_function', 'manage_options', true );
 *   });
 * 
 * @param callable $callback The function to execute
 * @param string   $capability Required capability (default: 'manage_options')
 * @param bool     $verify_nonce Whether to verify nonce (default: true)
 * @return void Sends JSON response
 */
function hws_safe_ajax_handler( $callback, $capability = 'manage_options', $verify_nonce = true ) {
    try {
        // Check capability
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [
                'message' => 'Unauthorized: You do not have permission to perform this action.',
                'code'    => 'unauthorized',
            ] );
            return;
        }

        // Verify nonce if required
        if ( $verify_nonce && ! hws_verify_nonce() ) {
            wp_send_json_error( [
                'message' => 'Security check failed. Please refresh the page and try again.',
                'code'    => 'invalid_nonce',
            ] );
            return;
        }

        // Execute the callback
        $result = call_user_func( $callback );

        // Send success response
        wp_send_json_success( $result );

    } catch ( \Exception $e ) {
        // Log the error
        if ( function_exists( __NAMESPACE__ . '\\write_log' ) ) {
            write_log( 'AJAX Error: ' . $e->getMessage(), true );
        } else {
            error_log( '[HWS Base Tools] AJAX Error: ' . $e->getMessage() );
        }

        // Send error response
        wp_send_json_error( [
            'message' => 'An error occurred: ' . $e->getMessage(),
            'code'    => 'exception',
        ] );

    } catch ( \Error $e ) {
        // Catch PHP 7+ fatal errors
        if ( function_exists( __NAMESPACE__ . '\\write_log' ) ) {
            write_log( 'AJAX Fatal Error: ' . $e->getMessage(), true );
        } else {
            error_log( '[HWS Base Tools] AJAX Fatal Error: ' . $e->getMessage() );
        }

        wp_send_json_error( [
            'message' => 'A fatal error occurred. Please check the error log.',
            'code'    => 'fatal_error',
        ] );
    }
}

/**
 * Check if a function is disabled in PHP
 * 
 * @param string $function_name Function name to check
 * @return bool True if disabled, false if available
 */
function hws_is_function_disabled( $function_name ) {
    $disabled = explode( ',', ini_get( 'disable_functions' ) );
    $disabled = array_map( 'trim', $disabled );
    return in_array( $function_name, $disabled, true );
}

/**
 * Safe shell_exec wrapper
 * 
 * Checks if shell_exec is available before executing.
 * Returns null if function is disabled or unavailable.
 * 
 * @param string $command Command to execute
 * @return string|null Command output or null on failure
 */
function hws_safe_shell_exec( $command ) {
    // Check if function exists
    if ( ! function_exists( 'shell_exec' ) ) {
        return null;
    }

    // Check if function is disabled
    if ( hws_is_function_disabled( 'shell_exec' ) ) {
        return null;
    }

    // Execute with error suppression
    try {
        $result = @shell_exec( $command );
        return $result !== null ? trim( $result ) : null;
    } catch ( \Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HWS] shell_exec error: ' . $e->getMessage() );
        }
        return null;
    }
}

/**
 * Safe exec wrapper with timeout
 * 
 * @param string $command Command to execute
 * @param int    $timeout Timeout in seconds (default: 5)
 * @return array|null Array with 'output' and 'return_code', or null on failure
 */
function hws_safe_exec( $command, $timeout = 5 ) {
    if ( ! function_exists( 'exec' ) || hws_is_function_disabled( 'exec' ) ) {
        return null;
    }

    try {
        // Add timeout to command on Linux
        if ( stripos( PHP_OS, 'WIN' ) === false ) {
            $command = "timeout {$timeout} {$command}";
        }

        $output = [];
        $return_code = 0;
        @exec( $command, $output, $return_code );

        return [
            'output'      => $output,
            'return_code' => $return_code,
        ];
    } catch ( \Exception $e ) {
        return null;
    }
}

/**
 * Safe constant getter
 * 
 * @param string $name Constant name
 * @param mixed  $default Default value if constant not defined
 * @return mixed Constant value or default
 */
function hws_get_constant( $name, $default = null ) {
    return defined( $name ) ? constant( $name ) : $default;
}

/**
 * Safe ini_get wrapper
 * 
 * @param string $name INI setting name
 * @param mixed  $default Default value if not available
 * @return mixed INI value or default
 */
function hws_get_ini( $name, $default = null ) {
    $value = @ini_get( $name );
    return $value !== false ? $value : $default;
}

/**
 * Parse PHP size string to bytes
 * 
 * @param string $size Size string like '128M', '1G', etc.
 * @return int Size in bytes
 */
function hws_parse_size( $size ) {
    $size = trim( $size );
    $last = strtolower( $size[ strlen( $size ) - 1 ] );
    $value = (int) $size;

    switch ( $last ) {
        case 'g':
            $value *= 1024;
            // fall through
        case 'm':
            $value *= 1024;
            // fall through
        case 'k':
            $value *= 1024;
    }

    return $value;
}

/**
 * Get server memory information safely
 * 
 * @return array Memory info with 'total', 'free', 'used' in bytes, or null values if unavailable
 */
function hws_get_memory_info() {
    $info = [
        'total' => null,
        'free'  => null,
        'used'  => null,
    ];

    // Try reading from /proc/meminfo first (most reliable on Linux)
    if ( is_readable( '/proc/meminfo' ) ) {
        $meminfo = @file_get_contents( '/proc/meminfo' );
        if ( $meminfo ) {
            if ( preg_match( '/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                $info['total'] = (int) $matches[1] * 1024;
            }
            if ( preg_match( '/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                $info['free'] = (int) $matches[1] * 1024;
            } elseif ( preg_match( '/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                $info['free'] = (int) $matches[1] * 1024;
            }
            if ( $info['total'] && $info['free'] ) {
                $info['used'] = $info['total'] - $info['free'];
            }
            return $info;
        }
    }

    // Fallback to shell command
    $total = hws_safe_shell_exec( "free -b 2>/dev/null | awk '/^Mem:/{print $2}'" );
    $free = hws_safe_shell_exec( "free -b 2>/dev/null | awk '/^Mem:/{print $7}'" );

    if ( $total ) {
        $info['total'] = (int) $total;
    }
    if ( $free ) {
        $info['free'] = (int) $free;
    }
    if ( $info['total'] && $info['free'] ) {
        $info['used'] = $info['total'] - $info['free'];
    }

    return $info;
}

/**
 * Get CPU count safely
 * 
 * @return int|null Number of processors or null if unavailable
 */
function hws_get_cpu_count() {
    // Try /proc/cpuinfo first
    if ( is_readable( '/proc/cpuinfo' ) ) {
        $cpuinfo = @file_get_contents( '/proc/cpuinfo' );
        if ( $cpuinfo ) {
            $count = substr_count( $cpuinfo, 'processor' );
            if ( $count > 0 ) {
                return $count;
            }
        }
    }

    // Fallback to nproc command
    $nproc = hws_safe_shell_exec( 'nproc 2>/dev/null' );
    if ( $nproc && is_numeric( $nproc ) ) {
        return (int) $nproc;
    }

    return null;
}

/**
 * Format bytes to human-readable string
 * 
 * @param int $bytes Bytes to format
 * @param int $precision Decimal places (default: 2)
 * @return string Formatted string like "1.5 GB"
 */
function hws_format_bytes( $bytes, $precision = 2 ) {
    $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );

    $bytes /= pow( 1024, $pow );

    return round( $bytes, $precision ) . ' ' . $units[ $pow ];
}
