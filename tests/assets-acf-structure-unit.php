<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once $root . '/src/AcfFields/legacy-website-settings.php';

use function hws_base_tools\hws_brand_assets_gallery_acf_group;

function assets_expect( bool $condition, string $message ): void {
    if ( $condition ) {
        return;
    }

    fwrite( STDERR, 'FAIL: ' . $message . "\n" );
    exit( 1 );
}

$group  = hws_brand_assets_gallery_acf_group();
$fields = $group['fields'] ?? [];
$brand  = $fields[0] ?? [];
$banner = $fields[1] ?? [];

assets_expect( 'group_hws_brand_assets_gallery' === ( $group['key'] ?? '' ), 'the established group key changed' );
assets_expect( 'Assets' === ( $group['title'] ?? '' ), 'the group title is not Assets' );
assets_expect( 2 === count( $fields ), 'Assets must contain exactly the Brand and Banners galleries' );

assets_expect( 'Brand' === ( $brand['label'] ?? '' ), 'the first field label is not Brand' );
assets_expect( 'field_hws_brand_assets_gallery' === ( $brand['key'] ?? '' ), 'the existing Brand field key changed' );
assets_expect( 'brand_assets_gallery' === ( $brand['name'] ?? '' ), 'the existing Brand field name changed' );
assets_expect( 'gallery' === ( $brand['type'] ?? '' ), 'Brand is not a gallery' );

assets_expect( 'Banners' === ( $banner['label'] ?? '' ), 'the second field label is not Banners' );
assets_expect( 'field_hws_banners_gallery' === ( $banner['key'] ?? '' ), 'the Banners field key is incorrect' );
assets_expect( 'banners' === ( $banner['name'] ?? '' ), 'the Banners field name is incorrect' );
assets_expect( 'gallery' === ( $banner['type'] ?? '' ), 'Banners is not a gallery' );
assets_expect( 'id' === ( $banner['return_format'] ?? '' ), 'Banners must store attachment IDs' );
assets_expect( 'jpg,jpeg,png,gif,webp,svg' === ( $banner['mime_types'] ?? '' ), 'Banners does not accept the approved image formats' );

echo "PASS: Assets contains stable Brand storage and the new Banners gallery.\n";
