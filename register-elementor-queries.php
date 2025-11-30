<?php
namespace hws_base_tools;


function enable_elementor_queries()
{
    // Hook into Elementor's custom query (set your Loop Query ID to 'featured_team_members')
add_action(
    'elementor/query/featured_team_members',
    __NAMESPACE__ . '\\query_featured_team_members'
);
}
/**
 * Query Elementor for featured team members by ACF flag.
 *
 * Filters the 'team-member' CPT where ACF true/false field 'featured'
 * is selected (true). Assumes ACF stores true as '1'.
 *
 * Usage: In the Elementor Loop, set the Query ID to `featured_team_members`.
 *
 * @param \WP_Query $query
 */
function query_featured_team_members( $query ) {

    // Target team-member CPT
    $query->set( 'post_type', 'team-member' );

    // ACF true/false stores "true" as '1' by default
    $meta_query = array(
        array(
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',
        ),
    );

    $query->set( 'meta_query', $meta_query );

    // Only published team members
    $query->set( 'post_status', 'publish' );
}


