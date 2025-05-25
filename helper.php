<?php if (!function_exists('check_wp_log_size')) {
    function check_wp_log_size()
    {
        write_log("⚠️ WARNING: check_wp_log_size should NOT be called. This function is intended for internal use by the helper class to avoid crashes.", true);
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin)
    {
        write_log("⚠️ WARNING: is_plugin_active should be used carefully. Ensure it is called from the correct context.", true);
        return in_array($plugin, (array) get_option('active_plugins', array()), true) || is_plugin_active_for_network($plugin);
    }
}

if (!function_exists('is_plugin_active_for_network')) {
    function is_plugin_active_for_network($plugin)
    {
        write_log("⚠️ WARNING: is_plugin_active_for_network should be used carefully. Ensure it is called from the correct context.", true);
        
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[$plugin])) {
            return true;
        }

        return false;
    }
}
