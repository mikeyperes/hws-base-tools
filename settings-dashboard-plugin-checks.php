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
use function hws_base_tools\is_plugin_auto_update_enabled;
 
 
function display_settings_check_plugins() {
   // Get the last time WordPress checked for plugin updates
   $update_plugins = get_site_transient('update_plugins');
   $last_checked_timestamp = isset($update_plugins->last_checked) ? $update_plugins->last_checked : false;
   $last_checked = $last_checked_timestamp ? date('Y-m-d H:i:s', $last_checked_timestamp) : 'Never';

   // Use get_plugin_updates() to reliably get plugins with updates
   $plugin_updates = get_plugin_updates();
   $plugins_with_updates = count($plugin_updates);
   $plugins_list = [];

   if (!empty($plugin_updates)) {
       foreach ($plugin_updates as $plugin_file => $plugin_data) {
           $plugin_name = $plugin_data->Name;
           $plugins_list[] = $plugin_name;
       }
   }

   $cron_name = 'wp_version_check'; // The cron job responsible for updates
   ?>

    <!-- Plugins Status Panel --> 
    <div class="panel">
        <h2 class="panel-title">Plugins Status</h2>
        <small>
    <a href="<?= admin_url('plugins.php'); ?>" target="_blank">view plugin page</a>
</small>
        <h3>Force WordPress to Check for Plugin Updates (Execute Cron)</h3>
       <?php
// Generate the dynamic URL
$update_check_url = admin_url('update-core.php?force-check=1');

// Output the button with the dynamic URL
?>
        <p>Last checked: <span id="last-checked"><?= esc_html($last_checked) ?></span></p>
        <p>Number of plugins with available updates: <span id="plugins-with-updates"><?= esc_html($plugins_with_updates) ?></span></p>
        <?php if ($plugins_with_updates > 0): ?>
            <p>Plugins with updates available:</p>
            <ul id="plugins-list">
                <?php foreach ($plugins_list as $plugin_name): ?>
                    <li><span style="color:red"><?= esc_html($plugin_name) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p>Cron job name: <span id="cron-name"><?= esc_html($cron_name) ?></span></p>
        <button id="force-update-check" class="button button-primary">Force WordPress to Check for Plugin Updates</button>
    
        <div style="margin-top:10px;"><a href="<?= esc_url($update_check_url); ?>" target="_blank" class="button">Force WordPress to Perform an Update Check</a></div>
        <div class="panel-content">
            <?php
            // Get the list of plugins
            $plugins = hws_ct_get_plugins_list();
            $plugins_page_url = admin_url('plugins.php'); // Define the variable before the loop
$installed_plugins = get_plugins(); // Get the list of all installed plugins
$plugin_updates = get_plugin_updates(); // Get plugins that have updates available

// The main loop to display plugin status and additional info
foreach ($plugins as $plugin) {
    $is_installed = isset($installed_plugins[$plugin['id']]);
    $should_be_installed = $plugin['approved_constraints']['is_installed'];
    $is_active = is_plugin_active($plugin['id']);
    $should_be_active = $plugin['approved_constraints']['is_active'];
    $is_auto_update_enabled = $plugin['approved_constraints']['is_auto_update_enabled'];

    echo "<div><strong>{$plugin['name']}:</strong></div>";

    if ($is_installed) {
        $current_version = $installed_plugins[$plugin['id']]['Version'] ?? 'Unknown';

        // Check for updates
        if (isset($plugin_updates[$plugin['id']])) {
            $new_version = $plugin_updates[$plugin['id']]->update->new_version;
            echo "<div style='color:red; font-size:small;'>v $current_version -> $new_version</div>";
            echo "<small><i><span style='color:red'><a href='$plugins_page_url' target='_blank'>click here to update.</a></span></i></small>";
        } else {
            echo "<div style='font-size:small;'>v $current_version</div>";
        }

        // Check if the plugin is installed when it shouldn't be
        if (!$should_be_installed) {
            echo "<p style='color: red;'>&#x274C; Installed (This plugin should not be used)</p>";
        } else {
            echo "<p style='color: green;'>&#x2705; Installed</p>";
        }

        // Activation status
        if ($is_active) {
            if ($should_be_active) {
                echo "<p style='color: green;'>&#x2705; Enabled</p>";
            } else {
                echo "<p style='color: red;'>&#x274C; Enabled (Should be Inactive)</p>";
             }
        } else {
            if ($should_be_active) {
                echo "<p style='color: red;'>&#x274C; Not Enabled (Should be Active)</p>";
            } else {
                echo "<p style='color: green;'>&#x2705; Not Enabled (Correct)</p>";
            }
        }

  // Use the new function to check auto-update status
  if (is_plugin_auto_update_enabled($plugin['id'])) {
    echo "<p style='color: green;'>&#x2705; Auto-update Enabled</p>";
} else {
    echo "<p style='color: red;'>&#x274C; Auto-update Disabled</p>";
}

        // Activation/Deactivation links
        if ($is_active) {
            $deactivate_url = wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . $plugin['id']), 'deactivate-plugin_' . $plugin['id']);
            echo "<p><a href='$deactivate_url' target='_blank'>Deactivate plugin</a></p>";
        } else {
            $activate_url = wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . $plugin['id']), 'activate-plugin_' . $plugin['id']);
            echo "<p><a href='$activate_url' target='_blank'>Activate plugin</a></p>";
        }
    } else {
        // If the plugin is not installed and it's supposed to be, show a warning
        if ($should_be_installed) {
            echo "<p style='color: red;'>&#x274C; Not Installed (This plugin should be installed)</p>";
            echo "<p>{$plugin['additional_info']}</p>";
        } else {
            echo "<p style='color: green;'>&#x2705; Not Installed (Correct)</p>";
            echo "<p>⚠️ This plugin should not be used.</p>";
        }
    }

    echo "<hr>";
}

?>
        </div>
    </div>


<?php }

function hws_ct_plugin_info_determine_plugin_download_message($plugin_id, $plugin_name, $upload_manually = false) {
    if ($upload_manually) {
        return 'Upload the plugin manually';
    } else {
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
            'id' => 'wp-smush-pro/wp-smush.php',
            'name' => 'Smush Pro',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-smush-pro/wp-smush.php', 'Smush Pro', true)
        ],
        
        [
            'id' => 'litespeed-cache/litespeed-cache.php',
            'name' => 'LiteSpeed Cache',
            'approved_constraints' => [
                'is_installed' => true,
                'is_active' => true,
                'is_auto_update_enabled' => true
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('litespeed-cache/litespeed-cache.php', 'LiteSpeed Cache')
        ],
        [
            'id' => 'wp-file-manager/file_folder_manager.php',
            'name' => 'WP File Manager',
            'approved_constraints' => [
                'is_installed' => false,
                'is_active' => false,
                'is_auto_update_enabled' => false
            ],
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('wp-file-manager/wp-file-manager.php', 'WP File Manager')
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
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('elementor-pro/elementor-pro.php', 'Elementor Pro', true)
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
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('jet-engine/jet-engine.php', 'JetEngine', true)
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
                'is_auto_update_enabled' => true
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
            'additional_info' => hws_ct_plugin_info_determine_plugin_download_message('advanced-custom-fields-pro/acf.php', 'Advanced Custom Fields Pro', true)
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




// Handle the AJAX request to force the update check


function hws_ct_force_update_check() {
    // Force WordPress to check for plugin and theme updates
    wp_clean_update_cache();
    wp_update_plugins();
    wp_update_themes();

    // Get the updated last checked time and plugins with updates using get_plugin_updates()
    $plugin_updates = get_plugin_updates();
    $last_checked_timestamp = time();
    $last_checked = date('Y-m-d H:i:s', $last_checked_timestamp);
    $plugins_with_updates = count($plugin_updates);
    $plugins_list = [];
 
    if (!empty($plugin_updates)) {
        foreach ($plugin_updates as $plugin_file => $plugin_data) {
            $plugin_name = $plugin_data->Name;
            $plugins_list[] = $plugin_name;
        }
    }
 
    // Send back the last checked time, number of plugins with updates, and their names
    echo json_encode([
        'last_checked' => $last_checked,
        'plugins_with_updates' => $plugins_with_updates,
        'plugins_list' => $plugins_list,
    ]);
    wp_die();
}

add_action('wp_ajax_hws_ct_force_update_check', 'hws_base_tools\hws_ct_force_update_check');

?>