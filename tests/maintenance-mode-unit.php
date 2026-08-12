<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$root = dirname( __DIR__ );
$options = [];
$actions = [];
$rewrite_flushes = 0;
$cache_flushes = 0;
$failures = [];
$passes = 0;

function mm_expect( bool $condition, string $message ): void {
    global $failures, $passes;
    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function get_option( string $key, mixed $default = false ): mixed {
    global $options;
    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    global $options;
    $changed = ! array_key_exists( $key, $options ) || $options[ $key ] !== $value;
    $options[ $key ] = $value;
    return $changed;
}

function get_bloginfo( string $show = '' ): string {
    return 'name' === $show ? 'Maintenance Test Site' : '';
}

function home_url( string $path = '' ): string {
    return 'https://example.test' . ( '/' === $path ? '/' : $path );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr( mixed $value ): string {
    return esc_html( $value );
}

function esc_url( mixed $value ): string {
    return (string) $value;
}

function current_time( string $type ): string {
    return '2026-08-12 20:30:00';
}

function flush_rewrite_rules( bool $hard = true ): void {
    global $rewrite_flushes, $options;
    ++$rewrite_flushes;
    $options['rewrite_rules'] = [ '^sample/?$' => 'index.php?name=sample' ];
}

function wp_cache_flush(): bool {
    global $cache_flushes;
    ++$cache_flushes;
    return true;
}

function do_action( string $hook, mixed ...$args ): void {
    global $actions;
    $actions[] = $hook;
}

require_once $root . '/src/MaintenanceMode/MaintenanceSettings.php';
require_once $root . '/src/MaintenanceMode/MaintenanceTemplateRenderer.php';
require_once $root . '/src/MaintenanceMode/MaintenanceModeOperations.php';

use HWS\BaseTools\MaintenanceMode\MaintenanceModeOperations;
use HWS\BaseTools\MaintenanceMode\MaintenanceSettings;
use HWS\BaseTools\MaintenanceMode\MaintenanceTemplateRenderer;

$templates = MaintenanceSettings::templates();
mm_expect( 5 === count( $templates ), 'five selectable maintenance templates are available' );
mm_expect( 'focused' === MaintenanceSettings::normalize_template( 'invalid' ), 'invalid templates normalize to the safe default' );

$documents = [];
foreach ( array_keys( $templates ) as $template ) {
    $document = MaintenanceTemplateRenderer::document( $template );
    $documents[ $template ] = $document;
    mm_expect(
        str_starts_with( $document, '<!doctype html>' )
        && str_contains( $document, '<style>' )
        && str_contains( $document, 'hws-maintenance--' . $template )
        && str_contains( $document, 'Maintenance Test Site' )
        && str_contains( $document, 'noindex,nofollow,noarchive' ),
        "{$template} is a complete production maintenance document"
    );
}
mm_expect( 5 === count( array_unique( array_values( $documents ) ) ), 'all maintenance templates have distinct complete documents' );

foreach ( MaintenanceModeOperations::OPERATIONS as $operation ) {
    $result = MaintenanceModeOperations::run( $operation, true, 'blueprint' );
    mm_expect( $operation === $result['operation'], "{$operation} reports its completed server operation" );
}
mm_expect( MaintenanceSettings::enabled(), 'enable process persists maintenance mode' );
mm_expect( 'blueprint' === MaintenanceSettings::selected_template(), 'enable process persists the selected template' );
mm_expect( 1 === $rewrite_flushes, 'enable process refreshes permalinks exactly once' );
mm_expect( 1 === $cache_flushes && in_array( 'litespeed_purge_all', $actions, true ), 'enable process purges object and LiteSpeed caches' );
mm_expect( ! empty( MaintenanceSettings::last_transition()['complete'] ), 'enable process records a completed live checklist' );

foreach ( MaintenanceModeOperations::OPERATIONS as $operation ) {
    MaintenanceModeOperations::run( $operation, false, 'minimal' );
}
mm_expect( ! MaintenanceSettings::enabled(), 'disable process restores normal public rendering' );
mm_expect( 2 === $rewrite_flushes, 'disable process refreshes permalinks again' );
mm_expect( 2 === $cache_flushes, 'disable process purges caches again' );

if ( $failures ) {
    fwrite( STDERR, count( $failures ) . " maintenance mode assertion(s) failed.\n" );
    exit( 1 );
}

echo "PASS: {$passes} maintenance mode assertions passed.\n";
