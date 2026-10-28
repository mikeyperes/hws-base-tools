<?php

namespace HWS\BaseTools\SitemapTools;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * [hws_rss_feeds] lists the site's active RSS feeds (those switched on in Settings, RSS Feeds),
 * for an RSS Feed page or a footer. Each feed is referred to by its ID, shown on that tab.
 *
 *     [hws_rss_feeds]                                  every active feed
 *     [hws_rss_feeds only="posts,press-release"]       just these, in this order
 *     [hws_rss_feeds exclude="comments,testimonial"]   all but these
 *     [hws_rss_feeds hide="url,count,icon"]            leave parts out of each row
 *     [hws_rss_feeds style="none"]                     no styling, for a theme or Elementor to style
 *
 * Feeds whose address is one example of many (each category, tag or author) are listed only when named in only="".
 */
final class FeedListShortcode implements ModuleInterface {
    public const SHORTCODE = 'hws_rss_feeds';
    public const PARTS     = [ 'icon', 'title', 'url', 'count', 'copy' ];

    public function register(): void {
        add_action( 'init', [ self::class, 'register_shortcode' ] );
    }

    public static function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** The active feeds the shortcode would list, keyed by registry key. */
    public static function feeds( array $only = [], array $exclude = [] ): array {
        $active = [];
        foreach ( SitemapFeedRegistry::feeds() as $key => $feed ) {
            if ( ! $feed['available'] || '' === $feed['url'] || SitemapFeedRegistry::is_disabled( $key ) ) {
                continue;
            }
            $active[ $feed['slug'] ] = $feed + [ 'key' => $key ];
        }

        if ( $only ) {
            $picked = [];
            foreach ( $only as $slug ) {
                if ( isset( $active[ $slug ] ) ) {
                    $picked[ $slug ] = $active[ $slug ];
                }
            }
            return $picked;
        }

        return array_filter(
            $active,
            static fn( array $feed, string $slug ): bool => empty( $feed['example'] ) && ! in_array( $slug, $exclude, true ),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function render( $atts = [] ): string {
        $atts = shortcode_atts( [ 'only' => '', 'exclude' => '', 'hide' => '', 'style' => 'default', 'icon' => 'outline' ], (array) $atts, self::SHORTCODE );
        $list = static fn( string $value ): array => array_values( array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $value ) ) ) ) );
        $hide = array_intersect( $list( (string) $atts['hide'] ), self::PARTS );
        $show = static fn( string $part ): bool => ! in_array( $part, $hide, true );

        $feeds = self::feeds( $list( (string) $atts['only'] ), $list( (string) $atts['exclude'] ) );
        if ( ! $feeds ) {
            return '';
        }

        $rows = '';
        foreach ( self::expand( $feeds ) as $item ) {
            $slug  = $item['slug'];
            $feed  = $item['feed'];
            $total = is_callable( $feed['total'] ) ? (int) call_user_func( $feed['total'] ) : null;
            $rows .= '<li class="hws-rss-feed hws-rss-feed--' . esc_attr( $slug ) . ( $item['depth'] ? ' is-child' : '' ) . '">';
            if ( $show( 'icon' ) ) {
                // icon="solid" draws the filled feed glyph; the default stays the outlined one.
                $glyph  = 'solid' === $atts['icon']
                    ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" focusable="false"><circle cx="6.18" cy="17.82" r="2.18"/><path d="M4 4.44v2.83c7.03 0 12.73 5.7 12.73 12.73h2.83c0-8.59-6.97-15.56-15.56-15.56zm0 5.66v2.83c3.9 0 7.07 3.17 7.07 7.07h2.83c0-5.47-4.43-9.9-9.9-9.9z"/></svg>'
                    : '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="5" cy="19" r="2.2" fill="currentColor"/><path d="M3 10.5a10.5 10.5 0 0 1 10.5 10.5M3 4a17 17 0 0 1 17 17" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';
                $rows .= '<span class="hws-rss-feed__icon" aria-hidden="true">' . $glyph . '</span>';
            }
            $rows .= '<span class="hws-rss-feed__body">';
            if ( $show( 'title' ) ) {
                $rows .= '<a class="hws-rss-feed__title" href="' . esc_url( $feed['url'] ) . '">' . esc_html( $feed['label'] ) . '</a>';
            }
            if ( $show( 'count' ) && null !== $total ) {
                $rows .= ' <span class="hws-rss-feed__count' . ( 1 === $total ? ' is-one' : '' ) . '">' . esc_html( number_format_i18n( $total ) ) . '</span>';
            }
            if ( $show( 'url' ) ) {
                // A break point after each slash lets a long address wrap at a slash on a narrow screen.
                $rows .= '<span class="hws-rss-feed__url">' . str_replace( '/', '/<wbr>', esc_html( $feed['url'] ) ) . '</span>';
            }
            $rows .= '</span>';
            if ( $show( 'copy' ) ) {
                $rows .= '<button type="button" class="hws-rss-feed__copy" data-hws-copy="' . esc_attr( $feed['url'] ) . '">Copy link</button>';
            }
            $rows .= '</li>';
        }

        $styled = 'none' !== $atts['style'];
        return ( $styled ? self::styles() : '' )
            . '<ul class="hws-rss-feeds' . ( $styled ? ' hws-rss-feeds--default' : '' ) . '">' . $rows . '</ul>'
            . ( $show( 'copy' ) ? self::copy_script() : '' );
    }

    /**
     * Turns the "each category" and "each tag" entries into one row per category or tag that has posts, a parent
     * followed by its own sub-categories (depth 1 and deeper rows get is-child). Every other feed stays one row.
     *
     * @param array<string,array<string,mixed>> $feeds
     * @return array<int,array{slug:string,feed:array<string,mixed>,depth:int}>
     */
    private static function expand( array $feeds ): array {
        $items = [];
        foreach ( $feeds as $slug => $feed ) {
            $taxonomy = 'categories' === $slug ? 'category' : ( 'tags' === $slug ? 'post_tag' : '' );
            $terms    = $taxonomy && ! empty( $feed['example'] ) ? get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'name' ] ) : [];
            if ( is_wp_error( $terms ) || ! $terms ) {
                $items[] = [ 'slug' => $slug, 'feed' => $feed, 'depth' => 0 ];
                continue;
            }
            $ids      = wp_list_pluck( $terms, 'term_id' );
            $children = [];
            foreach ( $terms as $term ) {
                $children[ in_array( (int) $term->parent, array_map( 'intval', $ids ), true ) ? (int) $term->parent : 0 ][] = $term;
            }
            $walk = static function ( int $parent, int $depth ) use ( &$walk, &$items, $children, $slug, $feed, $taxonomy ): void {
                foreach ( $children[ $parent ] ?? [] as $term ) {
                    $count   = (int) $term->count;
                    $items[] = [ 'slug' => $slug, 'depth' => $depth, 'feed' => [ 'url' => (string) get_term_feed_link( $term->term_id, $taxonomy ), 'label' => $term->name, 'total' => static fn() => $count ] + $feed ];
                    $walk( (int) $term->term_id, $depth + 1 );
                }
            };
            $walk( 0, 0 );
        }
        return $items;
    }

    private static function styles(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style id="hws-rss-feeds-css">'
            . '.hws-rss-feeds--default{list-style:none;margin:0;padding:0}'
            . '.hws-rss-feeds--default .hws-rss-feed{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(127,127,127,.2);margin:0}'
            . '.hws-rss-feeds--default .hws-rss-feed__icon{flex:0 0 auto;display:inline-flex;color:#f26522}'
            . '.hws-rss-feeds--default .hws-rss-feed__body{flex:1;min-width:0}'
            . '.hws-rss-feeds--default .hws-rss-feed__title{font-weight:600;text-decoration:none}'
            . '.hws-rss-feeds--default .hws-rss-feed__count{font-size:.85em;opacity:.65}'
            . '.hws-rss-feeds--default .hws-rss-feed__url{display:block;font-size:.85em;opacity:.7;overflow-wrap:anywhere}'
            . '.hws-rss-feeds--default .hws-rss-feed__copy{flex:0 0 auto;font:inherit;font-size:.85em;padding:4px 10px;border:1px solid currentColor;border-radius:4px;background:none;color:inherit;cursor:pointer;opacity:.8}'
            . '</style>';
    }

    private static function copy_script(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<script>document.addEventListener("click",function(e){var b=e.target.closest(".hws-rss-feed__copy");if(!b||!navigator.clipboard)return;navigator.clipboard.writeText(b.getAttribute("data-hws-copy")).then(function(){var t=b.textContent;b.textContent="Copied";setTimeout(function(){b.textContent=t;},1500);});});</script>';
    }
}
