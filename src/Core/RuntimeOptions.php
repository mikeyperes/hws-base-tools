<?php

namespace HWS\BaseTools\Core;

final class RuntimeOptions {
    /**
     * Seed the runtime options so secret routes always depend on explicit DB state.
     */
    public static function seed_defaults() {
        self::get_master_secret();

        $defaults = [
            'hws_secret_urls_enabled'       => 'no',
            'hws_secret_setup_enabled'      => 'no',
            'hws_secret_permalinks_enabled' => 'no',
            'hws_update_urls_enabled'       => 'yes',
            'hws_login_urls_enabled'        => 'yes',
        ];

        foreach ( $defaults as $option_name => $default_value ) {
            if ( null === get_option( $option_name, null ) ) {
                update_option( $option_name, $default_value );
            }
        }
    }

    /**
     * Normalize yes/no style options to booleans.
     *
     * @param string $option_name Option key.
     * @param bool   $default     Default state when missing.
     *
     * @return bool
     */
    public static function option_is_enabled( $option_name, $default = false ) {
        $value = get_option( $option_name, $default ? 'yes' : 'no' );

        return 'yes' === $value || true === $value || '1' === $value || 1 === $value;
    }

    /**
     * Read the stored master secret, generating one when missing.
     *
     * @return string
     */
    public static function get_master_secret() {
        $secret = get_option( 'hws_master_secret_key', '' );

        if ( is_string( $secret ) ) {
            $secret = trim( $secret );
        } else {
            $secret = '';
        }

        if ( '' !== $secret ) {
            return $secret;
        }

        $secret = wp_generate_password( 32, false, false );
        update_option( 'hws_master_secret_key', $secret );

        return $secret;
    }
}
