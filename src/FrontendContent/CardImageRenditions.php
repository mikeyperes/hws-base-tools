<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Responsive WordPress card photos, without changing a publication's layout.
 */
final class CardImageRenditions implements ModuleInterface {
    public const SIZE = 'hws-card';

    public function register(): void {
        if ( did_action( 'after_setup_theme' ) ) {
            self::register_size();
        } else {
            add_action( 'after_setup_theme', [ self::class, 'register_size' ], 20 );
        }
        add_filter( 'image_size_names_choose', [ self::class, 'size_label' ] );
        add_filter( 'elementor/widget/render_content', [ self::class, 'card_sizes' ], 100, 2 );
    }

    public static function register_size(): void {
        // Preserve the source ratio so this becomes a candidate in medium srcsets.
        // WordPress's image editor never enlarges a source smaller than 600px.
        add_image_size( self::SIZE, 600, 0, false );
    }

    public static function size_label( array $sizes ): array {
        $sizes[ self::SIZE ] = __( 'Card (600px)', 'hws-base-tools' );
        return $sizes;
    }

    /**
     * Work at the list widget boundary, including cached loop-item HTML.
     * Only card-sized WordPress images participate; heroes and avatars keep
     * their own rendition and markup.
     *
     * @param object $widget Elementor widget.
     */
    public static function card_sizes( string $html, $widget ): string {
        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' )
            || ! in_array( $widget->get_name(), [ 'loop-grid', 'loop-carousel', 'posts', 'archive-posts' ], true )
            || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
            return $html;
        }

        $settings = $widget->get_settings_for_display();
        $sizes = self::sizes( $settings, self::boxed_width( $widget ) );
        $sizes = (string) apply_filters( 'hws_base_tools_card_image_sizes', $sizes, $widget );
        $tags = new \WP_HTML_Tag_Processor( $html );
        while ( $tags->next_tag( 'IMG' ) ) {
            if ( ! $tags->has_class( 'attachment-medium' ) && ! $tags->has_class( 'attachment-' . self::SIZE ) ) {
                continue;
            }
            if ( ! $tags->get_attribute( 'srcset' ) && ! $tags->get_attribute( 'data-srcset' ) ) {
                continue;
            }
            // Optimizers can add native lazy loading after Elementor renders.
            // Eager images ignore auto and use the following responsive fallback.
            $value = 'auto, ' . $sizes;
            $tags->set_attribute( 'sizes', $value );
            if ( null !== $tags->get_attribute( 'data-sizes' ) ) {
                $tags->set_attribute( 'data-sizes', $value );
            }
        }
        return $tags->get_updated_html();
    }

    /**
     * Use the widget's real columns, gaps and active Elementor breakpoints.
     * The viewport is a conservative upper bound for the enclosing grid.
     * A boxed ancestor supplies a maximum; a full-width grid has no guessed cap.
     *
     * @param array<string,mixed> $settings
     */
    public static function sizes( array $settings, float $boxed_width = 0 ): string {
        $breakpoints = [];
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            foreach ( \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $device => $point ) {
                $breakpoints[] = [ 'device' => $device, 'width' => (int) $point->get_value(), 'direction' => $point->get_direction() ];
            }
        } else {
            $breakpoints = [
                [ 'device' => 'tablet', 'width' => 1024, 'direction' => 'max' ],
                [ 'device' => 'mobile', 'width' => 767, 'direction' => 'max' ],
            ];
        }
        $columns_key = isset( $settings['slides_to_show'] ) ? 'slides_to_show' : 'columns';
        $columns = max( 1, (int) ( $settings[ $columns_key ] ?? 3 ) );
        $gap = self::gap( $settings['column_gap'] ?? $settings['space_between'] ?? [], 0 );
        $desktop = self::slot( $columns, $gap, $boxed_width );
        $parts = [];
        $max_points = array_filter( $breakpoints, static fn( array $p ): bool => 'max' === $p['direction'] );
        usort( $max_points, static fn( array $a, array $b ): int => $b['width'] <=> $a['width'] );
        foreach ( $max_points as $point ) {
            $suffix = '_' . $point['device'];
            if ( '' !== (string) ( $settings[ $columns_key . $suffix ] ?? '' ) ) {
                $columns = max( 1, (int) $settings[ $columns_key . $suffix ] );
            }
            $gap = self::gap( $settings[ 'column_gap' . $suffix ] ?? $settings[ 'space_between' . $suffix ] ?? [], $gap );
            $parts[] = [ 'width' => $point['width'], 'value' => '(max-width: ' . $point['width'] . 'px) ' . self::slot( $columns, $gap, $boxed_width ) ];
        }
        usort( $parts, static fn( array $a, array $b ): int => $a['width'] <=> $b['width'] );
        // Elementor's optional widescreen breakpoint is a minimum-width rule.
        foreach ( $breakpoints as $point ) {
            if ( 'min' !== $point['direction'] ) {
                continue;
            }
            $suffix = '_' . $point['device'];
            $wide_columns = max( 1, (int) ( ( $settings[ $columns_key . $suffix ] ?? '' ) ?: ( $settings[ $columns_key ] ?? 3 ) ) );
            $wide_gap = self::gap( $settings[ 'column_gap' . $suffix ] ?? [], self::gap( $settings['column_gap'] ?? [], 0 ) );
            $parts[] = [ 'value' => '(min-width: ' . $point['width'] . 'px) ' . self::slot( $wide_columns, $wide_gap, $boxed_width ) ];
        }
        return implode( ', ', array_column( $parts, 'value' ) ) . ( $parts ? ', ' : '' ) . $desktop;
    }

    private static function gap( $value, float $fallback ): float {
        return is_array( $value ) && 'px' === ( $value['unit'] ?? 'px' ) && is_numeric( $value['size'] ?? null )
            ? max( 0, (float) $value['size'] ) : $fallback;
    }

    private static function slot( int $columns, float $gap, float $boxed_width ): string {
        $spacing = $gap * ( $columns - 1 );
        $viewport = 'calc((100vw - ' . $spacing . 'px) / ' . $columns . ')';
        return $boxed_width > $spacing
            ? 'min(' . $viewport . ', ' . round( ( $boxed_width - $spacing ) / $columns, 2 ) . 'px)'
            : $viewport;
    }

    /** @param object $widget */
    private static function boxed_width( $widget ): float {
        if ( ! method_exists( $widget, 'get_parent' ) ) {
            return 0;
        }
        for ( $parent = $widget->get_parent(); $parent; $parent = $parent->get_parent() ) {
            $settings = $parent->get_settings_for_display();
            if ( 'boxed' !== ( $settings['content_width'] ?? '' ) ) {
                continue;
            }
            $width = $settings['boxed_width'] ?? [];
            if ( ! is_numeric( $width['size'] ?? null ) && class_exists( '\\Elementor\\Plugin' ) ) {
                $width = \Elementor\Plugin::$instance->kits_manager->get_active_kit()->get_settings( 'container_width' );
            }
            if ( 'px' === ( $width['unit'] ?? 'px' ) && is_numeric( $width['size'] ?? null ) ) {
                return max( 0, (float) $width['size'] );
            }
        }
        return 0;
    }
}
