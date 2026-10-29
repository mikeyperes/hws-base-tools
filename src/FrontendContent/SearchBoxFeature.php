<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\Search\SearchBox;
use Hexa\PluginCore\Search\SearchContext;
use Hexa\PluginCore\Search\SearchRegistry;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;

defined( 'ABSPATH' ) || exit;

/**
 * The site's search, drawn by Hexa WP Core's one search (SearchBox):
 *
 * - `[hws_search]` places the search box with type, category, tag and author
 *   filters above any list of posts. On a category, tag, author, search or
 *   post type page it searches inside that page; on any other page it
 *   searches what its `post_type`, `category`, `tag` and `author`
 *   attributes say (a homepage section). The list it refreshes is the
 *   page's own list or the Elementor loop with the CSS class
 *   `hexa-listing-results`. Its look is chosen in Settings, Search.
 * - `[hws_search_box]` is the header search: a magnifying glass that opens
 *   a field in place, or an always-open field. It goes to the search page.
 *
 * It also keeps lists loading while visitors scroll: a Loop Grid or Posts
 * widget set to "Load on click" with the CSS class `hws-auto-load-more`
 * presses its own Load more button as it comes into view, and the button
 * stays as the fallback.
 *
 * Markup and styling: docs/hws-search.md.
 */
final class SearchBoxFeature implements ModuleInterface {
    public const SHORTCODE = 'hws_search';
    public const HEADER_SHORTCODE = 'hws_search_box';
    public const PROFILE = 'listing';
    public const AUTO_LOAD_CLASSES = [ 'hws-auto-load-more', 'smpi-auto-load-more' ];
    public const STYLE_OPTION = 'hws_listing_search_style';
    public const HEADER_OPTION = 'hws_search_display';
    public const STYLES = [
        'none'      => 'No styling',
        'default-1' => 'Default 1',
        'default-2' => 'Default 2',
    ];
    public const HEADER_STYLES = [
        'icon-reveal' => 'Magnifying glass that opens a field in place',
        'field'       => 'Always-open search field',
    ];

    public const ATTRIBUTES = [
        'Look'    => [
            'style'        => [ 'none, default-1 or default-2', 'style="none"' ],
            'accent'       => [ 'Focus line, ticked filters, badge', 'accent="#c2410c"' ],
            'border'       => [ 'Field and button border', 'border="#d0d5dd"' ],
            'panel_bg'     => [ 'Opened filter list background', 'panel_bg="#fff"' ],
            'radius'       => [ 'Corner rounding', 'radius="0"' ],
            'field_width'  => [ 'Widest the field gets', 'field_width="480px"' ],
            'field_height' => [ 'Field and button height', 'field_height="42px"' ],
            'font_size'    => [ 'Text size', 'font_size="15px"' ],
        ],
        'Content' => [
            'filters'      => [ 'Any of type, category, tag, author', 'filters="category,author"' ],
            'post_type'    => [ 'On an ordinary page: what it searches', 'post_type="press_release"' ],
            'category'     => [ 'On an ordinary page: only these categories', 'category="business,finance"' ],
            'tag'          => [ 'On an ordinary page: only these tags', 'tag="women-founders"' ],
            'author'       => [ 'On an ordinary page: only these author IDs', 'author="12"' ],
            'placeholder'  => [ 'Empty field text', 'placeholder="Search stories"' ],
            'results_one'  => [ 'One result; %d is the number', 'results_one="%d article"' ],
            'results_many' => [ 'Several results', 'results_many="%d articles"' ],
            'empty'        => [ 'No results', 'empty="Nothing found"' ],
            'clear'        => [ 'Reset link text', 'clear="Reset"' ],
        ],
        'Advanced' => [
            'target'       => [ 'List to refresh (CSS selector)', 'target=".my-grid"' ],
            'class'        => [ 'Extra CSS class on the box', 'class="sidebar-search"' ],
        ],
    ];

    /** The site's chosen look for the search box (Settings: Search). */
    public static function style(): string {
        $style = (string) get_option( self::STYLE_OPTION, 'default-1' );

        return isset( self::STYLES[ $style ] ) ? $style : 'default-1';
    }

    /** @return array{style:string,accent:string,placeholder:string} The header search box's saved look. */
    public static function header_settings(): array {
        $stored = get_option( self::HEADER_OPTION, [] );

        return self::sanitize_header( is_array( $stored ) ? $stored : [] );
    }

    /**
     * @param array<string,mixed> $settings
     * @return array{style:string,accent:string,placeholder:string}
     */
    public static function sanitize_header( array $settings ): array {
        $style = sanitize_key( (string) ( $settings['style'] ?? 'icon-reveal' ) );
        // Looks from before the one search: the reveal and overlay became the in-place reveal, the rest a field.
        $style = isset( self::HEADER_STYLES[ $style ] ) ? $style : ( in_array( $style, [ 'overlay' ], true ) ? 'icon-reveal' : 'field' );
        $accent = trim( (string) ( $settings['accent'] ?? '' ) );
        $accent = '' !== $accent && function_exists( 'sanitize_hex_color' ) ? (string) sanitize_hex_color( $accent ) : ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent ) ? $accent : '' );
        $placeholder = sanitize_text_field( (string) ( $settings['placeholder'] ?? '' ) );

        return [ 'style' => $style, 'accent' => $accent, 'placeholder' => '' !== trim( $placeholder ) ? $placeholder : 'Search…' ];
    }

    public function register(): void {
        add_filter( 'hws_base_tools_render_people_search', static fn( $html, array $directory ): string => PeopleSearchProfile::render( $directory ), 10, 2 );
        add_action( SearchRegistry::REGISTER_ACTION, [ self::class, 'register_profile' ] );
        add_filter( SearchContext::SEARCH_TYPES_FILTER, [ self::class, 'search_post_types' ] );
        add_action( 'init', [ self::class, 'register_shortcodes' ] );
        add_filter( 'elementor/frontend/the_content', [ self::class, 'detect_auto_load_more' ] );
        add_action( 'wp_footer', [ self::class, 'print_auto_load_more_script' ], 34 );
    }

    private static bool $auto_load_more_used = false;

    /** Notes when Elementor output on this page carries an auto-load class (cached elements included). */
    public static function detect_auto_load_more( $content ) {
        if ( ! self::$auto_load_more_used && is_string( $content ) ) {
            foreach ( self::AUTO_LOAD_CLASSES as $class ) {
                if ( str_contains( $content, $class ) ) {
                    self::$auto_load_more_used = true;
                    break;
                }
            }
        }
        return $content;
    }

    public static function register_profile(): void {
        SearchRegistry::register( self::PROFILE, [
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

    public static function register_shortcodes(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
        add_shortcode( self::HEADER_SHORTCODE, [ self::class, 'render_header' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_shortcode( $attributes = [] ): string {
        if ( ! class_exists( SearchBox::class ) ) {
            return '';
        }

        if ( is_array( $attributes ) && 'people' === ( $attributes['profile'] ?? '' ) ) {
            if ( 'team' === ( $attributes['directory'] ?? '' ) ) {
                return ( new \HWS\BaseTools\TeamMembers\TeamMemberDirectory() )->render_shortcode( array_merge( [ 'per_page' => 10, 'search' => '1' ], $attributes ) );
            }
            $directory = (array) apply_filters( 'hws_base_tools_people_directory', [], sanitize_key( (string) ( $attributes['directory'] ?? 'people' ) ) );
            $directory['filter_layout'] = $attributes['filter_layout'] ?? 'dropdown';
            $directory['search_style'] = $attributes['style'] ?? self::style();
            return PeopleSearchProfile::render( $directory );
        }

        $attributes = shortcode_atts(
            [ 'profile' => self::PROFILE ] + array_fill_keys( array_keys( array_merge( ...array_values( self::ATTRIBUTES ) ) ), '' ),
            is_array( $attributes ) ? $attributes : [],
            self::SHORTCODE
        );
        $options = array_filter( array_map( 'strval', $attributes ), static fn( string $value ): bool => '' !== trim( $value ) );
        unset( $options['profile'] );
        $options['style'] = isset( self::STYLES[ (string) $attributes['style'] ] ) ? (string) $attributes['style'] : self::style();
        if ( is_search() ) {
            $options['hidden'] = SearchQueryFeature::marker_fields();
        }

        return SearchBox::render( sanitize_key( (string) $attributes['profile'] ), $options );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_header( $attributes = [] ): string {
        if ( ! class_exists( SearchBox::class ) ) {
            return '';
        }

        $settings   = self::header_settings();
        $attributes = shortcode_atts(
            [ 'style' => $settings['style'], 'accent' => $settings['accent'], 'placeholder' => $settings['placeholder'], 'label' => 'Search' ],
            is_array( $attributes ) ? $attributes : [],
            self::HEADER_SHORTCODE
        );

        $attributes          = array_map( 'strval', $attributes );
        $attributes['style'] = self::sanitize_header( [ 'style' => $attributes['style'] ] )['style'];

        return SearchBox::header( $attributes + [ 'hidden' => SearchQueryFeature::marker_fields() ] );
    }

    /**
     * Infinite scroll with a visible Load more button. Page captures that
     * compare first-load states set window.hexaFreezeAutoLoad, which keeps the
     * first batch.
     */
    public static function print_auto_load_more_script(): void {
        if ( is_admin() || wp_doing_ajax() || is_feed() || ! self::$auto_load_more_used ) {
            return;
        }

        $selectors = [];
        foreach ( self::AUTO_LOAD_CLASSES as $class ) {
            $selectors[] = '.' . $class . ' .e-loop__load-more .elementor-button';
            $selectors[] = '.' . $class . ' .elementor-button-wrapper .elementor-button';
        }
        $wrappers = '.' . implode( ',.', self::AUTO_LOAD_CLASSES );
        ?>
<script id="hws-auto-load-more">(function(){var sel=<?php echo wp_json_encode( implode( ',', $selectors ) ); ?>,wrap=<?php echo wp_json_encode( $wrappers ); ?>,queued=false;function check(){queued=false;if(window.hexaFreezeAutoLoad)return;document.querySelectorAll(sel).forEach(function(btn){var w=btn.closest(wrap);if(!w||w.classList.contains("e-load-more-pagination-loading")||w.classList.contains("e-load-more-pagination-end")||!btn.offsetParent)return;var r=btn.getBoundingClientRect();if(r.top<window.innerHeight+300&&r.bottom>0)btn.click();});}function queue(){if(!queued){queued=true;window.requestAnimationFrame(check);}}window.addEventListener("scroll",queue,{passive:true});window.addEventListener("resize",queue);document.addEventListener("DOMContentLoaded",queue);window.addEventListener("load",queue);document.addEventListener("hexa-search:updated",queue);})();</script>
        <?php
    }
}
