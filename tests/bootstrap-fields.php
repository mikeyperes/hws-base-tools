<?php
/**
 * Loads the bundled Hexa WP Core for unit tests and runs Hexa\PluginCore\Fields
 * in ACF mode, delegating to the test's own ACF stubs exactly as it delegates
 * to ACF on a live site. Include it after the test's stubs.
 */
foreach ( [ 'add_action', 'add_filter' ] as $hexa_test_fn ) {
    if ( ! function_exists( $hexa_test_fn ) ) {
        eval( 'function ' . $hexa_test_fn . '( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS["hexa_test_hooks"][] = [ $hook, $callback, $priority, $args ]; return true; }' );
    }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value, ...$args ) { return $value; }
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook, ...$args ) {}
}
if ( ! function_exists( 'did_action' ) ) {
    function did_action( $hook ) { return 1; }
}
if ( ! function_exists( 'doing_action' ) ) {
    function doing_action( $hook = null ) { return false; }
}
if ( ! function_exists( 'get_field' ) ) {
    function get_field( $selector, $post_id = false, $format = true ) {
        if ( is_string( $post_id ) && str_starts_with( $post_id, 'user_' ) && function_exists( 'get_user_meta' ) ) {
            return get_user_meta( (int) substr( $post_id, 5 ), $selector, true );
        }
        return function_exists( 'get_post_meta' ) ? get_post_meta( (int) $post_id, $selector, true ) : null;
    }
}
if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    function acf_add_local_field_group( $group ) { $GLOBALS['hexa_test_local_field_groups'][ $group['key'] ?? '' ] = $group; return true; }
}
require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/bootstrap.php';
hexa_plugin_core_register_package( 'hws-base-tools-tests', dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core' );
\HexaPluginCorePackageRegistry::resolve();
