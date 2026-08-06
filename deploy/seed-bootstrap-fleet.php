#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seeds the HWS bootstrap MU loader without loading or mutating WordPress data.
 *
 * Examples:
 *   php seed-bootstrap-fleet.php --root=/home/example/public_html --apply
 *   php seed-bootstrap-fleet.php --toolkit --apply
 *
 * Omit --apply for a read-only plan.
 */

if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 404 );
    exit;
}

$options = getopt( '', [ 'root:', 'toolkit', 'loader:', 'apply', 'help' ] );
if ( isset( $options['help'] ) ) {
    fwrite( STDOUT, "Usage: php seed-bootstrap-fleet.php [--root=/absolute/wordpress/root] [--toolkit] [--loader=/absolute/loader.php] [--apply]\n" );
    exit( 0 );
}

$apply  = isset( $options['apply'] );
$loader = isset( $options['loader'] ) ? (string) $options['loader'] : __DIR__ . '/hws-base-tools-bootstrap.php';
$loader = realpath( $loader ) ?: '';
if ( '' === $loader || ! is_readable( $loader ) || ! str_contains( (string) file_get_contents( $loader ), 'Plugin Name: HWS Base Tools Bootstrap Loader' ) ) {
    fwrite( STDERR, "The loader source is missing or invalid.\n" );
    exit( 1 );
}

$roots = [];
if ( isset( $options['root'] ) ) {
    foreach ( (array) $options['root'] as $root ) {
        $roots[] = (string) $root;
    }
}
if ( isset( $options['toolkit'] ) ) {
    $toolkit = toolkit_roots();
    if ( isset( $toolkit['error'] ) ) {
        fwrite( STDERR, (string) $toolkit['error'] . "\n" );
        exit( 1 );
    }
    $roots = array_merge( $roots, (array) $toolkit['roots'] );
}
$roots = array_values( array_unique( array_filter( array_map( 'trim', $roots ) ) ) );
if ( [] === $roots ) {
    fwrite( STDERR, "Provide at least one --root or use --toolkit. No files were changed.\n" );
    exit( 1 );
}

$results = [];
$failed  = 0;
foreach ( $roots as $root ) {
    $result    = seed_loader( $root, $loader, $apply );
    $results[] = $result;
    $failed   += ! empty( $result['success'] ) ? 0 : 1;
    fwrite( STDOUT, json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n" );
}

fwrite(
    STDOUT,
    json_encode(
        [
            'mode'    => $apply ? 'apply' : 'dry-run',
            'total'   => count( $results ),
            'passed'  => count( $results ) - $failed,
            'failed'  => $failed,
        ],
        JSON_UNESCAPED_SLASHES
    ) . "\n"
);
exit( 0 === $failed ? 0 : 2 );

/** @return array{roots?:list<string>,error?:string} */
function toolkit_roots(): array {
    $binary = '/usr/local/bin/wp-toolkit';
    if ( ! is_executable( $binary ) ) {
        return [ 'error' => 'WP Toolkit is unavailable at /usr/local/bin/wp-toolkit.' ];
    }
    $lines = [];
    $code  = 0;
    exec( escapeshellarg( $binary ) . ' --list -format json 2>/dev/null', $lines, $code );
    $raw = trim( implode( "\n", $lines ) );
    $start = strpos( $raw, '[' );
    if ( 0 !== $code || false === $start ) {
        return [ 'error' => 'WP Toolkit did not return its WordPress installation inventory.' ];
    }
    $inventory = json_decode( substr( $raw, $start ), true );
    if ( ! is_array( $inventory ) ) {
        return [ 'error' => 'WP Toolkit returned invalid installation JSON.' ];
    }
    $roots = [];
    foreach ( $inventory as $site ) {
        if ( ! is_array( $site ) || empty( $site['alive'] ) || ! empty( $site['broken'] ) ) {
            continue;
        }
        $root = trim( (string) ( $site['fullPath'] ?? '' ) );
        if ( '' !== $root ) {
            $roots[] = $root;
        }
    }
    return [ 'roots' => array_values( array_unique( $roots ) ) ];
}

/** @return array<string,mixed> */
function seed_loader( string $requested_root, string $loader, bool $apply ): array {
    $root = realpath( $requested_root );
    if ( false === $root || strlen( $root ) < 6 || '/' === $root || ! is_file( $root . '/wp-load.php' ) ) {
        return result( false, $requested_root, '', 'Not a validated WordPress root.' );
    }
    $content = realpath( $root . '/wp-content' );
    if ( false === $content || ! is_dir( $content ) || ! path_is_inside( $content, $root ) ) {
        return result( false, $root, '', 'wp-content is missing or resolves outside the WordPress root.' );
    }

    $mu_dir = $content . '/mu-plugins';
    $target = $mu_dir . '/hws-base-tools-bootstrap.php';
    if ( is_link( $target ) ) {
        return result( false, $root, $target, 'Refusing to replace a symbolic-link target.' );
    }
    $source_hash  = hash_file( 'sha256', $loader );
    $current_hash = is_readable( $target ) ? hash_file( 'sha256', $target ) : '';
    if ( is_string( $source_hash ) && '' !== $source_hash && hash_equals( $source_hash, (string) $current_hash ) ) {
        return result( true, $root, $target, 'Bootstrap loader is already current.', false, $apply );
    }
    if ( ! $apply ) {
        return result( true, $root, $target, is_file( $target ) ? 'Bootstrap loader would be updated.' : 'Bootstrap loader would be installed.', true, false );
    }

    if ( ! is_dir( $mu_dir ) && ! mkdir( $mu_dir, 0755, true ) ) {
        return result( false, $root, $target, 'The MU-plugin directory could not be created.' );
    }
    if ( ! is_dir( $mu_dir ) || ! is_writable( $mu_dir ) ) {
        return result( false, $root, $target, 'The MU-plugin directory is not writable.' );
    }

    $token  = bin2hex( random_bytes( 8 ) );
    $stage  = $mu_dir . '/.hws-base-tools-bootstrap.stage-' . $token;
    $backup = $mu_dir . '/.hws-base-tools-bootstrap.backup-' . $token;
    if ( false === file_put_contents( $stage, (string) file_get_contents( $loader ), LOCK_EX )
        || ! is_readable( $stage )
        || ! hash_equals( (string) $source_hash, (string) hash_file( 'sha256', $stage ) )
    ) {
        is_file( $stage ) && unlink( $stage );
        return result( false, $root, $target, 'The staged loader failed hash verification.' );
    }

    $owner = fileowner( $content );
    $group = filegroup( $content );
    chmod( $stage, 0644 );
    false !== $owner && @chown( $stage, $owner );
    false !== $group && @chgrp( $stage, $group );
    $had_existing = is_file( $target );
    if ( $had_existing && ! rename( $target, $backup ) ) {
        unlink( $stage );
        return result( false, $root, $target, 'The existing loader could not be moved to its rollback file.' );
    }
    if ( ! rename( $stage, $target ) ) {
        $had_existing && is_file( $backup ) && rename( $backup, $target );
        is_file( $stage ) && unlink( $stage );
        return result( false, $root, $target, 'The verified loader could not be committed.' );
    }
    if ( ! is_readable( $target ) || ! hash_equals( (string) $source_hash, (string) hash_file( 'sha256', $target ) ) ) {
        unlink( $target );
        $had_existing && is_file( $backup ) && rename( $backup, $target );
        return result( false, $root, $target, 'Committed loader verification failed; the prior file was restored.' );
    }
    $had_existing && is_file( $backup ) && unlink( $backup );
    return result( true, $root, $target, $had_existing ? 'Bootstrap loader updated and verified.' : 'Bootstrap loader installed and verified.', true, true );
}

function path_is_inside( string $candidate, string $root ): bool {
    $candidate = rtrim( str_replace( '\\', '/', $candidate ), '/' );
    $root      = rtrim( str_replace( '\\', '/', $root ), '/' );
    return $candidate !== $root && str_starts_with( $candidate . '/', $root . '/' );
}

/** @return array<string,mixed> */
function result( bool $success, string $root, string $target, string $message, bool $changed = false, bool $applied = false ): array {
    return compact( 'success', 'root', 'target', 'message', 'changed', 'applied' );
}
