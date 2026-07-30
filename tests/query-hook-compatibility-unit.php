<?php

declare( strict_types=1 );

use HWS\BaseTools\QueryCompatibility\QueryHookCompatibility;

define( 'ABSPATH', __DIR__ . '/' );
define( 'POST_TYPE_TRANSFER_VERSION', '1.6' );

$failures = [];
$passes = 0;
$GLOBALS['hws_test_context'] = [ 'admin' => false, 'ajax' => false, 'cron' => false, 'rest' => false ];
$GLOBALS['hws_test_did_actions'] = [];
$GLOBALS['hws_test_global_is_tax_calls'] = 0;
$GLOBALS['hws_test_options'] = [];
$GLOBALS['hws_test_ptt_rule_exists'] = true;
$GLOBALS['wp_filter'] = [];

function hws_test_expect( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function hws_test_callback_id( mixed $callback ): string {
    if ( $callback instanceof Closure ) {
        return spl_object_hash( $callback );
    }
    if ( is_array( $callback ) && 2 === count( $callback ) ) {
        $owner = is_object( $callback[0] ) ? spl_object_hash( $callback[0] ) : (string) $callback[0];

        return $owner . '::' . (string) $callback[1];
    }

    return (string) $callback;
}

class WP_Hook {
    /** @var array<int,array<string,array{function:mixed,accepted_args:int}>> */
    public array $callbacks = [];
}

function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
        $GLOBALS['wp_filter'][ $hook ] = new WP_Hook();
    }
    $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ hws_test_callback_id( $callback ) ] = [
        'function'      => $callback,
        'accepted_args' => $accepted_args,
    ];

    return true;
}

function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return add_action( $hook, $callback, $priority, $accepted_args );
}

function remove_action( string $hook, mixed $callback, int $priority = 10 ): bool {
    $id = hws_test_callback_id( $callback );
    if ( ! isset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $id ] ) ) {
        return false;
    }
    unset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $id ] );

    return true;
}

function do_action( string $hook, mixed ...$args ): void {
    $GLOBALS['hws_test_did_actions'][ $hook ] = ( $GLOBALS['hws_test_did_actions'][ $hook ] ?? 0 ) + 1;
    $callbacks = $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [];
    ksort( $callbacks );
    foreach ( $callbacks as $entries ) {
        foreach ( $entries as $entry ) {
            call_user_func_array( $entry['function'], array_slice( $args, 0, $entry['accepted_args'] ) );
        }
    }
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    $callbacks = $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [];
    ksort( $callbacks );
    foreach ( $callbacks as $entries ) {
        foreach ( $entries as $entry ) {
            $call_args = array_slice( [ $value, ...$args ], 0, $entry['accepted_args'] );
            $value = call_user_func_array( $entry['function'], $call_args );
        }
    }

    return $value;
}

function did_action( string $hook ): int {
    return (int) ( $GLOBALS['hws_test_did_actions'][ $hook ] ?? 0 );
}

function is_admin(): bool {
    return $GLOBALS['hws_test_context']['admin'];
}

function wp_doing_ajax(): bool {
    return $GLOBALS['hws_test_context']['ajax'];
}

function wp_doing_cron(): bool {
    return $GLOBALS['hws_test_context']['cron'];
}

function wp_is_serving_rest_request(): bool {
    return $GLOBALS['hws_test_context']['rest'];
}

function wp_unslash( string $value ): string {
    return stripslashes( $value );
}

function get_current_blog_id(): int {
    return 1;
}

function get_option( string $key, mixed $default = false ): mixed {
    return array_key_exists( $key, $GLOBALS['hws_test_options'] ) ? $GLOBALS['hws_test_options'][ $key ] : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    $GLOBALS['hws_test_options'][ $key ] = $value;

    return true;
}

function delete_option( string $key ): bool {
    $existed = array_key_exists( $key, $GLOBALS['hws_test_options'] );
    unset( $GLOBALS['hws_test_options'][ $key ] );

    return $existed;
}

function is_tax( string $taxonomy = '' ): bool {
    ++$GLOBALS['hws_test_global_is_tax_calls'];

    return true;
}

class HWS_Test_Wpdb {
    public string $postmeta = 'wp_postmeta';
    public string $last_error = '';
    public int $queries = 0;
    public string $last_like = '';

    public function esc_like( string $value ): string {
        return addcslashes( $value, '_%\\' );
    }

    public function prepare( string $sql, string $like ): string {
        $this->last_like = $like;

        return str_replace( '%s', "'" . addslashes( $like ) . "'", $sql );
    }

    public function get_var( string $sql ): mixed {
        ++$this->queries;

        return $GLOBALS['hws_test_ptt_rule_exists'] ? '1' : null;
    }
}

$GLOBALS['wpdb'] = new HWS_Test_Wpdb();

class WP_Query {
    /** @var array<string,mixed> */
    private array $vars;
    private bool $main;
    private bool $taxonomy;
    public bool $is_404 = false;

    /** @param array<string,mixed> $vars */
    public function __construct( array $vars = [], bool $main = true, bool $taxonomy = false ) {
        $this->vars = $vars;
        $this->main = $main;
        $this->taxonomy = $taxonomy;
    }

    public function get( string $key ): mixed {
        return $this->vars[ $key ] ?? null;
    }

    public function set( string $key, mixed $value ): void {
        $this->vars[ $key ] = $value;
    }

    public function is_main_query(): bool {
        return $this->main;
    }

    public function is_tax( string $taxonomy = '' ): bool {
        return $this->taxonomy && 'coderevolution_post_source' === $taxonomy;
    }

    public function set_404(): void {
        $this->is_404 = true;
    }
}

require_once __DIR__ . '/fixtures/wp-content/plugins/post-type-transfer/admin/class-ptt-post-visibility.php';
require_once __DIR__ . '/fixtures/wp-content/plugins/rss-feed-post-generator-echo/rss-feed-post-generator-echo.php';
require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/QuerySafety/QueryEligibility.php';
require_once dirname( __DIR__ ) . '/src/QueryCompatibility/QueryHookCompatibility.php';

function hws_test_reset_compatibility(): void {
    $reflection = new ReflectionClass( QueryHookCompatibility::class );
    $values = [
        'reconcile_scheduled' => false,
        'reconciled'          => false,
        'ptt_cache_hooks_registered' => false,
        'ptt_visibility'      => null,
        'ptt_rule_presence'   => [],
        'status'              => [
            'post_type_transfer' => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
            'echo_rss'           => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
            'elementor_search'   => [ 'state' => 'pending', 'detail' => 'Compatibility has not run.' ],
        ],
    ];
    foreach ( $values as $property => $value ) {
        $reflection_property = $reflection->getProperty( $property );
        $reflection_property->setValue( null, $value );
    }
}

function hws_test_reset_runtime(): void {
    $GLOBALS['wp_filter'] = [];
    $GLOBALS['hws_test_did_actions'] = [];
    $GLOBALS['hws_test_context'] = [ 'admin' => false, 'ajax' => false, 'cron' => false, 'rest' => false ];
    $GLOBALS['hws_test_global_is_tax_calls'] = 0;
    $GLOBALS['hws_test_options'] = [];
    $GLOBALS['hws_test_ptt_rule_exists'] = true;
    $GLOBALS['wpdb'] = new HWS_Test_Wpdb();
    $_GET = [];
    hws_test_reset_compatibility();
}

/** @return array{0:PTT_Post_Visibility,1:Closure} */
function hws_test_register_vendor_hooks( bool $echo_drift = false, bool $duplicate_ptt = false ): array {
    $ptt = new PTT_Post_Visibility();
    add_action( 'parse_query', [ $ptt, 'fix_queried_object' ], 10 );
    add_action( 'pre_get_posts', [ $ptt, 'filter_queries' ], 99 );
    add_filter( 'widget_posts_args', [ $ptt, 'filter_recent_posts_widget' ], 10 );
    add_filter( 'query_loop_block_query_vars', [ $ptt, 'filter_query_loop_block' ], 10 );

    if ( $duplicate_ptt ) {
        $duplicate = new PTT_Post_Visibility();
        add_action( 'parse_query', [ $duplicate, 'fix_queried_object' ], 10 );
        add_action( 'pre_get_posts', [ $duplicate, 'filter_queries' ], 99 );
        add_filter( 'widget_posts_args', [ $duplicate, 'filter_recent_posts_widget' ], 10 );
        add_filter( 'query_loop_block_query_vars', [ $duplicate, 'filter_query_loop_block' ], 10 );
    }

    $echo = $echo_drift ? hws_test_echo_drifted_callback() : hws_test_echo_source_callback();
    add_action( 'pre_get_posts', $echo, 10 );

    return [ $ptt, $echo ];
}

function hws_test_hook_count( string $hook, mixed $callback, int $priority ): int {
    $id = hws_test_callback_id( $callback );

    return isset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $id ] ) ? 1 : 0;
}

hws_test_reset_runtime();
[ $ptt, $echo ] = hws_test_register_vendor_hooks();
$module = new QueryHookCompatibility();
$module->register();
$module->register();
hws_test_expect(
    1 === hws_test_hook_count( 'wp_loaded', [ QueryHookCompatibility::class, 'reconcile' ], PHP_INT_MAX ),
    'registration schedules one late reconciliation callback'
);
do_action( 'wp_loaded' );
$audit = QueryHookCompatibility::audit();
$counts = $audit['callbacks'];
hws_test_expect( 'guarded' === $audit['status']['post_type_transfer']['state'], 'Post Type Transfer exact source is guarded' );
hws_test_expect( 'guarded' === $audit['status']['echo_rss']['state'], 'Echo exact source is guarded' );
hws_test_expect( 'capped' === $audit['status']['elementor_search']['state'], 'Elementor enhanced search cap is active' );
hws_test_expect(
    0 === $counts['ptt_parse_vendor'] && 1 === $counts['ptt_parse_guard']
        && 0 === $counts['ptt_query_vendor'] && 1 === $counts['ptt_query_guard'],
    'PTT main-query callbacks are replaced one-for-one'
);
hws_test_expect(
    1 === $counts['ptt_widget_filter'] && 1 === $counts['ptt_widget_filter_all']
        && 1 === $counts['ptt_query_loop_filter'] && 1 === $counts['ptt_query_loop_filter_all'],
    'PTT dedicated secondary-query filters remain exactly once'
);
hws_test_expect(
    0 === $counts['echo_vendor_closure'] && 1 === $counts['echo_taxonomy_guard'],
    'Echo anonymous callback is replaced by one named guard'
);
hws_test_expect( 1 === $counts['elementor_search_cap'], 'Elementor cap callback count is exactly one' );
QueryHookCompatibility::reconcile();
hws_test_expect( $counts === QueryHookCompatibility::audit()['callbacks'], 'reconciliation is idempotent' );

$main = new WP_Query( [ 'suppress_filters' => false ] );
do_action( 'parse_query', $main );
do_action( 'pre_get_posts', $main );
hws_test_expect( [ 'fix_queried_object', 'filter_queries' ] === $ptt->calls, 'eligible frontend main query delegates to both PTT methods' );

$GLOBALS['hws_test_ptt_rule_exists'] = false;
do_action( 'deleted_post_meta', [], 42, '_ptt_hide_front_page', null );
$calls_before_empty_rules = count( $ptt->calls );
$queries_before_empty_rules = $GLOBALS['wpdb']->queries;
$empty_rules = new WP_Query();
do_action( 'parse_query', $empty_rules );
do_action( 'pre_get_posts', $empty_rules );
hws_test_expect( $calls_before_empty_rules === count( $ptt->calls ), 'PTT delegates are skipped when no visibility rule exists' );
hws_test_expect( $queries_before_empty_rules + 1 === $GLOBALS['wpdb']->queries, 'PTT rule absence uses one exact bounded existence query' );
hws_test_expect( '\\_ptt\\_hide\\_%' === $GLOBALS['wpdb']->last_like, 'PTT existence query escapes the exact metadata prefix' );
$cached_empty_rules = new WP_Query();
do_action( 'parse_query', $cached_empty_rules );
do_action( 'pre_get_posts', $cached_empty_rules );
hws_test_expect( $queries_before_empty_rules + 1 === $GLOBALS['wpdb']->queries, 'PTT rule absence is cached across later eligible queries' );

$GLOBALS['hws_test_ptt_rule_exists'] = true;
do_action( 'added_post_meta', 99, 42, '_ptt_hide_search_pages', '1' );
$rules_added = new WP_Query();
do_action( 'parse_query', $rules_added );
do_action( 'pre_get_posts', $rules_added );
hws_test_expect( $queries_before_empty_rules + 2 === $GLOBALS['wpdb']->queries, 'PTT metadata changes invalidate the rule-presence cache' );
hws_test_expect( 'filter_queries' === end( $ptt->calls ), 'new PTT rules immediately restore guarded vendor behavior' );

foreach ( [
    'secondary'  => [ new WP_Query( [ 'suppress_filters' => false ], false ), null ],
    'suppressed' => [ new WP_Query( [ 'suppress_filters' => true ] ), null ],
    'admin'      => [ new WP_Query(), 'admin' ],
    'ajax'       => [ new WP_Query(), 'ajax' ],
    'cron'       => [ new WP_Query(), 'cron' ],
    'rest'       => [ new WP_Query(), 'rest' ],
] as $label => [ $query, $context ] ) {
    $before = count( $ptt->calls );
    if ( null !== $context ) {
        $GLOBALS['hws_test_context'][ $context ] = true;
    }
    do_action( 'parse_query', $query );
    do_action( 'pre_get_posts', $query );
    if ( null !== $context ) {
        $GLOBALS['hws_test_context'][ $context ] = false;
    }
    hws_test_expect( $before === count( $ptt->calls ), "{$label} query does not reach PTT delegates" );
}

apply_filters( 'widget_posts_args', [] );
apply_filters( 'query_loop_block_query_vars', [] );
hws_test_expect(
    in_array( 'filter_recent_posts_widget', $ptt->calls, true ) && in_array( 'filter_query_loop_block', $ptt->calls, true ),
    'vendor widget and Query Loop behavior remains callable for secondary queries'
);

$taxonomy_main = new WP_Query( [], true, true );
do_action( 'pre_get_posts', $taxonomy_main );
hws_test_expect( $taxonomy_main->is_404, 'Echo guard sets 404 on its main post-source taxonomy query' );
hws_test_expect( 0 === $GLOBALS['hws_test_global_is_tax_calls'], 'Echo guard uses query-object taxonomy state, not global conditionals' );
foreach ( [
    new WP_Query( [], false, true ),
    new WP_Query( [ 'suppress_filters' => true ], true, true ),
    new WP_Query( [], true, false ),
] as $rejected_taxonomy ) {
    do_action( 'pre_get_posts', $rejected_taxonomy );
    hws_test_expect( ! $rejected_taxonomy->is_404, 'Echo guard preserves unrelated, secondary, and suppressed queries' );
}

// Exact format observed from Elementor Pro 4.2.1 on michaelperes.com.
$_GET = [ 'e_search_props' => '70ae8ff-270490', 's' => '' ];
$unbounded = new WP_Query( [ 'posts_per_page' => -1 ] );
do_action( 'pre_get_posts', $unbounded );
hws_test_expect( 100 === $unbounded->get( 'posts_per_page' ), 'Elementor unlimited enhanced search is capped at 100' );
$oversized = new WP_Query( [ 'posts_per_page' => '500' ] );
do_action( 'pre_get_posts', $oversized );
hws_test_expect( 100 === $oversized->get( 'posts_per_page' ), 'Elementor oversized enhanced search is capped' );
$smaller = new WP_Query( [ 'posts_per_page' => 24 ] );
do_action( 'pre_get_posts', $smaller );
hws_test_expect( 24 === $smaller->get( 'posts_per_page' ), 'Elementor smaller enhanced search limit is preserved' );
add_filter( QueryHookCompatibility::ELEMENTOR_SEARCH_MAX_FILTER, static fn(): int => 40 );
$filtered_max = new WP_Query( [ 'posts_per_page' => 0 ] );
do_action( 'pre_get_posts', $filtered_max );
hws_test_expect( 40 === $filtered_max->get( 'posts_per_page' ), 'Elementor cap maximum is filterable and remains positive' );

foreach ( [
    'ordinary'   => [ [], new WP_Query( [ 'posts_per_page' => -1 ] ), null ],
    'invalid'    => [ [ 'e_search_props' => '../bad-42', 's' => 'x' ], new WP_Query( [ 'posts_per_page' => -1 ] ), null ],
    'secondary'  => [ [ 'e_search_props' => '70ae8ff-270490', 's' => 'x' ], new WP_Query( [ 'posts_per_page' => -1 ], false ), null ],
    'suppressed' => [ [ 'e_search_props' => '70ae8ff-270490', 's' => 'x' ], new WP_Query( [ 'posts_per_page' => -1, 'suppress_filters' => true ] ), null ],
    'background' => [ [ 'e_search_props' => '70ae8ff-270490', 's' => 'x' ], new WP_Query( [ 'posts_per_page' => -1 ] ), 'ajax' ],
] as $label => [ $request, $query, $context ] ) {
    $_GET = $request;
    if ( null !== $context ) {
        $GLOBALS['hws_test_context'][ $context ] = true;
    }
    do_action( 'pre_get_posts', $query );
    if ( null !== $context ) {
        $GLOBALS['hws_test_context'][ $context ] = false;
    }
    hws_test_expect( -1 === $query->get( 'posts_per_page' ), "Elementor cap preserves {$label} query" );
}

hws_test_reset_runtime();
[ $drift_ptt, $drift_echo ] = hws_test_register_vendor_hooks( true );
( new QueryHookCompatibility() )->register();
do_action( 'wp_loaded' );
$drift_audit = QueryHookCompatibility::audit();
hws_test_expect( 'drift' === $drift_audit['status']['echo_rss']['state'], 'Echo source-body drift is reported' );
hws_test_expect(
    1 === hws_test_hook_count( 'pre_get_posts', $drift_echo, 10 ) && 0 === $drift_audit['callbacks']['echo_taxonomy_guard'],
    'Echo source drift leaves the vendor hook untouched and installs no replacement'
);
hws_test_expect( 'guarded' === $drift_audit['status']['post_type_transfer']['state'], 'one vendor drift does not block an independent valid integration' );

hws_test_reset_runtime();
[ $duplicate_ptt, $valid_echo ] = hws_test_register_vendor_hooks( false, true );
( new QueryHookCompatibility() )->register();
do_action( 'wp_loaded' );
$duplicate_audit = QueryHookCompatibility::audit();
hws_test_expect( 'quarantined' === $duplicate_audit['status']['post_type_transfer']['state'], 'duplicate PTT object callbacks are critically quarantined' );
hws_test_expect(
    0 === $duplicate_audit['callbacks']['ptt_parse_vendor_all']
        && 0 === $duplicate_audit['callbacks']['ptt_query_vendor_all']
        && 0 === $duplicate_audit['callbacks']['ptt_parse_guard']
        && 0 === $duplicate_audit['callbacks']['ptt_query_guard'],
    'PTT callback-count drift fails closed with no broad callback left active'
);
hws_test_expect(
    2 === $duplicate_audit['callbacks']['ptt_widget_filter'] && 2 === $duplicate_audit['callbacks']['ptt_query_loop_filter'],
    'PTT quarantine leaves dedicated secondary filters untouched'
);
hws_test_expect( 'guarded' === $duplicate_audit['status']['echo_rss']['state'], 'Echo still reconciles when PTT fails closed' );

foreach ( [
    'parse_query'  => [ 'fix_queried_object', 11 ],
    'pre_get_posts' => [ 'filter_queries', 100 ],
] as $duplicate_hook => [ $duplicate_method, $duplicate_priority ] ) {
    hws_test_reset_runtime();
    [ $off_priority_ptt ] = hws_test_register_vendor_hooks();
    add_action( $duplicate_hook, [ $off_priority_ptt, $duplicate_method ], $duplicate_priority );
    ( new QueryHookCompatibility() )->register();
    do_action( 'wp_loaded' );
    $off_priority_audit = QueryHookCompatibility::audit();
    hws_test_expect(
        'quarantined' === $off_priority_audit['status']['post_type_transfer']['state'],
        "off-priority {$duplicate_hook} PTT duplicate is critically quarantined"
    );
    hws_test_expect(
        0 === $off_priority_audit['callbacks']['ptt_parse_vendor_all']
            && 0 === $off_priority_audit['callbacks']['ptt_query_vendor_all']
            && 0 === $off_priority_audit['callbacks']['ptt_parse_guard']
            && 0 === $off_priority_audit['callbacks']['ptt_query_guard'],
        "off-priority {$duplicate_hook} drift leaves no broad PTT callback active"
    );
    hws_test_expect(
        1 === $off_priority_audit['callbacks']['ptt_widget_filter_all']
            && 1 === $off_priority_audit['callbacks']['ptt_query_loop_filter_all'],
        "off-priority {$duplicate_hook} quarantine preserves dedicated PTT filters"
    );
}

$eligibility_source = (string) file_get_contents( dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/QuerySafety/QueryEligibility.php' );
hws_test_expect(
    str_contains( $eligibility_source, "defined( 'WP_CLI' )" )
        && str_contains( $eligibility_source, "defined( 'DOING_AJAX' )" )
        && str_contains( $eligibility_source, "defined( 'DOING_CRON' )" )
        && str_contains( $eligibility_source, "defined( 'REST_REQUEST' )" )
        && str_contains( $eligibility_source, "defined( 'XMLRPC_REQUEST' )" ),
    'selected Core guard statically covers CLI, AJAX, cron, REST, and XML-RPC constants'
);

if ( [] !== $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo "PASS: {$passes} query-hook compatibility assertions.\n";
