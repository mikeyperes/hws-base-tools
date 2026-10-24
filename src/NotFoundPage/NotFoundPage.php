<?php

declare( strict_types=1 );

namespace HWS\BaseTools\NotFoundPage;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * 404 page: a plain "Page not found" page between the site's own header and
 * footer, in the site's Elementor colours and fonts, with a real 404 status.
 * Off by default; while off the site's own 404 handling is untouched.
 */
final class NotFoundPage implements ModuleInterface {
    public const NONCE_ACTION = 'hws_base_tools_404_page';

    public function register(): void {
        AjaxActionRegistry::create( [ 'action_prefix' => 'hws_base_tools', 'nonce_action' => self::NONCE_ACTION, 'nonce_field' => 'nonce' ] )
            ->register( [ '404_page_save' => [ 'callback' => [ self::class, 'handle_save' ] ] ] );

        if ( ! NotFoundSettings::enabled() ) {
            return;
        }
        add_filter( 'template_include', [ $this, 'template' ], 999 );
    }

    public function template( string $template ): string {
        if ( ! is_404() ) {
            return $template;
        }
        status_header( 404 );
        nocache_headers();
        $settings = NotFoundSettings::all();
        self::enqueue_styles( $settings );

        return dirname( __DIR__, 2 ) . '/templates/404.php';
    }

    public static function enqueue_styles( array $settings ): void {
        $handle = 'hws-404-' . $settings['design'];
        wp_enqueue_style( $handle, plugins_url( 'assets/frontend/404-' . $settings['design'] . '.css', dirname( __DIR__, 2 ) . '/' . PluginMetadata::MAIN_FILE ), [], PluginMetadata::VERSION );
        wp_add_inline_style( $handle, '.hws-404{' . self::variables( $settings ) . '}' );
    }

    /** The page's markup for one design. */
    public static function markup( string $design ): string {
        $home = esc_url( home_url( '/' ) );
        $text = '<p class="text">The page you are looking for does not exist or has moved.</p>';
        $btn  = '<a class="btn" href="' . $home . '">Back to homepage</a>';
        $open = '<main class="hws-404 hws-404--' . esc_attr( $design ) . '" id="main"><div class="inner">';

        if ( 'divider' === $design ) {
            return $open . '<p class="code" aria-hidden="true">404</p><span class="rule" aria-hidden="true"></span><div class="msg"><h1>Page not found</h1>' . $text . $btn . '</div></div></main>';
        }
        if ( 'headline' === $design ) {
            return $open . '<h1><span class="code">404</span> Page not found</h1>' . $text . $btn . '</div></main>';
        }

        return $open . '<p class="code" aria-hidden="true">404</p><h1>Page not found</h1>' . $text . $btn . '</div></main>';
    }

    /** Brand values: saved colour, else the site's Elementor global colour or font. */
    public static function variables( array $settings ): string {
        $kit     = self::kit();
        $primary = $settings['primary_color'] ?: ( self::kit_color( $kit, 'primary' ) ?: '#111111' );
        $ink     = $settings['text_color'] ?: ( self::kit_color( $kit, 'text' ) ?: '#111111' );
        $bg      = $settings['background'] ?: '#ffffff';
        $heading = self::kit_font( $kit, 'primary' );
        $body    = self::kit_font( $kit, 'text' );
        $stack   = '-apple-system,"Segoe UI",Arial,sans-serif';

        return '--brand-primary:' . $primary . ';--brand-ink:' . $ink . ';--brand-bg:' . $bg
            . ';--brand-muted:color-mix(in srgb,' . $ink . ' 66%,' . $bg . ')'
            . ';--font-heading:' . ( $heading ? '"' . $heading . '",' : '' ) . $stack
            . ';--font-body:' . ( $body ? '"' . $body . '",' : '' ) . $stack;
    }

    /** @return array<string,mixed> */
    private static function kit(): array {
        $kit_id   = (int) get_option( 'elementor_active_kit', 0 );
        $settings = $kit_id > 0 ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : [];

        return is_array( $settings ) ? $settings : [];
    }

    private static function kit_color( array $kit, string $id ): string {
        foreach ( (array) ( $kit['system_colors'] ?? [] ) as $color ) {
            if ( is_array( $color ) && $id === ( $color['_id'] ?? '' ) ) {
                return strtolower( (string) ( sanitize_hex_color( (string) ( $color['color'] ?? '' ) ) ?? '' ) );
            }
        }

        return '';
    }

    private static function kit_font( array $kit, string $id ): string {
        foreach ( (array) ( $kit['system_typography'] ?? [] ) as $type ) {
            if ( is_array( $type ) && $id === ( $type['_id'] ?? '' ) ) {
                return (string) preg_replace( '/[^A-Za-z0-9 \-]/', '', (string) ( $type['typography_font_family'] ?? '' ) );
            }
        }

        return '';
    }

    /** Rank Math sends every missing page to the homepage when this is on, so this page never shows. */
    public static function rank_math_redirects_404(): bool {
        $modules = (array) get_option( 'rank_math_modules', [] );
        $general = (array) get_option( 'rank-math-options-general', [] );

        return in_array( 'redirections', $modules, true ) && in_array( $general['redirections_fallback'] ?? 'default', [ 'frontpage', 'custom' ], true );
    }

    /** Saves the 404 Page tab. Capability and nonce are checked by Hexa WP Core. */
    public static function handle_save( AjaxRequest $request ): array {
        $settings = NotFoundSettings::update(
            [
                'enabled'       => $request->bool( 'enabled', false, 'post' ),
                'design'        => $request->key( 'design', 'plinth', 'post' ),
                'primary_color' => $request->text( 'primary_color', '', 'post' ),
                'text_color'    => $request->text( 'text_color', '', 'post' ),
                'background'    => $request->text( 'background', '', 'post' ),
            ]
        );
        do_action( 'litespeed_purge_all' );

        return [ 'saved' => true, 'message' => '404 page settings saved.', 'enabled' => $settings['enabled'] ];
    }
}
