<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A Loop Grid with an alternate template for its first story (the lead story) counts the first item of every page it
 * loads, so "Load more" and infinite scroll add a second lead on the next batch. Adding the CSS class
 * hws-lead-first-page to such a Loop Grid keeps the lead on the first batch only: from the second batch on the
 * grid is drawn without its alternate templates.
 */
final class LoopLeadFirstPage implements ModuleInterface {
    public const CSS_CLASS = 'hws-lead-first-page';

    public function register(): void {
        add_action( 'elementor/frontend/widget/before_render', [ self::class, 'before_render' ], 1 );
    }

    /** @param \Elementor\Widget_Base $widget */
    public static function before_render( $widget ): void {
        if ( ! is_object( $widget ) || 'loop-grid' !== $widget->get_name() || ! method_exists( $widget, 'get_current_page' ) ) {
            return;
        }
        $classes = (string) $widget->get_settings( '_css_classes' );
        if ( false === strpos( ' ' . $classes . ' ', ' ' . self::CSS_CLASS . ' ' ) ) {
            return;
        }
        if ( (int) $widget->get_current_page() > 1 ) {
            $widget->set_settings( 'alternate_templates', [] );
        }
    }
}
