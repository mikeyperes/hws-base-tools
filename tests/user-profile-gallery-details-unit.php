<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$hooks = [];

function add_action( string $hook, mixed $callback ): void {
    global $hooks;
    $hooks[ $hook ] = $callback;
}

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
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

function acf_get_form_data( string $key ): string {
    return 'post_id' === $key ? 'user_144' : '';
}

function current_user_can( string $capability, mixed ...$args ): bool {
    return 'edit_user' === $capability && 144 === ( $args[0] ?? 0 );
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function wp_create_nonce( string $action ): string {
    return 'test-nonce-' . md5( $action );
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
require $root . '/lib/hexa-wordpress-plugin-core/src/FieldStructures/AcfGalleryDetailsModule.php';
require $root . '/src/AcfFields/UserProfileGalleryDetails.php';

use Hexa\PluginCore\FieldStructures\AcfGalleryDetailsModule;
use HWS\BaseTools\AcfFields\UserProfileGalleryDetails;

$module = UserProfileGalleryDetails::module();
if ( ! $module instanceof AcfGalleryDetailsModule ) {
    fwrite( STDERR, "FAIL: HWS Photos did not return the generic Core ACF gallery module.\n" );
    exit( 1 );
}

$config = $module->config();
if (
    UserProfileGalleryDetails::FIELD_KEY !== ( $config['field_key'] ?? '' )
    || 112 !== ( $config['preview_pixels'] ?? 0 )
    || empty( $config['allow_remove'] )
    || empty( $config['live_refresh'] )
) {
    fwrite( STDERR, "FAIL: HWS Photos did not provide the expected declarative Core configuration.\n" );
    exit( 1 );
}

$module->register();
$hook = 'acf/render_field/key=field_hws_user_profile_2025_photos';
if ( ! isset( $hooks[ $hook ] ) || ! is_callable( $hooks[ $hook ] ) ) {
    fwrite( STDERR, "FAIL: Generic Core module did not register the HWS Photos field hook.\n" );
    exit( 1 );
}

ob_start();
$module->render( [ 'value' => [ [ 'ID' => 44 ] ] ] );
$html = (string) ob_get_clean();

$checks = [
    'Core renders the exact HWS field and user context.' => str_contains( $html, 'data-hpc-gallery-field-key="field_hws_user_profile_2025_photos"' )
        && str_contains( $html, 'data-hpc-gallery-context="user_144"' ),
    'Preview is materially larger than the old 52px thumbnail.' => str_contains( $html, '--hpc-media-gallery-preview-size:112px' )
        && str_contains( $html, 'profile-44-medium.jpg' ),
    'Every size provides separate image and URL clipboard controls.' => str_contains( $html, 'data-hpc-gallery-copy-image' )
        && str_contains( $html, 'data-hpc-gallery-copy-url' )
        && str_contains( $html, 'Copy image' )
        && str_contains( $html, 'Copy URL' ),
    'Core supplies dynamic gallery-only deletion.' => str_contains( $html, 'data-hpc-gallery-remove="44"' )
        && str_contains( $html, '>Delete</span>' )
        && str_contains( $html, 'Media Library attachment will not be deleted' ),
    'Core observes native ACF changes and refreshes through AJAX.' => str_contains( $html, 'data-hpc-gallery-live-refresh="1"' )
        && str_contains( $html, 'new MutationObserver' )
        && str_contains( $html, "request(root,'refresh'" ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

$hws_source = (string) file_get_contents( $root . '/src/AcfFields/UserProfileGalleryDetails.php' );
foreach ( [ 'add_action(', 'MediaGalleryDetailsRenderer::render', 'handle_ajax', 'update_field(' ] as $implementation_term ) {
    if ( str_contains( $hws_source, $implementation_term ) ) {
        fwrite( STDERR, 'FAIL: HWS still owns generic gallery implementation: ' . $implementation_term . "\n" );
        exit( 1 );
    }
}

echo "PASS: HWS Photos is a thin configuration of the generic Hexa Core ACF gallery module.\n";
