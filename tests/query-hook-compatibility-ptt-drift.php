<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'POST_TYPE_TRANSFER_VERSION', '1.6' );

$GLOBALS['wp_filter'] = [];
$GLOBALS['hws_critical_events'] = [];

class WP_Hook {
    /** @var array<int,array<string,array{function:mixed,accepted_args:int}>> */
    public array $callbacks = [];
}

function hws_drift_callback_id( mixed $callback ): string {
    if ( is_array( $callback ) ) {
        return ( is_object( $callback[0] ) ? spl_object_hash( $callback[0] ) : (string) $callback[0] ) . '::' . $callback[1];
    }

    return $callback instanceof Closure ? spl_object_hash( $callback ) : (string) $callback;
}

function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    $GLOBALS['wp_filter'][ $hook ] ??= new WP_Hook();
    $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ hws_drift_callback_id( $callback ) ] = [
        'function'      => $callback,
        'accepted_args' => $accepted_args,
    ];

    return true;
}

function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return add_action( $hook, $callback, $priority, $accepted_args );
}

function remove_action( string $hook, mixed $callback, int $priority = 10 ): bool {
    $id = hws_drift_callback_id( $callback );
    if ( ! isset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $id ] ) ) {
        return false;
    }
    unset( $GLOBALS['wp_filter'][ $hook ]->callbacks[ $priority ][ $id ] );

    return true;
}

function do_action( string $hook, mixed ...$args ): void {
    if ( 'hws_query_hook_compatibility_critical' === $hook ) {
        $GLOBALS['hws_critical_events'][] = $args;
    }
}

function did_action( string $hook ): int {
    return 'wp_loaded' === $hook ? 1 : 0;
}

class WP_Query {
    public function get( string $key ): mixed {
        return null;
    }

    public function is_main_query(): bool {
        return true;
    }
}

// Deliberately wrong source path and method arity for the supported 1.6 identity.
class PTT_Post_Visibility {
    public function fix_queried_object( $query, $unexpected = null ) {}
    public function filter_queries( $query, $unexpected = null ) {}
    public function filter_recent_posts_widget( $args ) { return $args; }
    public function filter_query_loop_block( $args ) { return $args; }
}

$ptt = new PTT_Post_Visibility();
add_action( 'parse_query', [ $ptt, 'fix_queried_object' ], 10 );
add_action( 'pre_get_posts', [ $ptt, 'filter_queries' ], 99 );
add_filter( 'widget_posts_args', [ $ptt, 'filter_recent_posts_widget' ], 10 );
add_filter( 'query_loop_block_query_vars', [ $ptt, 'filter_query_loop_block' ], 10 );

require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/QuerySafety/QueryEligibility.php';
require_once dirname( __DIR__ ) . '/src/QueryCompatibility/QueryHookCompatibility.php';

HWS\BaseTools\QueryCompatibility\QueryHookCompatibility::reconcile();
$audit = HWS\BaseTools\QueryCompatibility\QueryHookCompatibility::audit();
$callbacks = $audit['callbacks'];
$passed = 'quarantined' === $audit['status']['post_type_transfer']['state']
    && 0 === $callbacks['ptt_parse_vendor_all']
    && 0 === $callbacks['ptt_query_vendor_all']
    && 0 === $callbacks['ptt_parse_guard']
    && 0 === $callbacks['ptt_query_guard']
    && 1 === $callbacks['ptt_widget_filter']
    && 1 === $callbacks['ptt_query_loop_filter']
    && 1 === count( $GLOBALS['hws_critical_events'] );

if ( ! $passed ) {
    fwrite( STDERR, 'PTT source/signature drift did not fail closed.' . PHP_EOL );
    exit( 1 );
}

echo "PASS: PTT source/signature drift is critically quarantined with dedicated secondary filters intact.\n";
