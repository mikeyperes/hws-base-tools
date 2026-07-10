<?php namespace hws_base_tools;

/**
 * Bootstrap all custom Elementor queries.
 *
 * This is hooked into `init` so the Elementor query IDs are
 * registered early and available inside the editor and frontend.
 *
 * Query IDs exposed:
 *  - featured_team_members   → query_featured_team_members()
 *  - featured_testimonials   → query_featured_testimonials()
 *  - query_featured_posts    → query_featured_posts()
 *
 * Usage in Elementor:
 *  - In a Loop/Grid widget → Query → Advanced → Query ID
 *    Set the ID to one of the above strings.
 *
 * @return void
 */
function enable_elementor_queries() {

    // Featured team members (CPT: team-member)
    add_action(
        'elementor/query/featured_team_members',
        __NAMESPACE__ . '\\query_featured_team_members'
    );

    // Featured testimonials (CPT: testimonial)
    add_action(
        'elementor/query/featured_testimonials',
        __NAMESPACE__ . '\\query_featured_testimonials'
    );

    // Featured posts (regular posts, `post` type)
    add_action(
        'elementor/query/query_featured_posts',
        __NAMESPACE__ . '\\query_featured_posts'
    );
}

/**
 * Query Elementor for featured team members by ACF flag.
 *
 * Filters the 'team-member' CPT where ACF true/false field 'featured'
 * is selected (true). Assumes ACF stores true as '1'.
 *
 * Usage in Elementor:
 *  - Set Loop → Query → Query ID to: featured_team_members
 *
 * @param \WP_Query $query The WP_Query object provided by Elementor.
 *
 * @return void
 */
function query_featured_team_members( $query ) {

    // Target team-member CPT.
    $query->set( 'post_type', 'team-member' );

    // ACF true/false stores "true" as '1' by default.
    $meta_query = array(
        array(
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',
        ),
    );

    // Apply the "featured" flag constraint.
    $query->set( 'meta_query', $meta_query );

    // Only published team members are returned.
    $query->set( 'post_status', 'publish' );
}

/**
 * Query Elementor for featured testimonials by ACF flag.
 *
 * Filters the 'testimonial' CPT where ACF true/false field 'featured'
 * is selected (true). Assumes ACF stores true as '1'.
 *
 * Usage in Elementor:
 *  - Set Loop → Query → Query ID to: featured_testimonials
 *
 * @param \WP_Query $query The WP_Query object provided by Elementor.
 *
 * @return void
 */
function query_featured_testimonials( $query ) {

    // Target testimonial CPT.
    $query->set( 'post_type', 'testimonial' );

    // ACF true/false stores "true" as '1' by default.
    $meta_query = array(
        array(
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',
        ),
    );

    // Apply the ACF meta query for the "featured" flag.
    $query->set( 'meta_query', $meta_query );

    // Only published testimonials are returned.
    $query->set( 'post_status', 'publish' );
}

/**
 * Query Elementor for featured posts (regular `post` type).
 *
 * This function modifies the Elementor query to retrieve posts
 * from the built-in post type 'post' that are marked as featured
 * based on a custom field.
 *
 * NOTE:
 *  - Uses a meta field named 'featured' with a value of 1.
 *
 * Usage in Elementor:
 *  - Set Loop → Query → Query ID to: query_featured_posts
 *
 * @param \WP_Query $query The WP_Query object passed in by Elementor.
 *
 * @return void
 */
function query_featured_posts( $query ) {

    // Target the default "post" post type.
    $query->set( 'post_type', 'post' );

    // Build meta query parameters:
    // 'featured' is a custom field and is checked against the value of 1.
    $meta_query = array(
        array(
            'key'     => 'featured',
            'value'   => 1,
            'compare' => 'IN',
        ),
    );

    // Apply the meta query to the query object.
    $query->set( 'meta_query', $meta_query );
}
