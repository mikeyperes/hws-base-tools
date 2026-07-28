<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateBackupStore {
    public const META_KEY = '_hws_brand_template_backups';
    private const LIMIT = 8;
    private const FORMAT_VERSION = 2;

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
        if ( is_string( $meta['_elementor_data'] ) && '' !== $meta['_elementor_data'] ) {
            $meta['_elementor_data'] = base64_encode( $meta['_elementor_data'] );
        }

        $snapshot = [
            'created_at' => gmdate( 'c' ),
            'reason'     => sanitize_text_field( $reason ),
            'format'     => self::FORMAT_VERSION,
            'data_encoding' => 'base64',
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

        return false !== update_post_meta( $post_id, self::META_KEY, wp_slash( $backups ) );
    }

    /** @param array<string,mixed> $snapshot */
    public static function elementor_data( array $snapshot ): ?string {
        $meta = isset( $snapshot['meta'] ) && is_array( $snapshot['meta'] ) ? $snapshot['meta'] : [];
        $stored = $meta['_elementor_data'] ?? '';
        if ( ! is_string( $stored ) ) {
            return null;
        }

        if ( 'base64' === ( $snapshot['data_encoding'] ?? '' ) ) {
            $decoded = base64_decode( $stored, true );
            if ( false === $decoded ) {
                return null;
            }
            $stored = $decoded;
        }

        if ( '' === trim( $stored ) ) {
            return '';
        }
        json_decode( $stored, true );
        return JSON_ERROR_NONE === json_last_error() ? $stored : null;
    }
}
