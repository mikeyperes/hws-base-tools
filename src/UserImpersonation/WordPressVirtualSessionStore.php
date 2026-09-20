<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class WordPressVirtualSessionStore implements VirtualSessionStoreInterface {
    private const TRANSIENT_PREFIX = 'hws_view_as_';
    private const TOKEN_BYTES = 32;
    private const DEFAULT_TTL = 7200;

    public function __construct( private readonly int $ttl = self::DEFAULT_TTL ) {
    }

    public function create( int $actor_id, int $target_id, string $actor_session_token ): string {
        if ( $actor_id < 1 || $target_id < 1 || '' === $actor_session_token ) {
            throw new \InvalidArgumentException( 'A valid administrator session and target user are required.' );
        }

        $request_token = self::generate_token();
        $now = time();
        $session = new VirtualSession(
            hash( 'sha256', $request_token ),
            $actor_id,
            $target_id,
            hash( 'sha256', $actor_session_token ),
            $now,
            $now + max( 300, $this->ttl )
        );

        if ( ! set_transient( self::transient_key( $request_token ), $session->to_array(), max( 300, $this->ttl ) ) ) {
            throw new \RuntimeException( 'The virtual user session could not be stored.' );
        }

        return $request_token;
    }

    public function find( string $request_token ): ?VirtualSession {
        $request_token = self::normalize_token( $request_token );
        if ( '' === $request_token ) {
            return null;
        }

        $stored = get_transient( self::transient_key( $request_token ) );
        $session = is_array( $stored ) ? VirtualSession::from_array( $stored ) : null;

        if ( ! $session || $session->is_expired() ) {
            if ( false !== $stored ) {
                $this->revoke( $request_token );
            }
            return null;
        }

        return $session;
    }

    public function revoke( string $request_token ): void {
        $request_token = self::normalize_token( $request_token );
        if ( '' !== $request_token ) {
            delete_transient( self::transient_key( $request_token ) );
        }
    }

    public static function normalize_token( string $request_token ): string {
        $request_token = trim( $request_token );

        return preg_match( '/^[A-Za-z0-9_-]{43}$/', $request_token ) ? $request_token : '';
    }

    public static function transient_key( string $request_token ): string {
        return self::TRANSIENT_PREFIX . hash( 'sha256', $request_token );
    }

    private static function generate_token(): string {
        return rtrim( strtr( base64_encode( random_bytes( self::TOKEN_BYTES ) ), '+/', '-_' ), '=' );
    }
}
