<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$passes = 0;
$failures = [];
$options = [
    'default_comment_status' => 'open',
    'default_ping_status'    => 'open',
];
$filters = [];

function discussion_expect( bool $condition, string $message ): void {
    global $passes, $failures;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function get_option( string $key, mixed $default = false ): mixed {
    global $options;

    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    global $filters;
    $filters[ $hook ] = compact( 'callback', 'priority', 'accepted_args' );

    return true;
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $root . '/' );
}

require_once $root . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once $root . '/src/FrontendContent/DiscussionPolicy.php';

use HWS\BaseTools\FrontendContent\DiscussionPolicy;

( new DiscussionPolicy() )->register();

discussion_expect(
    isset( $filters['wp_insert_post_data'], $filters['comments_open'], $filters['pings_open'] )
    && PHP_INT_MAX === $filters['wp_insert_post_data']['priority']
    && 4 === $filters['wp_insert_post_data']['accepted_args']
    && PHP_INT_MAX === $filters['comments_open']['priority']
    && PHP_INT_MAX === $filters['pings_open']['priority'],
    'discussion policy registers final-priority write and runtime enforcement'
);

$imported = DiscussionPolicy::enforce_post_data( [ 'comment_status' => 'open', 'ping_status' => 'open' ] );
discussion_expect(
    'open' === $imported['comment_status']
    && 'open' === $imported['ping_status']
    && DiscussionPolicy::enforce_comments_open( true, 10 )
    && DiscussionPolicy::enforce_pings_open( true, 10 ),
    'open HWS discussion defaults preserve explicit plugin choices'
);

$options['default_comment_status'] = 'closed';
$comments_closed = DiscussionPolicy::enforce_post_data( [ 'comment_status' => 'open', 'ping_status' => 'open' ] );
discussion_expect(
    'closed' === $comments_closed['comment_status']
    && 'open' === $comments_closed['ping_status']
    && ! DiscussionPolicy::enforce_comments_open( true, 10 )
    && DiscussionPolicy::enforce_pings_open( true, 10 ),
    'disabled comments override an importer without changing the independent ping policy'
);

$options['default_comment_status'] = 'open';
$options['default_ping_status'] = 'closed';
$pings_closed = DiscussionPolicy::enforce_post_data( [ 'comment_status' => 'open', 'ping_status' => 'open' ] );
discussion_expect(
    'open' === $pings_closed['comment_status']
    && 'closed' === $pings_closed['ping_status']
    && DiscussionPolicy::enforce_comments_open( true, 10 )
    && ! DiscussionPolicy::enforce_pings_open( true, 10 ),
    'disabled pings override an importer without changing the independent comment policy'
);

$options['default_comment_status'] = 'closed';
$both_closed = DiscussionPolicy::enforce_post_data( [ 'comment_status' => 'open', 'ping_status' => 'open' ] );
discussion_expect(
    [ 'comment_status' => 'closed', 'ping_status' => 'closed' ] === $both_closed
    && ! DiscussionPolicy::enforce_comments_open( true, 10 )
    && ! DiscussionPolicy::enforce_pings_open( true, 10 ),
    'HWS-disabled discussion remains authoritative over imported open statuses'
);

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";

if ( $failures ) {
    exit( 1 );
}
