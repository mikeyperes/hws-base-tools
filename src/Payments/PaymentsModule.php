<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Payments;

use Hexa\PluginCore\ApiKeys\ApiKeyField;
use Hexa\PluginCore\ApiKeys\ApiKeyFields;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

defined( 'ABSPATH' ) || exit;

/** Payments: the Stripe switch (off by default), its three keys and the webhook endpoint. */
final class PaymentsModule implements ModuleInterface {
    public const NONCE_ACTION = 'hws_base_tools_payments';
    public const SLUG         = 'hws-base-tools';

    public function register(): void {
        // The webhook address needs WordPress' rewrite rules, so the fields are set up on init.
        add_action( 'init', [ self::class, 'register_fields' ], 5 );
        AjaxActionRegistry::create( [ 'action_prefix' => 'hws_base_tools', 'nonce_action' => self::NONCE_ACTION, 'nonce_field' => 'nonce' ] )
            ->register( [ 'payments_save' => [ 'callback' => [ self::class, 'handle_save' ] ] ] );

        if ( Stripe::enabled() ) {
            add_action( 'rest_api_init', [ self::class, 'register_route' ] );
        }
    }

    public static function register_fields(): void {
        if ( ApiKeyFields::get( Stripe::SECRET ) ) {
            return;
        }
        ApiKeyFields::register( new ApiKeyField(
            Stripe::SECRET, self::SLUG, Stripe::SECRET, 'Secret key', new StripeKeyVerifier(),
            [
                'Sign in to Stripe and open Developers → API keys.',
                'Pick Test mode to try it first, or switch it off for real payments.',
                'Under Standard keys, click Reveal on the Secret key and copy it (it starts with <code>sk_test_</code> or <code>sk_live_</code>).',
                'Paste it here, click Save key, then Test key.',
            ],
            'sk_live_...', 'https://dashboard.stripe.com/apikeys', 'Open Stripe API keys'
        ) );
        ApiKeyFields::register( new ApiKeyField(
            Stripe::PUBLISHABLE, self::SLUG, Stripe::PUBLISHABLE, 'Publishable key', new StripePublishableKeyVerifier(),
            [ 'On the same Stripe page, copy the Publishable key (it starts with <code>pk_</code>), from the same mode as the secret key.' ],
            'pk_live_...', 'https://dashboard.stripe.com/apikeys', 'Open Stripe API keys'
        ) );
        ApiKeyFields::register( new ApiKeyField(
            Stripe::WEBHOOK, self::SLUG, Stripe::WEBHOOK, 'Webhook signing secret', null,
            [
                'In Stripe, open Developers → Webhooks and click Add endpoint.',
                'Endpoint URL: <code>' . esc_html( Stripe::webhook_url() ) . '</code>',
                'Events: <code>checkout.session.completed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, <code>invoice.paid</code>, <code>invoice.payment_failed</code>.',
                'Save it, click Reveal under Signing secret and paste the value here (it starts with <code>whsec_</code>).',
            ],
            'whsec_...', 'https://dashboard.stripe.com/webhooks', 'Open Stripe webhooks'
        ) );
    }

    public static function register_route(): void {
        register_rest_route( Stripe::REST_NAMESPACE, Stripe::REST_ROUTE, [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => [ self::class, 'handle_webhook' ],
        ] );
    }

    public static function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $event = Stripe::verify_event( (string) $request->get_body(), (string) $request->get_header( 'stripe_signature' ) );
        if ( null === $event ) {
            return new \WP_REST_Response( [ 'received' => false, 'message' => 'Signature did not match.' ], 400 );
        }
        do_action( Stripe::EVENT_ACTION, $event );

        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    /** @return array<string,mixed> */
    public static function handle_save( AjaxRequest $request ): array {
        $on = $request->bool( 'stripe_enabled', false, 'post' );
        Stripe::set_enabled( $on );

        return [ 'saved' => true, 'enabled' => $on, 'message' => $on ? 'Stripe payments are on.' : 'Stripe payments are off.' ];
    }
}
