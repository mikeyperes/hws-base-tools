<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['hws_reading_progress_options'] = [];
$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => false, 'post_type' => '' ];

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

function is_singular( string|array $post_type = '' ): bool {
    $current = (string) $GLOBALS['hws_reading_progress_context']['post_type'];
    if ( '' === $current ) {
        return false;
    }
    if ( '' === $post_type ) {
        return true;
    }

    return is_array( $post_type ) ? in_array( $current, $post_type, true ) : $current === $post_type;
}

function get_post_types( array $args = [], string $output = 'names' ): array {
    $types = [
        'post' => (object) [
            'name' => 'post',
            'label' => 'Posts',
            'labels' => (object) [ 'name' => 'Posts', 'singular_name' => 'Post' ],
        ],
        'page' => (object) [
            'name' => 'page',
            'label' => 'Pages',
            'labels' => (object) [ 'name' => 'Pages', 'singular_name' => 'Page' ],
        ],
        'book' => (object) [
            'name' => 'book',
            'label' => 'Books',
            'labels' => (object) [ 'name' => 'Books', 'singular_name' => 'Book' ],
        ],
        'attachment' => (object) [
            'name' => 'attachment',
            'label' => 'Media',
            'labels' => (object) [ 'name' => 'Media', 'singular_name' => 'Attachment' ],
        ],
    ];

    return 'objects' === $output ? $types : array_keys( $types );
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
reading_progress_expect(
    [ 'post', 'page', 'book' ] === array_keys( ReadingProgress::post_type_choices() ),
    'every public post type is selectable while attachments stay excluded'
);
reading_progress_expect(
    [ 'book', 'page' ] === ReadingProgress::normalize_post_types( [ 'book', 'invalid', 'page', 'book' ] ),
    'selected content types normalize against public choices'
);

$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => false, 'post_type' => 'post' ];
reading_progress_expect( ReadingProgress::scope_matches_current_request( 'posts' ), 'post scope matches single posts' );
$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => true, 'post_type' => '' ];
reading_progress_expect( ReadingProgress::scope_matches_current_request( 'posts_front_page' ), 'front-page scope matches the front page' );
reading_progress_expect( ! ReadingProgress::scope_matches_current_request( 'posts' ), 'post-only scope excludes the front page' );
$GLOBALS['hws_reading_progress_context'] = [ 'front_page' => false, 'post_type' => 'book' ];
reading_progress_expect(
    ReadingProgress::targets_match_current_request( [ 'entire_site' => false, 'front_page' => false, 'post_types' => [ 'book' ] ] ),
    'a selected custom post type matches every single item in that type'
);
reading_progress_expect(
    ! ReadingProgress::targets_match_current_request( [ 'entire_site' => false, 'front_page' => false, 'post_types' => [ 'page' ] ] ),
    'unselected custom post types stay excluded'
);
reading_progress_expect(
    ReadingProgress::targets_match_current_request( [ 'entire_site' => true, 'front_page' => false, 'post_types' => [] ] ),
    'entire-site targeting overrides narrower choices'
);

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
    [
        'scope'       => 'sitewide',
        'entire_site' => true,
        'front_page'  => false,
        'post_types'  => [ 'post' ],
        'style'       => 'segmented',
        'color'       => '#a1b2c3',
    ] === $saved,
    'supported settings save in normalized form'
);
$saved = ReadingProgress::save_settings(
    [
        'entire_site' => false,
        'front_page'  => true,
        'post_types'  => [ 'page', 'book', 'not-public' ],
        'style'       => 'thin',
        'color'       => '#00ff41',
    ]
);
reading_progress_expect(
    'selected' === $saved['scope'] && true === $saved['front_page'] && [ 'page', 'book' ] === $saved['post_types'],
    'specific front-page and public content-type targets save independently'
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
        && str_contains( $feature_ui, 'ReadingProgress::preview_html' )
        && str_contains( $feature_ui, 'data-hws-feature-field="entire_site"' )
        && str_contains( $feature_ui, 'data-hws-feature-field="post_types"' )
        && str_contains( $feature_ui, "prop('disabled', entireSite)" ),
    'Features UI uses the Core color control, live previews, sitewide override, and per-CPT choices'
);

echo "PASS: HWS owns reading progress settings, migration, previews, Core color control, and frontend runtime.\n";
