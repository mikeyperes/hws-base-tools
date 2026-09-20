<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

interface VirtualSessionStoreInterface {
    public function create( int $actor_id, int $target_id, string $actor_session_token ): string;

    public function find( string $request_token ): ?VirtualSession;

    public function revoke( string $request_token ): void;
}
