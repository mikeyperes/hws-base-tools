<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\ListingSearch\ListingContext;
use Hexa\PluginCore\ListingSearch\ListingSearchRegistry;
use Hexa\PluginCore\ListingSearch\ListingSearchRenderer;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;

defined( 'ABSPATH' ) || exit;

/**
 * The site's listing search: `[hws_listing_search]` places a search box with
 * type, category, tag and author filters above any list of posts. On a
 * category, tag, author, search or post type page it searches inside that
 * page; the list refreshes in place as visitors type or tick.
 *
 * The list to refresh is the page's Elementor Loop Grid, Posts or Archive
 * Posts widget with the CSS class `hexa-listing-results` (or one a plugin
 * marks itself). Text matching follows the site's search settings. Its look
 * is chosen in Settings, Search: No styling, Default 1 or Default 2.
 *
 * It also keeps lists loading while visitors scroll: a Loop Grid or Posts
 * widget set to "Load on click" with the CSS class `hws-auto-load-more`
 * presses its own Load more button as it comes into view, and the button
 * stays as the fallback. Elementor offers either the button or infinite
 * scroll, never both.
 */
final class ListingSearchFeature implements ModuleInterface {
    public const SHORTCODE = 'hws_listing_search';
    public const PROFILE = 'listing';
    public const AUTO_LOAD_CLASSES = [ 'hws-auto-load-more', 'smpi-auto-load-more' ];
    public const STYLE_OPTION = 'hws_listing_search_style';
    public const STYLES = [
        'none'      => 'No styling',
        'default-1' => 'Default 1',
        'default-2' => 'Default 2',
    ];

    /** The site's chosen look for the search box (Settings: Search). */
    public static function style(): string {
        $style = (string) get_option( self::STYLE_OPTION, 'default-1' );

        return isset( self::STYLES[ $style ] ) ? $style : 'default-1';
    }

    public function register(): void {
        add_action( ListingSearchRegistry::REGISTER_ACTION, [ self::class, 'register_profile' ] );
        add_filter( ListingContext::SEARCH_TYPES_FILTER, [ self::class, 'search_post_types' ] );
        add_action( 'init', [ self::class, 'register_shortcode' ] );
        add_action( 'wp_footer', [ self::class, 'print_auto_load_more_script' ], 34 );
    }

    public static function register_profile(): void {
        ListingSearchRegistry::register( self::PROFILE, [
            'filters'     => [
                'type'     => [ 'type' => 'post_type', 'options' => 'types', 'control' => 'checkboxes', 'label' => 'Type', 'hide_empty' => true ],
                'category' => [ 'type' => 'taxonomy', 'taxonomy' => 'category', 'options' => 'terms', 'control' => 'checkboxes', 'label' => 'Category', 'hide_empty' => true ],
                'tag'      => [ 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'options' => 'terms', 'control' => 'checkboxes', 'label' => 'Tag', 'hide_empty' => true, 'limit' => 15 ],
                'author'   => [ 'type' => 'author', 'options' => 'authors', 'control' => 'checkboxes', 'label' => 'Author', 'hide_empty' => true, 'limit' => 20 ],
            ],
            'search'      => [ SearchQueryFeature::class, 'settings' ],
            'search_args' => SearchQueryFeature::marker_fields() + [ SearchQueryEngine::EXPLICIT_QUERY_VAR => '1' ],
            'post_types'  => [ 'post' ],
        ] );
    }

    /**
     * Site search lists what the site's search settings cover once they are
     * switched on; otherwise every public type WordPress searches.
     *
     * @param string[] $types
     * @return string[]
     */
    public static function search_post_types( $types ): array {
        $settings = SearchQueryFeature::settings();

        return ! empty( $settings['enabled'] ) && [] !== (array) $settings['post_types'] ? array_values( (array) $settings['post_types'] ) : (array) $types;
    }

    public static function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_shortcode( $attributes = [] ): string {
        if ( ! class_exists( ListingSearchRenderer::class ) ) {
            return '';
        }

        $attributes = shortcode_atts(
            [
                'profile'      => self::PROFILE,
                'style'        => '',
                'placeholder'  => '',
                'filters'      => '',
                'target'       => '',
                'class'        => '',
                'results_one'  => '',
                'results_many' => '',
                'clear'        => '',
            ],
            is_array( $attributes ) ? $attributes : [],
            self::SHORTCODE
        );

        $options = array_filter( [
            'style'        => isset( self::STYLES[ (string) $attributes['style'] ] ) ? (string) $attributes['style'] : self::style(),
            'placeholder'  => (string) $attributes['placeholder'],
            'filters'      => (string) $attributes['filters'],
            'target'       => (string) $attributes['target'],
            'class'        => (string) $attributes['class'],
            'results_one'  => (string) $attributes['results_one'],
            'results_many' => (string) $attributes['results_many'],
            'clear'        => (string) $attributes['clear'],
        ], static fn( string $value ): bool => '' !== trim( $value ) );
        if ( is_search() ) {
            $options['hidden'] = SearchQueryFeature::marker_fields();
        }

        return ( new ListingSearchRenderer() )->render( sanitize_key( (string) $attributes['profile'] ), $options );
    }

    /**
     * Infinite scroll with a visible Load more button. Page captures that
     * compare first-load states set window.hexaFreezeAutoLoad, which keeps the
     * first batch.
     */
    public static function print_auto_load_more_script(): void {
        if ( is_admin() || wp_doing_ajax() || is_feed() ) {
            return;
        }

        $selectors = [];
        foreach ( self::AUTO_LOAD_CLASSES as $class ) {
            $selectors[] = '.' . $class . ' .e-loop__load-more .elementor-button';
            $selectors[] = '.' . $class . ' .elementor-button-wrapper .elementor-button';
        }
        $wrappers = '.' . implode( ',.', self::AUTO_LOAD_CLASSES );
        ?>
<script id="hws-auto-load-more">(function(){var sel=<?php echo wp_json_encode( implode( ',', $selectors ) ); ?>,wrap=<?php echo wp_json_encode( $wrappers ); ?>,queued=false;function check(){queued=false;if(window.hexaFreezeAutoLoad)return;document.querySelectorAll(sel).forEach(function(btn){var w=btn.closest(wrap);if(!w||w.classList.contains("e-load-more-pagination-loading")||w.classList.contains("e-load-more-pagination-end")||!btn.offsetParent)return;var r=btn.getBoundingClientRect();if(r.top<window.innerHeight+300&&r.bottom>0)btn.click();});}function queue(){if(!queued){queued=true;window.requestAnimationFrame(check);}}window.addEventListener("scroll",queue,{passive:true});window.addEventListener("resize",queue);document.addEventListener("DOMContentLoaded",queue);window.addEventListener("load",queue);document.addEventListener("hexa-listing:updated",queue);})();</script>
        <?php
    }
}
