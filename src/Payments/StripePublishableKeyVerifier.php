<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Payments;

use Hexa\PluginCore\ApiKeys\ApiKeyCheck;
use Hexa\PluginCore\ApiKeys\ApiKeyVerifier;

defined( 'ABSPATH' ) || exit;

/** A publishable key cannot read anything, so it is checked for its shape and for matching the secret key's mode. */
final class StripePublishableKeyVerifier implements ApiKeyVerifier {
    public function verify( string $key ): ApiKeyCheck {
        $key = trim( $key );
        if ( '' === $key ) {
            return ApiKeyCheck::missing();
        }
        if ( ! preg_match( '/^pk_(live|test)_[A-Za-z0-9]+$/', $key, $m ) ) {
            return ApiKeyCheck::invalid( 'A Stripe publishable key starts with pk_live_ or pk_test_.' );
        }
        $secret = Stripe::secret_key();
        if ( '' !== $secret && ! str_contains( $secret, '_' . $m[1] . '_' ) ) {
            return ApiKeyCheck::invalid( 'This is a ' . $m[1] . ' key, but the secret key is not; use both keys from the same mode.' );
        }

        return ApiKeyCheck::active( 'Looks right, in ' . $m[1] . ' mode.', [ 'mode' => $m[1] ] );
    }
}
