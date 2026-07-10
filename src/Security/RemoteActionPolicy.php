<?php

namespace HWS\BaseTools\Security;

final class RemoteActionPolicy {
    public static function legacy_get_routes_allowed(): bool {
        return defined( 'HWS_ALLOW_LEGACY_REMOTE_ACTIONS' )
            && true === HWS_ALLOW_LEGACY_REMOTE_ACTIONS;
    }

    public static function option_enabled( string $option_name ): bool {
        if ( ! self::legacy_get_routes_allowed() ) {
            return false;
        }

        $value = get_option( $option_name, 'no' );

        return in_array( $value, [ 'yes', '1', 1, true ], true );
    }
}
