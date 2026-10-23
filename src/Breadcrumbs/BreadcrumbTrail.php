<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Breadcrumbs;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the breadcrumb band. The trail comes from Rank Math when it is
 * active, otherwise from WordPress itself. The markup keeps the class names
 * the band used in SMP Publication Integration (smpi-breadcrumbs-band,
 * smpi-bc-b1..b6, smpi-breadcrumb-*) so sites that styled it keep their look.
 */
final class BreadcrumbTrail {
    public const CSS_SCOPE_MARKER = '.smpi-breadcrumbs-band';

    /** Whether the current front-end request should show a breadcrumb. */
    public static function should_render( array $settings ): bool {
        if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
            return false;
        }
        if ( $settings['hide_home'] && ( is_front_page() || is_home() ) ) {
            return false;
        }
        if ( is_category() || is_tag() || is_tax() ) {
            return get_queried_object() instanceof \WP_Term && ! $settings['hide_term_archives'];
        }
        if ( '' !== self::archive_label() ) {
            return true;
        }
        if ( ! is_singular() ) {
            return false;
        }
        $post = get_post();
        if ( ! $post instanceof \WP_Post ) {
            return false;
        }
        if ( in_array( (string) get_post_type( $post ), (array) $settings['disabled_post_types'], true ) ) {
            return false;
        }

        return ! in_array( (int) $post->ID, (array) $settings['disabled_ids'], true );
    }

    public static function markup( array $settings, string $style = '' ): string {
        $style  = in_array( $style, BreadcrumbSettings::STYLES, true ) ? $style : (string) $settings['style'];
        $crumbs = self::rank_math_markup();
        if ( '' === trim( $crumbs ) ) {
            $crumbs = self::fallback_markup();
        }

        return '' === trim( $crumbs ) ? '' : self::band( self::decorate( $crumbs ), $style, self::current_title() );
    }

    /** A sample band for the settings screen, built with the live markup. */
    public static function sample( string $style ): string {
        $items = [
            [ 'url' => home_url( '/' ), 'label' => __( 'Home', 'hws-base-tools' ) ],
            [ 'url' => home_url( '/news/' ), 'label' => __( 'News', 'hws-base-tools' ) ],
            [ 'url' => '', 'label' => __( 'Sample article headline', 'hws-base-tools' ) ],
        ];

        return self::band( self::items_markup( $items ), $style, __( 'Sample article headline', 'hws-base-tools' ) );
    }

    /** @return array<int,string> CSS selectors the band is placed after, in order. */
    public static function header_selectors(): array {
        return [ '.elementor-location-header', '[data-elementor-type="header"]', 'header.site-header', 'header#masthead', 'header' ];
    }

    public static function rank_math_available(): bool {
        return function_exists( 'rank_math_get_breadcrumbs' ) || function_exists( 'rank_math_the_breadcrumbs' ) || shortcode_exists( 'rank_math_breadcrumb' );
    }

    private static function band( string $crumbs, string $style, string $title ): string {
        $title_html = in_array( $style, [ 'bc-b1', 'bc-b5' ], true ) && '' !== $title
            ? '<div class="smpi-template-title smpi-breadcrumb-title">' . esc_html( $title ) . '</div>'
            : '';
        $content   = 'bc-b5' === $style ? $crumbs . $title_html : $title_html . $crumbs;
        $selectors = wp_json_encode( self::header_selectors() );

        return '<div class="smpi-breadcrumbs-band hws-breadcrumbs-band" data-hws-breadcrumbs-band data-hws-header-selectors="' . esc_attr( $selectors ? $selectors : '[]' ) . '">'
            . '<div class="smpi-template smpi-template--breadcrumbs smpi-breadcrumbs hws-breadcrumbs smpi-' . esc_attr( $style ) . '" data-hws-breadcrumbs-style="' . esc_attr( $style ) . '">'
            . self::safe_markup( $content )
            . '</div></div>';
    }

    private static function rank_math_markup(): string {
        if ( is_category() || is_tag() ) {
            return '';
        }
        try {
            if ( function_exists( 'rank_math_get_breadcrumbs' ) ) {
                return (string) rank_math_get_breadcrumbs();
            }
            if ( shortcode_exists( 'rank_math_breadcrumb' ) ) {
                return (string) do_shortcode( '[rank_math_breadcrumb]' );
            }
            if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
                ob_start();
                rank_math_the_breadcrumbs();
                return (string) ob_get_clean();
            }
        } catch ( \Throwable $exception ) {
            return '';
        }

        return '';
    }

    private static function fallback_markup(): string {
        $items = [ [ 'url' => home_url( '/' ), 'label' => __( 'Home', 'hws-base-tools' ) ] ];
        $term  = get_queried_object();
        if ( $term instanceof \WP_Term && ( is_category() || is_tag() || is_tax() ) ) {
            $link    = get_term_link( $term );
            $items[] = [ 'url' => is_wp_error( $link ) ? '' : $link, 'label' => $term->name ];
            return self::items_markup( $items );
        }
        $archive = self::archive_label();
        if ( '' !== $archive ) {
            $items[] = [ 'url' => '', 'label' => $archive ];
            return self::items_markup( $items );
        }
        $post = get_post();
        if ( $post instanceof \WP_Post ) {
            if ( 'post' === get_post_type( $post ) ) {
                $categories = get_the_category( $post->ID );
                if ( ! empty( $categories ) ) {
                    $link    = get_category_link( $categories[0] );
                    $items[] = [ 'url' => is_wp_error( $link ) ? '' : (string) $link, 'label' => $categories[0]->name ];
                }
            } else {
                $object = get_post_type_object( (string) get_post_type( $post ) );
                if ( $object && ! empty( $object->labels->name ) ) {
                    $items[] = [ 'url' => (string) get_post_type_archive_link( (string) get_post_type( $post ) ), 'label' => $object->labels->name ];
                }
            }
        }
        $title = self::current_title();
        if ( '' !== $title ) {
            $items[] = [ 'url' => '', 'label' => $title ];
        }

        return self::items_markup( $items );
    }

    /** @param array<int,array{url:string,label:string}> $items */
    private static function items_markup( array $items ): string {
        if ( count( $items ) < 2 ) {
            return '';
        }
        $html = '<nav aria-label="breadcrumbs" class="rank-math-breadcrumb smpi-template-content smpi-breadcrumb-nav"><p class="smpi-template-list smpi-breadcrumb-list">';
        foreach ( $items as $index => $item ) {
            if ( $index > 0 ) {
                $html .= '<span class="separator smpi-breadcrumb-separator"> - </span>';
            }
            if ( count( $items ) - 1 === $index || '' === $item['url'] ) {
                $html .= '<span class="last smpi-template-item smpi-breadcrumb-item smpi-breadcrumb-current">' . esc_html( $item['label'] ) . '</span>';
            } else {
                $html .= '<a class="smpi-template-item smpi-template-link smpi-breadcrumb-item smpi-breadcrumb-link" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
            }
        }

        return $html . '</p></nav>';
    }

    /** Adds the band's classes to Rank Math's own breadcrumb markup. */
    private static function decorate( string $html ): string {
        if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
            return $html;
        }
        $processor = new \WP_HTML_Tag_Processor( $html );
        while ( $processor->next_tag() ) {
            $tag   = $processor->get_tag();
            $class = ' ' . (string) $processor->get_attribute( 'class' ) . ' ';
            $add   = match ( true ) {
                'NAV' === $tag                                      => [ 'smpi-template-content', 'smpi-breadcrumb-nav' ],
                in_array( $tag, [ 'P', 'OL', 'UL' ], true )         => [ 'smpi-template-list', 'smpi-breadcrumb-list' ],
                'LI' === $tag                                       => [ 'smpi-template-item', 'smpi-breadcrumb-item' ],
                'A' === $tag                                        => [ 'smpi-template-item', 'smpi-template-link', 'smpi-breadcrumb-item', 'smpi-breadcrumb-link' ],
                'SPAN' === $tag && str_contains( $class, ' separator ' ) => [ 'smpi-breadcrumb-separator' ],
                'SPAN' === $tag && str_contains( $class, ' last ' ) => [ 'smpi-template-item', 'smpi-breadcrumb-item', 'smpi-breadcrumb-current' ],
                default                                             => [],
            };
            foreach ( $add as $name ) {
                $processor->add_class( $name );
            }
        }

        return $processor->get_updated_html();
    }

    private static function current_title(): string {
        $term = get_queried_object();
        if ( $term instanceof \WP_Term && ( is_category() || is_tag() || is_tax() ) ) {
            return wp_strip_all_tags( single_term_title( '', false ) );
        }
        $archive = self::archive_label();
        if ( '' !== $archive ) {
            return $archive;
        }
        $post = get_post();

        return $post instanceof \WP_Post ? wp_strip_all_tags( get_the_title( $post ) ) : '';
    }

    /** Last crumb on author archives, search results and the not-found page; '' elsewhere. */
    private static function archive_label(): string {
        if ( is_author() ) {
            $author = get_queried_object();
            return $author instanceof \WP_User ? wp_strip_all_tags( $author->display_name ) : '';
        }
        if ( is_search() ) {
            /* translators: %s: the search term. */
            return sprintf( __( "Results for \u{201C}%s\u{201D}", 'hws-base-tools' ), get_search_query( false ) );
        }
        if ( is_404() ) {
            $rank_math = get_option( 'rank-math-options-general' );
            $label     = is_array( $rank_math ) ? trim( (string) ( $rank_math['breadcrumbs_404_label'] ?? '' ) ) : '';
            return '' !== $label ? $label : __( '404 Error: page not found', 'hws-base-tools' );
        }

        return '';
    }

    private static function safe_markup( string $html ): string {
        $allowed        = wp_kses_allowed_html( 'post' );
        $allowed['nav'] = [ 'class' => true, 'aria-label' => true ];
        $allowed['p']   = [ 'class' => true ];
        $allowed['span'] = [ 'class' => true ];
        $allowed['div'] = [ 'class' => true, 'role' => true, 'aria-level' => true ];

        return wp_kses( $html, $allowed );
    }
}
