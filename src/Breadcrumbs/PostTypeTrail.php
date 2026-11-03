<?php

declare( strict_types=1 );
namespace HWS\BaseTools\Breadcrumbs;
defined( 'ABSPATH' ) || exit;

/** Settings-owned Rank Math trail shared by visible HTML and BreadcrumbList. */
final class PostTypeTrail {
    public static function filter( array $crumbs, $breadcrumbs = null ): array {
        $s = BreadcrumbSettings::all();
        if ( empty( $s['enabled'] ) || ! is_singular() ) { return $crumbs; }
        $id = (int) get_queried_object_id();
        $rule = $s['trail_rules'][ get_post_type( $id ) ] ?? null;
        if ( ! $id || ! is_array( $rule ) ) { return $crumbs; }
        $items = [ $crumbs[0] ?? [ __( 'Home', 'hws-base-tools' ), home_url( '/' ) ] ];
        if ( '' !== $rule['section_label'] && '' !== $rule['section_url'] ) {
            $items[] = [ $rule['section_label'], $rule['section_url'] ];
        }
        $taxonomy = $rule['taxonomy'];
        if ( taxonomy_exists( $taxonomy ) ) {
            $terms = get_the_terms( $id, $taxonomy );
            if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
                usort( $terms, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
                $default = ! empty( $rule['exclude_default_term'] ) ? (int) get_option( 'category' === $taxonomy ? 'default_category' : 'default_term_' . $taxonomy ) : 0;
                foreach ( $terms as $term ) {
                    if ( $default === (int) $term->term_id || in_array( $term->slug, $rule['excluded_term_slugs'], true ) ) { continue; }
                    $url = get_term_link( $term );
                    if ( ! is_wp_error( $url ) ) { $items[] = [ $term->name, $url ]; }
                    break;
                }
            }
        }
        $items[] = $crumbs ? $crumbs[array_key_last( $crumbs )] : [ get_the_title( $id ), '' ];
        return $items;
    }
}
