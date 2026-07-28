<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandAssets;

use HWS\BaseTools\SiteProfile\PrimaryEntityIntegration;

defined( 'ABSPATH' ) || exit;

final class PrimaryAuthorImage {
    /** @return array{id:int,name:string,url:string,attachment_id:int,source:string,edit_url:string,view_url:string}|null */
    public static function resolve(): ?array {
        $entity = PrimaryEntityIntegration::manager()->resolve();
        if ( ! is_array( $entity ) || 'user' !== (string) ( $entity['kind'] ?? '' ) ) {
            return null;
        }

        $user_id = (int) ( $entity['id'] ?? 0 );
        if ( $user_id <= 0 ) {
            return null;
        }

        $profile_image = function_exists( 'get_field' ) ? get_field( 'profile_photo', 'user_' . $user_id ) : null;
        $image         = self::normalize_image( $profile_image );
        $source        = '' !== $image['url'] ? 'Profile photo' : '';

        if ( '' === $image['url'] && function_exists( 'get_avatar_url' ) ) {
            $image  = self::normalize_image( (string) get_avatar_url( $user_id, [ 'size' => 512 ] ) );
            $source = '' !== $image['url'] ? 'WordPress avatar' : '';
        }

        if ( '' === $image['url'] ) {
            $image  = self::normalize_image( (string) ( $entity['image_url'] ?? '' ) );
            $source = '' !== $image['url'] ? 'Canonical entity image' : '';
        }

        return [
            'id'            => $user_id,
            'name'          => sanitize_text_field( (string) ( $entity['name'] ?? '' ) ),
            'url'           => $image['url'],
            'attachment_id' => $image['attachment_id'],
            'source'        => $source,
            'edit_url'      => esc_url_raw( (string) ( $entity['edit_url'] ?? '' ) ),
            'view_url'      => esc_url_raw( (string) ( $entity['view_url'] ?? '' ) ),
        ];
    }

    /** @return array{url:string,attachment_id:int} */
    private static function normalize_image( mixed $value ): array {
        $attachment_id = 0;
        $url           = '';

        if ( is_array( $value ) ) {
            $attachment_id = (int) ( $value['ID'] ?? $value['id'] ?? 0 );
            $url           = (string) ( $value['url'] ?? $value['sizes']['large'] ?? $value['sizes']['medium'] ?? '' );
        } elseif ( is_object( $value ) && isset( $value->ID ) ) {
            $attachment_id = (int) $value->ID;
        } elseif ( is_numeric( $value ) ) {
            $attachment_id = (int) $value;
        } elseif ( is_string( $value ) ) {
            $url = $value;
        }

        if ( $attachment_id > 0 && '' === $url && function_exists( 'wp_get_attachment_image_url' ) ) {
            $url = (string) ( wp_get_attachment_image_url( $attachment_id, 'full' ) ?: '' );
        }

        $url = esc_url_raw( $url );
        if ( $attachment_id <= 0 && '' !== $url ) {
            $attachment_id = self::attachment_id_from_url( $url );
        }

        return [ 'url' => $url, 'attachment_id' => max( 0, $attachment_id ) ];
    }

    private static function attachment_id_from_url( string $url ): int {
        if ( ! function_exists( 'attachment_url_to_postid' ) ) {
            return 0;
        }

        $attachment_id = (int) attachment_url_to_postid( $url );
        if ( $attachment_id > 0 ) {
            return $attachment_id;
        }

        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $full = preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $path );
        if ( ! is_string( $full ) || $full === $path ) {
            return 0;
        }

        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return 0;
        }

        $original_url = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . $full;
        return (int) attachment_url_to_postid( $original_url );
    }
}
