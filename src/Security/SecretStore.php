<?php

namespace HWS\BaseTools\Security;

final class SecretStore {
    private const SODIUM_PREFIX = 'hws:sodium:v1:';
    private const OPENSSL_PREFIX = 'hws:openssl:v1:';

    public function __construct( private readonly string $option_name ) {
    }

    public function get(): string {
        $stored = get_option( $this->option_name, '' );
        $stored = is_string( $stored ) ? trim( $stored ) : '';

        if ( '' === $stored ) {
            $secret = wp_generate_password( 32, false, false );
            $this->set( $secret );

            return $secret;
        }

        $decrypted = $this->decrypt( $stored );
        if ( null !== $decrypted ) {
            return $decrypted;
        }

        // Migrate the legacy plaintext option on first read.
        $this->set( $stored );

        return $stored;
    }

    public function set( string $secret ): bool {
        $secret = trim( $secret );
        if ( '' === $secret ) {
            return false;
        }

        $encrypted = $this->encrypt( $secret );
        if ( null === $encrypted ) {
            return false;
        }

        return update_option( $this->option_name, $encrypted, false )
            || get_option( $this->option_name, '' ) === $encrypted;
    }

    public function is_encrypted(): bool {
        $stored = (string) get_option( $this->option_name, '' );

        return str_starts_with( $stored, self::SODIUM_PREFIX )
            || str_starts_with( $stored, self::OPENSSL_PREFIX );
    }

    private function encrypt( string $secret ): ?string {
        $key = $this->key();

        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $box   = sodium_crypto_secretbox( $secret, $nonce, $key );

            return self::SODIUM_PREFIX . base64_encode( $nonce . $box );
        }

        if ( function_exists( 'openssl_encrypt' ) ) {
            $iv  = random_bytes( 12 );
            $tag = '';
            $box = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

            if ( false !== $box ) {
                return self::OPENSSL_PREFIX . base64_encode( $iv . $tag . $box );
            }
        }

        return null;
    }

    private function decrypt( string $stored ): ?string {
        $key = $this->key();

        if ( str_starts_with( $stored, self::SODIUM_PREFIX ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
            $raw = base64_decode( substr( $stored, strlen( self::SODIUM_PREFIX ) ), true );
            if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return null;
            }

            $nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $box   = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $plain = sodium_crypto_secretbox_open( $box, $nonce, $key );

            return false === $plain ? null : $plain;
        }

        if ( str_starts_with( $stored, self::OPENSSL_PREFIX ) && function_exists( 'openssl_decrypt' ) ) {
            $raw = base64_decode( substr( $stored, strlen( self::OPENSSL_PREFIX ) ), true );
            if ( false === $raw || strlen( $raw ) <= 28 ) {
                return null;
            }

            $iv    = substr( $raw, 0, 12 );
            $tag   = substr( $raw, 12, 16 );
            $box   = substr( $raw, 28 );
            $plain = openssl_decrypt( $box, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

            return false === $plain ? null : $plain;
        }

        return null;
    }

    private function key(): string {
        $material = function_exists( 'wp_salt' )
            ? wp_salt( 'auth' )
            : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : $this->option_name );

        return hash( 'sha256', $material . '|' . $this->option_name, true );
    }
}
