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
require_once dirname( __DIR__ ) . '/src/AcfFields/LegacySmp/register-acf-testimonial.php';

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
    array_keys( $definitions ) === [ 'team-member', 'testimonial', 'services' ],
    'registry owns Team Member, Testimonial, and Services while Organization remains external'
);

$testimonial_group = \hws_base_tools\enable_acf_testimonial();
$testimonial_fields = array_column( $testimonial_group['fields'] ?? [], null, 'name' );
$notable_quotes = $testimonial_fields['notable_quotes'] ?? [];
$quote_fields = array_column( $notable_quotes['sub_fields'] ?? [], null, 'name' );
$expect(
    'repeater' === ( $notable_quotes['type'] ?? '' )
        && 'Add Quote' === ( $notable_quotes['button_label'] ?? '' )
        && 'textarea' === ( $quote_fields['quote']['type'] ?? '' ),
    'Testimonial fields include a Notable Quotes repeater with one textarea per quote'
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

$expect( ! SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'external organization type cannot be enabled by HWS' );
$expect( null === SharedContentTypes::option_for( SharedContentTypes::ORGANIZATION ), 'HWS no longer maps the legacy Organization option' );
SharedContentTypes::register_enabled();
$expect( ! isset( $registered['organization'] ), 'HWS never registers Organization' );
$expect( ! isset( $registered['testimonial'], $registered['team-member'], $registered['services'] ), 'unselected HWS types remain disabled' );

$expect( SharedContentTypes::enable( SharedContentTypes::SERVICES ), 'services option can be enabled' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['services'] ), 'enabled services registers' );
$expect( false === $definitions['services']['has_archive'], 'services preserves the static landing page' );

$count = count( $registered );
$expect( ! SharedContentTypes::register_type( SharedContentTypes::ORGANIZATION ), 'legacy Organization requests remain non-registering' );
$expect( $count === count( $registered ), 'external Organization requests do not mutate the registry' );
$expect( ! SharedContentTypes::register_type( 'unknown' ), 'unknown content types are rejected' );

if ( $failures ) {
    exit( 1 );
}

echo count( $definitions ) . ' shared content types verified.' . PHP_EOL;
