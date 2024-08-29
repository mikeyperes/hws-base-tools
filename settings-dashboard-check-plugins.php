<?php namespace hws_base_tools;

// Import functions from the hws_base_tools namespace
use function hws_base_tools\get_database_table_prefix;
use function hws_base_tools\check_wordpress_main_email;
use function hws_base_tools\check_imagick_available;
use function hws_base_tools\get_constant_value_from_wp_config;
use function hws_base_tools\check_cloudflare_active;
use function hws_base_tools\check_php_type;
use function hws_base_tools\check_php_handler;
use function hws_base_tools\hws_ct_highlight_if_essential_setting_failed;
use function hws_base_tools\check_myisam_tables;
use function hws_base_tools\check_wordfence_notification_email;
use function hws_base_tools\check_wp_config_constant_status;
use function hws_base_tools\check_log_file_sizes;
use function hws_base_tools\check_smtp_auth_status_and_mailer;
use function hws_base_tools\check_redis_active;
use function hws_base_tools\check_caching_source;
use function hws_base_tools\check_wordpress_memory_limit;
use function hws_base_tools\check_server_memory_limit;
use function hws_base_tools\check_server_specs;


function hws_ct_display_settings_check_plugins() { ?>

    <!-- Plugins Status Panel --> 
    <div class="panel">
        <h2 class="panel-title">Plugins Status</h2>
        <div class="panel-content">
            <?php
            // Get the list of plugins
            $plugins = hws_ct_get_plugins_list();

            foreach ($plugins as $plugin) {
                list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status($plugin['id']);

                $plugin_name = $plugin['name'];
                $constraints = $plugin['approved_constraints'];
                $additional_info = $plugin['additional_info'];

                echo "<p><strong>$plugin_name:</strong></p>";

                // Installation status
                if ($is_installed) {
                    echo "<p style='color: green;'>&#x2705; Installed</p>";
                } else {
                    echo "<p style='color: red;'>&#x274C; Not Installed</p>";
                }

                // Activation status
                if ($is_active) {
                    if ($constraints['is_active']) {
                        echo "<p style='color: green;'>&#x2705; Enabled</p>";
                    } else {
                        echo "<p style='color: red;'>&#x274C; Enabled (Should be Inactive)</p>";
                    }
                } else {
                    if ($constraints['is_active']) {
                        echo "<p style='color: red;'>&#x274C; Not Enabled</p>";
                    } else {
                        echo "<p style='color: green;'>&#x2705; Not Enabled (Correct)</p>";
                    }
                }

                // Auto-update status
                if ($is_auto_update_enabled) {
                    echo "<p style='color: green;'>&#x2705; Auto-update Enabled</p>";
                } else {
                    echo "<p style='color: red;'>&#x274C; Auto-update Disabled</p>";
                }

                // Add the additional info if the plugin is not installed
                if (!$is_installed) {
                    echo "<p>$additional_info</p>";
                }

                // Divider for each plugin
                echo "<hr>";
            } ?>
        </div>
    </div>
<?php }

function hws_ct_plugin_info_determine_plugin_download_message($plugin_id, $plugin_name, $upload_manually = false) {
    if ($upload_manually) {
        return 'Upload the plugin manually';
    } else {
        // Dynamically get the WordPress admin URL
        $base_url = admin_url('plugin-install.php');
        $search_term = urlencode($plugin_name);
        $search_url = "{$base_url}?s={$search_term}&tab=search&type=term";

        return '<a href="' . esc_url($search_url) . '" target="_blank">Download ' . esc_html($plugin_name) . '</a>';
    }
}

// Function to get the plugins list with dynamic download messages
function hws_ct_get_plugins_list() {
    return [

        

        [
            'id' => 'wp-optimize/wp-optimize.php',
            'name' => 'WP Optimize',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => false,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-optimize/wp-optimize.php', 'WP Optimize')
        ],
        [
            'id' => 'hws-base-tools/initialization.php',
            'name' => 'HWS Base Tools',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('hws-base-tools/initialization.php', 'HWS Base Tools')
        ],
        [
            'id' => 'elementor/elementor.php',
            'name' => 'Elementor',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('elementor/elementor.php', 'Elementor')
        ],
        [
            'id' => 'elementor-pro/elementor-pro.php',
            'name' => 'Elementor Pro',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('elementor-pro/elementor-pro.php', 'Elementor Pro',true)
        ],
        [
            'id' => 'seo-by-rank-math/rank-math.php',
            'name' => 'Rank Math',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => false
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('seo-by-rank-math/rank-math.php', 'Rank Math')
        ],
        [
            'id' => 'seo-by-rank-math-pro/rank-math-pro.php',
            'name' => 'Rank Math Pro',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => false
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('seo-by-rank-math-pro/rank-math-pro.php', 'Rank Math Pro',true)
        ],
        [
            'id' => 'classic-editor/classic-editor.php',
            'name' => 'Classic Editor',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('classic-editor/classic-editor.php', 'Classic Editor')
        ],
        [
            'id' => 'jet-engine/jet-engine.php',
            'name' => 'JetEngine',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('jet-engine/jet-engine.php', 'JetEngine',true)
        ],
        [
            'id' => 'media-cleaner/media-cleaner-pro.php',
            'name' => 'Media Cleaner Pro',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('media-cleaner/media-cleaner.php', 'Media Cleaner', true)
        ],
        [
            'id' => 'wordfence/wordfence.php',
            'name' => 'Wordfence',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => false
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wordfence/wordfence.php', 'Wordfence')
        ],
        [
            'id' => 'google-site-kit/google-site-kit.php',
            'name' => 'Google Site Kit',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('google-site-kit/google-site-kit.php', 'Google Site Kit')
        ],
        [
            'id' => 'wp-mail-smtp/wp_mail_smtp.php',
            'name' => 'WP Mail SMTP',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-mail-smtp/wp_mail_smtp.php', 'WP Mail SMTP')
        ],
        [
            'id' => 'wp-user-avatars/wp-user-avatars.php',
            'name' => 'WP User Avatars',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-user-avatars/wp-user-avatars.php', 'WP User Avatars')
        ],
        [
            'id' => 'advanced-custom-fields-pro/acf.php',
            'name' => 'Advanced Custom Fields Pro',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('advanced-custom-fields-pro/acf.php', 'Advanced Custom Fields Pro',true)
        ],
        [
            'id' => 'wp-sweep/wp-sweep.php',
            'name' => 'WP Sweep',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => false,  // WP Sweep should not be active
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-sweep/wp-sweep.php', 'WP Sweep')
        ]
    ];
}

?>