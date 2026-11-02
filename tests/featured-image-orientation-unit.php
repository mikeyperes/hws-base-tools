<?php

declare( strict_types=1 );

// Shape detection and default switches for the featured image orientation feature.
namespace {
    define( 'ABSPATH', __DIR__ );
    $GLOBALS['fio_options'] = [];
    function get_option( $k, $d = false ) { return $GLOBALS['fio_options'][ $k ] ?? $d; }
    function get_post_types( $a, $o ) {
        $t = static fn( $n ) => (object) [ 'labels' => (object) [ 'singular_name' => ucfirst( $n ) ] ];
        return [ 'post' => $t( 'post' ), 'page' => $t( 'page' ), 'event' => $t( 'event' ), 'attachment' => $t( 'attachment' ), 'nothumb' => $t( 'nothumb' ) ];
    }
    function is_post_type_viewable( $t ) { return true; }
    function post_type_supports( $n, $f ) { return 'nothumb' !== $n; }
}
namespace Hexa\PluginCore\CoreContracts { interface ModuleInterface { public function register(): void; } }
namespace {
    require dirname( __DIR__ ) . '/src/FrontendContent/FeaturedImageOrientation.php';
    use HWS\BaseTools\FrontendContent\FeaturedImageOrientation as F;
    $fail = 0;
    $check = static function ( bool $ok, string $what ) use ( &$fail ): void { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . "\n"; $fail += $ok ? 0 : 1; };
    $check( 'landscape' === F::shape( 1200, 630 ), 'a 1200x630 photo is landscape' );
    $check( 'portrait' === F::shape( 769, 1024 ), 'a 769x1024 photo is portrait' );
    $check( 'square' === F::shape( 1000, 1020 ), 'a near-square photo is square' );
    $check( 'landscape' === F::shape( 0, 0 ), 'an unknown size stays landscape' );
    $check( [ 'post', 'page', 'event' ] === array_keys( F::post_types() ), 'only viewable types with featured images get a switch' );
    $s = F::settings();
    $check( true === $s['front_page'] && [ 'post' => true, 'page' => true, 'event' => true ] === $s['post_types'], 'every switch is on by default' );
    $GLOBALS['fio_options'][ F::OPTION ] = [ 'front_page' => false, 'post_types' => [ 'event' => false ] ];
    $s = F::settings();
    $check( false === $s['front_page'] && false === $s['post_types']['event'] && true === $s['post_types']['post'], 'a switch turned off stays off and new types start on' );
    exit( $fail ? 1 : 0 );
}
