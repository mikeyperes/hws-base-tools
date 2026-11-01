<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Payments;

use Hexa\PluginCore\ApiKeys\ApiKeyFields;

defined( 'ABSPATH' ) || exit;

/**
 * The one Stripe connection every HWS plugin uses (Verified Profiles,
 * Contributor Network and the rest): its keys, API calls, Checkout sessions
 * and verified webhook events. Off until switched on in Payments.
 *
 * Plugins call Stripe::enabled(), Stripe::checkout() and listen to
 * the `hws_base_tools_stripe_event` action ( array $event ).
 */
final class Stripe {
    public const OPTION        = 'hws_base_tools_payments';
    public const SECRET        = 'stripe_secret_key';
    public const PUBLISHABLE   = 'stripe_publishable_key';
    public const WEBHOOK       = 'stripe_webhook_secret';
    public const EVENT_ACTION  = 'hws_base_tools_stripe_event';
    public const REST_NAMESPACE = 'hws/v1';
    public const REST_ROUTE    = '/stripe/webhook';

    public static function enabled(): bool {
        $settings = get_option( self::OPTION, [] );

        return is_array( $settings ) && ! empty( $settings['stripe_enabled'] );
    }

    public static function set_enabled( bool $on ): void {
        $settings = get_option( self::OPTION, [] );
        $settings = is_array( $settings ) ? $settings : [];
        $settings['stripe_enabled'] = $on;
        update_option( self::OPTION, $settings, false );
    }

    public static function secret_key(): string {
        return ApiKeyFields::value( self::SECRET );
    }

    public static function publishable_key(): string {
        return ApiKeyFields::value( self::PUBLISHABLE );
    }

    public static function ready(): bool {
        return self::enabled() && '' !== self::secret_key();
    }

    public static function mode(): string {
        return str_contains( self::secret_key(), '_live_' ) ? 'live' : 'test';
    }

    public static function webhook_url(): string {
        return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
    }

    /**
     * One Stripe API call. $params are sent form-encoded, as Stripe expects.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>|\WP_Error
     */
    public static function request( string $method, string $path, array $params = [] ) {
        if ( ! self::ready() ) {
            return new \WP_Error( 'hws_stripe_off', 'Stripe payments are off or have no secret key.' );
        }
        $args = [ 'method' => strtoupper( $method ), 'timeout' => 20, 'headers' => [ 'Authorization' => 'Bearer ' . self::secret_key() ] ];
        $url  = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );
        if ( 'GET' === $args['method'] ) {
            $url = add_query_arg( $params, $url );
        } else {
            $args['body'] = $params;
        }
        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( 'hws_stripe_' . $code, (string) ( $body['error']['message'] ?? 'Stripe answered with status ' . $code . '.' ) );
        }

        return is_array( $body ) ? $body : [];
    }

    /**
     * A Stripe Checkout session for a one-off payment or a subscription.
     * $args: price (a Stripe price ID), mode (payment|subscription), success_url,
     * cancel_url, email, metadata (stored on the session and its subscription).
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>|\WP_Error The session, with its `url` to send the buyer to.
     */
    public static function checkout( array $args ) {
        $mode   = 'subscription' === ( $args['mode'] ?? '' ) ? 'subscription' : 'payment';
        $params = [
            'mode'        => $mode,
            'line_items'  => [ [ 'price' => (string) ( $args['price'] ?? '' ), 'quantity' => 1 ] ],
            'success_url' => (string) ( $args['success_url'] ?? home_url( '/' ) ),
            'cancel_url'  => (string) ( $args['cancel_url'] ?? home_url( '/' ) ),
            'metadata'    => (array) ( $args['metadata'] ?? [] ),
        ];
        if ( ! empty( $args['email'] ) ) {
            $params['customer_email'] = (string) $args['email'];
        }
        if ( 'subscription' === $mode ) {
            $params['subscription_data'] = [ 'metadata' => (array) ( $args['metadata'] ?? [] ) ];
        }

        return self::request( 'POST', 'checkout/sessions', $params );
    }

    /**
     * Checks a webhook's Stripe-Signature header against the signing secret.
     * Returns the event, or null when the signature does not match or is older than five minutes.
     *
     * @return array<string,mixed>|null
     */
    public static function verify_event( string $payload, string $header, ?int $now = null ): ?array {
        $secret = ApiKeyFields::value( self::WEBHOOK );
        if ( '' === $secret || '' === $header ) {
            return null;
        }
        $time = 0;
        $signatures = [];
        foreach ( explode( ',', $header ) as $part ) {
            [ $k, $v ] = array_pad( explode( '=', trim( $part ), 2 ), 2, '' );
            if ( 't' === $k ) {
                $time = (int) $v;
            } elseif ( 'v1' === $k ) {
                $signatures[] = $v;
            }
        }
        $now = $now ?? time();
        if ( 0 === $time || abs( $now - $time ) > 300 ) {
            return null;
        }
        $expected = hash_hmac( 'sha256', $time . '.' . $payload, $secret );
        foreach ( $signatures as $signature ) {
            if ( hash_equals( $expected, $signature ) ) {
                $event = json_decode( $payload, true );

                return is_array( $event ) ? $event : null;
            }
        }

        return null;
    }
}
