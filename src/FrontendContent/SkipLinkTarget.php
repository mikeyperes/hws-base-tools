<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the Hello Elementor "Skip to content" link a real place to land.
 *
 * Hello points the link at #content, which only its own page layout has. Pages built with an Elementor Pro header
 * never print it, so keyboard users land nowhere. When an Elementor header is shown, this prints an empty anchor
 * right after it and points the link there instead. Nothing visible changes.
 */
final class SkipLinkTarget implements ModuleInterface {
    public const ANCHOR = 'hws-main-content';

    public function register(): void {
        add_filter( 'hello_elementor_skip_link_url', [ self::class, 'skip_link_url' ] );
        add_action( 'elementor/theme/after_do_header', [ self::class, 'print_anchor' ] );
    }

    private static function elementor_header(): bool {
        return function_exists( 'elementor_location_exits' ) && elementor_location_exits( 'header', true );
    }

    public static function skip_link_url( $url ) {
        return self::elementor_header() ? '#' . self::ANCHOR : $url;
    }

    public static function print_anchor(): void {
        echo '<span id="' . esc_attr( self::ANCHOR ) . '" tabindex="-1"></span>' . "\n";
    }
}
