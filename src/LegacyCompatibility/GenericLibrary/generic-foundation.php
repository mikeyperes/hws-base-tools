<?php

namespace hws_base_tools;

use HWS\BaseTools\PluginRuntime\PluginMetadata;





if (!function_exists('hws_ct_highlight_if_essential_setting_failed')) {
    function hws_ct_highlight_if_essential_setting_failed($result) {
        return $result['status'] ? $result['details'] : '<span style="color: red;">' . $result['details'] . '</span>';
    }
}


/**
 * Check if the given plugin is installed, active, and auto-update is enabled.
 *
 * @param string $plugin The plugin's folder/plugin-file name (e.g., 'plugin-directory/plugin-file.php').
 * @return array An array containing three boolean values:
 *               - Whether the plugin is installed
 *               - Whether the plugin is active
 *               - Whether the plugin's auto-update is enabled
 */





 if (!function_exists(__NAMESPACE__ . '\\check_plugin_status')) {
    function check_plugin_status($plugin_slug) {

        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug);
        $is_active = $is_installed && is_plugin_active($plugin_slug);

        // Initialize auto-update as not enabled since it's meaningless if not installed
        $is_auto_update_enabled = false;

        if ($is_installed) {
            // Check global auto-update setting first
            $global_auto_update_enabled = apply_filters('auto_update_plugin', false, (object) array('plugin' => $plugin_slug));

            // If globally enabled, set auto-update to true
            if ($global_auto_update_enabled) {
                $is_auto_update_enabled = true;
            } else {
                // Get the current list of plugins with auto-updates enabled
                $auto_update_plugins = get_option('auto_update_plugins', []);

                // Check if this specific plugin is in the list
                $is_auto_update_enabled = in_array($plugin_slug, $auto_update_plugins);

                // If not in the auto-update plugins list, apply the global filter
                if (!$is_auto_update_enabled) {
                    $update_plugins = get_site_transient('update_plugins');

                    // Check the transient data for this specific plugin
                    if (isset($update_plugins->no_update[$plugin_slug])) {
                        $plugin_data = $update_plugins->no_update[$plugin_slug];
                    } elseif (isset($update_plugins->response[$plugin_slug])) {
                        $plugin_data = $update_plugins->response[$plugin_slug];
                    }

                    // Apply the auto_update_plugin filter with both arguments
                    if (isset($plugin_data)) {
                        $is_auto_update_enabled = apply_filters('auto_update_plugin', false, $plugin_data);
                    }
                }
            }
        }

        // Log the final auto-update status for debugging
        write_log("Plugin Slug: $plugin_slug - Installed: " . ($is_installed ? 'Yes' : 'No') . " - Auto-Update Enabled: " . ($is_auto_update_enabled ? 'Yes' : 'No'),false);

        return [$is_installed, $is_active, $is_auto_update_enabled];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_plugin_status function is already declared", true);


/**
 * Check if a user exists by login name.
 *
 * @param string $login The login name of the user.
 * @return bool True if the user exists, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\does_user_exist')) {
    function does_user_exist($login) {
        return get_user_by('login', $login) !== false;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\does_user_exist function is already declared",true);

/**
 * Check if a custom post type exists.
 *
 * @param string $post_type The custom post type name.
 * @return bool True if the post type exists, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\does_post_type_exist')) {
    function does_post_type_exist($post_type) {
        return post_type_exists($post_type);
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\does_post_type_exist function is already declared",true);


/**
 * Check if a specified theme is currently active.
 *
 * @param string $theme_name The name of the theme.
 * @return bool True if the theme is active, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\is_theme_active')) {
    function is_theme_active($theme_name) {
        return wp_get_theme()->get('Name') === $theme_name;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\is_theme_active function is already declared",true);



/**
 * Display the status of a condition with a message and colored icon.
 *
 * @param bool $condition The condition to evaluate.
 * @param string $message The message to display.
 */
if (!function_exists(__NAMESPACE__ . '\\display_check_status')) {
    function display_check_status($condition, $message) {
        $color = $condition ? 'green' : 'red';
        $icon = $condition ? '&#x2705;' : '&#x274C;';
        echo "<div style='color: $color;'>$icon $message</div>";
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\display_check_status function is already declared",true);


/**
 * Check if a taxonomy exists.
 *
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the taxonomy exists, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\does_taxonomy_exist')) {
    function does_taxonomy_exist($taxonomy) {
        return taxonomy_exists($taxonomy);
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\does_taxonomy_exist function is already declared",true);


/**
 * Check if a term exists within a specified taxonomy.
 *
 * @param string $term The term to check.
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the term exists in the taxonomy, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\does_term_exist')) {
    function does_term_exist($term, $taxonomy) {
        $term_exists = term_exists($term, $taxonomy);
        return $term_exists !== 0 && $term_exists !== null;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\does_term_exist function is already declared",true);




/**
 * Ensure ACF (Advanced Custom Fields) form functions are available.
 *
 * This function adds the `acf_form_head` action to the `admin_head` hook if the function exists.
 */
if (function_exists('acf_form_head')) {
    add_action('admin_head', 'acf_form_head');
}

/**
 * Check if a specific ACF field group is imported.
 *
 * @param string $key The key of the ACF field group.
 * @return bool True if the field group is imported, false otherwise.
 */
if (!function_exists(__NAMESPACE__ . '\\is_acf_field_group_imported')) {
    function is_acf_field_group_imported($key) {
        if ( ! function_exists( 'acf_get_local_field_groups' ) ) {
            return false;
        }

        $groups = acf_get_local_field_groups();
        foreach ($groups as $group) {
            if ($group['key'] === $key) {
                return true;
            }
        }
        return false;
    }
}

// Generic function to add a settings page under "Settings"
if (!function_exists(__NAMESPACE__ . '\\add_settings_menu')) {
    function add_settings_menu($page_title, $menu_title, $capability, $menu_slug, $callback_function) {
        add_options_page(
            $page_title,      // Page title
            $menu_title,      // Menu title
            $capability,      // Capability required to access this page
            $menu_slug,       // Menu slug
            $callback_function // Callback function to display the page content
        );
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\add_settings_menu function is already declared",true);













if (!function_exists(__NAMESPACE__ . '\\check_smtp_auth_status_and_mailer')) {
    /**
     * Report the authoritative SMTP2GO status from the shared authentication service.
     *
     * @since 10.9.0
     * @return array { status: bool, mailer: string, raw_value: string }
     */
    function check_smtp_auth_status_and_mailer() {
        return ( new \HWS\BaseTools\MailAuthentication\Smtp2goAuthenticationService() )->legacy_status();
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_smtp_auth_status_and_mailer function is already declared",true);

if (!function_exists(__NAMESPACE__ . '\\enable_auto_update_themes')) {
    function enable_auto_update_themes() {
        add_filter('auto_update_theme', '__return_true');
        return [
            'status' => true,
            'details' => 'Theme auto-updates are enabled.'
        ];
    }
} else {
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\enable_auto_update_themes function is already declared", true);
}



if (!function_exists(__NAMESPACE__ . '\\get_smtp_sending_domain')) {
    function get_smtp_sending_domain() {
        $sending_domain = '';

        // Check if the WP Mail SMTP plugin is active
        if (is_plugin_active('wp-mail-smtp/wp_mail_smtp.php')) {
            // Get the WP Mail SMTP options
            $wp_mail_smtp_options = get_option('wp_mail_smtp');

            // Ensure the from_email is set up
            if ($wp_mail_smtp_options && isset($wp_mail_smtp_options['mail']['from_email'])) {
                $from_email = $wp_mail_smtp_options['mail']['from_email'];
                $sending_domain = $from_email ? substr(strrchr($from_email, "@"), 1) : 'Domain not set';
            }
        }

        return $sending_domain;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\get_smtp_sending_domain function is already declared",true);


function hws_ct_highlight_based_on_criteria($setting, $fail_criteria = null) {

// Initialize the value
$raw_value = isset($setting['raw_value']) ? $setting['raw_value'] : null;
// Log if 'value' is not set or null
if ($raw_value === null) {
    write_log($setting['function'].": a raw_value has not set a value yet", true);
}
$status = true;


    if(isset($setting['status']))
    $status = $setting['status'];
    // Highlight the value based on the status
    if ($status === false || $status === 0 || $status === 'false' || $status === '0') {
        return "<span style='color: red;'>{$raw_value}</span>";
    }

    return $raw_value;
}
