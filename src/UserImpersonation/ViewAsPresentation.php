<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class ViewAsPresentation {
    private bool $rendered = false;

    public function __construct(
        private readonly VirtualRequestContext $context,
        private readonly ViewAsController $controller
    ) {
    }

    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'in_admin_header', [ $this, 'render' ], -PHP_INT_MAX );
        add_action( 'wp_body_open', [ $this, 'render' ], -PHP_INT_MAX );
        add_action( 'wp_footer', [ $this, 'render' ], -PHP_INT_MAX );
        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        add_filter( 'body_class', [ $this, 'frontend_body_classes' ] );
    }

    public function enqueue_assets(): void {
        if ( ! $this->context->is_active() ) {
            return;
        }

        $base_url = plugin_dir_url( PluginMetadata::plugin_file() );
        wp_enqueue_style(
            'hws-view-as-user',
            $base_url . 'assets/user-impersonation/view-as-user.css',
            [],
            PluginMetadata::VERSION
        );
        wp_enqueue_script(
            'hws-view-as-user',
            $base_url . 'assets/user-impersonation/view-as-user.js',
            [],
            PluginMetadata::VERSION,
            true
        );
    }

    public function render(): void {
        if ( $this->rendered || ! $this->context->is_active() ) {
            return;
        }

        $actor = $this->context->actor();
        $target = $this->context->target();
        $end_url = $this->controller->end_url();
        if ( ! $actor || ! $target || '' === $end_url ) {
            return;
        }

        $this->rendered = true;
        $actor_name = (string) ( $actor->display_name ?? $actor->user_login ?? 'Administrator' );
        $target_name = (string) ( $target->display_name ?? $target->user_login ?? 'User' );
        ?>
        <script>document.documentElement.classList.add('hws-view-as-root');if(document.body&&document.body.classList.contains('admin-bar')){document.documentElement.classList.add('hws-view-as-with-admin-bar');}</script>
        <div
            class="hws-view-as-banner"
            role="status"
            aria-live="polite"
            data-hws-view-as-banner
            data-request-key="<?php echo esc_attr( VirtualRequestContext::REQUEST_KEY ); ?>"
            data-request-token="<?php echo esc_attr( $this->context->request_token() ); ?>"
        >
            <div class="hws-view-as-banner__identity">
                <strong><?php echo esc_html( sprintf( 'Viewing as %s', $target_name ) ); ?></strong>
                <span><?php echo esc_html( sprintf( 'Administrator: %s', $actor_name ) ); ?></span>
            </div>
            <a class="hws-view-as-banner__end" href="<?php echo esc_url( $end_url ); ?>">
                <?php echo esc_html__( 'End View As', 'hws-base-tools' ); ?>
            </a>
        </div>
        <?php
    }

    public function admin_body_class( string $classes ): string {
        return $this->context->is_active() ? trim( $classes . ' hws-view-as-active' ) : $classes;
    }

    /** @param array<int,string> $classes @return array<int,string> */
    public function frontend_body_classes( array $classes ): array {
        if ( $this->context->is_active() ) {
            $classes[] = 'hws-view-as-active';
        }

        return $classes;
    }
}
