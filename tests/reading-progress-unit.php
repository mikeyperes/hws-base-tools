<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['hws_reading_progress_options'] = [];
$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => false, 'singular_post' => false ];

function get_option( string $key, mixed $default = false ): mixed {
    return array_key_exists( $key, $GLOBALS['hws_reading_progress_options'] )
        ? $GLOBALS['hws_reading_progress_options'][ $key ]
        : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    $GLOBALS['hws_reading_progress_options'][ $key ] = $value;

    return true;
}

function add_action( string $hook, mixed $callback, int $priority = 10 ): void {
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function sanitize_hex_color( mixed $value ): ?string {
    $value = trim( (string) $value );

    return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : null;
}

function esc_attr( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function is_front_page(): bool {
    return (bool) $GLOBALS['hws_reading_progress_context']['front_page'];
}

function is_singular( string $post_type = '' ): bool {
    return 'post' === $post_type && (bool) $GLOBALS['hws_reading_progress_context']['singular_post'];
}

require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require dirname( __DIR__ ) . '/src/FrontendContent/ReadingProgress.php';

use HWS\BaseTools\FrontendContent\ReadingProgress;

function reading_progress_expect( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

reading_progress_expect(
    [ 'thin', 'track', 'glow', 'floating', 'segmented' ] === array_keys( ReadingProgress::designs() ),
    'five progress designs remain available'
);
reading_progress_expect( 'thin' === ReadingProgress::normalize_style( 'invalid' ), 'invalid styles fall back to Thin' );
reading_progress_expect( '#00ff41' === ReadingProgress::normalize_color( 'invalid' ), 'invalid colors fall back to green' );
reading_progress_expect( '#a1b2c3' === ReadingProgress::normalize_color( '#A1B2C3' ), 'colors normalize consistently' );

$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => false, 'singular_post' => true ];
reading_progress_expect( ReadingProgress::scope_matches_current_request( 'posts' ), 'post scope matches single posts' );
$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => true, 'singular_post' => false ];
reading_progress_expect( ReadingProgress::scope_matches_current_request( 'posts_front_page' ), 'front-page scope matches the front page' );
reading_progress_expect( ! ReadingProgress::scope_matches_current_request( 'posts' ), 'post-only scope excludes the front page' );

$GLOBALS['hws_reading_progress_options']['smpi_settings'] = [
    'reading_progress_enabled' => true,
    'reading_progress_scope'   => 'posts',
    'reading_progress_style'   => 'thin',
    'reading_progress_color'   => '#00ff41',
];
ReadingProgress::migrate_from_smp();
reading_progress_expect( true === get_option( ReadingProgress::FEATURE_OPTION ), 'SMP enablement migrates to HWS' );
reading_progress_expect( 'posts' === get_option( ReadingProgress::SCOPE_OPTION ), 'SMP scope migrates to HWS' );
reading_progress_expect( 'thin' === get_option( ReadingProgress::STYLE_OPTION ), 'SMP style migrates to HWS' );
reading_progress_expect( '#00ff41' === get_option( ReadingProgress::COLOR_OPTION ), 'SMP color migrates to HWS' );
reading_progress_expect( '1.0.24' === get_option( ReadingProgress::MIGRATION_OPTION ), 'migration records the SMP ownership-removal release' );

$GLOBALS['hws_reading_progress_options'] = [
    ReadingProgress::FEATURE_OPTION => false,
    'smpi_settings' => [
        'reading_progress_enabled' => true,
    ],
];
ReadingProgress::migrate_from_smp();
reading_progress_expect( false === get_option( ReadingProgress::FEATURE_OPTION ), 'explicit HWS enablement is never overwritten' );

$saved = ReadingProgress::save_settings( [ 'scope' => 'sitewide', 'style' => 'segmented', 'color' => '#A1B2C3' ] );
reading_progress_expect(
    [ 'scope' => 'sitewide', 'style' => 'segmented', 'color' => '#a1b2c3' ] === $saved,
    'supported settings save in normalized form'
);

$preview_css = ReadingProgress::preview_css();
$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/FrontendContent/ReadingProgress.php' );
$feature_ui = (string) file_get_contents( dirname( __DIR__ ) . '/src/FeatureCatalog/legacy-features.php' );
reading_progress_expect(
    str_contains( ReadingProgress::preview_html( 'thin' ), 'hws-reading-progress--thin' )
        && str_contains( $preview_css, '.hws-reading-progress--segmented' ),
    'admin previews share the HWS frontend design classes'
);
reading_progress_expect(
    str_contains( $source, 'id="hws-reading-progress"' )
        && str_contains( $source, 'role="progressbar"' )
        && str_contains( $source, 'window.requestAnimationFrame(update)' )
        && str_contains( $source, 'legacy_smp_will_render' ),
    'frontend runtime is semantic, animation-safe, and duplicate-safe during transition'
);
reading_progress_expect(
    str_contains( $feature_ui, 'ColorControl::render(' )
        && str_contains( $feature_ui, "'--hws-reading-progress-color' => 'color'" )
        && str_contains( $feature_ui, 'ReadingProgress::preview_html' ),
    'Features UI uses the Hexa Core color control and live visual previews'
);

echo "PASS: HWS owns reading progress settings, migration, previews, Core color control, and frontend runtime.\n";
