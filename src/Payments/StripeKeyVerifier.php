<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Payments;

use Hexa\PluginCore\ApiKeys\ApiKeyCheck;
use Hexa\PluginCore\ApiKeys\HttpApiKeyVerifier;

defined( 'ABSPATH' ) || exit;

/** Tests a Stripe secret or restricted key by reading the account it belongs to. */
final class StripeKeyVerifier extends HttpApiKeyVerifier {
    protected function endpoint(): string {
        return 'https://api.stripe.com/v1/account';
    }

    protected function shape_error( string $key ): string {
        return preg_match( '/^(sk|rk)_(live|test)_[A-Za-z0-9]+$/', $key ) ? '' : 'A Stripe secret key starts with sk_live_ or sk_test_ (a restricted key with rk_).';
    }

    protected function on_active( array $body, string $key ): ApiKeyCheck {
        $mode = str_contains( $key, '_live_' ) ? 'live' : 'test';
        $name = (string) ( $body['settings']['dashboard']['display_name'] ?? $body['business_profile']['name'] ?? $body['email'] ?? '' );

        return ApiKeyCheck::active(
            'Active' . ( '' !== $name ? ' for ' . $name : '' ) . ', in ' . $mode . ' mode.',
            [ 'mode' => $mode, 'account' => (string) ( $body['id'] ?? '' ) ]
        );
    }
}
