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
 *  - hpr_resources           → query_hpr_resources()
 *  - hpr_publications_featured_new → query_hpr_publications_featured_new()
 *  - hpr_publications_standard_featured → query_hpr_publications_standard_featured()
 *  - hpr_updates_internal    → query_hpr_updates_internal()
 *  - hpr_external_cision     → query_hpr_external_cision()
 *  - hpr_external_prcom      → query_hpr_external_prcom()
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

    $query_callbacks = array(
        'hpr_resources'                      => 'query_hpr_resources',
        'hpr_publications_featured_new'      => 'query_hpr_publications_featured_new',
        'hpr_publications_standard_featured' => 'query_hpr_publications_standard_featured',
        'hpr_updates_internal'               => 'query_hpr_updates_internal',
        'hpr_external_cision'                => 'query_hpr_external_cision',
        'hpr_external_prcom'                 => 'query_hpr_external_prcom',
    );

    foreach ( $query_callbacks as $query_id => $callback ) {
        add_action( 'elementor/query/' . $query_id, __NAMESPACE__ . '\\' . $callback );
    }

    add_action( 'elementor/dynamic_tags/register', __NAMESPACE__ . '\\register_hws_elementor_dynamic_tags' );
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

/**
 * Register HWS text transformations that replace JetEngine callbacks.
 *
 * @param object $dynamic_tags Elementor's dynamic tag manager.
 * @return void
 */
function register_hws_elementor_dynamic_tags( $dynamic_tags ) {
    $class = \HWS\BaseTools\Elementor\TrimmedAcfTextTag::class;

    if ( ! class_exists( $class ) || ! is_object( $dynamic_tags ) || ! method_exists( $dynamic_tags, 'register' ) ) {
        return;
    }

    $dynamic_tags->register( new $class() );
}

/** @param \WP_Query $query */
function query_hpr_resources( $query ) {
    $query->set( 'post_type', 'resource' );
    $query->set( 'post_status', 'publish' );
}

/** @param \WP_Query $query */
function query_hpr_publications_featured_new( $query ) {
    hpr_configure_publication_query(
        $query,
        array(
            array(
                'key'     => 'new_source',
                'value'   => '1',
                'compare' => '=',
            ),
            array(
                'key'     => 'featured',
                'value'   => '1',
                'compare' => '=',
            ),
        )
    );
}

/** @param \WP_Query $query */
function query_hpr_publications_standard_featured( $query ) {
    hpr_configure_publication_query(
        $query,
        array(
            array(
                'key'     => 'product_tier',
                'value'   => 'standard',
                'compare' => '=',
            ),
            array(
                'key'     => 'status',
                'value'   => '1',
                'compare' => '=',
            ),
            array(
                'key'     => 'featured',
                'value'   => '1',
                'compare' => '=',
            ),
        )
    );
}

/**
 * @param \WP_Query $query
 * @param array<int,array<string,string>> $clauses
 */
function hpr_configure_publication_query( $query, array $clauses ) {
    $query->set( 'post_type', 'publication' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $clauses ) );
    $query->set( 'order', 'ASC' );
}

/** @param \WP_Query $query */
function query_hpr_updates_internal( $query ) {
    hpr_configure_category_query( $query, 'post', 'internal-announcement' );
}

/** @param \WP_Query $query */
function query_hpr_external_cision( $query ) {
    hpr_configure_category_query( $query, 'press-release', 'pr-news-wire-cision' );
}

/** @param \WP_Query $query */
function query_hpr_external_prcom( $query ) {
    hpr_configure_category_query( $query, 'press-release', 'pr-com' );
}

/** @param \WP_Query $query */
function hpr_configure_category_query( $query, string $post_type, string $category_slug ) {
    $query->set( 'post_type', $post_type );
    $query->set( 'post_status', 'publish' );
    $query->set( 'category_name', $category_slug );
}
