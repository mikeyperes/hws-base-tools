<?php

namespace HWS\BaseTools\LiteSpeed;

use Hexa\PluginCore\LiteSpeedCache\Profile;

/** HWS-owned LiteSpeed profiles; Core owns the generic option engine. */
final class LiteSpeedProfileRegistry {
    public const DEFAULT_PROFILE = 'safe_baseline';

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array {
        $definitions = [
            'compatibility' => [
                'id'          => 'compatibility',
                'label'       => 'Compatibility',
                'description' => 'Stable page and browser caching with CSS, JavaScript, guest optimization, media lazy loading, and crawler features disabled.',
                'settings'    => self::common_settings( false, false, false ),
            ],
            'safe_baseline' => [
                'id'          => 'safe_baseline',
                'label'       => 'Safe Baseline (Recommended)',
                'description' => 'Production-safe caching, browser cache, query-string cleanup, conservative minification, media lazy loading, and no file combining.',
                'settings'    => self::common_settings( true, true, false ),
            ],
            'editorial' => [
                'id'          => 'editorial',
                'label'       => 'Editorial / News',
                'description' => 'Safe baseline tuned for frequently updated publication homepages, feeds, REST responses, and archives.',
                'settings'    => self::common_settings( true, true, false, true ),
            ],
            'aggressive_test_first' => [
                'id'          => 'aggressive_test_first',
                'label'       => 'Aggressive — Test First',
                'description' => 'Adds deferred JavaScript, asynchronous CSS, guest optimization, and unused-CSS generation. Use only with visual regression testing.',
                'settings'    => self::common_settings( true, true, true ),
            ],
        ];

        return function_exists( 'apply_filters' )
            ? (array) apply_filters( 'hws_base_tools_litespeed_profiles', $definitions )
            : $definitions;
    }

    public static function profile( string $id, string $group = '' ): Profile {
        $definitions = self::definitions();
        $definition  = $definitions[ self::clean_key( $id ) ] ?? $definitions[ self::DEFAULT_PROFILE ];

        if ( '' !== $group ) {
            $definition['settings'] = array_values(
                array_filter(
                    (array) $definition['settings'],
                    static fn( array $setting ): bool => $group === (string) ( $setting['group'] ?? '' )
                )
            );
            $definition['id'] .= '-' . self::clean_key( $group );
        }

        return new Profile( $definition );
    }

    /** @return array<string,array<string,mixed>> */
    public static function checklist_templates(): array {
        $templates = [];
        foreach ( self::definitions() as $id => $definition ) {
            $templates[ $id ] = [
                'label'       => (string) $definition['label'],
                'description' => (string) $definition['description'],
                'steps'       => self::checklist_steps( (string) $id ),
            ];
        }
        return $templates;
    }

    /** @return array<int,array<string,mixed>> */
    private static function checklist_steps( string $profile_id ): array {
        return [
            self::group( 'readiness', '1. Plugin & Server', 'Confirm that LiteSpeed can operate on this WordPress site.', [
                self::task( 'provision', 'Install and activate LiteSpeed Cache', 'setup_action', 'Installs the official WordPress.org package when absent and activates it.', 'Install', $profile_id ),
                self::task( 'environment', 'Check LiteSpeed environment', 'status_check', 'Checks plugin state, web server, PHP handler, cache constants, and Redis availability.', 'Check', $profile_id ),
            ] ),
            self::group( 'cache', '2. Page Cache', 'Apply public, private, commenter, REST, login, mobile, and TTL policy.', [
                self::task( 'apply_cache', 'Apply page-cache settings', 'config_mutation', 'Applies only the selected profile cache group.', 'Apply', $profile_id, [ 'settings_group' => 'cache' ] ),
                self::task( 'apply_purge', 'Apply purge settings', 'config_mutation', 'Applies purge-on-upgrade and content-change rules.', 'Apply', $profile_id, [ 'settings_group' => 'purge' ] ),
            ] ),
            self::group( 'browser', '3. Browser & Query Strings', 'Apply browser-cache TTL and tracking-query normalization.', [
                self::task( 'apply_browser', 'Apply browser-cache settings', 'config_mutation', 'Enables long-lived browser caching and drops common tracking query strings from cache variations.', 'Apply', $profile_id, [ 'settings_group' => 'browser' ] ),
            ] ),
            self::group( 'optimization', '4. Page Optimization', 'Apply profile-specific CSS, JavaScript, HTML, guest, and emoji behavior.', [
                self::task( 'apply_optimization', 'Apply page-optimization settings', 'config_mutation', 'Keeps file combining off in normal profiles. The aggressive profile enables only explicitly declared test-first features.', 'Apply', $profile_id, [ 'settings_group' => 'optimization' ] ),
            ] ),
            self::group( 'media', '5. Media', 'Apply profile-specific image and iframe lazy-loading policy.', [
                self::task( 'apply_media', 'Apply media settings', 'config_mutation', 'Applies lazy loading without deleting original images or enabling cloud image processing.', 'Apply', $profile_id, [ 'settings_group' => 'media' ] ),
            ] ),
            self::group( 'object_cache', '6. Object Cache', 'Use Redis only when the server and WordPress object-cache path verify it is available.', [
                self::task( 'configure_redis', 'Configure Redis when available', 'setup_action', 'Detects Redis before changing LiteSpeed object-cache settings, then verifies a WordPress cache write/read/delete cycle.', 'Configure', $profile_id ),
            ] ),
            self::group( 'crawler', '7. Crawler & CDN', 'Keep high-risk server-load and external-service features explicit.', [
                self::task( 'apply_crawler', 'Apply crawler policy', 'config_mutation', 'Disables the crawler on shared hosting profiles. CDN and Cloudflare credentials remain untouched.', 'Apply', $profile_id, [ 'settings_group' => 'crawler' ] ),
            ] ),
            self::group( 'verification', '8. Verify & Purge', 'Compare every declared setting and inspect public cache integration.', [
                self::task( 'audit', 'Audit selected profile', 'status_check', 'Shows every matching and mismatched declared option.', 'Audit', $profile_id ),
                self::task( 'purge', 'Purge LiteSpeed cache', 'setup_action', 'Purges cached pages after profile changes.', 'Purge', $profile_id ),
                self::task( 'verify', 'Verify settings and public cache', 'status_check', 'Re-reads every setting and checks public response headers.', 'Verify', $profile_id ),
            ] ),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function common_settings( bool $minify, bool $lazy_media, bool $aggressive, bool $editorial = false ): array {
        $public_ttl = $editorial ? 86400 : 604800;
        $front_ttl  = $editorial ? 3600 : 604800;
        $feed_ttl   = $editorial ? 3600 : 604800;

        return [
            self::setting( 'public_cache', 'cache', 1, 'cache', 'Enable public page cache', 'bool' ),
            self::setting( 'private_cache', 'cache-priv', 0, 'cache', 'Do not separately cache logged-in users by default.', 'bool' ),
            self::setting( 'commenter_cache', 'cache-commenter', 0, 'cache', 'Comments are disabled by HWS, so commenter cache is unnecessary.', 'bool' ),
            self::setting( 'rest_cache', 'cache-rest', 1, 'cache', 'Cache public REST requests.', 'bool' ),
            self::setting( 'login_cache', 'cache-page_login', 0, 'cache', 'Do not cache the login page.', 'bool' ),
            self::setting( 'mobile_cache', 'cache-mobile', 0, 'cache', 'Use responsive output instead of a separate mobile cache.', 'bool' ),
            self::setting( 'public_ttl', 'cache-ttl_pub', $public_ttl, 'cache', 'Default public cache lifetime.', 'int' ),
            self::setting( 'private_ttl', 'cache-ttl_priv', 1800, 'cache', 'Default private cache lifetime.', 'int' ),
            self::setting( 'front_page_ttl', 'cache-ttl_frontpage', $front_ttl, 'cache', 'Front-page cache lifetime.', 'int' ),
            self::setting( 'feed_ttl', 'cache-ttl_feed', $feed_ttl, 'cache', 'Feed cache lifetime.', 'int' ),
            self::setting( 'rest_ttl', 'cache-ttl_rest', $public_ttl, 'cache', 'Public REST cache lifetime.', 'int' ),
            self::setting( 'status_ttl', 'cache-ttl_status', [ '404 3600', '500 0' ], 'cache', 'Cache 404 responses briefly and never cache server errors.', 'array' ),

            self::setting( 'purge_on_upgrade', 'purge-upgrade', 1, 'purge', 'Purge after WordPress, plugin, or theme upgrades.', 'bool' ),
            self::setting( 'serve_stale', 'purge-stale', 1, 'purge', 'Serve a stale cached copy while regeneration completes.', 'bool' ),
            self::setting( 'purge_front', 'purge-post_f', 1, 'purge', 'Purge the front page after content changes.', 'bool' ),
            self::setting( 'purge_home', 'purge-post_h', 1, 'purge', 'Purge the posts page after content changes.', 'bool' ),
            self::setting( 'purge_post', 'purge-post_p', 1, 'purge', 'Purge changed posts.', 'bool' ),
            self::setting( 'purge_author', 'purge-post_a', 1, 'purge', 'Purge author archives after content changes.', 'bool' ),
            self::setting( 'purge_month', 'purge-post_m', 1, 'purge', 'Purge month archives after content changes.', 'bool' ),
            self::setting( 'purge_terms', 'purge-post_t', 1, 'purge', 'Purge term archives after content changes.', 'bool' ),
            self::setting( 'purge_post_type', 'purge-post_pt', 1, 'purge', 'Purge post-type archives after content changes.', 'bool' ),

            self::setting( 'browser_cache', 'cache-browser', 1, 'browser', 'Enable browser cache.', 'bool' ),
            self::setting( 'browser_ttl', 'cache-ttl_browser', 31557600, 'browser', 'Cache static files in browsers for one year.', 'int' ),
            self::setting( 'drop_tracking_queries', 'cache-drop_qs', [ 'fbclid', 'gclid', 'utm*', '_ga' ], 'browser', 'Ignore common marketing query strings when varying cache.', 'array' ),

            self::setting( 'css_minify', 'optm-css_min', $minify ? 1 : 0, 'optimization', 'Minify CSS.', 'bool' ),
            self::setting( 'css_combine', 'optm-css_comb', 0, 'optimization', 'Keep CSS combining disabled for HTTP/2 and compatibility.', 'bool' ),
            self::setting( 'js_minify', 'optm-js_min', $minify ? 1 : 0, 'optimization', 'Minify JavaScript.', 'bool' ),
            self::setting( 'js_combine', 'optm-js_comb', 0, 'optimization', 'Keep JavaScript combining disabled.', 'bool' ),
            self::setting( 'html_minify', 'optm-html_min', $minify ? 1 : 0, 'optimization', 'Minify HTML.', 'bool' ),
            self::setting( 'emoji_remove', 'optm-emoji_rm', $minify ? 1 : 0, 'optimization', 'Remove the WordPress emoji compatibility script.', 'bool' ),
            self::setting( 'js_defer', 'optm-js_defer', $aggressive ? 1 : 0, 'optimization', 'Defer JavaScript only in the aggressive profile.', 'int' ),
            self::setting( 'async_css', 'optm-css_async', $aggressive ? 1 : 0, 'optimization', 'Generate asynchronous CSS only in the aggressive profile.', 'bool' ),
            self::setting( 'unused_css', 'optm-ucss', $aggressive ? 1 : 0, 'optimization', 'Generate unused CSS only in the aggressive profile.', 'bool' ),
            self::setting( 'guest_mode', 'guest', $aggressive ? 1 : 0, 'optimization', 'Enable Guest Mode only in the aggressive profile.', 'bool' ),
            self::setting( 'guest_optimization', 'guest_optm', $aggressive ? 1 : 0, 'optimization', 'Enable Guest Optimization only in the aggressive profile.', 'bool' ),
            self::setting( 'optimize_guests_only', 'optm-guest_only', 1, 'optimization', 'Limit page optimization to guest traffic.', 'bool' ),

            self::setting( 'image_lazy_load', 'media-lazy', $lazy_media ? 1 : 0, 'media', 'Lazy-load content images.', 'bool' ),
            self::setting( 'iframe_lazy_load', 'media-iframe_lazy', $lazy_media ? 1 : 0, 'media', 'Lazy-load embeds and iframes.', 'bool' ),
            self::setting( 'responsive_placeholders', 'media-placeholder_resp', $lazy_media ? 1 : 0, 'media', 'Use responsive placeholders to reduce layout shift.', 'bool' ),
            self::setting( 'add_missing_sizes', 'media-add_missing_sizes', $lazy_media ? 1 : 0, 'media', 'Add missing image dimensions when possible.', 'bool' ),

            self::setting( 'crawler', 'crawler', 0, 'crawler', 'Keep the crawler disabled unless hosting capacity is separately approved.', 'bool' ),
        ];
    }

    /** @return array<string,mixed> */
    private static function setting( string $id, string $option_id, mixed $expected, string $group, string $description, string $cast ): array {
        return [
            'id'          => $id,
            'label'       => ucwords( str_replace( '_', ' ', $id ) ),
            'description' => $description,
            'option_name' => $option_id,
            'expected'    => $expected,
            'cast'        => $cast,
            'group'       => $group,
        ];
    }

    /** @param array<int,array<string,mixed>> $subtasks */
    private static function group( string $id, string $label, string $description, array $subtasks ): array {
        return [ 'id' => $id, 'label' => $label, 'type' => 'setup_action', 'description' => $description, 'subtasks' => $subtasks ];
    }

    /** @param array<string,mixed> $context */
    private static function task( string $id, string $label, string $type, string $description, string $action_label, string $profile_id, array $context = [] ): array {
        return [
            'id'           => $id,
            'label'        => $label,
            'type'         => $type,
            'description'  => $description,
            'action_label' => $action_label,
            'callback'     => [ LiteSpeedTaskRunner::class, 'run' ],
            'context'      => array_merge( [ 'litespeed_task' => $id, 'litespeed_profile' => $profile_id ], $context ),
        ];
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }
}
