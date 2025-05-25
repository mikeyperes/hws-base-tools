<?php namespace hws_base_tools;


function display_settings_rss_dashboard() { ?>
    <!-- RSS Feeds Dashboard Panel -->
    <div class="panel">
        <h2 class="panel-title">RSS Feeds Dashboard</h2>
        <div class="panel-content">

            <!-- RSS Feeds Section -->
            <section style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">Active RSS Feeds</h3>
                <div style="padding-left: 20px;">

                    <!-- RSS feeds for Post Types -->
                    <?php if (have_rows('rss_post_type', 'option')): ?>
                        <div>
                            <h4 style="color: #0073aa; margin-bottom: 8px;">Post Type RSS Feeds:</h4>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <?php while (have_rows('rss_post_type', 'option')): the_row(); ?>
                                    <?php 
                                        $rss_id = get_sub_field('rss_id'); 
                                        $rss_url = home_url("/feed/$rss_id");
                                        $random_cache_buster = rand(1000, 9999); // Generate random number for cache busting
                                    ?>
                                    <li>
                                        <a href="<?= esc_url($rss_url) ?>" target="_blank" style="text-decoration: none; color: #0073aa;"><?= esc_html($rss_id) ?> RSS Feed</a>
                                        |
                                        <a href="<?= esc_url($rss_url . '?v=' . $random_cache_buster) ?>" target="_blank" style="text-decoration: none; color: #0073aa;">Feed with Cache Purge</a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- RSS feeds for Categories -->
                    <?php if (have_rows('rss_post_category', 'option')): ?>
                        <div style="margin-top: 20px;">
                            <h4 style="color: #0073aa; margin-bottom: 8px;">Category RSS Feeds:</h4>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <?php while (have_rows('rss_post_category', 'option')): the_row(); ?>
                                    <?php 
                                        $rss_id = get_sub_field('rss_id'); 
                                        $rss_url = home_url("/feed/$rss_id");
                                        $random_cache_buster = rand(1000, 9999); // Generate random number for cache busting
                                    ?>
                                    <li>
                                        <a href="<?= esc_url($rss_url) ?>" target="_blank" style="text-decoration: none; color: #0073aa;"><?= esc_html($rss_id) ?> RSS Feed</a>
                                        |
                                        <a href="<?= esc_url($rss_url . '?v=' . $random_cache_buster) ?>" target="_blank" style="text-decoration: none; color: #0073aa;">Feed with Cache Purge</a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Link to RSS Structures Page -->
            <section style="margin-bottom: 20px;">
                <h3>RSS Structures</h3>
                <div style="padding-left: 20px;">
                    <a href="<?= esc_url(admin_url('options-general.php?page=rss-structures')) ?>" target="_blank" class="button button-primary">View Theme Options Page</a>
                </div>
            </section>

            <!-- Refresh Permalinks Section -->
            <section style="margin-bottom: 20px;">
                <h3>Refresh Permalinks</h3>
                <div style="padding-left: 20px;">
                    <a href="<?= esc_url(admin_url('options-permalink.php')) ?>" target="_blank" class="button button-secondary">Refresh Permalinks</a>
                </div>
            </section>

        </div>
    </div>
    <?php
}


function enable_custom_rss_functionality() {
register_acf_rss();
register_rss_feeds_from_acf();
 }
 

 /**
 * Register custom RSS feeds based on ACF fields.
 */
function register_rss_feeds_from_acf() {
 
    // Ensure that ACF options page exists and ACF fields are populated
    if (!function_exists('have_rows') ) {
        return; // Exit early if ACF is not initialized or fields are missing
    }

    //|| !have_rows('rss_post_type', 'option')

    // Post Type RSS feeds
    if (have_rows('rss_post_type', 'option')) {
        while (have_rows('rss_post_type', 'option')) {
            the_row();
            $post_slug = get_sub_field('slug');
            $rss_id = get_sub_field('rss_id');

            // Ensure that both slug and RSS ID are available before registering the feed
            if (!empty($post_slug) && !empty($rss_id)) {
                add_feed($rss_id, function() use ($post_slug) {
                    custom_rss_feed($post_slug);
                });
            } else {
                // Log or handle missing RSS ID or Post Slug
                error_log("Missing post slug or RSS ID for post type RSS feed.");
            }
        }
    }

    // Category Type RSS feeds
    if (have_rows('rss_post_category', 'option')) {
        while (have_rows('rss_post_category', 'option')) {
            the_row();
            $category_slug = get_sub_field('slug');
            $rss_id = get_sub_field('rss_id');

            // Ensure that both category slug and RSS ID are available before registering the feed
            if (!empty($category_slug) && !empty($rss_id)) {
                add_feed($rss_id, function() use ($category_slug) {
                    custom_rss_feed($category_slug, true);
                });
            } else {
                // Log or handle missing RSS ID or Category Slug
                error_log("Missing category slug or RSS ID for category type RSS feed.");
            }
        }
    }
}



/**
 * Generate the RSS feed for a given post type or category.
 *
 * @param string $slug The post type or category slug.
 * @param bool $is_category Whether this is a category feed or not.
 *//**
 * Generate the RSS feed for a given post type or category.
 *
 * @param string $slug The post type or category slug.
 * @param bool $is_category Whether this is a category feed or not.
 */
function custom_rss_feed( $slug, $is_category = false ) {
    // Properly reference WP_Query as a global class
    $args = array(
        'post_type' => $slug,
        'posts_per_page' => 10,
    );

    if ( $is_category ) {
        $args['category_name'] = $slug;
    }

    // Use \WP_Query to ensure proper namespace usage
    $posts = new \WP_Query( $args );

    header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );
    echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';

    ?>
    <rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
        <channel>
            <title><?php bloginfo_rss( 'name' ); ?></title>
            <link><?php bloginfo_rss( 'url' ); ?></link>
            <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
            <description><?php bloginfo_rss( 'description' ); ?></description>
            <lastBuildDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_lastpostmodified( 'GMT' ), false ); ?></lastBuildDate>
            <language><?php bloginfo_rss( 'language' ); ?></language>
            <?php while ( $posts->have_posts() ) : $posts->the_post(); ?>
                <item>
                    <title><?php the_title_rss(); ?></title>
                    <link><?php the_permalink_rss(); ?></link>
                    <description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
                    <pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
                    <guid><?php the_guid(); ?></guid>
                    <content:encoded><![CDATA[<?php the_content(); ?>]]></content:encoded>
                </item>
            <?php endwhile; wp_reset_postdata(); ?>
        </channel>
    </rss>
    <?php
}