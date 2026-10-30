<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandAssets;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Default featured image: the Brand Assets "default_featured" slot stands in
 * for the featured image of any post that has none, on the front end only.
 * Nothing is saved to posts, and an empty slot (the default) changes nothing.
 */
final class DefaultFeaturedImage implements ModuleInterface {
    public const SLOT       = 'default_featured';
    public const POST_TYPES = [ 'post' ];

    /** True while the stand-in is being looked up, so a plugin that asks for a featured image during that lookup (FIFU does, inside get_attached_file) cannot loop back into it. */
    private static bool $resolving = false;

    public function register(): void {
        add_filter( 'post_thumbnail_id', [ self::class, 'fallback' ], 20, 2 );
    }

    /** @param int|false $thumbnail_id */
    public static function fallback( $thumbnail_id, $post ) {
        if ( $thumbnail_id || self::$resolving || ( is_admin() && ! wp_doing_ajax() ) ) {
            return $thumbnail_id;
        }

        $post = get_post( $post );
        if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) ) {
            return $thumbnail_id;
        }

        self::$resolving = true;
        try {
            $default = self::attachment_id();
        } finally {
            self::$resolving = false;
        }

        return $default ?: $thumbnail_id;
    }

    public static function attachment_id(): int {
        if ( ! function_exists( '\\hws_base_tools\\hws_get_brand_asset_attachment_id' ) ) {
            return 0;
        }

        $id = \hws_base_tools\hws_get_brand_asset_attachment_id( self::SLOT );

        // The stored MIME type is enough to know it is an image; it needs no file lookup, which other plugins filter.
        return $id && str_starts_with( (string) get_post_mime_type( $id ), 'image/' ) ? $id : 0;
    }

    /** @return string[] */
    private static function post_types(): array {
        return (array) apply_filters( 'hws_base_tools_default_featured_image_post_types', self::POST_TYPES );
    }
}
