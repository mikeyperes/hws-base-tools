<?php

namespace HWS\BaseTools\ReviewCenter;

/**
 * Stores the exact result an administrator reviewed before a cleanup action.
 *
 * Records are intentionally user- and site-scoped, short lived, and consumed
 * before a mutation starts. The browser never supplies the trusted target set.
 */
final class ReviewScanStore {
    public const SCHEMA_VERSION = 1;
    public const TTL_SECONDS = 900;
    public const META_PREFIX = '_hws_review_center_scan_v1_';

    public function __construct( private mixed $clock = null ) {}

    /** @return array<string,mixed> */
    public function save( string $scan_task, string $cleanup_task, array $scan ): array {
        $user_id = $this->user_id();
        if ( $user_id <= 0 || ! function_exists( 'update_user_meta' ) || ! function_exists( 'get_user_meta' ) ) {
            return $this->failure( 'scan_store_unavailable', 'The scan could not be bound to the current administrator.' );
        }

        $now     = $this->now();
        $binding = self::binding( $scan );
        $record  = [
            'schema_version' => self::SCHEMA_VERSION,
            'user_id'        => $user_id,
            'blog_id'        => $this->blog_id(),
            'scan_task'      => self::clean_key( $scan_task ),
            'cleanup_task'   => self::clean_key( $cleanup_task ),
            'scanned_at'     => $now,
            'expires_at'     => $now + self::TTL_SECONDS,
            'binding'        => $binding,
        ];
        $record['digest'] = self::record_digest( $record );

        update_user_meta( $user_id, $this->meta_key( $cleanup_task ), $record );
        $stored = get_user_meta( $user_id, $this->meta_key( $cleanup_task ), true );
        if ( ! is_array( $stored ) || ! hash_equals( (string) $record['digest'], (string) ( $stored['digest'] ?? '' ) ) ) {
            return $this->failure( 'scan_store_failed', 'The reviewed scan could not be stored safely. Run the scan again.' );
        }

        return [
            'success'    => true,
            'digest'     => (string) $record['digest'],
            'scanned_at' => $now,
            'expires_at' => (int) $record['expires_at'],
        ];
    }

    /**
     * Verify the fresh scan against the stored scan and consume the record.
     *
     * @return array<string,mixed>
     */
    public function authorize_and_consume( string $scan_task, string $cleanup_task, array $fresh_scan ): array {
        $user_id = $this->user_id();
        if ( $user_id <= 0 || ! function_exists( 'get_user_meta' ) || ! function_exists( 'delete_user_meta' ) ) {
            return $this->failure( 'scan_store_unavailable', 'A logged-in administrator must run the matching scan first.' );
        }

        $meta_key = $this->meta_key( $cleanup_task );
        $record   = get_user_meta( $user_id, $meta_key, true );
        if ( ! is_array( $record ) || [] === $record ) {
            return $this->failure( 'scan_required', 'Run the matching Review Center scan before this cleanup action.' );
        }

        $expected_scan    = self::clean_key( $scan_task );
        $expected_cleanup = self::clean_key( $cleanup_task );
        $valid_scope      = self::SCHEMA_VERSION === (int) ( $record['schema_version'] ?? 0 )
            && $user_id === (int) ( $record['user_id'] ?? 0 )
            && $this->blog_id() === (int) ( $record['blog_id'] ?? -1 )
            && $expected_scan === (string) ( $record['scan_task'] ?? '' )
            && $expected_cleanup === (string) ( $record['cleanup_task'] ?? '' );

        if ( ! $valid_scope || ! self::valid_record_digest( $record ) ) {
            delete_user_meta( $user_id, $meta_key );
            return $this->failure( 'scan_invalid', 'The stored scan is invalid or belongs to another site. Run the scan again.' );
        }

        if ( (int) ( $record['expires_at'] ?? 0 ) <= $this->now() ) {
            delete_user_meta( $user_id, $meta_key );
            return $this->failure( 'scan_expired', 'The reviewed scan expired. Run the scan again before cleaning up.' );
        }

        $fresh_record            = $record;
        $fresh_record['binding'] = self::binding( $fresh_scan );
        $fresh_record['digest']  = self::record_digest( $fresh_record );
        if ( ! hash_equals( (string) $record['digest'], (string) $fresh_record['digest'] ) ) {
            delete_user_meta( $user_id, $meta_key );
            return $this->failure( 'scan_changed', 'The reviewed targets changed after the scan. No cleanup ran; review a new scan.' );
        }

        // Compare-and-delete before mutation so refreshes, retries, and
        // concurrent clicks cannot reuse or replace an authorization record.
        $consumed = delete_user_meta( $user_id, $meta_key, $record );
        if ( ! $consumed ) {
            return $this->failure( 'scan_consumed', 'This reviewed scan was already used or replaced. Run the scan again.' );
        }

        return [
            'success' => true,
            'digest'  => (string) $record['digest'],
            'binding' => is_array( $record['binding'] ?? null ) ? $record['binding'] : [],
        ];
    }

    /** @return array<string,mixed> */
    public static function binding( array $scan ): array {
        $binding = [];
        foreach ( [
            'matches',
            'protected',
            'retained',
            'litespeed_active',
            'destructive_supported',
            'snapshot_id',
            'snapshot_digest',
            'snapshot_scope',
        ] as $key ) {
            if ( array_key_exists( $key, $scan ) ) {
                $binding[ $key ] = $scan[ $key ];
            }
        }

        return self::canonicalize( $binding );
    }

    /** @param array<string,mixed> $record */
    private static function valid_record_digest( array $record ): bool {
        $digest = (string) ( $record['digest'] ?? '' );
        return 64 === strlen( $digest ) && hash_equals( $digest, self::record_digest( $record ) );
    }

    /** @param array<string,mixed> $record */
    private static function record_digest( array $record ): string {
        unset( $record['digest'] );
        return hash( 'sha256', serialize( self::canonicalize( $record ) ) );
    }

    private static function canonicalize( mixed $value ): mixed {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        if ( array_is_list( $value ) ) {
            $value = array_map( [ self::class, 'canonicalize' ], $value );
            usort(
                $value,
                static fn( mixed $left, mixed $right ): int => strcmp( serialize( $left ), serialize( $right ) )
            );
            return $value;
        }

        ksort( $value, SORT_STRING );
        foreach ( $value as $key => $item ) {
            $value[ $key ] = self::canonicalize( $item );
        }
        return $value;
    }

    private function user_id(): int {
        return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
    }

    private function blog_id(): int {
        return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
    }

    private function now(): int {
        return is_callable( $this->clock ) ? (int) call_user_func( $this->clock ) : time();
    }

    private function meta_key( string $cleanup_task ): string {
        return self::META_PREFIX . self::clean_key( $cleanup_task );
    }

    /** @return array<string,mixed> */
    private function failure( string $code, string $message ): array {
        return [ 'success' => false, 'code' => $code, 'message' => $message ];
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' )
            ? sanitize_key( $value )
            : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }
}
