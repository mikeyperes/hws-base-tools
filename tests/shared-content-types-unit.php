<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$options = [];
$hooks = [];
$registered = [];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hooks'][] = [ $hook, $callback, $priority ];
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['options'][ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    $GLOBALS['options'][ $key ] = $value;
    return true;
}

function post_type_exists( string $post_type ): bool {
    return isset( $GLOBALS['registered'][ $post_type ] );
}

function register_post_type( string $post_type, array $args ): object {
    $GLOBALS['registered'][ $post_type ] = $args;
    return (object) [ 'name' => $post_type ];
}

function is_wp_error( mixed $value ): bool {
    return false;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    return $value;
}

$core_root = dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core';
require_once $core_root . '/bootstrap.php';
hexa_plugin_core_register_package( 'hws-shared-content-types-test', $core_root );
HexaPluginCorePackageRegistry::resolve();
$hooks = [];

require_once dirname( __DIR__ ) . '/src/ContentTypes/SharedContentTypes.php';

use HWS\BaseTools\ContentTypes\SharedContentTypes;

$failures = [];
$expect = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
        echo 'FAIL ' . $message . PHP_EOL;
        return;
    }
    echo 'PASS ' . $message . PHP_EOL;
};

$definitions = SharedContentTypes::definitions();
$expect(
    array_keys( $definitions ) === [ 'organization', 'team-member', 'testimonial', 'services' ],
    'registry owns Organization, Team Member, Testimonial, and Services while publication content types remain in SMP'
);

foreach ( $definitions as $post_type => $definition ) {
    $expect( true === $definition['public'], $post_type . ' is public' );
    $expect( true === $definition['show_in_rest'], $post_type . ' is REST-enabled' );
    $expect( in_array( 'custom-fields', $definition['supports'], true ), $post_type . ' preserves custom fields' );
}

SharedContentTypes::boot();
$init_hooks = array_values( array_filter( $hooks, static fn( array $hook ): bool => 'init' === $hook[0] ) );
$expect(
    1 === count( $init_hooks ) && 0 === $init_hooks[0][2],
    'registry hooks WordPress init at priority zero'
);
$expect( in_array( 'acf/init', array_column( $hooks, 0 ), true ), 'registry owns the ACF registration hook' );
$expect( in_array( 'wp_ajax_hws_save_content_type', array_column( $hooks, 0 ), true ), 'registry owns the guarded AJAX save hook' );

SharedContentTypes::register_enabled();
$expect( [] === $registered, 'disabled types do not register' );

$expect( SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'organization option can be enabled' );
$expect( SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'enabling a selected type is idempotent' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['organization'] ), 'enabled organization registers' );
$expect( ! isset( $registered['testimonial'], $registered['team-member'], $registered['services'] ), 'unselected shared types remain disabled' );

$expect( SharedContentTypes::enable( SharedContentTypes::SERVICES ), 'services option can be enabled' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['services'] ), 'enabled services registers' );
$expect( false === $definitions['services']['has_archive'], 'services preserves the static landing page' );

$count = count( $registered );
$expect( SharedContentTypes::register_type( SharedContentTypes::ORGANIZATION ), 'an already registered content type remains available' );
$expect( $count === count( $registered ), 'duplicate registration does not mutate the registry' );
$expect( ! SharedContentTypes::register_type( 'unknown' ), 'unknown content types are rejected' );

if ( $failures ) {
    exit( 1 );
}

echo count( $definitions ) . ' shared content types verified.' . PHP_EOL;
