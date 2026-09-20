<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class VirtualRequestContext {
    public const REQUEST_KEY = 'hws_view_as';

    private bool $resolved = false;
    private bool $active = false;
    private string $request_token = '';
    private ?VirtualSession $session = null;

    public function __construct(
        private readonly VirtualSessionStoreInterface $store,
        private readonly ImpersonationAccessPolicy $policy
    ) {
    }

    public function register(): void {
        add_filter( 'determine_current_user', [ $this, 'filter_current_user' ], PHP_INT_MAX );
    }

    public function filter_current_user( mixed $resolved_user_id ): mixed {
        if ( $this->resolved ) {
            return $this->active && $this->session ? $this->session->target_id : $resolved_user_id;
        }

        $this->resolved = true;
        $request_token = self::request_token_from_globals();
        $actor_id = is_numeric( $resolved_user_id ) ? (int) $resolved_user_id : 0;

        if ( '' === $request_token || $actor_id < 1 ) {
            return $resolved_user_id;
        }

        $session = $this->store->find( $request_token );
        $actor_session_token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';

        if ( ! $session
            || ! $this->policy->administrator_can_control( $actor_id )
            || ! $session->matches_actor( $actor_id, $actor_session_token )
            || false === get_userdata( $session->target_id )
        ) {
            return $resolved_user_id;
        }

        $this->active = true;
        $this->request_token = $request_token;
        $this->session = $session;

        return $session->target_id;
    }

    public function ensure_resolved(): void {
        if ( ! $this->resolved && function_exists( 'wp_get_current_user' ) ) {
            wp_get_current_user();
        }
    }

    public function is_active(): bool {
        $this->ensure_resolved();

        return $this->active && $this->session instanceof VirtualSession;
    }

    public function session(): ?VirtualSession {
        return $this->is_active() ? $this->session : null;
    }

    public function request_token(): string {
        return $this->is_active() ? $this->request_token : '';
    }

    public function actor(): mixed {
        $session = $this->session();

        return $session ? get_userdata( $session->actor_id ) : false;
    }

    public function target(): mixed {
        $session = $this->session();

        return $session ? get_userdata( $session->target_id ) : false;
    }

    public function deactivate(): void {
        $this->active = false;
        $this->request_token = '';
        $this->session = null;
    }

    public static function request_token_from_globals(): string {
        $value = '';

        if ( isset( $_GET[ self::REQUEST_KEY ] ) && is_scalar( $_GET[ self::REQUEST_KEY ] ) ) {
            $value = (string) $_GET[ self::REQUEST_KEY ];
        } elseif ( isset( $_POST[ self::REQUEST_KEY ] ) && is_scalar( $_POST[ self::REQUEST_KEY ] ) ) {
            $value = (string) $_POST[ self::REQUEST_KEY ];
        } elseif ( isset( $_SERVER['HTTP_X_HWS_VIEW_AS'] ) && is_scalar( $_SERVER['HTTP_X_HWS_VIEW_AS'] ) ) {
            $value = (string) $_SERVER['HTTP_X_HWS_VIEW_AS'];
        }

        if ( function_exists( 'wp_unslash' ) ) {
            $value = (string) wp_unslash( $value );
        }

        return WordPressVirtualSessionStore::normalize_token( $value );
    }
}
