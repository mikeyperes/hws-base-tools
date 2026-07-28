<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class BrandTemplateRenderer {
    public static function render( string $context ): void {
        $context = sanitize_key( $context );
        echo '<div class="hws-default-template hws-default-template--' . esc_attr( str_replace( '_', '-', $context ) ) . '">';
        if ( BrandTemplateRegistry::AUTHOR === $context ) {
            self::render_author();
        } elseif ( BrandTemplateRegistry::PAGE === $context ) {
            self::render_page();
        } elseif ( BrandTemplateRegistry::CATEGORY === $context ) {
            self::render_term( BrandTemplateRegistry::CATEGORY );
        } elseif ( BrandTemplateRegistry::TAG === $context ) {
            self::render_term( BrandTemplateRegistry::TAG );
        }
        echo '</div>';
    }

    private static function render_page(): void {
        while ( have_posts() ) {
            the_post();
            $title = get_the_title();
            self::intro( $title, '' );
            echo '<main class="hws-default-template__body"><div class="hws-default-template__content hws-brand-content">';
            the_content();
            wp_link_pages(
                [
                    'before' => '<nav class="hws-default-template__page-links" aria-label="' . esc_attr__( 'Page sections', 'hws-base-tools' ) . '">',
                    'after'  => '</nav>',
                ]
            );
            echo '</div></main>';
        }
    }

    private static function render_author(): void {
        $author = get_queried_object();
        if ( ! $author instanceof \WP_User ) {
            return;
        }
        $name = $author->display_name ?: $author->user_login;
        $description = (string) get_the_author_meta( 'description', $author->ID );

        echo '<header class="hws-default-template__author" data-smpi-breadcrumbs-injected="1">';
        echo '<div class="hws-default-template__inner hws-default-template__author-inner">';
        echo get_avatar( $author->ID, 136, '', $name, [ 'class' => 'hws-default-template__avatar' ] );
        echo '<div class="hws-default-template__author-copy">';
        echo self::breadcrumbs( $name );
        echo '<h1>' . esc_html( $name ) . '</h1>';
        if ( '' !== trim( $description ) ) {
            echo '<div class="hws-default-template__description">' . wp_kses_post( wpautop( $description ) ) . '</div>';
        }
        echo '</div></div></header>';

        self::archive_results( sprintf( 'Latest from %s', $name ) );
    }

    private static function render_term( string $context ): void {
        $term = get_queried_object();
        if ( ! $term instanceof \WP_Term ) {
            return;
        }
        $title = $term->name;
        $description = term_description( $term );
        self::intro( $title, is_string( $description ) ? $description : '' );
        self::archive_results(
            BrandTemplateRegistry::CATEGORY === $context
                ? sprintf( 'Latest in %s', $title )
                : sprintf( 'Latest tagged %s', $title )
        );
    }

    private static function intro( string $title, string $description ): void {
        echo '<header class="hws-default-template__intro" data-smpi-breadcrumbs-injected="1">';
        echo '<div class="hws-default-template__inner">';
        echo self::breadcrumbs( $title );
        echo '<h1>' . esc_html( $title ) . '</h1>';
        if ( '' !== trim( wp_strip_all_tags( $description ) ) ) {
            echo '<div class="hws-default-template__description">' . wp_kses_post( $description ) . '</div>';
        }
        echo '</div></header>';
    }

    private static function archive_results( string $heading ): void {
        echo '<main class="hws-default-template__archive"><div class="hws-default-template__inner">';
        echo '<h2>' . esc_html( $heading ) . '</h2>';
        if ( have_posts() ) {
            echo '<div class="hws-default-template__grid">';
            while ( have_posts() ) {
                the_post();
                self::archive_card();
            }
            echo '</div>';
            the_posts_pagination(
                [
                    'mid_size'  => 2,
                    'prev_text' => esc_html__( 'Previous', 'hws-base-tools' ),
                    'next_text' => esc_html__( 'Next', 'hws-base-tools' ),
                ]
            );
        } else {
            echo '<p class="hws-default-template__empty">' . esc_html__( 'No articles are available yet.', 'hws-base-tools' ) . '</p>';
        }
        echo '</div></main>';
    }

    private static function archive_card(): void {
        $url = get_permalink();
        echo '<article class="hws-default-template__card">';
        if ( has_post_thumbnail() ) {
            echo '<a class="hws-default-template__card-image" href="' . esc_url( $url ) . '" aria-hidden="true" tabindex="-1">';
            the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy' ] );
            echo '</a>';
        }
        echo '<div class="hws-default-template__card-copy">';
        echo '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>';
        echo '<h3><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        $excerpt = trim( wp_strip_all_tags( get_the_excerpt() ) );
        if ( '' !== $excerpt ) {
            echo '<p>' . esc_html( wp_trim_words( $excerpt, 22 ) ) . '</p>';
        }
        echo '<a class="hws-default-template__read-more" href="' . esc_url( $url ) . '">' . esc_html__( 'Read article', 'hws-base-tools' ) . '<span aria-hidden="true"> &rarr;</span></a>';
        echo '</div></article>';
    }

    private static function breadcrumbs( string $current_title ): string {
        if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
            ob_start();
            rank_math_the_breadcrumbs();
            $breadcrumbs = (string) ob_get_clean();
            if ( '' !== trim( $breadcrumbs ) ) {
                return '<div class="hws-default-template__breadcrumbs">' . $breadcrumbs . '</div>';
            }
        }
        if ( shortcode_exists( 'rank_math_breadcrumb' ) ) {
            $breadcrumbs = do_shortcode( '[rank_math_breadcrumb]' );
            if ( '' !== trim( $breadcrumbs ) ) {
                return '<div class="hws-default-template__breadcrumbs">' . $breadcrumbs . '</div>';
            }
        }

        return '<nav class="hws-default-template__breadcrumbs" aria-label="Breadcrumbs"><a href="'
            . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'hws-base-tools' )
            . '</a><span aria-hidden="true">/</span><span aria-current="page">' . esc_html( $current_title ) . '</span></nav>';
    }
}
