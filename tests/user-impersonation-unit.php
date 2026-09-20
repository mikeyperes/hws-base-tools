<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$root = dirname( __DIR__ );
$passes = 0;
$failures = [];
$options = [];
$transients = [];
$hooks = [];
$users = [
    1 => (object) [ 'ID' => 1, 'display_name' => 'Primary Admin', 'user_login' => 'admin', 'manage_options' => true ],
    2 => (object) [ 'ID' => 2, 'display_name' => 'Customer', 'user_login' => 'customer', 'manage_options' => false ],
    3 => (object) [ 'ID' => 3, 'display_name' => 'Other Admin', 'user_login' => 'other-admin', 'manage_options' => true ],
];
$session_token = 'actor-session-token';

function impersonation_expect( bool $condition, string $message ): void {
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

function set_transient( string $key, mixed $value, int $expiration ): bool {
    global $transients;
    $transients[ $key ] = $value;
    return true;
}

function get_transient( string $key ): mixed {
    global $transients;
    return $transients[ $key ] ?? false;
}

function delete_transient( string $key ): bool {
    global $transients;
    unset( $transients[ $key ] );
    return true;
}

function user_can( int $user_id, string $capability ): bool {
    global $users;
    return 'manage_options' === $capability && ! empty( $users[ $user_id ]->manage_options );
}

function get_userdata( int $user_id ): mixed {
    global $users;
    return $users[ $user_id ] ?? false;
}

function wp_get_session_token(): string {
    global $session_token;
    return $session_token;
}

function wp_unslash( mixed $value ): mixed {
    return $value;
}

function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    global $hooks;
    $hooks[] = [ 'type' => 'filter', 'hook' => $hook, 'priority' => $priority, 'accepted_args' => $accepted_args ];
    return true;
}

function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    global $hooks;
    $hooks[] = [ 'type' => 'action', 'hook' => $hook, 'priority' => $priority, 'accepted_args' => $accepted_args ];
    return true;
}

function home_url( string $path = '' ): string {
    return 'https://example.test' . $path;
}

function wp_parse_url( string $url ): array|false {
    return parse_url( $url );
}

function add_query_arg( mixed $key, mixed $value = null, string $url = '' ): string {
    $args = is_array( $key ) ? $key : [ (string) $key => $value ];
    if ( is_array( $key ) ) {
        $url = (string) $value;
    }

    $fragment = '';
    if ( str_contains( $url, '#' ) ) {
        [ $url, $fragment ] = explode( '#', $url, 2 );
        $fragment = '#' . $fragment;
    }
    $separator = str_contains( $url, '?' ) ? '&' : '?';
    $query = http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

    return $url . $separator . $query . $fragment;
}

function current_time( string $type ): string {
    return '2026-09-20 12:00:00';
}

require_once $root . '/lib/hexa-wordpress-plugin-core/src/CoreContracts/ModuleInterface.php';
require_once $root . '/src/UserImpersonation/VirtualSession.php';
require_once $root . '/src/UserImpersonation/VirtualSessionStoreInterface.php';
require_once $root . '/src/UserImpersonation/WordPressVirtualSessionStore.php';
require_once $root . '/src/UserImpersonation/ImpersonationAccessPolicy.php';
require_once $root . '/src/UserImpersonation/VirtualRequestContext.php';
require_once $root . '/src/UserImpersonation/ViewAsController.php';
require_once $root . '/src/UserImpersonation/VirtualRequestTransport.php';
require_once $root . '/src/UserImpersonation/ViewAsPresentation.php';
require_once $root . '/src/UserImpersonation/UserImpersonationFeature.php';

use HWS\BaseTools\UserImpersonation\ImpersonationAccessPolicy;
use HWS\BaseTools\UserImpersonation\UserImpersonationFeature;
use HWS\BaseTools\UserImpersonation\VirtualRequestContext;
use HWS\BaseTools\UserImpersonation\VirtualRequestTransport;
use HWS\BaseTools\UserImpersonation\WordPressVirtualSessionStore;

impersonation_expect( ! UserImpersonationFeature::enabled(), 'View As is disabled by default' );
( new UserImpersonationFeature() )->register();
impersonation_expect( [] === $hooks, 'disabled View As registers no runtime hooks' );

$policy = new ImpersonationAccessPolicy();
impersonation_expect( $policy->can_start( 1, 2 ), 'an administrator may start View As for an existing user' );
impersonation_expect( ! $policy->can_start( 2, 1 ), 'a non-administrator cannot start View As' );
impersonation_expect( ! $policy->can_start( 1, 999 ), 'an administrator cannot target a missing user' );

$store = new WordPressVirtualSessionStore( 3600 );
$request_token = $store->create( 1, 2, $session_token );
$transient_key = WordPressVirtualSessionStore::transient_key( $request_token );
$session = $store->find( $request_token );
impersonation_expect(
    43 === strlen( $request_token )
        && isset( $transients[ $transient_key ] )
        && ! str_contains( serialize( $transients[ $transient_key ] ), $request_token ),
    'the raw virtual-session token is random, URL-safe, and never stored'
);
impersonation_expect(
    $session && 1 === $session->actor_id && 2 === $session->target_id && $session->matches_actor( 1, $session_token ),
    'stored sessions bind the administrator, target, and original WordPress session'
);
impersonation_expect( $session && ! $session->matches_actor( 1, 'different-session' ), 'a different administrator login session cannot reuse the token' );

$_GET[ VirtualRequestContext::REQUEST_KEY ] = $request_token;
$context = new VirtualRequestContext( $store, $policy );
impersonation_expect( 2 === $context->filter_current_user( 1 ), 'a valid virtual request resolves WordPress capabilities to the target user' );
impersonation_expect( $context->is_active() && 1 === $context->session()?->actor_id, 'the administrator identity remains available separately from the virtual user' );

$other_context = new VirtualRequestContext( $store, $policy );
impersonation_expect( 3 === $other_context->filter_current_user( 3 ), 'another administrator cannot reuse the first administrator session' );

$internal = VirtualRequestTransport::append_token( 'https://example.test/wp-admin/edit.php', $request_token );
$external = VirtualRequestTransport::append_token( 'https://outside.test/path', $request_token );
impersonation_expect( str_contains( $internal, 'hws_view_as=' ) && $external === 'https://outside.test/path', 'transport keeps the token on same-site requests and never appends it to external URLs' );

$store->revoke( $request_token );
impersonation_expect( null === $store->find( $request_token ), 'ending View As immediately revokes the virtual session' );

$options[ UserImpersonationFeature::FEATURE_OPTION ] = true;
( new UserImpersonationFeature( $store ) )->register();
$registered_hooks = array_column( $hooks, 'hook' );
impersonation_expect(
    in_array( 'determine_current_user', $registered_hooks, true )
        && in_array( 'user_row_actions', $registered_hooks, true )
        && in_array( 'admin_post_hws_view_as_start', $registered_hooks, true )
        && in_array( 'admin_post_hws_view_as_end', $registered_hooks, true )
        && in_array( 'in_admin_header', $registered_hooks, true ),
    'enabled View As registers identity switching, user actions, revocation, and banner hooks'
);

$controller_source = (string) file_get_contents( $root . '/src/UserImpersonation/ViewAsController.php' );
$context_source = (string) file_get_contents( $root . '/src/UserImpersonation/VirtualRequestContext.php' );
$feature_source = (string) file_get_contents( $root . '/src/UserImpersonation/UserImpersonationFeature.php' );
$runtime_source = (string) file_get_contents( $root . '/src/LegacyCompatibility/legacy-runtime.php' );
impersonation_expect(
    ! str_contains( $controller_source . $context_source . $feature_source, 'wp_set_auth_cookie' )
        && ! str_contains( $controller_source . $context_source . $feature_source, 'wp_clear_auth_cookie' ),
    'View As never replaces or clears the administrator authentication cookie'
);
impersonation_expect(
    str_contains( $runtime_source, "'name'             => 'View As User'" )
        && str_contains( $runtime_source, "'scope_admin_only' => true" ),
    'View As is listed in the HWS Admin Features catalog'
);

if ( $failures ) {
    fwrite( STDERR, count( $failures ) . " user impersonation assertion(s) failed.\n" );
    exit( 1 );
}

echo "PASS: {$passes} user impersonation assertions passed.\n";
