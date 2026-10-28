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
        if ( ! is_object( $widget ) || 'loop-grid' !== $widget->get_name() ) {
            return;
        }
        $classes = (string) $widget->get_settings( '_css_classes' );
        if ( false === strpos( ' ' . $classes . ' ', ' ' . self::CSS_CLASS . ' ' ) ) {
            return;
        }
        // The page number is worked out here, from the raw settings, the way the grid does: asking the widget for it
        // would build and keep its display settings before the alternate templates are cleared.
        $page = 1;
        if ( '' !== (string) $widget->get_settings( 'pagination_type' ) ) {
            $get  = isset( $_GET[ 'e-page-' . $widget->get_id() ] ) ? absint( wp_unslash( $_GET[ 'e-page-' . $widget->get_id() ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), $get );
        }
        if ( $page > 1 ) {
            $widget->set_settings( 'alternate_templates', [] );
            // Elementor keeps the settings it already worked out for display; its own reset drops that copy so the change is used.
            if ( method_exists( $widget, 'reset_render_state' ) ) {
                $widget->reset_render_state();
            }
        }
    }
}
