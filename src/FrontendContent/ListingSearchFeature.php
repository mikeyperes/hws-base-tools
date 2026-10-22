<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\ListingSearch\ListingContext;
use Hexa\PluginCore\ListingSearch\ListingSearchRegistry;
use Hexa\PluginCore\ListingSearch\ListingSearchRenderer;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;

defined( 'ABSPATH' ) || exit;

/**
 * The site's listing search: `[hws_search]` places a search box with
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
    public const SHORTCODE = 'hws_search';
    /** Former name, still served so existing pages keep working. */
    public const LEGACY_SHORTCODE = 'hws_listing_search';
    public const PROFILE = 'listing';
    public const AUTO_LOAD_CLASSES = [ 'hws-auto-load-more', 'smpi-auto-load-more' ];
    public const STYLE_OPTION = 'hws_listing_search_style';
    public const STYLES = [
        'none'      => 'No styling',
        'default-1' => 'Default 1',
        'default-2' => 'Default 2',
    ];

    /**
     * Every `[hws_search]` attribute, with what it does and an example. The
     * settings screen lists these, so code and help never drift apart.
     */
    public const ATTRIBUTES = [
        'style'        => [ 'Look of this box, overriding the site setting: none (no styling), default-1 or default-2.', 'style="none"' ],
        'filters'      => [ 'Which filters to show: any of type, category, tag, author. Leave out for all.', 'filters="category,author"' ],
        'placeholder'  => [ 'Text inside the empty search field. Default: "Search <page title>".', 'placeholder="Search stories"' ],
        'results_one'  => [ 'Count line for one result; %d is the number.', 'results_one="%d article"' ],
        'results_many' => [ 'Count line for several results; %d is the number.', 'results_many="%d articles"' ],
        'empty'        => [ 'Line shown when nothing matches.', 'empty="Nothing found"' ],
        'clear'        => [ 'Text of the link that resets the search.', 'clear="Reset"' ],
        'accent'       => [ 'Accent colour (focus line, ticked filters, count badge).', 'accent="#c2410c"' ],
        'border'       => [ 'Border colour of the field and filter buttons.', 'border="#d0d5dd"' ],
        'radius'       => [ 'Corner rounding.', 'radius="0"' ],
        'panel_bg'     => [ 'Background of the opened filter lists.', 'panel_bg="#ffffff"' ],
        'field_width'  => [ 'Widest the search field gets.', 'field_width="480px"' ],
        'field_height' => [ 'Height of the field and filter buttons.', 'field_height="42px"' ],
        'font_size'    => [ 'Text size of the box.', 'font_size="15px"' ],
        'target'       => [ 'CSS selector of the list to refresh, when it is not the page\'s marked list.', 'target=".my-grid"' ],
        'class'        => [ 'Extra CSS class on the box, for site CSS.', 'class="sidebar-search"' ],
    ];

    /** Look attributes, each setting one of the box's custom properties. */
    private const LOOK_ATTRIBUTES = [
        'accent'       => '--hls-accent',
        'border'       => '--hls-border',
        'radius'       => '--hls-radius',
        'panel_bg'     => '--hls-panel-bg',
        'field_width'  => '--hls-field-width',
        'field_height' => '--hls-field-height',
        'font_size'    => '--hls-font-size',
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
        add_shortcode( self::LEGACY_SHORTCODE, [ self::class, 'render_shortcode' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_shortcode( $attributes = [] ): string {
        if ( ! class_exists( ListingSearchRenderer::class ) ) {
            return '';
        }

        $attributes = shortcode_atts(
            [ 'profile' => self::PROFILE ] + array_fill_keys( array_keys( self::ATTRIBUTES ), '' ),
            is_array( $attributes ) ? $attributes : [],
            self::SHORTCODE
        );

        // Look attributes become this box's own custom properties through a unique class.
        $look = '';
        foreach ( self::LOOK_ATTRIBUTES as $key => $property ) {
            $value = trim( (string) $attributes[ $key ] );
            if ( '' === $value || ! preg_match( '/^[#a-zA-Z0-9.,%()\s-]{1,40}$/', $value ) ) {
                continue;
            }
            $look .= $property . ':' . ( preg_match( '/^\d+(\.\d+)?$/', $value ) && 'border' !== $key && 'accent' !== $key && 'panel_bg' !== $key ? $value . 'px' : $value ) . ';';
        }
        $look_class = '' !== $look ? 'hws-hls-' . substr( md5( $look ), 0, 8 ) : '';

        $options = array_filter( [
            'style'        => isset( self::STYLES[ (string) $attributes['style'] ] ) ? (string) $attributes['style'] : self::style(),
            'placeholder'  => (string) $attributes['placeholder'],
            'filters'      => (string) $attributes['filters'],
            'target'       => (string) $attributes['target'],
            'class'        => trim( (string) $attributes['class'] . ' ' . $look_class ),
            'results_one'  => (string) $attributes['results_one'],
            'results_many' => (string) $attributes['results_many'],
            'empty'        => (string) $attributes['empty'],
            'clear'        => (string) $attributes['clear'],
        ], static fn( string $value ): bool => '' !== trim( $value ) );
        if ( is_search() ) {
            $options['hidden'] = SearchQueryFeature::marker_fields();
        }

        $html = ( new ListingSearchRenderer() )->render( sanitize_key( (string) $attributes['profile'] ), $options );

        return '' !== $html && '' !== $look ? '<style>.' . $look_class . '.hls{' . $look . '}</style>' . $html : $html;
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
