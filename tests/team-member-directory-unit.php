<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( string $value ): string {
    return trim( (string) preg_replace( '/[^a-z0-9_\-]+/', '', strtolower( $value ) ), '-' );
}

function sanitize_html_class( string $value ): string {
    return sanitize_key( $value );
}

function esc_attr( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function get_option( string $key, mixed $default = false ): mixed {
    return $default;
}

require_once dirname( __DIR__ ) . '/src/TeamMembers/TeamMemberDirectory.php';

use HWS\BaseTools\TeamMembers\TeamMemberDirectory;

$failures = [];

function team_expect( bool $condition, string $message ): void {
    global $failures;
    if ( ! $condition ) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }

    echo "PASS: {$message}\n";
}

$templates = TeamMemberDirectory::template_options();
team_expect(
    array_keys( $templates ) === [ 'portrait_grid', 'editorial_list', 'compact_directory' ],
    'exactly three ordered HWS Team Member templates are available'
);
team_expect( 'portrait_grid' === TeamMemberDirectory::normalize_style( 'invalid' ), 'invalid template values fall back safely' );

foreach ( array_keys( $templates ) as $style ) {
    $preview = TeamMemberDirectory::preview_html( $style );
    team_expect( str_contains( $preview, 'hws-team-directory--' . $style ), $style . ' preview uses its HWS template class' );
    team_expect( 2 === substr_count( $preview, 'class="hws-team-card"' ), $style . ' preview renders two sample people' );
    team_expect( ! str_contains( $preview, 'smpi-' ), $style . ' preview has no SMP-owned classes' );
}

$styles = TeamMemberDirectory::styles();
team_expect( str_contains( $styles, '@media(max-width:600px)' ), 'team templates include a mobile breakpoint' );
team_expect( str_contains( $styles, 'object-fit:cover' ), 'team images preserve their aspect ratio without skewing' );
team_expect( TeamMemberDirectory::SHORTCODE === 'hws_team_members', 'shortcode is owned and prefixed by HWS' );

if ( $failures ) {
    exit( 1 );
}
