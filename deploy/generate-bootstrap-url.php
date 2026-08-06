#!/usr/bin/env php
<?php

declare(strict_types=1);

$base_url = isset( $argv[1] ) ? trim( (string) $argv[1] ) : '';
$ttl      = isset( $argv[2] ) ? max( 60, min( 600, (int) $argv[2] ) ) : 300;
$secret   = getenv( 'HWS_BOOTSTRAP_SECRET' );

if ( '' === $base_url || false === filter_var( $base_url, FILTER_VALIDATE_URL ) ) {
    fwrite( STDERR, "Usage: HWS_BOOTSTRAP_SECRET=... generate-bootstrap-url.php https://example.com [ttl-seconds]\n" );
    exit( 1 );
}
if ( false === $secret || strlen( trim( $secret ) ) < 32 ) {
    fwrite( STDERR, "HWS_BOOTSTRAP_SECRET must be provided through the environment and contain at least 32 characters.\n" );
    exit( 1 );
}

$parts  = parse_url( $base_url );
$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
    fwrite( STDERR, "The website URL must be an HTTP(S) site root without a query or fragment.\n" );
    exit( 1 );
}

$canonical_home = $scheme . '://' . $host;
if ( isset( $parts['port'] ) ) {
    $canonical_home .= ':' . (int) $parts['port'];
}
$path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
$canonical_home = rtrim( $canonical_home . ( '/' === $path ? '' : $path ), '/' );

$action     = 'hws_bootstrap_install';
$expires    = time() + $ttl;
$request_id = bin2hex( random_bytes( 16 ) );
$payload    = $action . '|GET|' . $canonical_home . '|' . $expires . '|' . $request_id;
$signature  = hash_hmac( 'sha256', $payload, trim( $secret ) );
$query      = http_build_query( [
    'action'     => $action,
    'expires'    => $expires,
    'request_id' => $request_id,
    'signature'  => $signature,
    'format'     => 'text',
], '', '&', PHP_QUERY_RFC3986 );

echo $canonical_home . '/wp-admin/admin-post.php?' . $query . PHP_EOL;
