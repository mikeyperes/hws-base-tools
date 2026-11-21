<?php

declare( strict_types=1 );

/**
 * Supplies in-memory WordPress metadata for migrations using native Core Fields.
 *
 * Bug: Core Fields reads and writes WordPress metadata directly, so ACF mocks
 * miss both the migrations' writes and the native field-group behavior.
 * Fix: load Core's existing test support and provide its WordPress storage calls.
 * Why this way: the tests exercise the real field definitions and storage layout.
 * Tests: quote-repeater-migration-unit.php and user-profile-2025-migration-unit.php.
 */

function metadata_exists( string $type, int $id, string $key ): bool {
    return array_key_exists( $key, $GLOBALS[ $type . '_meta' ][ $id ] ?? [] );
}

function get_metadata( string $type, int $id, string $key, bool $single = false ): mixed {
    $values = $GLOBALS[ $type . '_meta' ][ $id ] ?? [];
    if ( ! array_key_exists( $key, $values ) ) {
        return $single ? '' : [];
    }
    $value = $values[ $key ];

    // WordPress returns scalar metadata as strings and preserves array values.
    $value = is_array( $value ) ? $value : ( is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value );
    return $single ? $value : [ $value ];
}

function wp_slash( mixed $value ): mixed {
    if ( is_array( $value ) ) {
        return array_map( 'wp_slash', $value );
    }
    return is_string( $value ) ? addslashes( $value ) : $value;
}

function wp_unslash( mixed $value ): mixed {
    if ( is_array( $value ) ) {
        return array_map( 'wp_unslash', $value );
    }
    return is_string( $value ) ? stripslashes( $value ) : $value;
}

function update_metadata( string $type, int $id, string $key, mixed $value ): bool {
    $value = wp_unslash( $value );
    $GLOBALS['write_log'][] = [ 'update_metadata', $type, $id, $key, $value ];
    $GLOBALS[ $type . '_meta' ][ $id ][ $key ] = $value;
    return true;
}

function delete_metadata( string $type, int $id, string $key ): bool {
    $GLOBALS['write_log'][] = [ 'delete_metadata', $type, $id, $key ];
    unset( $GLOBALS[ $type . '_meta' ][ $id ][ $key ] );
    return true;
}

require_once dirname( __DIR__, 2 ) . '/lib/hexa-wordpress-plugin-core/tests/support/fields.php';
