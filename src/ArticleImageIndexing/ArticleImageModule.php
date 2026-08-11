<?php

namespace HWS\BaseTools\ArticleImageIndexing;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide article image indexing policy for every HWS Base Tools install.
 */
final class ArticleImageModule implements ModuleInterface {
    public function register(): void {
        if ( did_action( 'after_setup_theme' ) ) {
            ImageFamily::register_sizes();
        } else {
            add_action( 'after_setup_theme', [ ImageFamily::class, 'register_sizes' ], 20 );
        }

        add_action( 'save_post', [ self::class, 'ensure_featured_image' ], 120, 3 );
        add_action( 'added_post_meta', [ self::class, 'featured_image_meta_changed' ], 120, 4 );
        add_action( 'updated_post_meta', [ self::class, 'featured_image_meta_changed' ], 120, 4 );
        add_action( 'transition_post_status', [ self::class, 'post_published' ], 120, 3 );

        add_filter( 'wp_get_attachment_image_attributes', [ HeroImagePolicy::class, 'attributes' ], 120, 3 );
        add_filter( 'litespeed_media_lazy_img_excludes', [ HeroImagePolicy::class, 'litespeed_url_exclusions' ], 120 );
        add_filter( 'litespeed_media_lazy_img_cls_excludes', [ HeroImagePolicy::class, 'litespeed_class_exclusions' ], 120 );

        RankMathIntegration::register();
        RankMathSitemapCache::register();
        FeaturedImageBackfill::register();
    }

    /**
     * @param mixed $post
     */
    public static function ensure_featured_image( int $post_id, $post, bool $update ): void {
        unset( $update );

        if ( ! self::is_eligible_post( $post ) ) {
            return;
        }

        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        if ( $attachment_id > 0 ) {
            ImageFamily::ensure( $attachment_id );
        }
    }

    /**
     * @param mixed $meta_value
     */
    public static function featured_image_meta_changed( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
        unset( $meta_id );

        if ( '_thumbnail_id' !== $meta_key ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! self::is_eligible_post( $post ) ) {
            return;
        }

        $attachment_id = (int) $meta_value;
        if ( $attachment_id > 0 ) {
            ImageFamily::ensure( $attachment_id );
        }
    }

    /**
     * @param mixed $post
     */
    public static function post_published( string $new_status, string $old_status, $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status || ! self::is_eligible_post( $post ) ) {
            return;
        }

        $post_id       = (int) $post->ID;
        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        if ( $attachment_id > 0 ) {
            ImageFamily::ensure( $attachment_id );
        }

        if ( self::rank_math_active() ) {
            do_action( 'rank_math/sitemap/invalidate_object_type', 'post', $post_id );
            RankMathSitemapCache::evict_for_post( $post_id );
            RankMathSitemapCache::schedule_refresh( $post_id );
        }

        do_action( 'hws_base_tools_article_image_indexing_published', $post_id, $attachment_id );
    }

    /**
     * @return array<int,string>
     */
    public static function eligible_post_types(): array {
        $eligible = [];
        $titles   = get_option( 'rank-math-options-titles', [] );
        $titles   = is_array( $titles ) ? $titles : [];

        foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
            if ( 'attachment' === $post_type || ! post_type_supports( $post_type, 'thumbnail' ) ) {
                continue;
            }

            $rank_math_schema = strtolower( (string) ( $titles[ 'pt_' . $post_type . '_default_rich_snippet' ] ?? '' ) );
            if ( 'post' === $post_type || 'article' === $rank_math_schema ) {
                $eligible[] = (string) $post_type;
            }
        }

        /**
         * Filter the standard post type and public thumbnail-capable types that
         * declare Rank Math's Article schema. No hostnames or site identities
         * are consulted.
         *
         * @param array<int,string> $eligible
         */
        $eligible = apply_filters( 'hws_base_tools_article_image_post_types', $eligible );

        return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $eligible ) ) ) );
    }

    /**
     * @param mixed $post
     */
    public static function is_eligible_post( $post ): bool {
        if ( ! is_object( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) {
            return false;
        }

        if ( 'publish' !== (string) $post->post_status || ! empty( $post->post_password ) ) {
            return false;
        }

        if ( wp_is_post_revision( (int) $post->ID ) || wp_is_post_autosave( (int) $post->ID ) ) {
            return false;
        }

        return in_array( (string) $post->post_type, self::eligible_post_types(), true );
    }

    private static function rank_math_active(): bool {
        return defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath\\Plugin' );
    }
}
