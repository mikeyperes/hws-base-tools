<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateBackupStore {
    public const META_KEY = '_hws_brand_template_backups';
    private const LIMIT = 8;

    /** @return array<int,array<string,mixed>> */
    public static function all( int $post_id ): array {
        $backups = get_post_meta( $post_id, self::META_KEY, true );
        return is_array( $backups ) ? array_values( array_filter( $backups, 'is_array' ) ) : [];
    }

    /** @return array<string,mixed>|null */
    public static function latest( int $post_id ): ?array {
        $backups = self::all( $post_id );
        return isset( $backups[0] ) && is_array( $backups[0] ) ? $backups[0] : null;
    }

    public static function count( int $post_id ): int {
        return count( self::all( $post_id ) );
    }

    public static function create( int $post_id, string $reason ): bool {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return false;
        }

        $meta_keys = [
            '_elementor_data',
            '_elementor_page_settings',
            '_elementor_conditions',
            '_elementor_template_type',
            '_elementor_edit_mode',
            '_elementor_version',
            '_elementor_pro_version',
            '_wp_page_template',
            ElementorTemplateImporter::META_CONTEXT,
            ElementorTemplateImporter::META_CONTENT_HASH,
            ElementorTemplateImporter::META_VERSION,
        ];
        $meta = [];
        foreach ( $meta_keys as $key ) {
            $meta[ $key ] = get_post_meta( $post_id, $key, true );
        }

        $snapshot = [
            'created_at' => gmdate( 'c' ),
            'reason'     => sanitize_text_field( $reason ),
            'post'       => [
                'post_title'  => $post->post_title,
                'post_status' => $post->post_status,
            ],
            'terms'      => wp_get_object_terms( $post_id, 'elementor_library_type', [ 'fields' => 'slugs' ] ),
            'meta'       => $meta,
        ];

        $backups = self::all( $post_id );
        array_unshift( $backups, $snapshot );
        $backups = array_slice( $backups, 0, self::LIMIT );

        return false !== update_post_meta( $post_id, self::META_KEY, $backups );
    }
}
