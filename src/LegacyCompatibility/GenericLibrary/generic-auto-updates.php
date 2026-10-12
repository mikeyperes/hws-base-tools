<?php

namespace hws_base_tools;

/**
 * Auto-Update All Plugins / Themes callbacks.
 *
 * WordPress installs automatic updates in background (cron) and WP-CLI runs,
 * never on a wp-admin page view, so this file is loaded on every request.
 */
if (!function_exists(__NAMESPACE__ . '\\enable_auto_update_plugins')) {
    function enable_auto_update_plugins() {
        add_filter('auto_update_plugin', function ($update, $item) {
            $blocked = get_option('gplvault_blocked_plugins', []);
            $file = is_object($item) && isset($item->plugin) ? $item->plugin : '';
            if ($file !== '' && is_array($blocked) && in_array($file, $blocked, true)) {
                return false;
            }
            return true;
        }, 10, 2);
    }
}

if (!function_exists(__NAMESPACE__ . '\\enable_auto_update_themes')) {
    function enable_auto_update_themes() {
        add_filter('auto_update_theme', '__return_true');
        return [
            'status' => true,
            'details' => 'Theme auto-updates are enabled.'
        ];
    }
}
