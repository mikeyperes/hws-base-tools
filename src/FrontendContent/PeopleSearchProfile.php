<?php
namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\Search\SearchBox;
use Hexa\PluginCore\Search\SearchRegistry;

/** Public directories supplied by their owning plugin; never a directory of all accounts. */
final class PeopleSearchProfile {
    public static function render( array $directory ): string {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $directory['ids'] ?? [] ) ) ) ) );
        if ( ! $ids || ! is_callable( $directory['render_item'] ?? null ) ) {
            return '';
        }
        $roles = [];
        foreach ( get_users( [ 'include' => $ids ] ) as $user ) {
            $roles = array_merge( $roles, $user->roles );
        }
        $roles = array_values( array_unique( $roles ) );
        if ( ! $roles ) {
            return '';
        }
        $profile = 'people-' . substr( md5( wp_json_encode( [ $ids, $directory['key'] ?? 'people' ] ) ), 0, 12 );
        $groups = (array) ( $directory['groups'] ?? [] );
        $filters = [ 'group' => [ 'type' => 'callback', 'control' => 'checkboxes', 'label' => 'People',
            'options' => [ 'byline' => 'Byline writers', 'contributor' => 'Contributors', 'staff' => 'Staff' ],
            'apply' => static fn( $value ): array => array_values( array_intersect( $ids, (array) ( $groups[ $value ] ?? [] ) ) ),
        ] ];
        SearchRegistry::register( $profile, [
            'source' => 'users', 'roles' => $roles, 'fields' => [ 'display_name', 'nicename' ],
            'render_item' => $directory['render_item'], 'filters' => $filters,
            'per_page' => max( 1, min( 50, (int) ( $directory['per_page'] ?? 10 ) ) ),
            'load_mode' => $directory['pagination'] ?? 'load_more',
            'sorts' => [ 'directory' => [ 'type' => 'field', 'field' => 'name', 'label' => 'Directory order',
            ] ],
            'labels' => [ 'search' => ( $directory['search_label'] ?? '' ) ?: 'Search', 'placeholder' => ( $directory['placeholder'] ?? '' ) ?: 'Search people', 'results_one' => '%d person', 'results_many' => '%d people' ],
            'class' => 'hws-people-directory',
        ] );
        // Core restricts by role. Intersect every query (including totals) with the exact public list too.
        $restrict = static function ( $query ) use ( $ids, $roles ): void {
            if ( $query->get( 'role__in' ) === $roles ) {
                $existing = (array) $query->get( 'include' );
                $query->set( 'include', $existing ? array_values( array_intersect( $ids, $existing ) ) ?: [ 0 ] : $ids );
                $query->set( 'orderby', 'include' );
            }
        };
        add_action( 'pre_get_users', $restrict );
        try {
            $html = SearchBox::render( $profile, [ 'style' => ( $directory['search_style'] ?? '' ) ?: SearchBoxFeature::style(), 'filter_layout' => $directory['filter_layout'] ?? 'dropdown' ] );
        } finally {
            remove_action( 'pre_get_users', $restrict );
        }
        // Default threshold preserves small-list behavior; zero shows the form for any nonempty list.
        $threshold = isset( $directory['search_threshold'] ) && is_numeric( $directory['search_threshold'] )
            ? max( 0, (int) $directory['search_threshold'] ) : 10;
        if ( ( count( $ids ) <= $threshold && 'always' !== ( $directory['search'] ?? true ) ) || false === ( $directory['search'] ?? true ) ) {
            $html = str_replace( '<form class="hls ', '<form hidden class="hls ', $html );
        }
        return $html;
    }
}
