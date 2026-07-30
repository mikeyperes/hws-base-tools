<?php

declare( strict_types=1 );

namespace HWS\BaseTools\QueryCompatibility;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\QuerySafety\QueryEligibility;

defined( 'ABSPATH' ) || exit;

/**
 * Narrows known third-party query callbacks without editing vendor plugins.
 */
final class QueryHookCompatibility implements ModuleInterface {
    public const ELEMENTOR_SEARCH_MAX_FILTER = 'hws_query_hook_compatibility_elementor_search_max_results';
    public const ELEMENTOR_SEARCH_DEFAULT_MAX = 100;
    public const ELEMENTOR_SEARCH_CAP_PRIORITY = PHP_INT_MAX - 100;

    private const PTT_VERSION = '1.6';
    private const PTT_CLASS = 'PTT_Post_Visibility';
    private const PTT_SOURCE_SUFFIX = '/post-type-transfer/admin/class-ptt-post-visibility.php';
    private const PTT_RULE_CACHE_OPTION = 'hws_query_compatibility_ptt_rule_presence';
    private const ECHO_VERSION = '5.5.1.2';
    private const ECHO_SOURCE_SUFFIX = '/rss-feed-post-generator-echo/rss-feed-post-generator-echo.php';

    private static bool $reconcile_scheduled = false;
    private static bool $reconciled = false;
    private static bool $ptt_cache_hooks_registered = false;
    private static ?object $ptt_visibility = null;

    /** @var array<int,bool> */
    private static array $ptt_rule_presence = [];

    /** @var array<string,array{state:string,detail:string}> */
    private static array $status = [
        'post_type_transfer' => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
        'echo_rss'           => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
        'elementor_search'   => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
    ];

    public function register(): void {
        if ( self::$reconciled || self::$reconcile_scheduled || ! function_exists( 'add_action' ) ) {
            return;
        }

        self::$reconcile_scheduled = true;
        if ( function_exists( 'did_action' ) && did_action( 'wp_loaded' ) ) {
            self::reconcile();
            return;
        }

        add_action( 'wp_loaded', [ self::class, 'reconcile' ], PHP_INT_MAX );
    }

    public static function reconcile(): void {
        if ( self::$reconciled ) {
            return;
        }

        self::$reconciled = true;
        self::reconcile_post_type_transfer();
        self::reconcile_echo_rss();
        self::register_elementor_search_cap();
    }

    public static function guarded_ptt_fix_queried_object( mixed $query ): void {
        if ( ! self::$ptt_visibility || ! self::eligible_main_query( $query ) || ! self::ptt_visibility_rules_exist() ) {
            return;
        }

        self::$ptt_visibility->fix_queried_object( $query );
    }

    public static function guarded_ptt_filter_queries( mixed $query ): void {
        if ( ! self::$ptt_visibility || ! self::eligible_main_query( $query ) || ! self::ptt_visibility_rules_exist() ) {
            return;
        }

        self::$ptt_visibility->filter_queries( $query );
    }

    public static function guard_echo_source_taxonomy( mixed $query ): void {
        if ( ! self::eligible_main_query( $query ) || ! $query->is_tax( 'coderevolution_post_source' ) ) {
            return;
        }

        $query->set_404();
    }

    public static function cap_elementor_search_results( mixed $query ): void {
        if ( ! self::eligible_main_query( $query ) || ! self::is_valid_elementor_search_request() ) {
            return;
        }

        $current = $query->get( 'posts_per_page' );
        if ( ! is_int( $current ) && ! ( is_string( $current ) && preg_match( '/^-?[0-9]+$/D', $current ) ) ) {
            return;
        }

        $maximum = self::ELEMENTOR_SEARCH_DEFAULT_MAX;
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( self::ELEMENTOR_SEARCH_MAX_FILTER, $maximum, $query );
            if ( is_int( $filtered ) || ( is_string( $filtered ) && preg_match( '/^[0-9]+$/D', $filtered ) ) ) {
                $maximum = (int) $filtered;
            }
        }
        if ( $maximum < 1 ) {
            $maximum = self::ELEMENTOR_SEARCH_DEFAULT_MAX;
        }

        $current = (int) $current;
        if ( $current <= 0 || $current > $maximum ) {
            $query->set( 'posts_per_page', $maximum );
        }
    }

    /** @return array<string,array{state:string,detail:string}> */
    public static function status(): array {
        return self::$status;
    }

    /** @return array<string,mixed> */
    public static function audit(): array {
        return [
            'status'    => self::status(),
            'callbacks' => [
                'ptt_parse_vendor'      => self::count_object_callback( 'parse_query', self::PTT_CLASS, 'fix_queried_object', 10 ),
                'ptt_parse_vendor_all'  => self::count_object_callback( 'parse_query', self::PTT_CLASS, 'fix_queried_object', null ),
                'ptt_parse_guard'       => self::count_callback( 'parse_query', [ self::class, 'guarded_ptt_fix_queried_object' ], 10 ),
                'ptt_query_vendor'      => self::count_object_callback( 'pre_get_posts', self::PTT_CLASS, 'filter_queries', 99 ),
                'ptt_query_vendor_all'  => self::count_object_callback( 'pre_get_posts', self::PTT_CLASS, 'filter_queries', null ),
                'ptt_query_guard'       => self::count_callback( 'pre_get_posts', [ self::class, 'guarded_ptt_filter_queries' ], 99 ),
                'ptt_widget_filter'     => self::count_object_callback( 'widget_posts_args', self::PTT_CLASS, 'filter_recent_posts_widget', 10 ),
                'ptt_query_loop_filter' => self::count_object_callback( 'query_loop_block_query_vars', self::PTT_CLASS, 'filter_query_loop_block', 10 ),
                'echo_vendor_closure'   => count( self::echo_source_callbacks() ),
                'echo_taxonomy_guard'   => self::count_callback( 'pre_get_posts', [ self::class, 'guard_echo_source_taxonomy' ], 10 ),
                'elementor_search_cap'  => self::count_callback( 'pre_get_posts', [ self::class, 'cap_elementor_search_results' ], self::ELEMENTOR_SEARCH_CAP_PRIORITY ),
            ],
        ];
    }

    private static function reconcile_post_type_transfer(): void {
        if ( ! class_exists( self::PTT_CLASS, false ) ) {
            self::set_status( 'post_type_transfer', 'inactive', 'Post Type Transfer is not active.' );
            return;
        }
        self::register_ptt_rule_cache_hooks();
        if ( ! defined( 'POST_TYPE_TRANSFER_VERSION' ) || self::PTT_VERSION !== (string) constant( 'POST_TYPE_TRANSFER_VERSION' ) ) {
            self::quarantine_ptt_callbacks( 'Expected Post Type Transfer 1.6.' );
            return;
        }

        $parse_callbacks = self::find_object_callbacks( 'parse_query', self::PTT_CLASS, 'fix_queried_object', 10 );
        $query_callbacks = self::find_object_callbacks( 'pre_get_posts', self::PTT_CLASS, 'filter_queries', 99 );
        $widget_callbacks = self::find_object_callbacks( 'widget_posts_args', self::PTT_CLASS, 'filter_recent_posts_widget', 10 );
        $loop_callbacks = self::find_object_callbacks( 'query_loop_block_query_vars', self::PTT_CLASS, 'filter_query_loop_block', 10 );

        if ( 1 !== count( $parse_callbacks ) || 1 !== count( $query_callbacks )
            || 1 !== count( $widget_callbacks ) || 1 !== count( $loop_callbacks )
        ) {
            self::quarantine_ptt_callbacks( 'Expected one visibility callback on each documented Post Type Transfer hook.' );
            return;
        }

        $visibility = $parse_callbacks[0];
        if ( $visibility !== $query_callbacks[0] || $visibility !== $widget_callbacks[0] || $visibility !== $loop_callbacks[0]
            || ! self::valid_ptt_method( 'fix_queried_object' )
            || ! self::valid_ptt_method( 'filter_queries' )
            || ! self::valid_ptt_method( 'filter_recent_posts_widget' )
            || ! self::valid_ptt_method( 'filter_query_loop_block' )
        ) {
            self::quarantine_ptt_callbacks( 'The Post Type Transfer visibility object or method signature changed.' );
            return;
        }

        $parse_callback = [ $visibility, 'fix_queried_object' ];
        $query_callback = [ $visibility, 'filter_queries' ];
        $removed_parse = remove_action( 'parse_query', $parse_callback, 10 );
        $removed_query = remove_action( 'pre_get_posts', $query_callback, 99 );
        if ( ! $removed_parse || ! $removed_query ) {
            self::quarantine_ptt_callbacks( 'The validated vendor callbacks could not be replaced atomically.' );
            return;
        }

        self::$ptt_visibility = $visibility;
        add_action( 'parse_query', [ self::class, 'guarded_ptt_fix_queried_object' ], 10 );
        add_action( 'pre_get_posts', [ self::class, 'guarded_ptt_filter_queries' ], 99 );

        $audit = self::audit()['callbacks'];
        $valid = 0 === $audit['ptt_parse_vendor'] && 1 === $audit['ptt_parse_guard']
            && 0 === $audit['ptt_query_vendor'] && 1 === $audit['ptt_query_guard']
            && 1 === $audit['ptt_widget_filter'] && 1 === $audit['ptt_query_loop_filter'];
        if ( ! $valid ) {
            remove_action( 'parse_query', [ self::class, 'guarded_ptt_fix_queried_object' ], 10 );
            remove_action( 'pre_get_posts', [ self::class, 'guarded_ptt_filter_queries' ], 99 );
            self::$ptt_visibility = null;
            self::quarantine_ptt_callbacks( 'Guarded Post Type Transfer callback counts were not exact.' );
            return;
        }

        self::set_status( 'post_type_transfer', 'guarded', 'Post Type Transfer 1.6 main-query callbacks are guarded; dedicated secondary filters remain active.' );
    }

    private static function register_ptt_rule_cache_hooks(): void {
        if ( self::$ptt_cache_hooks_registered ) {
            return;
        }

        add_action( 'added_post_meta', [ self::class, 'invalidate_ptt_rule_cache' ], 10, 4 );
        add_action( 'updated_post_meta', [ self::class, 'invalidate_ptt_rule_cache' ], 10, 4 );
        add_action( 'deleted_post_meta', [ self::class, 'invalidate_ptt_rule_cache' ], 10, 4 );
        self::$ptt_cache_hooks_registered = true;
    }

    public static function invalidate_ptt_rule_cache( mixed $meta_id, mixed $post_id, mixed $meta_key, mixed $meta_value = null ): void {
        if ( ! is_string( $meta_key ) || ! str_starts_with( $meta_key, '_ptt_hide_' ) ) {
            return;
        }

        unset( self::$ptt_rule_presence[ self::site_id() ] );
        if ( function_exists( 'delete_option' ) ) {
            delete_option( self::PTT_RULE_CACHE_OPTION );
        }
    }

    public static function ptt_visibility_rules_exist(): bool {
        $site_id = self::site_id();
        if ( array_key_exists( $site_id, self::$ptt_rule_presence ) ) {
            return self::$ptt_rule_presence[ $site_id ];
        }

        if ( function_exists( 'get_option' ) ) {
            $cached = get_option( self::PTT_RULE_CACHE_OPTION, null );
            if ( is_array( $cached ) && 1 === ( $cached['version'] ?? null ) && is_bool( $cached['present'] ?? null ) ) {
                self::$ptt_rule_presence[ $site_id ] = $cached['present'];

                return $cached['present'];
            }
        }

        global $wpdb;
        if ( ! is_object( $wpdb ) || empty( $wpdb->postmeta )
            || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' )
        ) {
            return true;
        }

        $prefix = '_ptt_hide_';
        $like = method_exists( $wpdb, 'esc_like' )
            ? $wpdb->esc_like( $prefix ) . '%'
            : addcslashes( $prefix, '_%\\' ) . '%';
        $sql = $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key LIKE %s LIMIT 1", $like );
        $result = is_string( $sql ) ? $wpdb->get_var( $sql ) : null;
        if ( ! empty( $wpdb->last_error ) ) {
            return true;
        }

        $present = null !== $result;
        self::$ptt_rule_presence[ $site_id ] = $present;
        if ( function_exists( 'update_option' ) ) {
            update_option(
                self::PTT_RULE_CACHE_OPTION,
                [ 'version' => 1, 'present' => $present ],
                false
            );
        }

        return $present;
    }

    private static function quarantine_ptt_callbacks( string $detail ): void {
        remove_action( 'parse_query', [ self::class, 'guarded_ptt_fix_queried_object' ], 10 );
        remove_action( 'pre_get_posts', [ self::class, 'guarded_ptt_filter_queries' ], 99 );
        self::$ptt_visibility = null;

        foreach ( [
            [ 'parse_query', 'fix_queried_object' ],
            [ 'pre_get_posts', 'filter_queries' ],
        ] as [ $hook, $method ] ) {
            foreach ( self::hook_entries( $hook ) as $entry ) {
                $callback = $entry['callback'];
                if ( ! is_array( $callback ) || 2 !== count( $callback ) || ! is_object( $callback[0] )
                    || self::PTT_CLASS !== get_class( $callback[0] ) || $method !== $callback[1]
                ) {
                    continue;
                }
                remove_action( $hook, $callback, $entry['priority'] );
            }
        }

        $remaining = self::count_object_callback( 'parse_query', self::PTT_CLASS, 'fix_queried_object', null )
            + self::count_object_callback( 'pre_get_posts', self::PTT_CLASS, 'filter_queries', null );
        $state = 0 === $remaining ? 'quarantined' : 'critical';
        $message = $detail . ( 0 === $remaining
            ? ' Broad PTT query callbacks were quarantined; dedicated secondary filters were left intact.'
            : ' One or more broad PTT query callbacks remain active.' );
        self::set_status( 'post_type_transfer', $state, $message );

        if ( function_exists( 'do_action' ) ) {
            do_action( 'hws_query_hook_compatibility_drift', 'post_type_transfer', $message );
            do_action( 'hws_query_hook_compatibility_critical', 'post_type_transfer', $message, $remaining );
        }
    }

    private static function site_id(): int {
        return function_exists( 'get_current_blog_id' ) ? max( 1, (int) get_current_blog_id() ) : 1;
    }

    private static function reconcile_echo_rss(): void {
        if ( ! function_exists( 'echo_get_version' ) && ! function_exists( 'echo_create_taxonomy' ) ) {
            self::set_status( 'echo_rss', 'inactive', 'Echo RSS Feed Post Generator is not active.' );
            return;
        }

        try {
            $version = function_exists( 'echo_get_version' ) ? (string) \echo_get_version() : '';
            $version_reflection = function_exists( 'echo_get_version' ) ? new \ReflectionFunction( 'echo_get_version' ) : null;
        } catch ( \Throwable ) {
            $version = '';
            $version_reflection = null;
        }
        if ( self::ECHO_VERSION !== $version || ! $version_reflection
            || ! self::path_ends_with( (string) $version_reflection->getFileName(), self::ECHO_SOURCE_SUFFIX )
            || 0 !== $version_reflection->getNumberOfParameters()
        ) {
            self::drift( 'echo_rss', 'Expected Echo RSS Feed Post Generator 5.5.1.2 source identity.' );
            return;
        }

        $callbacks = self::echo_source_callbacks();
        if ( 1 !== count( $callbacks ) ) {
            self::drift( 'echo_rss', 'Expected one anonymous Echo post-source taxonomy callback at pre_get_posts priority 10.' );
            return;
        }

        $callback = $callbacks[0];
        if ( ! remove_action( 'pre_get_posts', $callback, 10 ) ) {
            self::drift( 'echo_rss', 'The validated Echo taxonomy callback could not be removed.' );
            return;
        }

        add_action( 'pre_get_posts', [ self::class, 'guard_echo_source_taxonomy' ], 10 );
        $audit = self::audit()['callbacks'];
        if ( 0 !== $audit['echo_vendor_closure'] || 1 !== $audit['echo_taxonomy_guard'] ) {
            remove_action( 'pre_get_posts', [ self::class, 'guard_echo_source_taxonomy' ], 10 );
            add_action( 'pre_get_posts', $callback, 10 );
            self::drift( 'echo_rss', 'The named Echo guard callback count was not exact; the vendor callback was restored.' );
            return;
        }

        self::set_status( 'echo_rss', 'guarded', 'Echo 5.5.1.2 taxonomy 404 behavior is limited to its filtered frontend main query.' );
    }

    private static function register_elementor_search_cap(): void {
        $callback = [ self::class, 'cap_elementor_search_results' ];
        if ( 0 === self::count_callback( 'pre_get_posts', $callback, self::ELEMENTOR_SEARCH_CAP_PRIORITY ) ) {
            add_action( 'pre_get_posts', $callback, self::ELEMENTOR_SEARCH_CAP_PRIORITY );
        }

        if ( 1 !== self::count_callback( 'pre_get_posts', $callback, self::ELEMENTOR_SEARCH_CAP_PRIORITY ) ) {
            self::drift( 'elementor_search', 'The enhanced-search result cap callback count is not exactly one.' );
            return;
        }

        self::set_status( 'elementor_search', 'capped', 'Validated Elementor enhanced-search main queries are capped at a filterable default of 100 results.' );
    }

    private static function eligible_main_query( mixed $query ): bool {
        return $query instanceof \WP_Query
            && QueryEligibility::allows_main_filtered_frontend_query( $query );
    }

    private static function is_valid_elementor_search_request(): bool {
        if ( ! isset( $_GET['e_search_props'] ) || ! array_key_exists( 's', $_GET )
            || ! is_string( $_GET['e_search_props'] ) || ! is_scalar( $_GET['s'] )
        ) {
            return false;
        }

        $props = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['e_search_props'] ) : $_GET['e_search_props'];

        return 1 === preg_match( '/^[A-Za-z0-9_]{1,64}-[1-9][0-9]{0,19}$/D', $props );
    }

    private static function valid_ptt_method( string $method ): bool {
        try {
            $reflection = new \ReflectionMethod( self::PTT_CLASS, $method );
        } catch ( \ReflectionException ) {
            return false;
        }

        return self::PTT_CLASS === $reflection->getDeclaringClass()->getName()
            && $reflection->isPublic()
            && ! $reflection->isStatic()
            && 1 === $reflection->getNumberOfRequiredParameters()
            && 1 === $reflection->getNumberOfParameters()
            && self::path_ends_with( (string) $reflection->getFileName(), self::PTT_SOURCE_SUFFIX );
    }

    /** @return list<object> */
    private static function find_object_callbacks( string $hook, string $class, string $method, ?int $priority ): array {
        $objects = [];
        foreach ( self::hook_entries( $hook ) as $entry ) {
            $callback = $entry['callback'];
            if ( ( null !== $priority && $priority !== $entry['priority'] ) || ! is_array( $callback ) || 2 !== count( $callback )
                || ! is_object( $callback[0] ) || $method !== $callback[1] || $class !== get_class( $callback[0] )
            ) {
                continue;
            }
            $objects[] = $callback[0];
        }

        return $objects;
    }

    private static function count_object_callback( string $hook, string $class, string $method, ?int $priority ): int {
        return count( self::find_object_callbacks( $hook, $class, $method, $priority ) );
    }

    private static function count_callback( string $hook, callable $needle, int $priority ): int {
        $count = 0;
        foreach ( self::hook_entries( $hook ) as $entry ) {
            if ( $priority === $entry['priority'] && self::callbacks_equal( $entry['callback'], $needle ) ) {
                ++$count;
            }
        }

        return $count;
    }

    /** @return list<\Closure> */
    private static function echo_source_callbacks(): array {
        $callbacks = [];
        $expected_fingerprint = self::closure_fingerprint_from_source(
            "function(\$qry) {\n"
            . "if (is_admin()) return;\n"
            . "if (is_tax('coderevolution_post_source')) {\n"
            . "\$qry->set_404();\n"
            . "}\n"
            . "}"
        );

        foreach ( self::hook_entries( 'pre_get_posts' ) as $entry ) {
            if ( 10 !== $entry['priority'] || ! $entry['callback'] instanceof \Closure ) {
                continue;
            }
            try {
                $reflection = new \ReflectionFunction( $entry['callback'] );
            } catch ( \ReflectionException ) {
                continue;
            }
            if ( 1 !== $reflection->getNumberOfRequiredParameters()
                || 1 !== $reflection->getNumberOfParameters()
                || ! self::path_ends_with( (string) $reflection->getFileName(), self::ECHO_SOURCE_SUFFIX )
                || $expected_fingerprint !== self::closure_fingerprint( $reflection )
            ) {
                continue;
            }
            $callbacks[] = $entry['callback'];
        }

        return $callbacks;
    }

    private static function closure_fingerprint( \ReflectionFunction $reflection ): string {
        $file = $reflection->getFileName();
        if ( false === $file || ! is_readable( $file ) ) {
            return '';
        }

        $lines = file( $file );
        if ( false === $lines ) {
            return '';
        }

        $source = implode(
            '',
            array_slice( $lines, max( 0, $reflection->getStartLine() - 1 ), $reflection->getEndLine() - $reflection->getStartLine() + 1 )
        );

        return self::closure_fingerprint_from_source( $source );
    }

    private static function closure_fingerprint_from_source( string $source ): string {
        $tokens = token_get_all( "<?php\n" . $source );
        $signature = '';
        $started = false;
        $opened = false;
        $depth = 0;

        foreach ( $tokens as $token ) {
            if ( is_array( $token ) ) {
                if ( T_FUNCTION === $token[0] ) {
                    $started = true;
                }
                if ( ! $started || in_array( $token[0], [ T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
                    continue;
                }
                $signature .= $token[0] . ':' . $token[1] . ';';
                continue;
            }
            if ( ! $started ) {
                continue;
            }

            $signature .= 'c:' . $token . ';';
            if ( '{' === $token ) {
                $opened = true;
                ++$depth;
            } elseif ( '}' === $token && $opened ) {
                --$depth;
                if ( 0 === $depth ) {
                    break;
                }
            }
        }

        return $opened && 0 === $depth ? hash( 'sha256', $signature ) : '';
    }

    /** @return list<array{priority:int,callback:mixed}> */
    private static function hook_entries( string $hook ): array {
        $store = $GLOBALS['wp_filter'][ $hook ] ?? null;
        if ( is_object( $store ) && isset( $store->callbacks ) && is_array( $store->callbacks ) ) {
            $priorities = $store->callbacks;
        } elseif ( is_array( $store ) ) {
            $priorities = $store;
        } else {
            return [];
        }

        $entries = [];
        foreach ( $priorities as $priority => $callbacks ) {
            if ( ! is_array( $callbacks ) ) {
                continue;
            }
            foreach ( $callbacks as $entry ) {
                if ( is_array( $entry ) && array_key_exists( 'function', $entry ) ) {
                    $entries[] = [ 'priority' => (int) $priority, 'callback' => $entry['function'] ];
                }
            }
        }

        return $entries;
    }

    private static function callbacks_equal( mixed $left, mixed $right ): bool {
        if ( is_array( $left ) && is_array( $right ) && 2 === count( $left ) && 2 === count( $right ) ) {
            return $left[0] === $right[0] && $left[1] === $right[1];
        }

        return $left === $right;
    }

    private static function path_ends_with( string $path, string $suffix ): bool {
        $path = str_replace( '\\', '/', $path );

        return '' !== $path && str_ends_with( $path, $suffix );
    }

    private static function set_status( string $integration, string $state, string $detail ): void {
        self::$status[ $integration ] = [ 'state' => $state, 'detail' => $detail ];
    }

    private static function drift( string $integration, string $detail ): void {
        self::set_status( $integration, 'drift', $detail );
        if ( function_exists( 'do_action' ) ) {
            do_action( 'hws_query_hook_compatibility_drift', $integration, $detail );
        }
    }
}
