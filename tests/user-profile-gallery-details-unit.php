<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$hooks = [];

function add_action( string $hook, mixed $callback ): void {
    global $hooks;
    $hooks[ $hook ] = $callback;
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return filter_var( (string) $value, FILTER_SANITIZE_URL ) ?: '';
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function wp_attachment_is_image( int $attachment_id ): bool {
    return 44 === $attachment_id;
}

function wp_get_attachment_metadata( int $attachment_id ): array {
    return [
        'width'  => 1800,
        'height' => 1200,
        'sizes'  => [
            'thumbnail'    => [ 'width' => 150, 'height' => 150 ],
            'medium'       => [ 'width' => 300, 'height' => 200 ],
            'medium_large' => [ 'width' => 768, 'height' => 512 ],
        ],
    ];
}

function wp_get_attachment_url( int $attachment_id ): string {
    return 'https://example.test/uploads/profile-' . $attachment_id . '.jpg';
}

function wp_get_attachment_image_src( int $attachment_id, string $size ): array {
    return [ 'https://example.test/uploads/profile-' . $attachment_id . '-' . str_replace( '_', '-', $size ) . '.jpg', 300, 200 ];
}

function get_the_title( int $attachment_id ): string {
    return 'Profile image';
}

function wp_parse_url( string $url, int $component = -1 ): string|int|array|null|false {
    return parse_url( $url, $component );
}

function wp_basename( string $path ): string {
    return basename( $path );
}

$root = dirname( __DIR__ );
require $root . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php';
require $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/DynamicButton.php';
require $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/MediaGalleryDetailsRenderer.php';
require $root . '/src/AcfFields/UserProfileGalleryDetails.php';

use HWS\BaseTools\AcfFields\UserProfileGalleryDetails;

$module = new UserProfileGalleryDetails();
$module->register();

$hook = 'acf/render_field/key=field_hws_user_profile_2025_photos';
if ( ! isset( $hooks[ $hook ] ) || ! is_callable( $hooks[ $hook ] ) ) {
    fwrite( STDERR, "FAIL: HWS Photos field hook was not registered.\n" );
    exit( 1 );
}

ob_start();
$module->render( [ 'value' => [ [ 'ID' => 44 ] ] ] );
$html = (string) ob_get_clean();

$checks = [
    'Details panel uses the exact HWS field value.' => str_contains( $html, 'hpc-detail-card-title">Details</span>' )
        && str_contains( $html, 'data-attachment-id="44"' ),
    'Full, thumbnail, medium, and medium-large URLs render.' => str_contains( $html, 'profile-44.jpg' )
        && str_contains( $html, 'profile-44-thumbnail.jpg' )
        && str_contains( $html, 'profile-44-medium.jpg' )
        && str_contains( $html, 'profile-44-medium-large.jpg' ),
    'Media is selectable and URLs open in a new tab.' => str_contains( $html, 'data-hpc-gallery-select' )
        && str_contains( $html, 'target="_blank" rel="noopener noreferrer"' ),
    'Clipboard control is a Hexa Core dynamic button.' => str_contains( $html, 'data-hpc-dynamic-button' )
        && str_contains( $html, 'data-working-label="Copy to clipboard"' )
        && str_contains( $html, 'catch(function(){return legacyCopy(value)})' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: HWS Photos uses the selectable Hexa Core gallery details UI.\n";
