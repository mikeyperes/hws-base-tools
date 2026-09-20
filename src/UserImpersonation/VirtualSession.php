<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class VirtualSession {
    public function __construct(
        public readonly string $id,
        public readonly int $actor_id,
        public readonly int $target_id,
        public readonly string $actor_session_hash,
        public readonly int $created_at,
        public readonly int $expires_at
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function from_array( array $data ): ?self {
        $id = isset( $data['id'] ) ? (string) $data['id'] : '';
        $actor_id = isset( $data['actor_id'] ) ? (int) $data['actor_id'] : 0;
        $target_id = isset( $data['target_id'] ) ? (int) $data['target_id'] : 0;
        $actor_session_hash = isset( $data['actor_session_hash'] ) ? (string) $data['actor_session_hash'] : '';
        $created_at = isset( $data['created_at'] ) ? (int) $data['created_at'] : 0;
        $expires_at = isset( $data['expires_at'] ) ? (int) $data['expires_at'] : 0;

        if ( '' === $id || $actor_id < 1 || $target_id < 1 || '' === $actor_session_hash || $expires_at < 1 ) {
            return null;
        }

        return new self( $id, $actor_id, $target_id, $actor_session_hash, $created_at, $expires_at );
    }

    /** @return array{id:string,actor_id:int,target_id:int,actor_session_hash:string,created_at:int,expires_at:int} */
    public function to_array(): array {
        return [
            'id'                 => $this->id,
            'actor_id'           => $this->actor_id,
            'target_id'          => $this->target_id,
            'actor_session_hash' => $this->actor_session_hash,
            'created_at'         => $this->created_at,
            'expires_at'         => $this->expires_at,
        ];
    }

    public function is_expired( ?int $now = null ): bool {
        return $this->expires_at <= ( $now ?? time() );
    }

    public function matches_actor( int $actor_id, string $actor_session_token ): bool {
        return $actor_id === $this->actor_id
            && '' !== $actor_session_token
            && hash_equals( $this->actor_session_hash, hash( 'sha256', $actor_session_token ) );
    }
}
