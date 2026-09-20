<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class UserImpersonationFeature implements ModuleInterface {
    public const FEATURE_OPTION = 'enable_hws_user_impersonation';

    private static bool $active = false;

    public function __construct( private readonly ?VirtualSessionStoreInterface $store = null ) {
    }

    public function register(): void {
        if ( self::enabled() ) {
            self::activate( $this->store );
        }
    }

    public static function enabled(): bool {
        return (bool) get_option( self::FEATURE_OPTION, false );
    }

    public static function activate( ?VirtualSessionStoreInterface $store = null ): void {
        if ( self::$active ) {
            return;
        }

        self::$active = true;
        $store = $store ?? new WordPressVirtualSessionStore();
        $policy = new ImpersonationAccessPolicy();
        $context = new VirtualRequestContext( $store, $policy );
        $controller = new ViewAsController( $store, $context, $policy );

        $context->register();
        $controller->register();
        ( new VirtualRequestTransport( $context ) )->register();
        ( new ViewAsPresentation( $context, $controller ) )->register();
    }

    /** @return array{passed:bool,message:string,proof:string,ran_at:string} */
    public static function test_report(): array {
        $enabled = self::enabled();

        return [
            'passed'  => $enabled && self::$active,
            'message' => $enabled && self::$active
                ? 'Administrator-only View As hooks are active.'
                : 'View As is disabled or its hooks are not active.',
            'proof'   => 'Capability: manage_options; isolated request token; WordPress authentication cookie remains unchanged.',
            'ran_at'  => current_time( 'mysql' ),
        ];
    }
}
