<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

defined( 'ABSPATH' ) || exit;

final class ViewAsController {
    private const START_ACTION = 'hws_view_as_start';
    private const END_ACTION = 'hws_view_as_end';

    public function __construct(
        private readonly VirtualSessionStoreInterface $store,
        private readonly VirtualRequestContext $context,
        private readonly ImpersonationAccessPolicy $policy
    ) {
    }

    public function register(): void {
        add_filter( 'user_row_actions', [ $this, 'add_user_row_action' ], 20, 2 );
        add_action( 'edit_user_profile', [ $this, 'render_profile_action' ], 1 );
        add_action( 'show_user_profile', [ $this, 'render_profile_action' ], 1 );
        add_action( 'admin_post_' . self::START_ACTION, [ $this, 'start' ] );
        add_action( 'admin_post_' . self::END_ACTION, [ $this, 'end' ] );
        add_filter( 'logout_url', [ $this, 'filter_logout_url' ], PHP_INT_MAX, 2 );
        add_action( 'login_init', [ $this, 'intercept_virtual_logout' ], -PHP_INT_MAX );
    }

    /** @param array<string,string> $actions @return array<string,string> */
    public function add_user_row_action( array $actions, mixed $user ): array {
        $actor_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        $target_id = isset( $user->ID ) ? (int) $user->ID : 0;

        if ( $this->context->is_active() || ! $this->policy->can_start( $actor_id, $target_id ) ) {
            return $actions;
        }

        $actions['hws_view_as'] = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s</a>',
            esc_url( $this->start_url( $target_id ) ),
            esc_attr( sprintf( 'Open an isolated session as %s', (string) ( $user->display_name ?? $user->user_login ?? 'this user' ) ) ),
            esc_html__( 'View as', 'hws-base-tools' )
        );

        return $actions;
    }

    public function render_profile_action( mixed $user ): void {
        $actor_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        $target_id = isset( $user->ID ) ? (int) $user->ID : 0;

        if ( $this->context->is_active() || ! $this->policy->can_start( $actor_id, $target_id ) ) {
            return;
        }

        ?>
        <h2><?php echo esc_html__( 'View As User', 'hws-base-tools' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php echo esc_html__( 'Isolated session', 'hws-base-tools' ); ?></th>
                <td>
                    <a class="button" href="<?php echo esc_url( $this->start_url( $target_id ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html( sprintf( 'View as %s', (string) ( $user->display_name ?? $user->user_login ?? 'user' ) ) ); ?>
                    </a>
                    <p class="description"><?php echo esc_html__( 'Opens a separate virtual session without replacing your administrator login.', 'hws-base-tools' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function start(): void {
        $actor_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        $target_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;

        if ( ! $this->policy->can_start( $actor_id, $target_id ) ) {
            wp_die(
                esc_html__( 'Only administrators can start a View As session for an existing user.', 'hws-base-tools' ),
                '',
                [ 'response' => 403 ]
            );
        }

        check_admin_referer( self::START_ACTION . '_' . $target_id );

        try {
            $request_token = $this->store->create( $actor_id, $target_id, (string) wp_get_session_token() );
        } catch ( \Throwable $exception ) {
            wp_die( esc_html( $exception->getMessage() ), '', [ 'response' => 500 ] );
        }

        $destination = add_query_arg( VirtualRequestContext::REQUEST_KEY, rawurlencode( $request_token ), admin_url() );
        wp_safe_redirect( $destination );
        exit;
    }

    public function end(): void {
        $session = $this->context->session();
        $request_token = $this->context->request_token();

        if ( ! $session || '' === $request_token || ! $this->policy->administrator_can_control( $session->actor_id ) ) {
            wp_die( esc_html__( 'This View As session is no longer valid.', 'hws-base-tools' ), '', [ 'response' => 403 ] );
        }

        check_admin_referer( self::END_ACTION . '_' . $session->id );
        $this->store->revoke( $request_token );
        $this->context->deactivate();
        wp_safe_redirect( admin_url( 'users.php' ) );
        exit;
    }

    public function end_url(): string {
        $session = $this->context->session();
        $request_token = $this->context->request_token();
        if ( ! $session || '' === $request_token ) {
            return '';
        }

        $url = add_query_arg(
            [
                'action' => self::END_ACTION,
                VirtualRequestContext::REQUEST_KEY => $request_token,
            ],
            admin_url( 'admin-post.php' )
        );

        return add_query_arg( '_wpnonce', wp_create_nonce( self::END_ACTION . '_' . $session->id ), $url );
    }

    public function filter_logout_url( string $logout_url, string $redirect = '' ): string {
        $end_url = $this->end_url();

        return '' !== $end_url ? $end_url : $logout_url;
    }

    public function intercept_virtual_logout(): void {
        $action = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] )
            ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) )
            : '';

        if ( 'logout' !== $action || ! $this->context->is_active() ) {
            return;
        }

        $request_token = $this->context->request_token();
        if ( '' !== $request_token ) {
            $this->store->revoke( $request_token );
        }
        $this->context->deactivate();
        wp_safe_redirect( admin_url( 'users.php' ) );
        exit;
    }

    private function start_url( int $target_id ): string {
        $url = add_query_arg(
            [
                'action'  => self::START_ACTION,
                'user_id' => $target_id,
            ],
            admin_url( 'admin-post.php' )
        );

        return add_query_arg( '_wpnonce', wp_create_nonce( self::START_ACTION . '_' . $target_id ), $url );
    }
}
