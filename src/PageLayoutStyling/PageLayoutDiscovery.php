<?php

declare( strict_types=1 );

namespace HWS\BaseTools\PageLayoutStyling;

defined( 'ABSPATH' ) || exit;

final class PageLayoutDiscovery {
    /**
     * @return array{theme:string,renderer:string,file:string,line:int,excerpt:string,layout_selector:string,parent_selector:string,scoped_selector:string,edit_url:string}
     */
    public static function current(): array {
        $elementor = self::elementor_page_template();
        if ( null !== $elementor ) {
            return $elementor;
        }

        $theme = wp_get_theme();
        $theme_name = trim( (string) $theme->get( 'Name' ) );
        $theme_slug = (string) $theme->get_stylesheet();
        $template = self::locate_content_template();
        $selector = self::parent_selector( $template['contents'] );

        return [
            'theme'           => $theme_name ?: $theme_slug,
            'renderer'        => 'Theme PHP fallback',
            'file'            => $theme_slug . '/' . $template['relative'],
            'line'            => $template['line'],
            'excerpt'         => $template['excerpt'],
            'layout_selector' => '#content.site-main',
            'parent_selector' => $selector,
            'scoped_selector' => 'body.hws-page-layout-styled ' . $selector,
            'edit_url'        => '',
        ];
    }

    /**
     * @return array{theme:string,renderer:string,file:string,line:int,excerpt:string,layout_selector:string,parent_selector:string,scoped_selector:string,edit_url:string}|null
     */
    private static function elementor_page_template(): ?array {
        if ( ! post_type_exists( 'elementor_library' ) ) {
            return null;
        }
        $template_ids = get_posts( [
            'post_type'              => 'elementor_library',
            'post_status'            => 'publish',
            'posts_per_page'         => 20,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'meta_key'               => '_elementor_template_type',
            'meta_value'             => 'single-page',
        ] );

        foreach ( $template_ids as $template_id ) {
            $template_id = (int) $template_id;
            $conditions = get_post_meta( $template_id, '_elementor_conditions', true );
            if ( ! is_array( $conditions ) || ! in_array( 'include/singular/page', $conditions, true ) ) {
                continue;
            }
            $elements = json_decode( (string) get_post_meta( $template_id, '_elementor_data', true ), true );
            $widget = self::find_post_content_widget( is_array( $elements ) ? $elements : [] );
            if ( null === $widget ) {
                continue;
            }
            $call = self::elementor_content_call();
            $title = trim( (string) get_the_title( $template_id ) );
            $outer_selector = '.elementor-element-' . sanitize_html_class( $widget['outer_id'] );
            $content_selector = '.elementor-element-' . sanitize_html_class( $widget['widget_id'] ) . '.elementor-widget-theme-post-content';
            return [
                'theme'           => (string) wp_get_theme()->get( 'Name' ),
                'renderer'        => 'Elementor Theme Builder — ' . ( $title ?: 'Default Page Template' ) . ' (#' . $template_id . ')',
                'file'            => $call['file'],
                'line'            => $call['line'],
                'excerpt'         => $call['excerpt'],
                'layout_selector' => $outer_selector,
                'parent_selector' => $content_selector,
                'scoped_selector' => 'body.hws-page-layout-styled .elementor-location-single ' . $content_selector,
                'edit_url'        => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
            ];
        }

        return null;
    }

    /** @param array<int,mixed> $elements
     *  @param string[] $container_ids
     *  @return array{widget_id:string,outer_id:string}|null
     */
    private static function find_post_content_widget( array $elements, array $container_ids = [] ): ?array {
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $id = sanitize_key( (string) ( $element['id'] ?? '' ) );
            $containers = $container_ids;
            if ( 'container' === ( $element['elType'] ?? '' ) && '' !== $id ) {
                $containers[] = $id;
            }
            if ( 'theme-post-content' === ( $element['widgetType'] ?? '' ) && '' !== $id ) {
                return [ 'widget_id' => $id, 'outer_id' => $containers[0] ?? $id ];
            }
            $found = self::find_post_content_widget( is_array( $element['elements'] ?? null ) ? $element['elements'] : [], $containers );
            if ( null !== $found ) {
                return $found;
            }
        }
        return null;
    }

    /** @return array{file:string,line:int,excerpt:string} */
    private static function elementor_content_call(): array {
        $relative = 'elementor-pro/modules/posts/skins/skin-content-base.php';
        $path = trailingslashit( WP_PLUGIN_DIR ) . $relative;
        if ( is_readable( $path ) ) {
            $contents = (string) file_get_contents( $path );
            if ( preg_match( "/echo\s+apply_filters\(\s*'the_content'/", $contents, $match, PREG_OFFSET_CAPTURE ) ) {
                $line = substr_count( substr( $contents, 0, (int) $match[0][1] ), "\n" ) + 1;
                return [ 'file' => $relative, 'line' => $line, 'excerpt' => self::excerpt( $contents, $line ) ];
            }
        }
        return [
            'file'    => $relative,
            'line'    => 0,
            'excerpt' => "echo apply_filters( 'the_content', get_the_content() );",
        ];
    }

    /** @return array{relative:string,contents:string,line:int,excerpt:string} */
    private static function locate_content_template(): array {
        $roots = array_values(
            array_unique(
                array_filter( [ get_stylesheet_directory(), get_template_directory() ], 'is_dir' )
            )
        );
        $candidates = [
            'page.php',
            'singular.php',
            'template-parts/content-page.php',
            'template-parts/content/content-page.php',
            'template-parts/single.php',
            'index.php',
        ];

        foreach ( $roots as $root ) {
            foreach ( $candidates as $relative ) {
                $path = trailingslashit( $root ) . $relative;
                if ( ! is_readable( $path ) ) {
                    continue;
                }
                $contents = (string) file_get_contents( $path );
                if ( ! preg_match( '/\bthe_content\s*\(/', $contents, $match, PREG_OFFSET_CAPTURE ) ) {
                    continue;
                }
                $offset = (int) $match[0][1];
                $line = substr_count( substr( $contents, 0, $offset ), "\n" ) + 1;
                return [
                    'relative' => $relative,
                    'contents' => $contents,
                    'line'     => $line,
                    'excerpt'  => self::excerpt( $contents, $line ),
                ];
            }
        }

        return [
            'relative' => 'page.php',
            'contents' => '',
            'line'     => 0,
            'excerpt'  => "<?php the_content(); ?>",
        ];
    }

    private static function excerpt( string $contents, int $line ): string {
        $lines = preg_split( '/\R/', $contents ) ?: [];
        $start = max( 0, $line - 5 );
        $length = min( 10, count( $lines ) - $start );
        return trim( implode( "\n", array_slice( $lines, $start, $length ) ) );
    }

    private static function parent_selector( string $contents ): string {
        if ( str_contains( $contents, 'id="content"' )
            && str_contains( $contents, "post_class( 'site-main' )" )
            && str_contains( $contents, 'class="page-content"' ) ) {
            return '#content.site-main > .page-content';
        }
        if ( str_contains( $contents, 'class="entry-content"' ) ) {
            return '.entry-content';
        }
        if ( str_contains( $contents, 'class="page-content"' ) ) {
            return '.page-content';
        }
        return 'main .entry-content';
    }
}
