<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Photos on the first screen must not lazy-load. WordPress adds loading="lazy" to every image it draws, including the
 * ones an Elementor widget or a shortcode inside it prints. Adding the CSS class hws-eager to that widget (Advanced,
 * CSS Classes) makes its images load at once: loading="eager", and fetchpriority="high" on the first of them.
 */
final class FirstScreenImages implements ModuleInterface {
    public const CSS_CLASS = 'hws-eager';

    public function register(): void {
        add_filter( 'elementor/widget/render_content', [ self::class, 'render_content' ], 20, 2 );
    }

    /**
     * @param string                $content Widget HTML.
     * @param \Elementor\Widget_Base $widget  The widget.
     */
    public static function render_content( $content, $widget ): string {
        $content = (string) $content;
        if ( ! is_object( $widget ) || false === strpos( $content, '<img' ) ) {
            return $content;
        }
        $classes = (string) $widget->get_settings( '_css_classes' );
        if ( false === strpos( ' ' . $classes . ' ', ' ' . self::CSS_CLASS . ' ' ) ) {
            return $content;
        }
        $first = true;
        return (string) preg_replace_callback( '/<img\b[^>]*>/i', static function ( array $m ) use ( &$first ): string {
            $tag = (string) preg_replace( '/\sloading=("|\')lazy\1/i', ' loading="eager"', $m[0] );
            if ( $first && false === stripos( $tag, 'fetchpriority=' ) ) {
                $tag = (string) preg_replace( '/<img\b/i', '<img fetchpriority="high"', $tag, 1 );
            }
            $first = false;
            return $tag;
        }, $content );
    }
}
