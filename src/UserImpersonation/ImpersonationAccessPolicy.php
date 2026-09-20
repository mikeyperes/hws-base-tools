<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class ImpersonationAccessPolicy {
    public const ADMIN_CAPABILITY = 'manage_options';

    public function administrator_can_control( int $user_id ): bool {
        return $user_id > 0 && user_can( $user_id, self::ADMIN_CAPABILITY );
    }

    public function can_start( int $actor_id, int $target_id ): bool {
        return $this->administrator_can_control( $actor_id )
            && $target_id > 0
            && false !== get_userdata( $target_id );
    }
}
