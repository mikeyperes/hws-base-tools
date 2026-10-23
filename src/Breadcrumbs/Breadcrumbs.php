<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Breadcrumbs;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs: a "Home > Section > Page" band under the site header.
 * Off by default. The shortcodes stay registered while it is off so a
 * template's shortcode prints nothing instead of its raw text; the band, its
 * stylesheet and its script are added only while it is on.
 */
final class Breadcrumbs implements ModuleInterface {
    public const SHORTCODE        = 'hws_breadcrumbs';
    public const LEGACY_SHORTCODE = 'smp_breadcrumbs';
    public const NONCE_ACTION     = 'hws_base_tools_breadcrumbs';

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcodes' ], 20 );
        AjaxActionRegistry::create( [ 'action_prefix' => 'hws_base_tools', 'nonce_action' => self::NONCE_ACTION, 'nonce_field' => 'nonce' ] )
            ->register( [ 'breadcrumbs_save' => [ 'callback' => [ self::class, 'handle_save' ] ] ] );

        if ( ! BreadcrumbSettings::enabled() || self::smp_still_owns_breadcrumbs() ) {
            return;
        }
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 30 );
        add_action( 'wp_footer', [ $this, 'print_template' ], 18 );
    }

    public function register_shortcodes(): void {
        add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
        if ( ! shortcode_exists( self::LEGACY_SHORTCODE ) ) {
            add_shortcode( self::LEGACY_SHORTCODE, [ $this, 'render_shortcode' ] );
        }
    }

    /** @param array<string,string>|string $atts */
    public function render_shortcode( $atts = [] ): string {
        $settings = BreadcrumbSettings::all();
        if ( empty( $settings['enabled'] ) || ! BreadcrumbTrail::should_render( $settings ) ) {
            return '';
        }
        $atts = shortcode_atts( [ 'style' => '' ], (array) $atts, self::SHORTCODE );
        self::enqueue_styles( $settings );

        return BreadcrumbTrail::markup( $settings, sanitize_key( (string) $atts['style'] ) );
    }

    public function enqueue(): void {
        $settings = BreadcrumbSettings::all();
        if ( ! BreadcrumbTrail::should_render( $settings ) ) {
            return;
        }
        self::enqueue_styles( $settings );
        wp_enqueue_script( 'hws-breadcrumbs', self::asset_url( 'breadcrumbs.js' ), [], PluginMetadata::VERSION, true );
    }

    public function print_template(): void {
        $settings = BreadcrumbSettings::all();
        if ( ! BreadcrumbTrail::should_render( $settings ) ) {
            return;
        }
        $markup = BreadcrumbTrail::markup( $settings );
        if ( '' !== $markup ) {
            echo '<template data-hws-breadcrumbs-template>' . $markup . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and kses-filtered by BreadcrumbTrail.
        }
    }

    /** The stylesheet plus this site's colours, size and checked custom CSS. */
    public static function enqueue_styles( array $settings ): void {
        if ( wp_style_is( 'hws-breadcrumbs', 'enqueued' ) ) {
            return;
        }
        wp_enqueue_style( 'hws-breadcrumbs', self::asset_url( 'breadcrumbs.css' ), [], PluginMetadata::VERSION );
        $inline = self::variables_css( $settings );
        $custom = self::validate_custom_css( (string) $settings['css_override'] );
        if ( ! empty( $custom['valid'] ) && '' !== $custom['css'] ) {
            $inline .= $custom['css'];
        }
        if ( '' !== $inline ) {
            wp_add_inline_style( 'hws-breadcrumbs', $inline );
        }
    }

    public static function variables_css( array $settings ): string {
        $vars = [ '--smpi-bc-font-size:' . (int) $settings['font_size'] . 'px' ];
        if ( '' !== $settings['accent_color'] ) {
            [ $r, $g, $b ] = sscanf( (string) $settings['accent_color'], '#%02x%02x%02x' );
            $vars[]        = '--smpi-bc-accent:' . $settings['accent_color'];
            $vars[]        = '--smpi-bc-tint:rgba(' . (int) $r . ',' . (int) $g . ',' . (int) $b . ',.07)';
        }
        if ( '' !== $settings['background_color'] ) {
            $vars[] = '--smpi-bc-background:' . $settings['background_color'];
        }
        if ( '' !== $settings['text_color'] ) {
            $vars[] = '--smpi-bc-ink:' . $settings['text_color'];
            $vars[] = '--smpi-bc-body:' . $settings['text_color'];
        }

        return '.smpi-breadcrumbs-band,.smpi-breadcrumbs{' . implode( ';', $vars ) . '}';
    }

    /** Custom CSS must stay inside the band and contain nothing unsafe. */
    public static function validate_custom_css( string $css ): array {
        $css = trim( str_replace( [ chr( 13 ), chr( 0 ) ], '', $css ) );
        if ( '' === $css ) {
            return [ 'valid' => true, 'css' => '', 'message' => '' ];
        }
        if ( strlen( $css ) > 20000 ) {
            return [ 'valid' => false, 'css' => '', 'message' => 'Breadcrumb CSS must be 20,000 characters or fewer.' ];
        }
        if ( preg_match( '~<|@(?:import|charset|namespace)\b|javascript\s*:|expression\s*\(|behavior\s*:|-moz-binding\s*:~i', $css ) ) {
            return [ 'valid' => false, 'css' => '', 'message' => 'The CSS contains a blocked directive or unsafe value.' ];
        }
        if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) {
            return [ 'valid' => false, 'css' => '', 'message' => 'The CSS has unbalanced braces.' ];
        }
        preg_match_all( '~(?:^|(?<=[{}]))\s*([^{}]+?)\s*\{~s', (string) preg_replace( '~/\*.*?\*/~s', '', $css ), $matches );
        foreach ( (array) ( $matches[1] ?? [] ) as $prelude ) {
            $prelude = trim( (string) $prelude );
            if ( '' === $prelude ) {
                continue;
            }
            if ( '@' === $prelude[0] ) {
                if ( ! preg_match( '~^@(media|supports|container|layer)\b~i', $prelude ) ) {
                    return [ 'valid' => false, 'css' => '', 'message' => 'Only media, supports, container and layer wrappers are allowed.' ];
                }
                continue;
            }
            foreach ( preg_split( '~,(?![^()]*\))~', $prelude ) ?: [] as $selector ) {
                if ( ! str_contains( trim( (string) $selector ), BreadcrumbTrail::CSS_SCOPE_MARKER ) ) {
                    return [ 'valid' => false, 'css' => '', 'message' => 'Every selector must include ' . BreadcrumbTrail::CSS_SCOPE_MARKER . '.' ];
                }
            }
        }

        return [ 'valid' => true, 'css' => $css, 'message' => '' ];
    }

    /** Saves the Breadcrumbs tab. Capability and nonce are checked by Hexa WP Core. */
    public static function handle_save( AjaxRequest $request ): array {
        $css = self::validate_custom_css( $request->raw( 'css_override', '', 'post' ) ?? '' );
        if ( empty( $css['valid'] ) ) {
            return [ 'saved' => false, 'message' => $css['message'] ];
        }
        $settings = BreadcrumbSettings::update(
            [
                'enabled'             => $request->bool( 'enabled', false, 'post' ),
                'style'               => $request->key( 'style', 'bc-b2', 'post' ),
                'accent_color'        => $request->text( 'accent_color', '', 'post' ),
                'background_color'    => $request->text( 'background_color', '', 'post' ),
                'text_color'          => $request->text( 'text_color', '', 'post' ),
                'font_size'           => $request->int( 'font_size', 13, 'post' ),
                'hide_home'           => $request->bool( 'hide_home', false, 'post' ),
                'hide_term_archives'  => $request->bool( 'hide_term_archives', false, 'post' ),
                'disabled_post_types' => $request->key_array( 'disabled_post_types', 'post' ),
                'disabled_ids'        => $request->text( 'disabled_ids', '', 'post' ),
                'css_override'        => $css['css'],
            ]
        );
        do_action( 'litespeed_purge_all' );

        return [ 'saved' => true, 'message' => 'Breadcrumb settings saved.', 'enabled' => $settings['enabled'] ];
    }

    /**
     * True on a site still running a version of SMP Publication Integration
     * that draws its own breadcrumbs with them switched on, so the band is
     * never shown twice while the two plugins update.
     */
    private static function smp_still_owns_breadcrumbs(): bool {
        if ( ! class_exists( '\smp_publication_integration\Content\Breadcrumbs' ) ) {
            return false;
        }
        $legacy = get_option( 'smpi_settings', [] );

        return ! is_array( $legacy ) || ! array_key_exists( 'breadcrumbs_enabled', $legacy ) || ! empty( $legacy['breadcrumbs_enabled'] );
    }

    private static function asset_url( string $file ): string {
        return plugins_url( 'assets/frontend/' . $file, dirname( __DIR__, 2 ) . '/' . PluginMetadata::MAIN_FILE );
    }
}
