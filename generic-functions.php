<?
if (!function_exists('check_plugin_status')) {
    // Function to check if a plugin is installed, active, and auto update is enabled
    function check_plugin_status($plugin) {
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin);
        $is_active = is_plugin_active($plugin);
        $auto_updates = get_option('auto_update_plugins', []);
        $is_auto_update_enabled = in_array($plugin, $auto_updates);
        return [$is_installed, $is_active, $is_auto_update_enabled];
    }
}

// Function to check if a user exists by login
if (!function_exists('does_user_exist')) {
    function does_user_exist($login) {
        return get_user_by('login', $login) !== false;
    }
}

// Function to check if a custom post type exists
if (!function_exists('does_post_type_exist')) {
    function does_post_type_exist($post_type) {
        return post_type_exists($post_type);
    }
}

// Function to check if a specified theme is active
if (!function_exists('is_theme_active')) {
    function is_theme_active($theme_name) {
        return wp_get_theme()->get('Name') === $theme_name;
    }
}

// Function to check if auto updates are enabled for a given theme
if (!function_exists('is_theme_auto_update_enabled')) {
    function is_theme_auto_update_enabled($theme_name) {
        $theme_updates = get_option('auto_update_themes', []);
        return in_array($theme_name, $theme_updates);
    }
}

// Display check status
if (!function_exists('display_check_status')) {
    function display_check_status($condition, $message) {
        $color = $condition ? 'green' : 'red';
        $icon = $condition ? '&#x2705;' : '&#x274C;';
        echo "<span style='color: $color;'>$icon $message</span>";
    }
}


// Function to check if a taxonomy exists
if (!function_exists('does_taxonomy_exist')) {
    function does_taxonomy_exist($taxonomy) {
        return taxonomy_exists($taxonomy);
    }
}

// Function to check if a term exists within a taxonomy
if (!function_exists('does_term_exist')) {
    function does_term_exist($term, $taxonomy) {
        $term_exists = term_exists($term, $taxonomy);
        return $term_exists !== 0 && $term_exists !== null;
    }
}