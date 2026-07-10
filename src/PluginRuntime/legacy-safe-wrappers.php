<?php

namespace hws_base_tools;

use Hexa\PluginCore\SystemEnvironment\SystemEnvironment;
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Throwable;

defined( 'ABSPATH' ) || exit;

if ( ! defined( __NAMESPACE__ . '\\HWS_AJAX_NONCE' ) ) {
    define( __NAMESPACE__ . '\\HWS_AJAX_NONCE', 'hws_base_tools_ajax_nonce' );
}

function hws_create_nonce() {
    return AjaxGuard::create_nonce( HWS_AJAX_NONCE );
}

function hws_verify_nonce( $nonce = null ) {
    if ( null !== $nonce && ! is_string( $nonce ) ) {
        return false;
    }

    return AjaxGuard::verify_nonce( HWS_AJAX_NONCE, $nonce );
}

function hws_require_ajax_nonce_or_error( $field = 'nonce' ) {
    AjaxGuard::require_nonce_or_error( HWS_AJAX_NONCE, (string) $field );
}

function hws_log_ajax_throwable( Throwable $throwable ): void {
    $message = 'AJAX Error: ' . $throwable->getMessage();

    if ( function_exists( __NAMESPACE__ . '\\write_log' ) ) {
        write_log( $message, true );
        return;
    }

    error_log( '[HWS Base Tools] ' . $message );
}

function hws_safe_ajax_handler( $callback, $capability = 'manage_options', $verify_nonce = true ) {
    AjaxGuard::handle(
        $callback,
        [
            'capability'    => (string) $capability,
            'verify_nonce'  => (bool) $verify_nonce,
            'nonce_action'  => HWS_AJAX_NONCE,
            'nonce_field'   => 'nonce',
            'logger'        => __NAMESPACE__ . '\\hws_log_ajax_throwable',
        ]
    );
}

function hws_is_function_disabled( $function_name ) {
    return SystemEnvironment::is_function_disabled( (string) $function_name );
}

function hws_safe_shell_exec( $command ) {
    return SystemEnvironment::safe_shell_exec( (string) $command );
}

function hws_safe_exec( $command, $timeout = 5 ) {
    return SystemEnvironment::safe_exec( (string) $command, (int) $timeout );
}

function hws_get_constant( $name, $default = null ) {
    return SystemEnvironment::get_constant( (string) $name, $default );
}

function hws_get_ini( $name, $default = null ) {
    return SystemEnvironment::get_ini( (string) $name, $default );
}

function hws_parse_size( $size ) {
    return SystemEnvironment::parse_size( $size );
}

function hws_read_system_file( $path ) {
    return SystemEnvironment::read_system_file( (string) $path );
}

function hws_parse_cgroup_memory_limit( $value ) {
    return SystemEnvironment::parse_cgroup_memory_limit( is_string( $value ) ? $value : null );
}

function hws_get_cgroup_memory_limit() {
    return SystemEnvironment::get_cgroup_memory_limit();
}

function hws_count_cpuset_cpus( $cpuset ) {
    return SystemEnvironment::count_cpuset_cpus( is_string( $cpuset ) ? $cpuset : null );
}

function hws_get_cpu_info() {
    return SystemEnvironment::get_cpu_info();
}

function hws_get_memory_info() {
    return SystemEnvironment::get_memory_info();
}

function hws_get_cpu_count() {
    return SystemEnvironment::get_cpu_count();
}

function hws_format_bytes( $bytes, $precision = 2 ) {
    return SystemEnvironment::format_bytes( $bytes, (int) $precision );
}
