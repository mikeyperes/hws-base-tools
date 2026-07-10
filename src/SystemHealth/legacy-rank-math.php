<?php namespace hws_base_tools;

function display_settings_seo_reporting() {

    // ---- helpers -----------------------------------------------------------
    $rm_available     = class_exists('\RankMath\Plugin');
    $helper_available = class_exists('\RankMath\Helper');

    // Safe getter for Rank Math settings (returns default if Helper missing)
    $rm_get = function ($key, $default = '') use ($helper_available) {
        return $helper_available ? \RankMath\Helper::get_settings($key, $default) : $default;
    };

    // Links (fallbacks if Rank Math missing)
    $link_rm_instant   = $rm_available ? admin_url('admin.php?page=rank-math-options-instant-indexing')
                                       : admin_url('plugin-install.php?tab=search&s=Rank+Math');
    $link_rm_titles    = $rm_available ? admin_url('admin.php?page=rank-math') . '#titles'
                                       : admin_url('options-reading.php');
    $link_rm_sitemap   = $rm_available ? admin_url('admin.php?page=rank-math-options-sitemap') . '#sitemap-general'
                                       : admin_url('options-reading.php');
    $link_rm_news_tab  = $rm_available ? admin_url('admin.php?page=rank-math-options-sitemap') . '#news-sitemap'
                                       : admin_url('plugin-install.php?tab=search&s=Rank+Math');
    $link_rm_webmaster = $rm_available ? admin_url('admin.php?page=rank-math') . '#webmaster'
                                       : admin_url('plugin-install.php?tab=search&s=Rank+Math');
    $link_rm_robots    = $rm_available ? admin_url('admin.php?page=rank-math-options-general') . '#edit-robots'
                                       : home_url('/robots.txt');

    ?>
    <style>
    .panel-settings-reporting{border:1px solid #e0e0e0;border-radius:5px;margin:20px;background:#f7f7f7;padding:15px;box-shadow:0 1px 3px rgba(0,0,0,.1);font-size:14px}
    .panel-settings-reporting .panel-title{font-size:18px;font-weight:700;margin-bottom:15px;color:#333}
    .panel-settings-reporting .panel-content{padding:10px 0}
    .panel-settings-reporting .snippet-item{margin-bottom:12px;padding:10px;border-radius:4px;border:1px solid #dcdcdc;background:#fff}
    .panel-settings-reporting .snippet-item strong{display:block;margin-bottom:5px;color:#222}
    .panel-settings-reporting .snippet-item a{display:inline-block;margin-top:5px}
    .panel-settings-reporting input[readonly],.panel-settings-reporting textarea[readonly]{width:100%;padding:4px 6px;font-size:13px;border:1px solid #ccc;border-radius:3px;background:#eee}
    .panel-settings-reporting button{margin-top:5px;padding:4px 8px;font-size:13px}
    </style>

    <div class="panel panel-settings-reporting">
        <h2 class="panel-title">SEO / Rank Math Reporting</h2>
        <div class="panel-content">

            <div class="snippet-item">
                <strong>Rank Math Pro Installed:</strong>
                <?php echo defined('RANK_MATH_PRO_FILE') ? '✅ Yes' : '❌ No'; ?>
            </div>

            <?php
            // ---- Instant Indexing ----
            $instant_on = false;
            $key_url    = '';
            if ($rm_available) {
                $rm = \RankMath\Plugin::get_instance();
                if (isset($rm->modules['instant-indexing']) &&
                    is_object($rm->modules['instant-indexing']) &&
                    method_exists($rm->modules['instant-indexing'], 'is_module_active')) {
                    $instant_on = (bool) $rm->modules['instant-indexing']->is_module_active();
                }
                if (class_exists('\RankMath\Instant_Indexing\Api')) {
                    $api     = new \RankMath\Instant_Indexing\Api();
                    $key_url = (string) $api->get_key_location();
                }
            }
            ?>
            <div class="snippet-item">
                <strong>Instant Indexing:</strong>
                <?php echo $instant_on ? '✅ Enabled' : '❌ Disabled'; ?>
                <a href="<?php echo esc_url($link_rm_instant); ?>" target="_blank">Open Instant Indexing Settings</a>
                <?php if ($key_url): ?>
                    <br><strong>Check Key URL:</strong>
                    <a href="<?php echo esc_url($key_url); ?>" target="_blank"><?php echo esc_html($key_url); ?></a>
                <?php endif; ?>
            </div>

            <?php
            // ---- News Sitemap ----
            $news_on = false;
            if ($rm_available) {
                $rm     = \RankMath\Plugin::get_instance();
                $module = $rm->modules['news-sitemap'] ?? null;
                if (is_object($module) && method_exists($module, 'is_module_active')) {
                    $news_on = (bool) $module->is_module_active();
                }
            }
            ?>
            <div class="snippet-item">
                <strong>News Sitemap Enabled:</strong>
                <?php echo $news_on ? '✅ Yes' : '❌ No'; ?>
                <a href="<?php echo esc_url($link_rm_news_tab); ?>" target="_blank">Open News Sitemap Settings</a>
            </div>

            <div class="snippet-item">
                <strong>Index Status (All Public Post Types):</strong>
                <?php
                $types    = get_post_types(['public' => true], 'names');
                $excluded = apply_filters('rank_math/excluded_post_types', []);
                echo '<ul>';
                foreach ($types as $post_type) {
                    $enabled = !in_array($post_type, $excluded, true);
                    echo '<li>' . esc_html($post_type) . ': ' . ($enabled ? '✅' : '❌') . '</li>';
                }
                echo '</ul>';
                ?>
                <a href="<?php echo esc_url($link_rm_titles); ?>" target="_blank">Edit Titles Settings</a>
            </div>

            <?php
            // ---- robots.txt ----
            $robots_exists  = false;
            $robots_content = '';
            $physical       = ABSPATH . 'robots.txt';
            if (file_exists($physical)) {
                $robots_exists  = true;
                $robots_content = (string) @file_get_contents($physical);
            } else {
                $robots_url = home_url('/robots.txt');
                $response   = wp_remote_get($robots_url, ['timeout' => 5, 'sslverify' => false]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $robots_exists  = true;
                    $robots_content = (string) wp_remote_retrieve_body($response);
                }
            }
            ?>
            <div class="snippet-item">
                <strong>robots.txt Exists:</strong>
                <?php echo $robots_exists ? '✅ Yes' : '❌ No'; ?>
                <a href="<?php echo esc_url($link_rm_robots); ?>" target="_blank">
                    <?php echo $rm_available ? 'Edit robots.txt' : 'View robots.txt'; ?>
                </a>
                <br><strong>robots.txt Content:</strong><br>
                <textarea readonly style="width:100%;height:200px;font-family:monospace;padding:8px;border:1px solid #ccc;"><?php echo esc_textarea($robots_content); ?></textarea>
            </div>

            <?php
            // ---- General Sitemap Inclusion ----
            $sitemap_general     = (array) get_option('rank_math_sitemap_general', []);
            $post_types_included = isset($sitemap_general['post_types']) ? (array) $sitemap_general['post_types'] : [];
            $all_post_types      = get_post_types(['public' => true], 'names');
            ?>
            <div class="snippet-item">
                <strong>General Sitemap Inclusion Status:</strong>
                <ul>
                    <?php foreach ($all_post_types as $pt):
                        $enabled = empty($post_types_included) || in_array($pt, $post_types_included, true); ?>
                        <li><?php echo esc_html($pt); ?>: <?php echo $enabled ? '✅' : '❌'; ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url($link_rm_sitemap); ?>" target="_blank">Edit Sitemap Settings</a><br>
                <button type="button" onclick="window.open('<?php echo esc_js(admin_url('options-permalink.php')); ?>','_blank')">Permalink Settings</button>
            </div>

            <div class="snippet-item">
                <strong>Webmaster Tools Codes:</strong>
                <label>Google:</label>
                <input type="text" readonly value="<?php echo esc_attr($rm_get('general.webmaster_code.google', '')); ?>">
                <label>Bing:</label>
                <input type="text" readonly value="<?php echo esc_attr($rm_get('general.webmaster_code.bing', '')); ?>">
                <a href="<?php echo esc_url($link_rm_webmaster); ?>" target="_blank">Edit Webmaster Tools</a>
            </div>

            <div class="snippet-item">
                <strong>Site Logo (Theme):</strong><br>
                <?php
                if (function_exists('has_custom_logo') && has_custom_logo()) {
                    $logo_html = get_custom_logo();
                    $logo_html = str_replace('<img', '<img style="max-width:300px;height:auto;"', $logo_html);
                    echo $logo_html;
                } else {
                    echo '— No logo set —';
                }
                ?>
                <br>
                <a href="<?php echo esc_url(admin_url('customize.php?autofocus[section]=title_tagline')); ?>" target="_blank">Edit Site Logo</a>
            </div>

            <?php
            // ---- Local SEO (safe if RM missing) ----
            $business_name  = $rm_get('titles.website_name', '');
            $alternate_name = $rm_get('titles.website_alternate_name', '');
            $org_name       = $rm_get('titles.knowledgegraph_name', '');
            $description    = $rm_get('titles.organization_description', '');
            $logo_url       = $rm_get('titles.knowledgegraph_logo', '');
            $url_setting    = $rm_get('titles.url', '');

            if (!$helper_available) {
                // Core fallbacks
                $business_name = $business_name ?: get_bloginfo('name');
                $description   = $description ?: get_bloginfo('description');
                $url_setting   = $url_setting ?: home_url('/');
                if (function_exists('has_custom_logo') && has_custom_logo()) {
                    $logo_id  = get_theme_mod('custom_logo');
                    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
                }
            }

            $local_edit_link = $rm_available
                ? admin_url('admin.php?page=rank-math-options-general') . '#local-seo'
                : admin_url('customize.php?autofocus[section]=title_tagline');
            ?>
            <div class="snippet-item">
                <strong>Local SEO Settings:</strong>
                <ul>
                    <li><strong>Website Name:</strong> <?php echo esc_html($business_name ?: '—'); ?></li>
                    <li><strong>Alternate Name:</strong> <?php echo esc_html($alternate_name ?: '—'); ?></li>
                    <li><strong>Person/Org Name:</strong> <?php echo esc_html($org_name ?: '—'); ?></li>
                    <li><strong>Description:</strong> <?php echo esc_html($description ?: '—'); ?></li>
                    <li><strong>URL:</strong>
                        <?php if ($url_setting): ?>
                            <a href="<?php echo esc_url($url_setting); ?>" target="_blank"><?php echo esc_html($url_setting); ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </li>
                    <li><strong>Logo URL:</strong><br>
                        <input type="text" readonly style="width:100%;padding:4px;font-family:monospace;" value="<?php echo esc_url($logo_url); ?>">
                    </li>
                    <?php if ($logo_url): ?>
                        <li><img src="<?php echo esc_url($logo_url); ?>" alt="Business Logo" style="max-width:150px;height:auto;border:1px solid #ccc;padding:4px;"></li>
                    <?php endif; ?>
                </ul>
                <a href="<?php echo esc_url($local_edit_link); ?>" target="_blank">
                    <?php echo $rm_available ? 'Edit Local SEO Settings' : 'Edit Site Identity'; ?>
                </a>
            </div>

        </div>
    </div>
    <?php
}
