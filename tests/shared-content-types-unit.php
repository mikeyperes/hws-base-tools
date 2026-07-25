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
    array_keys( $definitions ) === [ 'organization', 'testimonial', 'team-member' ],
    'registry owns exactly the shared organization, testimonial, and team-member types'
);

foreach ( $definitions as $post_type => $definition ) {
    $expect( true === $definition['public'], $post_type . ' is public' );
    $expect( true === $definition['show_in_rest'], $post_type . ' is REST-enabled' );
    $expect( in_array( 'custom-fields', $definition['supports'], true ), $post_type . ' preserves custom fields' );
}

SharedContentTypes::boot();
$expect(
    1 === count( $hooks ) && 'init' === $hooks[0][0] && 0 === $hooks[0][2],
    'registry hooks WordPress init at priority zero'
);

SharedContentTypes::register_enabled();
$expect( [] === $registered, 'disabled types do not register' );

$expect( SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'organization option can be enabled' );
$expect( SharedContentTypes::enable( SharedContentTypes::ORGANIZATION ), 'enabling a selected type is idempotent' );
SharedContentTypes::register_enabled();
$expect( isset( $registered['organization'] ), 'enabled organization registers' );
$expect( ! isset( $registered['testimonial'], $registered['team-member'] ), 'unselected shared types remain disabled' );

$count = count( $registered );
$expect( ! SharedContentTypes::register_type( SharedContentTypes::ORGANIZATION ), 'duplicate registration is rejected' );
$expect( $count === count( $registered ), 'duplicate registration does not mutate the registry' );
$expect( ! SharedContentTypes::register_type( 'unknown' ), 'unknown content types are rejected' );

if ( $failures ) {
    exit( 1 );
}

echo count( $definitions ) . ' shared content types verified.' . PHP_EOL;
