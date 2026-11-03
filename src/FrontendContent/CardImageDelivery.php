<?php

namespace HWS\BaseTools\FrontendContent;

defined( 'ABSPATH' ) || exit;

/** Deliver external loop photos and Elementor byline avatars at their actual slot size. */
final class CardImageDelivery {
    private const QUERY = 'hws_card_image';
    private const MAX_WIDTH = 768;
    private const PLACEHOLDER = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/%3E';

    public function register(): void {
        add_action( 'template_redirect', [ $this, 'deliver' ], 0 );
        add_filter( 'elementor/widget/render_content', [ $this, 'render' ], 110, 2 );
    }

    public function render( string $html, $widget ): string {
        if ( is_admin() || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' )
            || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
            return $html;
        }
        $name = $widget->get_name();
        if ( 'post-info' === $name ) {
            return $this->avatars( $html, (array) $widget->get_settings_for_display() );
        }
        if ( ! in_array( $name, [ 'loop-grid', 'loop-carousel', 'posts', 'archive-posts' ], true ) ) {
            return $html;
        }
        $tags = new \WP_HTML_Tag_Processor( $html );
        $changed = false;
        while ( $tags->next_tag( 'IMG' ) ) {
            if ( $tags->get_attribute( 'data-hws-card-endpoint' ) || $tags->has_class( 'elementor-avatar' )
                || $tags->has_class( 'hws-fi-landscape' ) || $tags->has_class( 'hws-fi-portrait' ) || $tags->has_class( 'hws-fi-square' ) ) {
                continue;
            }
            $class = (string) $tags->get_attribute( 'class' );
            if ( ! preg_match( '/(?:^|\s)(?:attachment|size)-(?:thumbnail|medium|medium_large|hws-card)(?:\s|$)/', $class ) ) {
                continue;
            }
            $source = (string) ( $tags->get_attribute( 'data-src' ) ?: $tags->get_attribute( 'src' ) );
            if ( ! $this->valid_source( $source ) || ( $this->local_file( $source )
                && ( $tags->get_attribute( 'srcset' ) || $tags->get_attribute( 'data-srcset' ) ) ) ) {
                continue;
            }
            $tags->set_attribute( 'data-hws-card-endpoint', $this->endpoint( $source ) );
            $tags->set_attribute( 'src', self::PLACEHOLDER );
            foreach ( [ 'srcset', 'sizes', 'data-src', 'data-srcset', 'data-sizes', 'data-lazy-src', 'data-lazy-srcset' ] as $attribute ) {
                $tags->remove_attribute( $attribute );
            }
            $tags->set_attribute( 'data-no-lazy', '1' );
            $tags->add_class( 'skip-lazy' );
            $changed = true;
        }
        if ( $changed || str_contains( $html, 'data-hws-card-endpoint' ) ) {
            wp_enqueue_script( 'hws-card-image-delivery', plugins_url( 'assets/frontend/card-image-delivery.js', HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE ), [], '13.59.1', true );
        }
        return $tags->get_updated_html();
    }

    private function avatars( string $html, array $settings ): string {
        $widths = [];
        foreach ( (array) ( $settings['icon_list'] ?? [] ) as $item ) {
            if ( 'author' === ( $item['type'] ?? '' ) && 'yes' === ( $item['show_avatar'] ?? '' ) ) {
                $size = $item['avatar_size'] ?? [];
                if ( 'px' === ( $size['unit'] ?? 'px' ) && is_numeric( $size['size'] ?? null ) ) {
                    $widths[] = max( 1, (int) $size['size'] );
                }
            }
        }
        if ( ! $widths ) {
            return $html;
        }
        $pixels = min( self::MAX_WIDTH, min( $widths ) * 2 );
        $tags = new \WP_HTML_Tag_Processor( $html );
        while ( $tags->next_tag( 'IMG' ) ) {
            if ( ! $tags->has_class( 'elementor-avatar' ) ) {
                continue;
            }
            $source = (string) $tags->get_attribute( 'src' );
            if ( ! $this->valid_source( $source ) ) {
                continue;
            }
            if ( preg_match( '~(^|\.)gravatar\.com$~i', (string) wp_parse_url( $source, PHP_URL_HOST ) ) ) {
                $source = add_query_arg( 's', $pixels, $source );
            }
            $url = $this->rendition( $source, $pixels );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            $tags->set_attribute( 'src', $url );
            $tags->set_attribute( 'data-hws-avatar-pixels', (string) $pixels );
            foreach ( [ 'srcset', 'data-srcset', 'data-src', 'data-lazy-src' ] as $attribute ) {
                $tags->remove_attribute( $attribute );
            }
        }
        return $tags->get_updated_html();
    }

    private function endpoint( string $source ): string {
        $encoded = rtrim( strtr( base64_encode( $source ), '+/', '-_' ), '=' );
        return add_query_arg( [ self::QUERY => $encoded, 'sig' => hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) ) ], home_url( '/' ) );
    }

    public function deliver(): void {
        if ( ! isset( $_GET[ self::QUERY ] ) ) {
            return;
        }
        $encoded = is_string( $_GET[ self::QUERY ] ) ? wp_unslash( $_GET[ self::QUERY ] ) : '';
        $signature = isset( $_GET['sig'] ) && is_string( $_GET['sig'] ) ? wp_unslash( $_GET['sig'] ) : '';
        $source = base64_decode( strtr( $encoded, '-_', '+/' ), true );
        if ( ! $source || ! hash_equals( hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) ), $signature ) || ! $this->valid_source( $source ) ) {
            status_header( 400 );
            exit;
        }
        // Round down, never up: even a 2x screen must stay at or below twice the slot.
        $width = min( self::MAX_WIDTH, max( 8, (int) ( $_GET['w'] ?? 0 ) ) );
        $width -= $width % 8;
        $url = $this->rendition( $source, $width );
        if ( is_wp_error( $url ) ) {
            status_header( 502 );
            exit;
        }
        wp_safe_redirect( $url, 302, 'HWS Card Images' );
        exit;
    }

    private function valid_source( string $url ): bool {
        return in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true )
            && (bool) wp_http_validate_url( $url );
    }

    /** Only resolve files below this site's uploads directory. */
    private function local_file( string $url ): string {
        $uploads = wp_upload_dir();
        $base = trailingslashit( $uploads['baseurl'] );
        if ( ! str_starts_with( $url, $base ) ) {
            return '';
        }
        $relative = rawurldecode( (string) wp_parse_url( substr( $url, strlen( $base ) ), PHP_URL_PATH ) );
        $path = realpath( trailingslashit( $uploads['basedir'] ) . $relative );
        $root = realpath( $uploads['basedir'] );
        return $path && $root && str_starts_with( $path, trailingslashit( $root ) ) && is_file( $path ) ? $path : '';
    }

    /** A source-keyed image cache, not an attachment/post migration or an open proxy. */
    private function rendition( string $source, int $width ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return new \WP_Error( 'hws_card_uploads', 'Image cache is unavailable.' );
        }
        $local = $this->local_file( $source );
        $key = hash( 'sha256', $source . ( $local ? ':' . filemtime( $local ) : '' ) );
        $relative = 'hws-card-renditions/' . substr( $key, 0, 32 );
        $directory = trailingslashit( $uploads['basedir'] ) . $relative;
        $file = $directory . '/image-' . $width . '.webp';
        $url = trailingslashit( $uploads['baseurl'] ) . $relative . '/image-' . $width . '.webp';
        if ( is_file( $file ) ) {
            return $url;
        }
        if ( ! wp_mkdir_p( $directory ) ) {
            return new \WP_Error( 'hws_card_cache', 'Image cache cannot be created.' );
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $original = $local ?: $directory . '/source';
        if ( ! $local && ! is_file( $original ) ) {
            $temporary = wp_tempnam( 'hws-card-image' );
            $response = wp_safe_remote_get( $source, [ 'timeout' => 20, 'redirection' => 3, 'stream' => true, 'filename' => $temporary, 'limit_response_size' => 20 * MB_IN_BYTES ] );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! wp_get_image_mime( $temporary ) ) {
                wp_delete_file( $temporary );
                return new \WP_Error( 'hws_card_source', 'Source photo could not be read.' );
            }
            if ( ! rename( $temporary, $original ) ) {
                wp_delete_file( $temporary );
                return new \WP_Error( 'hws_card_source_cache', 'Source photo could not be cached.' );
            }
        }
        $editor = wp_get_image_editor( $original );
        if ( is_wp_error( $editor ) ) {
            return $editor;
        }
        $dimensions = $editor->get_size();
        if ( $dimensions['width'] > $width ) {
            $resized = $editor->resize( $width, null, false );
            if ( is_wp_error( $resized ) ) {
                return $resized;
            }
        }
        $temporary = $file . '.' . wp_generate_uuid4() . '.webp';
        $saved = $editor->save( $temporary, 'image/webp' );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }
        if ( ! rename( $saved['path'], $file ) ) {
            wp_delete_file( $saved['path'] );
            return new \WP_Error( 'hws_card_save', 'Image rendition could not be saved.' );
        }
        return $url;
    }
}
