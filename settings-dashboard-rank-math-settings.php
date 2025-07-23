<?php namespace hws_base_tools;


function display_settings_seo_reporting() {
  
    ?>
    <style>
    .panel-settings-reporting {
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        margin: 20px;
        background-color: #f7f7f7;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-size: 14px;
    }
    .panel-settings-reporting .panel-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 15px;
        color: #333;
    }
    .panel-settings-reporting .panel-content {
        padding: 10px 0;
    }
    .panel-settings-reporting .snippet-item {
        margin-bottom: 12px;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #dcdcdc;
        background-color: #fff;
    }
    .panel-settings-reporting .snippet-item strong {
        display: block;
        margin-bottom: 5px;
        color: #222;
    }
    .panel-settings-reporting .snippet-item a {
        display: inline-block;
        margin-top: 5px;
    }
    .panel-settings-reporting input[readonly] {
        width: 100%;
        padding: 4px 6px;
        font-size: 13px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background-color: #eee;
    }
    .panel-settings-reporting button {
        margin-top: 5px;
        padding: 4px 8px;
        font-size: 13px;
    }
    </style>

    <div class="panel panel-settings-reporting">
        <h2 class="panel-title">SEO / Rank Math Reporting</h2>
        <div class="panel-content">

            <div class="snippet-item">
                <strong>Rank Math Pro Installed:</strong>
                <?php echo defined( 'RANK_MATH_PRO_FILE' ) ? '✅ Yes' : '❌ No'; ?>
            </div>











            <?php
// 1) Default to “off”
$instant_on = false;

// 2) Get Rank Math plugin instance
if ( class_exists( '\RankMath\Plugin' ) ) {
    $rm = \RankMath\Plugin::get_instance();
}

// 3) Check if the 'instant-indexing' module is active
if ( ! empty( $rm )
  && isset( $rm->modules['instant-indexing'] )
  && is_object( $rm->modules['instant-indexing'] )
  && method_exists( $rm->modules['instant-indexing'], 'is_module_active' )
) {
    $instant_on = $rm->modules['instant-indexing']->is_module_active();
}

// 4) Instantiate the API to pull key and URL
if ( class_exists( '\RankMath\Instant_Indexing\Api' ) ) {
    $api     = new \RankMath\Instant_Indexing\Api();
    $key_url = $api->get_key_location();
} else {
    $key_url = '';
}

// 5) Build the dynamic settings link
$settings_link = admin_url( 'admin.php?page=rank-math-options-instant-indexing' );
?>

<div class="snippet-item">
    <strong>Instant Indexing:</strong>
    <?php echo $instant_on ? '✅ Enabled' : '❌ Disabled'; ?>
    <a href="<?php echo esc_url( $settings_link ); ?>" target="_blank">
        Open Instant Indexing Settings
    </a>

    <?php if ( $key_url ) : ?>
        <br><strong>Check Key URL:</strong>
        <a href="<?php echo esc_url( $key_url ); ?>" target="_blank">
            <?php echo esc_html( $key_url ); ?>
        </a>
    <?php endif; ?>
</div>









<?php
// 1) Default to “off” 
$news_on = false;

// 2) Get the main Rank Math plugin instance
if ( class_exists( '\RankMath\Plugin' ) ) {
    $rm = \RankMath\Plugin::get_instance();
}

// 3) Fetch the News Sitemap module object
$module = $rm->modules['news-sitemap'] ?? null;

// 4) Safely check if the module is active
if ( is_object( $module ) && method_exists( $module, 'is_module_active' ) ) {
    $news_on = $module->is_module_active();
}

// 5) Build the dynamic settings link to the News Sitemap tab
$settings_link = admin_url( 'admin.php?page=rank-math-options-sitemap' ) . '#news-sitemap';
?>
<div class="snippet-item">
    <strong>News Sitemap Enabled:</strong>
    <?php echo $news_on ? '✅ Yes' : '❌ No'; ?>
    <a href="<?php echo esc_url( $settings_link ); ?>" target="_blank">
        Open News Sitemap Settings
    </a>
</div>






























<div class="snippet-item">
    <strong>Index Status (All Public Post Types):</strong>
    <?php
    // 1) Get all public post types
    $types = get_post_types( [ 'public' => true ], 'names' );

    // 2) Retrieve list of excluded post types from Rank Math
    $excluded = apply_filters( 'rank_math/excluded_post_types', [] );

    echo '<ul>';
    // 3) Loop through each CPT and mark status based on exclusion
    foreach ( $types as $post_type ) {
        $enabled = ! in_array( $post_type, $excluded, true );
        $status  = $enabled ? '✅' : '❌';
        echo "<li>{$post_type}: {$status}</li>";
    }
    echo '</ul>';
    ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-math' ) . '#titles' ); ?>" target="_blank">
        Edit Titles Settings
    </a>
</div>




















<?php
// 1) Initialize
$robots_exists  = false;
$robots_content = '';

// 2) Check for a physical robots.txt in the site root
$physical = ABSPATH . 'robots.txt';
if ( file_exists( $physical ) ) {
    $robots_exists  = true;
    $robots_content = file_get_contents( $physical );
} else {
    // 3) Fallback to the (virtual) robots.txt URL served by Rank Math
    $robots_url = home_url( '/robots.txt' );
    $response   = wp_remote_get( $robots_url, [
        'timeout'   => 5,
        'sslverify' => false,
    ] );
    if (
        ! is_wp_error( $response ) &&
        wp_remote_retrieve_response_code( $response ) === 200
    ) {
        $robots_exists  = true;
        $robots_content = wp_remote_retrieve_body( $response );
    }
}

// 4) Build the link to Rank Math’s robots.txt editor dynamically
$edit_link = admin_url( 'admin.php?page=rank-math-options-general' ) . '#edit-robots';
?>
<div class="snippet-item">
    <strong>robots.txt Exists:</strong>
    <?php echo $robots_exists ? '✅ Yes' : '❌ No'; ?>

    <a href="<?php echo esc_url( $edit_link ); ?>" target="_blank">
        Edit robots.txt
    </a>

    <br><strong>robots.txt Content:</strong><br>
    <textarea
        readonly
        style="width:100%; height:200px; font-family:monospace; padding:8px; border:1px solid #ccc;"
    ><?php echo esc_textarea( $robots_content ); ?></textarea>
</div>
















<?php
// 1) Pull Rank Math’s General Sitemap settings array
$sitemap_general    = get_option( 'rank_math_sitemap_general', [] ); // WP option storing sitemap config :contentReference[oaicite:1]{index=1}

// 2) Extract the array of post types explicitly INCLUDED in the sitemap (UI field “Public Post Types”)
$post_types_included = isset( $sitemap_general['post_types'] )
    ? (array) $sitemap_general['post_types']
    : [];

// 3) Fetch every public post type (built‑in + CPTs)
$all_post_types = get_post_types( [ 'public' => true ], 'names' );      // Core WP function :contentReference[oaicite:2]{index=2}

// 4) Build the dynamic link to the “General” Sitemap tab
$settings_link  = admin_url( 'admin.php?page=rank-math-options-sitemap' ) . '#sitemap-general';

// 5) Render the list with correct status
?>
<div class="snippet-item">
    <strong>General Sitemap Inclusion Status:</strong>
    <ul>
    <?php foreach ( $all_post_types as $pt ) :
        // If the included array is empty, Rank Math includes ALL by default,
        // otherwise only those explicitly listed.
        $enabled = empty( $post_types_included ) || in_array( $pt, $post_types_included, true );
        $status  = $enabled ? '✅' : '❌';
    ?>
        <li><?php echo esc_html( $pt ); ?>: <?php echo $status; ?></li>
    <?php endforeach; ?>
    </ul>

    <a href="<?php echo esc_url( $settings_link ); ?>" target="_blank">
        Edit Sitemap Settings
    </a><br>

    <button type="button"
        onclick="window.open('<?php echo esc_js( admin_url( 'options-permalink.php' ) ); ?>','_blank')">
        Permalink Settings
    </button>
</div>










            <div class="snippet-item">
                <strong>Webmaster Tools Codes:</strong>
                <label>Google:</label>
                <input type="text" readonly
                    value="<?php echo esc_attr( class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::get_settings( 'general.webmaster_code.google' ) : '' ); ?>">
                <label>Bing:</label>
                <input type="text" readonly
                    value="<?php echo esc_attr( class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::get_settings( 'general.webmaster_code.bing' ) : '' ); ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-math' ) . '#webmaster' ); ?>" target="_blank">
                    Edit Webmaster Tools
                </a>
            </div>

        

            <div class="snippet-item">
    <strong>Site Logo (Theme):</strong><br>
    <?php
    if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
        // Get the logo HTML
        $logo_html = get_custom_logo();
        // Inject inline style to constrain the image to 300px max width
        $logo_html = str_replace(
            '<img',
            '<img style="max-width:300px;height:auto;"',
            $logo_html
        );
        echo $logo_html;
    } else {
        echo '— No logo set —';
    }
    ?>
    <br>
    <a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ); ?>" target="_blank">
        Edit Site Logo
    </a>
</div>













            <?php

// ==== 1) Pull each Local SEO field through the Helper API ====
$business_name  = \RankMath\Helper::get_settings( 'titles.website_name', '' );
$alternate_name = \RankMath\Helper::get_settings( 'titles.website_alternate_name', '' );
$org_name       = \RankMath\Helper::get_settings( 'titles.knowledgegraph_name', '' );
$description    = \RankMath\Helper::get_settings( 'titles.organization_description', '' );
$logo_url       = \RankMath\Helper::get_settings( 'titles.knowledgegraph_logo', '' );
$url            = \RankMath\Helper::get_settings( 'titles.url', '' );

// ==== 2) Build the Edit link to the exact Local SEO tab ====
$edit_link = admin_url( 'admin.php?page=rank-math-options-general' ) . '#local-seo';
?>

<div class="snippet-item">
    <strong>Local SEO Settings:</strong>
    <ul>
        <li><strong>Website Name:</strong>
            <?php echo esc_html( $business_name ?: '—' ); ?></li>
        <li><strong>Alternate Name:</strong>
            <?php echo esc_html( $alternate_name ?: '—' ); ?></li>
        <li><strong>Person/Org Name:</strong>
            <?php echo esc_html( $org_name ?: '—' ); ?></li>
        <li><strong>Description:</strong>
            <?php echo esc_html( $description ?: '—' ); ?></li>
        <li><strong>URL:</strong>
            <?php if ( $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a>
            <?php else : ?>
                — 
            <?php endif; ?>
        </li>
        <li><strong>Logo URL:</strong><br>
            <input type="text" readonly style="width:100%; padding:4px; font-family:monospace;"
                   value="<?php echo esc_url( $logo_url ); ?>">
        </li>
        <?php if ( $logo_url ) : ?>
            <li>
                <img src="<?php echo esc_url( $logo_url ); ?>"
                     alt="Business Logo"
                     style="max-width:150px; height:auto; border:1px solid #ccc; padding:4px;">
            </li>
        <?php endif; ?>
    </ul>
    <a href="<?php echo esc_url( $edit_link ); ?>" target="_blank">
        Edit Local SEO Settings
    </a>
</div>











        </div>
    </div>
    <?php
}
