<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Smooth (antialiased) font rendering on every front-end page, on by default.
 *
 * Without it macOS browsers draw text with subpixel edges, which makes light text on dark backgrounds look bolder
 * than the design. Elementor has no setting for it, so it is printed once in the head of every page.
 */
final class FontSmoothing implements ModuleInterface {
    public const OPTION = 'hws_font_smoothing';

    public function register(): void {
        add_action( 'wp_head', [ self::class, 'print_styles' ], 1 );
    }

    public static function enabled(): bool {
        return '0' !== (string) get_option( self::OPTION, '1' );
    }

    public static function print_styles(): void {
        if ( is_admin() || ! self::enabled() ) {
            return;
        }
        echo "<style id=\"hws-font-smoothing\">body{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}</style>\n";
    }
}
