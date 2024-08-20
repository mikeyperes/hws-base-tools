<?php
/**
 * Check if the given plugin is installed, active, and auto-update is enabled.
 * 
 * @param string $plugin The plugin's folder/plugin-file name (e.g., 'plugin-directory/plugin-file.php').
 * @return array An array containing three boolean values: 
 *               - Whether the plugin is installed
 *               - Whether the plugin is active
 *               - Whether the plugin's auto-update is enabled
 */
if (!function_exists('check_plugin_status')) {
    function check_plugin_status($plugin) {
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin);
        $is_active = is_plugin_active($plugin);
        $auto_updates = get_option('auto_update_plugins', []);
        $is_auto_update_enabled = in_array($plugin, $auto_updates);
        return [$is_installed, $is_active, $is_auto_update_enabled];
    }
}

/**
 * Check if a user exists by login name.
 * 
 * @param string $login The login name of the user.
 * @return bool True if the user exists, false otherwise.
 */
if (!function_exists('does_user_exist')) {
    function does_user_exist($login) {
        return get_user_by('login', $login) !== false;
    }
}

/**
 * Check if a custom post type exists.
 * 
 * @param string $post_type The custom post type name.
 * @return bool True if the post type exists, false otherwise.
 */
if (!function_exists('does_post_type_exist')) {
    function does_post_type_exist($post_type) {
        return post_type_exists($post_type);
    }
}

/**
 * Check if a specified theme is currently active.
 * 
 * @param string $theme_name The name of the theme.
 * @return bool True if the theme is active, false otherwise.
 */
if (!function_exists('is_theme_active')) {
    function is_theme_active($theme_name) {
        return wp_get_theme()->get('Name') === $theme_name;
    }
}

/**
 * Check if auto-updates are enabled for a specified theme.
 * 
 * @param string $theme_name The name of the theme.
 * @return bool True if auto-updates are enabled, false otherwise.
 */
if (!function_exists('is_theme_auto_update_enabled')) {
    function is_theme_auto_update_enabled($theme_name) {
        $theme_updates = get_option('auto_update_themes', []);
        return in_array($theme_name, $theme_updates);
    }
}

/**
 * Display the status of a condition with a message and colored icon.
 * 
 * @param bool $condition The condition to evaluate.
 * @param string $message The message to display.
 */
if (!function_exists('display_check_status')) {
    function display_check_status($condition, $message) {
        $color = $condition ? 'green' : 'red';
        $icon = $condition ? '&#x2705;' : '&#x274C;';
        echo "<span style='color: $color;'>$icon $message</span>";
    }
}

/**
 * Check if a taxonomy exists.
 * 
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the taxonomy exists, false otherwise.
 */
if (!function_exists('does_taxonomy_exist')) {
    function does_taxonomy_exist($taxonomy) {
        return taxonomy_exists($taxonomy);
    }
}

/**
 * Check if a term exists within a specified taxonomy.
 * 
 * @param string $term The term to check.
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the term exists in the taxonomy, false otherwise.
 */
if (!function_exists('does_term_exist')) {
    function does_term_exist($term, $taxonomy) {
        $term_exists = term_exists($term, $taxonomy);
        return $term_exists !== 0 && $term_exists !== null;
    }
}

/**
 * Add a "Verified Profiles" settings page under the "Settings" menu in WordPress admin.
 */
if (!function_exists('add_verified_profiles_menu')) {
    function add_verified_profiles_menu() {
        add_options_page(
            'Verified Profiles',   // Page title
            'Verified Profiles',   // Menu title
            'manage_options',      // Capability required to access this page
            'verified-profiles',   // Menu slug
            'verified_profiles_page' // Callback function to display the page content
        );
    }
}

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
if (!function_exists('is_acf_field_group_imported')) {
    function is_acf_field_group_imported($key) {
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
if (!function_exists('add_settings_menu')) {
    function add_settings_menu($page_title, $menu_title, $capability, $menu_slug, $callback_function) {
        add_options_page(
            $page_title,      // Page title
            $menu_title,      // Menu title
            $capability,      // Capability required to access this page
            $menu_slug,       // Menu slug
            $callback_function // Callback function to display the page content
        );
    }
}


if (!function_exists('check_wp_mail_smtp_authentication')) {
    function check_wp_mail_smtp_authentication() {
        // Default result
        $status = false;
        $details = 'WP Mail SMTP plugin is not active.';

        // Check if the WP Mail SMTP plugin is active
        if (is_plugin_active('wp-mail-smtp/wp_mail_smtp.php')) {

            // Get the WP Mail SMTP options
            $wp_mail_smtp_options = get_option('wp_mail_smtp');

            // Check if SMTP is enabled
            if ($wp_mail_smtp_options && $wp_mail_smtp_options['mail']['mailer'] === 'smtp') {

                // Check if SMTP authentication is enabled
                $smtp_auth = isset($wp_mail_smtp_options['mail']['smtp']['auth']) ? $wp_mail_smtp_options['mail']['smtp']['auth'] : false;

                if ($smtp_auth) {
                    $status = true;
                    $details = 'SMTP authentication is enabled and configured properly.';
                } else {
                    $details = 'SMTP authentication is not enabled.';
                }

            } else {
                $details = 'SMTP is not the selected mailer in WP Mail SMTP settings.';
            }

        }

        // Return the result as an array
        return [
            'status' => $status,
            'details' => $details
        ];
    }
}


if (!function_exists('check_wp_cache_enabled')) {
    function check_wp_cache_enabled() {
        return defined('WP_CACHE') && WP_CACHE;
    }
}

if (!function_exists('check_memory_limits')) {
    function check_memory_limits() {
        $memory_limit = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined';
        $max_memory_limit = defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'Not defined';
        $elementor_memory_limit = defined('ELEMENTOR_MEMORY_LIMIT') ? ELEMENTOR_MEMORY_LIMIT : 'Not defined';

        $memory_limit_ok = wp_convert_hr_to_bytes($memory_limit) >= 512 * 1024 * 1024;
        $max_memory_limit_ok = wp_convert_hr_to_bytes($max_memory_limit) >= 512 * 1024 * 1024;
        $elementor_memory_limit_ok = wp_convert_hr_to_bytes($elementor_memory_limit) >= 512 * 1024 * 1024;

        return [
            'memory_limit' => $memory_limit,
            'memory_limit_ok' => $memory_limit_ok,
            'max_memory_limit' => $max_memory_limit,
            'max_memory_limit_ok' => $max_memory_limit_ok,
            'elementor_memory_limit' => $elementor_memory_limit,
            'elementor_memory_limit_ok' => $elementor_memory_limit_ok,
        ];
    }
}

if (!function_exists('check_wp_debug_disabled')) {
    function check_wp_debug_disabled() {
        return defined('WP_DEBUG') && !WP_DEBUG;
    }
}

if (!function_exists('check_log_file_sizes')) {
    function check_log_file_sizes() {
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $error_log_path = ABSPATH . 'error_log';

        $debug_log_size = file_exists($debug_log_path) ? filesize($debug_log_path) : 0;
        $error_log_size = file_exists($error_log_path) ? filesize($error_log_path) : 0;

        $debug_log_size_ok = $debug_log_size < 50 * 1024 * 1024;
        $error_log_size_ok = $error_log_size < 50 * 1024 * 1024;

        return [
            'debug_log_size_ok' => $debug_log_size_ok,
            'error_log_size_ok' => $error_log_size_ok,
            'debug_log_size' => size_format($debug_log_size),
            'error_log_size' => size_format($error_log_size),
        ];
    }
}

if (!function_exists('check_server_is_litespeed')) {
    function check_server_is_litespeed() {
        return strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }
}

if (!function_exists('check_php_version')) {
    function check_php_version() {
        return version_compare(PHP_VERSION, '8.3', '>=');
    }
}

if (!function_exists('check_php_sapi_is_litespeed')) {
    function check_php_sapi_is_litespeed() {
        return strpos(php_sapi_name(), 'litespeed') !== false;
    }
}

if (!function_exists('display_precheck_result')) {
    function display_precheck_result($label, $status, $details = '') {
        $color = $status ? 'green' : 'red';
        $icon = $status ? '&#x2705;' : '&#x274C;';
        $details_html = $details ? "<span style='color: gray; font-size: 12px;'>$details</span>" : '';
        echo "<div style='color: $color; margin-bottom: 10px;'><strong>$label:</strong> $icon $details_html</div>";
    }
}
?>