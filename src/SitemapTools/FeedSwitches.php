<?php

namespace HWS\BaseTools\SitemapTools;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\FrontendContent\DiscussionPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the on/off switch of each feed on Settings, RSS Feeds: a feed
 * that is off answers "not found" and is no longer announced in the page head.
 * Every feed is on until it is turned off, so a new site keeps WordPress's feeds.
 * Comments feeds also follow the site's comments setting.
 */
final class FeedSwitches implements ModuleInterface {
    public function register(): void {
        add_action( 'template_redirect', [ self::class, 'block_disabled_feed' ], 0 );
        add_filter( 'feed_links_show_posts_feed', [ self::class, 'show_posts_feed_link' ] );
        add_filter( 'feed_links_show_comments_feed', [ self::class, 'show_comments_feed_link' ] );
        add_filter( 'feed_links_extra_show_post_comments_feed', [ self::class, 'show_post_comments_feed_link' ] );
        add_filter( 'feed_links_extra_show_post_type_archive_feed', [ self::class, 'show_post_type_feed_link' ] );
        add_filter( 'feed_links_extra_show_category_feed', [ self::class, 'show_category_feed_link' ] );
        add_filter( 'feed_links_extra_show_tag_feed', [ self::class, 'show_tag_feed_link' ] );
        add_filter( 'feed_links_extra_show_author_feed', [ self::class, 'show_author_feed_link' ] );
    }

    public static function block_disabled_feed(): void {
        if ( ! is_feed() ) {
            return;
        }
        // With comments off, every comments feed (site-wide and each post's) is off too.
        $comments_off = self::is_wordpress_comment_feed() && DiscussionPolicy::comments_disabled();
        if ( ! $comments_off ) {
            if ( ! SitemapFeedRegistry::disabled() ) {
                return;
            }
            $key = SitemapFeedRegistry::current_feed_key();
            if ( '' === $key || ! SitemapFeedRegistry::is_disabled( $key ) ) {
                return;
            }
        }

        do_action( 'litespeed_control_set_nocache', 'hws-feed-off' );
        status_header( 404 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=UTF-8' );
        echo 'This feed is turned off.';
        exit;
    }

    /**
     * WordPress also flags a plugin's own feed type (add_feed) as a comments
     * feed when it is requested on a single record, such as Hexa PR Wire's
     * ?feed=rss_publication&publication=<slug>. Only WordPress's own feed
     * types are real comments feeds.
     */
    private static function is_wordpress_comment_feed(): bool {
        if ( ! is_comment_feed() ) {
            return false;
        }
        $type = (string) get_query_var( 'feed' );
        return in_array( $type, [ '', 'feed', 'rss', 'rss2', 'rdf', 'atom' ], true )
            || $type === get_default_feed();
    }

    public static function show_posts_feed_link( $show ): bool {
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'main' );
    }

    public static function show_comments_feed_link( $show ): bool {
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'comments' );
    }

    public static function show_post_comments_feed_link( $show ): bool {
        return (bool) $show && ! DiscussionPolicy::comments_disabled();
    }

    public static function show_post_type_feed_link( $show ): bool {
        $post_type = (string) get_query_var( 'post_type' );
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'post_type_' . $post_type );
    }

    public static function show_category_feed_link( $show ): bool {
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'taxonomy_category' );
    }

    public static function show_tag_feed_link( $show ): bool {
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'taxonomy_post_tag' );
    }

    public static function show_author_feed_link( $show ): bool {
        return (bool) $show && ! SitemapFeedRegistry::is_disabled( 'authors' );
    }
}
