<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Responsive WordPress card photos, without changing a publication's layout.
 */
final class CardImageRenditions implements ModuleInterface {
    public const SIZE = 'hws-card';
    /** A loop card never needs a copy wider than medium_large, even on a sharp screen. */
    public const MAX_CANDIDATE = 768;

    /** @var float[] Boxed width of each Elementor container being rendered, outermost first (0 when not boxed). */
    private static array $containers = [];

    public function register(): void {
        if ( did_action( 'after_setup_theme' ) ) {
            self::register_size();
        } else {
            add_action( 'after_setup_theme', [ self::class, 'register_size' ], 20 );
        }
        add_filter( 'image_size_names_choose', [ self::class, 'size_label' ] );
        add_filter( 'elementor/widget/render_content', [ self::class, 'card_sizes' ], 100, 2 );
        // Elementor widgets carry no parent link, so the enclosing boxed width is tracked while containers render.
        add_action( 'elementor/frontend/container/before_render', [ self::class, 'enter_container' ] );
        add_action( 'elementor/frontend/container/after_render', [ self::class, 'leave_container' ] );
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
            // "auto" is only valid on a natively lazy image. A browser that sees it on any other image (one an
            // optimizer lazy-loads by script, or an eager one) can pick the largest file, so it is added only
            // when the image already carries loading="lazy".
            $value = 'lazy' === strtolower( (string) $tags->get_attribute( 'loading' ) ) ? 'auto, ' . $sizes : $sizes;
            foreach ( [ 'srcset', 'data-srcset' ] as $attr ) {
                $set = $tags->get_attribute( $attr );
                if ( is_string( $set ) && '' !== $set ) {
                    $tags->set_attribute( $attr, self::cap_srcset( $set ) );
                }
            }
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

    /** @param object $element Elementor container. */
    public static function enter_container( $element ): void {
        $width = 0.0;
        $settings = is_object( $element ) && method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display() : [];
        if ( 'boxed' === ( $settings['content_width'] ?? '' ) ) {
            $size = $settings['boxed_width'] ?? [];
            if ( ! is_numeric( $size['size'] ?? null ) && class_exists( '\\Elementor\\Plugin' ) ) {
                $size = \Elementor\Plugin::$instance->kits_manager->get_active_kit()->get_settings( 'container_width' );
            }
            if ( 'px' === ( $size['unit'] ?? 'px' ) && is_numeric( $size['size'] ?? null ) ) {
                $width = max( 0, (float) $size['size'] );
            }
        }
        self::$containers[] = $width;
    }

    public static function leave_container(): void {
        array_pop( self::$containers );
    }

    /**
     * The nearest enclosing boxed container's width, or 0 for a full-width grid.
     *
     * @param object $widget
     */
    private static function boxed_width( $widget ): float {
        foreach ( array_reverse( self::$containers ) as $width ) {
            if ( $width > 0 ) {
                return $width;
            }
        }
        return 0;
    }

    /** Drop candidates wider than MAX_CANDIDATE, keeping the smallest when every one is wider. */
    public static function cap_srcset( string $srcset ): string {
        $keep = [];
        $smallest = null;
        foreach ( array_filter( array_map( 'trim', explode( ',', $srcset ) ) ) as $candidate ) {
            $width = preg_match( '/\s(\d+)w$/', $candidate, $m ) ? (int) $m[1] : 0;
            if ( null === $smallest || ( $width && $width < $smallest[1] ) ) {
                $smallest = [ $candidate, $width ];
            }
            if ( ! $width || $width <= self::MAX_CANDIDATE ) {
                $keep[] = $candidate;
            }
        }
        return implode( ', ', $keep ?: ( $smallest ? [ $smallest[0] ] : [] ) );
    }
}
