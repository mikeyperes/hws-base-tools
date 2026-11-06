<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/** A shared button for automatic output and Elementor footers. */
final class ScrollToTop implements ModuleInterface {
    public const FEATURE_OPTION = 'hws_base_tools_scroll_to_top_enabled';
    public const SETTINGS_OPTION = 'hws_base_tools_scroll_to_top_settings';
    private static bool $rendered = false;

    public function register(): void {
        add_shortcode( 'hws_scroll_to_top', [ self::class, 'shortcode' ] );
        // Theme Builder footers render before the late automatic fallback.
        add_action( 'wp_footer', [ self::class, 'automatic' ], PHP_INT_MAX );
    }

    public static function enabled(): bool {
        return filter_var( get_option( self::FEATURE_OPTION, true ), FILTER_VALIDATE_BOOLEAN );
    }

    public static function defaults(): array {
        return [
            'position' => 'bottom_right', 'offset' => 24, 'size' => 48,
            'background' => '', 'icon_color' => '', 'hover_background' => '', 'hover_icon_color' => '',
            'show_after' => 300, 'hide_mobile' => false, 'style' => 'default',
        ];
    }

    public static function normalize( array $input ): array {
        $settings = array_intersect_key( $input, self::defaults() ) + self::defaults();
        $settings['position'] = 'bottom_left' === $settings['position'] ? 'bottom_left' : 'bottom_right';
        foreach ( [ 'offset' => [ 0, 300 ], 'size' => [ 44, 120 ], 'show_after' => [ 0, 20000 ] ] as $key => $range ) {
            $settings[ $key ] = max( $range[0], min( $range[1], (int) $settings[ $key ] ) );
        }
        foreach ( [ 'background', 'icon_color', 'hover_background', 'hover_icon_color' ] as $key ) {
            $settings[ $key ] = sanitize_hex_color( (string) $settings[ $key ] ) ?: '';
        }
        $settings['hide_mobile'] = filter_var( $settings['hide_mobile'], FILTER_VALIDATE_BOOLEAN );
        $settings['style'] = 'none' === $settings['style'] ? 'none' : 'default';
        return $settings;
    }

    public static function settings(): array {
        $saved = get_option( self::SETTINGS_OPTION, [] );
        return self::normalize( is_array( $saved ) ? $saved : [] );
    }

    public static function save_settings( array $input ): array {
        $settings = self::normalize( $input );
        update_option( self::SETTINGS_OPTION, $settings, false );
        return $settings;
    }

    public static function definition(): array {
        return [
            'id' => self::FEATURE_OPTION, 'name' => 'Scroll to top', 'default' => true,
            'description' => 'A round fixed button returns visitors to the top after scrolling. On by default.',
            'info' => 'Use [hws_scroll_to_top] in an Elementor footer without duplicating automatic output. Blank colors inherit Elementor globals.',
            'function' => '', 'scope_admin_only' => false,
            'code_example' => '[hws_scroll_to_top position="bottom_left" offset="24" size="48" show_after="300" hide_mobile="1"]',
        ];
    }

    public static function markup( array $settings, string $class = '' ): string {
        $variables = '--hws-stt-offset:' . $settings['offset'] . 'px;--hws-stt-size:' . $settings['size'] . 'px;';
        foreach ( [ 'background' => 'bg', 'icon_color' => 'icon', 'hover_background' => 'hover-bg', 'hover_icon_color' => 'hover-icon' ] as $key => $variable ) {
            if ( '' !== $settings[ $key ] ) {
                $variables .= '--hws-stt-' . $variable . ':' . $settings[ $key ] . ';';
            }
        }
        $classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $class ) ?: [] ) );
        return '<button type="button" class="hws-scroll-to-top hws-scroll-to-top--' . esc_attr( $settings['position'] ) . ' hws-scroll-to-top--' . esc_attr( $settings['style'] ) . ( $settings['hide_mobile'] ? ' hws-scroll-to-top--hide-mobile' : '' ) . ( $classes ? ' ' . esc_attr( implode( ' ', $classes ) ) : '' ) . '" data-hws-scroll-to-top data-show-after="' . $settings['show_after'] . '" aria-label="Back to top" hidden style="' . esc_attr( $variables ) . '"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m6 14 6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
    }

    public static function shortcode( $attributes = [] ): string {
        if ( is_admin() || ! self::enabled() || self::$rendered ) {
            return '';
        }
        $attributes = shortcode_atts( self::settings() + [ 'class' => '' ], (array) $attributes, 'hws_scroll_to_top' );
        self::$rendered = true;
        return '<style id="hws-scroll-to-top-css">' . self::css() . '</style>'
            . self::markup( self::normalize( $attributes ), (string) $attributes['class'] )
            . '<script id="hws-scroll-to-top-js" data-no-optimize="1" data-cfasync="false">' . file_get_contents( __DIR__ . '/../../assets/js/scroll-to-top.js' ) . '</script>';
    }

    public static function automatic(): void {
        echo self::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function css(): string {
        return (string) file_get_contents( __DIR__ . '/../../assets/css/scroll-to-top.css' );
    }

    public static function render_settings(): void {
        $settings = self::settings();
        echo '<div data-feature-settings="' . esc_attr( self::FEATURE_OPTION ) . '" style="display:grid;gap:14px">';
        foreach ( [ 'position' => 'Position', 'offset' => 'Edge offset (px)', 'size' => 'Button size (px)', 'background' => 'Background color', 'icon_color' => 'Icon color', 'hover_background' => 'Hover background color', 'hover_icon_color' => 'Hover icon color', 'show_after' => 'Show after distance (px)', 'hide_mobile' => 'Hide on mobile (767px and below)', 'style' => 'Styling' ] as $key => $label ) {
            echo '<div><label><strong style="display:block">' . esc_html( $label ) . '</strong>';
            $field = ' data-hws-scroll-field="' . esc_attr( $key ) . '"';
            if ( in_array( $key, [ 'position', 'style' ], true ) ) {
                echo '<select' . $field . '>';
                $choices = 'position' === $key ? [ 'bottom_right' => 'Bottom right', 'bottom_left' => 'Bottom left' ] : [ 'default' => 'Round button', 'none' => 'No plugin styling' ];
                foreach ( $choices as $value => $text ) {
                    echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $text ) . '</option>';
                }
                echo '</select>';
            } elseif ( 'hide_mobile' === $key ) {
                echo '<input type="checkbox"' . $field . checked( $settings[ $key ], true, false ) . '>';
            } elseif ( in_array( $key, [ 'offset', 'size', 'show_after' ], true ) ) {
                $ranges = [ 'offset' => [ 0, 300 ], 'size' => [ 44, 120 ], 'show_after' => [ 0, 20000 ] ];
                echo '<input type="number" min="' . $ranges[ $key ][0] . '" max="' . $ranges[ $key ][1] . '"' . $field . ' value="' . esc_attr( (string) $settings[ $key ] ) . '">';
            } else {
                echo \Hexa\PluginCore\WpAdminComponents\ColorControl::render( [
                    'key' => 'hws_stt_' . $key, 'label' => $label, 'value' => $settings[ $key ],
                    'default' => '', 'allow_inherit' => true,
                ] );
            }
            echo '</label></div>';
        }
        echo '<p>Blank colors use Elementor primary, secondary and background globals, with built-in fallbacks.</p>';
        echo '<style>' . self::css() . '.hws-stt-preview .hws-scroll-to-top{position:relative!important;inset:auto!important;display:grid!important}</style><div class="hws-stt-preview">';
        echo self::markup( $settings );
        echo '</div><button type="button" class="button button-primary hws-feature-save-settings" data-feature-id="' . esc_attr( self::FEATURE_OPTION ) . '">Save Settings</button><span class="hws-feature-setting-status" aria-live="polite"></span></div>';
    }
}
