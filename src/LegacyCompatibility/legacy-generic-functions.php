<?php namespace hws_base_tools;

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


if (!function_exists(__NAMESPACE__ . '\\check_php_ini_status')) {
    function check_php_ini_status($setting_name) {
        $ini_value = ini_get($setting_name);

        if ($ini_value !== false) {
            // Check if it's a boolean-like setting (e.g., "Off", "On")
            if (strtolower($ini_value) === 'off' || strtolower($ini_value) === 'on') {
                return strtolower($ini_value) === 'on' ? 'true' : 'false';
            }

            // Check for known constants like E_ALL and return their names instead of the numeric value
            if ($setting_name === 'error_reporting') {
                switch ((int) $ini_value) {
                    case E_ALL:
                        return 'E_ALL';
                    case E_ERROR:
                        return 'E_ERROR';
                    case E_WARNING:
                        return 'E_WARNING';
                    case E_PARSE:
                        return 'E_PARSE';
                    case E_NOTICE:
                        return 'E_NOTICE';
                    case E_STRICT:
                        return 'E_STRICT';
                    case E_RECOVERABLE_ERROR:
                        return 'E_RECOVERABLE_ERROR';
                    case E_DEPRECATED:
                        return 'E_DEPRECATED';
                    case E_USER_DEPRECATED:
                        return 'E_USER_DEPRECATED';
                    default:
                        return $ini_value; // Return the numerical value if it's not a known constant
                }
            }

            // Return the actual ini value for other settings
            return $ini_value;
        } else {
            return 'undefined';
        }
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_php_ini_status function is already declared", true);



if (!function_exists(__NAMESPACE__ . '\\check_wp_config_constant_status')) {
    function check_wp_config_constant_status($constant_name) {
        return \Hexa\PluginCore\WpConfigFile\WpConfigFile::constant_status( (string) $constant_name );
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_wp_config_constant_status function is already declared", true);



if (!function_exists(__NAMESPACE__ . '\\check_wordpress_memory_limit')) {
    function check_wordpress_memory_limit() {
        // Check if WP_MEMORY_LIMIT is defined
        if (defined('WP_MEMORY_LIMIT')) {
            $memory_limit = WP_MEMORY_LIMIT;
            $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);

            // Check if the memory limit is greater than 10MB (10 * 1024 * 1024 bytes)
            $status = $memory_limit_bytes > 1000 * 1024 * 1024;

            // Log for debugging
            write_log("Memory limit: {$memory_limit}, Converted to bytes: {$memory_limit_bytes}, Status: " . ($status ? 'true' : 'false'));

            return [
                'status' => $status, // true if greater than 10MB, false otherwise
                'raw_value' => $memory_limit
            ];
        } else {
            // Log for debugging
            write_log("Memory limit not defined.");

            // Return false as the memory limit is not defined
            return [
                'status' => false, // false as memory limit is not defined
                'raw_value' => 'Not defined'
            ];
        }
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_wordpress_memory_limit function is already declared", true);


if (!function_exists(__NAMESPACE__ . '\\get_database_table_prefix')) {
    function get_database_table_prefix() {
        global $table_prefix;

        $status = !empty($table_prefix);

        return [
            'status' => $status,
            'raw_value' => $status ? $table_prefix : 'Not available'
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\get_database_table_prefix function is already declared", true);








if (!function_exists(__NAMESPACE__ . '\\check_myisam_tables')) {
    function check_myisam_tables() {
        global $wpdb;

        // Get the current database prefix
        $current_prefix = $wpdb->prefix;

        // Check for valid prefix
        if (empty($current_prefix)) {
            return [
                'function' => "check_myisam_tables",
                'status' => false,
                'raw_value' => 'Unable to determine current database prefix.'
            ];
        }

        // Get MyISAM tables ONLY for current prefix
        $myisam_tables = $wpdb->get_results($wpdb->prepare("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = %s
            AND ENGINE = 'MyISAM'
            AND TABLE_NAME LIKE %s
        ", DB_NAME, $current_prefix . '%'));

        $table_names = [];
        foreach ($myisam_tables as $table) {
            $table_names[] = $table->TABLE_NAME;
        }

        // Determine status - FALSE if MyISAM tables found (makes it RED)
        $has_myisam = !empty($table_names);
        $status = !$has_myisam; // true = good (no MyISAM), false = bad (has MyISAM, show red)

        // Prepare details
        if ($has_myisam) {
            $count = count($table_names);
            $details = $count . ' MyISAM tables found: ' . implode(', ', $table_names);
        } else {
            $details = 'No MyISAM tables found.';
        }

        return [
            'function' => "check_myisam_tables",
            'status' => $status,
            'raw_value' => $details
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_myisam_tables function is already declared", true);








if (!function_exists(__NAMESPACE__ . '\\get_wp_version_from_file')) {
    function get_wp_version_from_file($file_path) {
        $version = 'Unknown';
        if (file_exists($file_path)) {
            $file_content = file_get_contents($file_path);
            if (preg_match('/\$wp_version = \'([^\']+)\'/', $file_content, $matches)) {
                $version = $matches[1];
            }
        }
        return $version;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\get_wp_version_from_file function is already declared", true);

if (!function_exists(__NAMESPACE__ . '\\detect_additional_wp_installs')) {
    function detect_additional_wp_installs() {
        global $wpdb;

        // Get the current database prefix
        $current_prefix_info = get_database_table_prefix();
        $current_prefix = $current_prefix_info['details'];

        // Get all prefixes from the database
        $prefixes = $wpdb->get_col("
            SELECT DISTINCT LEFT(TABLE_NAME, LOCATE('_', TABLE_NAME) - 1) as prefix
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND LOCATE('_', TABLE_NAME) > 0
        ");

        // Filter out the current prefix
        $additional_prefixes = array_filter($prefixes, function($prefix) use ($current_prefix) {
            return $prefix !== $current_prefix;
        });

        $installations = [];

        foreach ($additional_prefixes as $prefix) {
            // Assume default WordPress paths for additional installations
            $potential_paths = [
                $_SERVER['DOCUMENT_ROOT'] . '/' . $prefix,
                $_SERVER['DOCUMENT_ROOT'] . '/' . $prefix . '/public_html'
            ];

            foreach ($potential_paths as $path) {
                if (file_exists($path . '/wp-includes/version.php')) {
                    $version_file = $path . '/wp-includes/version.php';
                    $version = get_wp_version_from_file($version_file);
                    $installations[] = [
                        'prefix' => $prefix,
                        'url' => site_url(str_replace($_SERVER['DOCUMENT_ROOT'], '', $path)),
                        'version' => $version
                    ];
                    break;  // Stop searching once we've found a valid installation path
                }
            }
        }

        $status = !empty($installations);

        // Prepare the details string
        $details = $status ? '<p style="color: red;">&#x274C; ' . count($installations) . ' additional WordPress installs detected:</p>' : '<p style="">&#x2705; No additional WordPress installs detected.</p>';

        foreach ($installations as $install) {
            $details .= '<p style="color: red;">' . $install['prefix'] . ' - <a href="' . esc_url($install['url']) . '" target="_blank">' . esc_html($install['url']) . '</a> - WordPress Version: ' . esc_html($install['version']) . '</p>';
        }

        return [
            'function' => 'detect_additional_wp_installs',
            'status' => $status,
            'raw_value' => $details
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\detect_additional_wp_installs function is already declared", true);




if (!function_exists(__NAMESPACE__ . '\\check_server_memory_limit')) {
    function check_server_memory_limit() {
        // Use safe memory info getter
        $source = '';
        if ( function_exists( __NAMESPACE__ . '\\hws_get_memory_info' ) ) {
            $memory = hws_get_memory_info();
            $total_ram = $memory['total'] ?? 0;
            $source = $memory['source'] ?? '';
        } else {
            // Fallback to safe shell_exec
            $total_ram = 0;
            if ( function_exists( __NAMESPACE__ . '\\hws_safe_shell_exec' ) ) {
                $result = hws_safe_shell_exec( "free -b 2>/dev/null | awk '/^Mem:/{print $2}'" );
                $total_ram = $result ? (int) $result : 0;
            } elseif ( function_exists( 'shell_exec' ) ) {
                $total_ram = (int) @trim( shell_exec( "free -b | awk '/^Mem:/{print $2}'" ) );
            }
        }

        $status = $total_ram >= 4 * 1024 * 1024 * 1024; // Check if RAM is at least 4GB

        return [
            'status' => $status,
            'raw_value' => $total_ram ? size_format($total_ram) . hws_format_resource_source_note($source) : 'Not available'
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_server_memory_limit function is already declared",true);




if (!function_exists(__NAMESPACE__ . '\\check_redis_active')) {
    function check_redis_active() {
        $status = false;
        $details = 'Redis extension is not installed or not enabled';

        // Check if the Redis class exists, meaning the extension is loaded
        if (class_exists('Redis')) {
            try {
                // Initialize Redis object using the global namespace
                $redis = new \Redis();

                // Attempt to connect to Redis server WITH TIMEOUT (2 seconds)
                // This prevents hanging if Redis is not responding
                $connected = @$redis->connect('127.0.0.1', 6379, 2.0);

                if ($connected) {
                    // Set read timeout to prevent hanging on operations
                    $redis->setOption(\Redis::OPT_READ_TIMEOUT, 2);

                    // Test setting and getting a value
                    $redis->set("test-key", "Redis is working");
                    $test_value = $redis->get("test-key");

                    if ($test_value === "Redis is working") {
                        $status = true;

                        // Get Redis server information
                        $redis_info = $redis->info();

                        // Gather relevant details from the Redis server with proper formatting
                        $details = "Redis is working<br>" .
                        "<i>Server Version: " . $redis_info['redis_version'] . "<br>" .
                        "Port: " . $redis_info['tcp_port'] . "<br>" .
                        "Database: " . $redis->getDBNum() . "<br>" .
                        "Used Memory: " . $redis_info['used_memory_human'] . "<br>" .
                        "Peak Memory Used: " . $redis_info['used_memory_peak_human'] . "<br>" .
                        "Uptime: " . $redis_info['uptime_in_seconds'] . " seconds<br>" .
                        "Total Connections Received: " . $redis_info['total_connections_received'] . "<br>" .
                        "Total Commands Processed: " . $redis_info['total_commands_processed'] . "<br>" .
                        "<strong>Disclaimer:</strong> Just because Redis is active and working does not mean it is currently being used.<br> " .
                        "<a target=_blank href='" . admin_url('admin.php?page=litespeed-cache') . "'>View more info in LiteSpeed</a></i>";

                    } else {
                        $details = 'Redis connection successful, but failed to set/get a value';
                    }
                } else {
                    $details = 'Redis connection failed (timeout or refused)';
                }
            } catch (\RedisException $e) {
                $details = 'Redis error: ' . $e->getMessage();
            } catch (\Exception $e) {
                $details = 'Exception: ' . $e->getMessage();
            }
        }

        // Log the results for debugging purposes
        write_log('Redis check: ' . ($status ? 'Active' : 'Inactive'));

        return [
            'status' => $status,
            'raw_value' => $details
        ];
    }
} else {
    write_log("Warning: check_redis_active function is already declared", true);
}




if (!function_exists(__NAMESPACE__ . '\\check_server_ram')) {
    function check_server_ram() {
        // Use safe memory info getter if available
        $source = '';
        if ( function_exists( __NAMESPACE__ . '\\hws_get_memory_info' ) ) {
            $memory = hws_get_memory_info();
            $total_ram = $memory['total'] ?? 0;
            $source = $memory['source'] ?? '';
        } else {
            $total_ram = 0;
            if ( function_exists( __NAMESPACE__ . '\\hws_safe_shell_exec' ) ) {
                $result = hws_safe_shell_exec( "free -m 2>/dev/null | awk '/^Mem:/{print $2}'" );
                $total_ram = $result ? (int) $result * 1024 * 1024 : 0;
            } elseif ( function_exists( 'shell_exec' ) ) {
                $total_ram = (int) @trim( shell_exec( "free -m | awk '/^Mem:/{print $2}'" ) ) * 1024 * 1024;
            }
        }

        $status = $total_ram >= 4 * 1024 * 1024 * 1024; // Check if RAM is at least 4GB

        return [
            'status' => $status,
            'details' => $total_ram ? size_format($total_ram) . hws_format_resource_source_note($source) : 'Not available'
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_server_ram function is already declared",true);


if (!function_exists(__NAMESPACE__ . '\\check_wp_debug_disabled')) {
    function check_wp_debug_disabled() {
        return defined('WP_DEBUG') && !WP_DEBUG;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_wp_debug_disabled function is already declared",true);




if (!function_exists(__NAMESPACE__ . '\\check_log_file_sizes')) {
    function check_log_file_sizes() {
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $error_log_path = ABSPATH . 'error_log';

        // Check if the log files exist and get their sizes or show "Not Found"
        $debug_log_size = file_exists($debug_log_path) ? filesize($debug_log_path) : 'Not Found';
        $error_log_size = file_exists($error_log_path) ? filesize($error_log_path) : 'Not Found';

        // Determine the status based on whether the log files exceed 10KB, while ensuring "Not Found" cases do not set status to false
        $debug_log_status = ($debug_log_size === 'Not Found') ? null : (is_numeric($debug_log_size) && $debug_log_size <= 25 * 1000 * 1024);
        $error_log_status = ($error_log_size === 'Not Found') ? null : (is_numeric($error_log_size) && $error_log_size <= 25 * 1000 * 1024);

        return [
            'debug_log' => [
                'status' => $debug_log_status,
                'raw_value' => is_numeric($debug_log_size) ? size_format($debug_log_size) : 'Not Found'
            ],
            'error_log' => [
                'status' => $error_log_status,
                'raw_value' => is_numeric($error_log_size) ? size_format($error_log_size) : 'Not Found'
            ]
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_log_file_sizes function is already declared", true);



if (!function_exists(__NAMESPACE__ . '\\check_server_is_litespeed')) {
    function check_server_is_litespeed() {
        return strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_server_is_litespeed function is already declared",true);



if (!function_exists(__NAMESPACE__ . '\\check_php_sapi_is_litespeed')) {
    function check_php_sapi_is_litespeed() {
        return strpos(php_sapi_name(), 'litespeed') !== false;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_php_sapi_is_litespeed function is already declared",true);


if (!function_exists(__NAMESPACE__ . '\\display_precheck_result')) {
    function display_precheck_result($label, $status, $details = '') {
        $color = $status ? 'green' : 'red';
        $icon = $status ? '&#x2705;' : '&#x274C;';
        $details_html = $details ? "<span style='color: gray; font-size: 12px;'>$details</span>" : '';
        echo "<div style='color: $color; margin-bottom: 10px;'><strong>$label:</strong> $icon $details_html</div>";
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\display_precheck_result function is already declared",true);



if (!function_exists(__NAMESPACE__ . '\\check_imagick_available')) {
    function check_imagick_available() {
        // Check if the Imagick extension is loaded
        $is_available = extension_loaded('imagick');

        // Set status and raw_value based on availability
        $status = $is_available ? true : false;
        $raw_value = $is_available ? 'true' : 'false';

        // Return results in the required format
        return [
            'function' => 'check_imagick_available',
            'status' =>  $status,
            'raw_value' => $raw_value
        ];
    }
} else
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_imagick_available function is already declared", false);



if (!function_exists(__NAMESPACE__ . '\\check_query_monitor_status')) {
    function check_query_monitor_status() {
        // Check the status of Query Monitor plugin using the generic function
        list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status('query-monitor/query-monitor.php');
        return [
            'is_installed' => $is_installed,
            'is_active' => $is_active,
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_query_monitor_status function is already declared",true);




if ( ! function_exists( __NAMESPACE__ . '\\hws_format_resource_source_note' ) ) {
    function hws_format_resource_source_note( $source ) {
        if ( ! is_string( $source ) || '' === $source ) {
            return '';
        }

        $label = false !== strpos( $source, 'host-visible' )
            ? 'host-visible, not account limit'
            : $source;

        return ' <span style="color:#646970;font-size:11px;">(' . esc_html( $label ) . ')</span>';
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\hws_format_cpu_value' ) ) {
    function hws_format_cpu_value( $value ) {
        if ( null === $value || 'Unknown' === $value ) {
            return 'Unknown';
        }

        return (string) ( is_float( $value ) ? rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) : $value );
    }
}

if(!function_exists('hws_base_tools\check_server_specs')) {
    function check_server_specs() {
        // Use safe wrappers if available
        $num_processors = 'Unknown';
        $total_ram = 'Unknown';
        $cpu_source = '';
        $memory_source = '';

        if ( function_exists( __NAMESPACE__ . '\\hws_get_cpu_info' ) ) {
            $cpu = hws_get_cpu_info();
            $num_processors = null !== ( $cpu['count'] ?? null ) ? hws_format_cpu_value( $cpu['count'] ) : 'Unknown';
            $cpu_source = $cpu['source'] ?? '';
        } elseif ( function_exists( __NAMESPACE__ . '\\hws_get_cpu_count' ) ) {
            $cpu = hws_get_cpu_count();
            $num_processors = $cpu !== null ? hws_format_cpu_value( $cpu ) : 'Unknown';
        } elseif ( function_exists( __NAMESPACE__ . '\\hws_safe_shell_exec' ) ) {
            $result = hws_safe_shell_exec( 'nproc 2>/dev/null' );
            $num_processors = $result ?: 'Unknown';
        } elseif ( function_exists( 'shell_exec' ) ) {
            $num_processors = @trim( shell_exec( 'nproc' ) ) ?: 'Unknown';
        }

        if ( function_exists( __NAMESPACE__ . '\\hws_get_memory_info' ) ) {
            $memory = hws_get_memory_info();
            $total_ram = $memory['total'] ? round( $memory['total'] / 1024 / 1024 ) : 'Unknown';
            $memory_source = $memory['source'] ?? '';
        } elseif ( function_exists( __NAMESPACE__ . '\\hws_safe_shell_exec' ) ) {
            $result = hws_safe_shell_exec( "free -m 2>/dev/null | awk '/^Mem:/{print $2}'" );
            $total_ram = $result ?: 'Unknown';
        } elseif ( function_exists( 'shell_exec' ) ) {
            $total_ram = @trim( shell_exec( "free -m | awk '/^Mem:/{print $2}'" ) ) ?: 'Unknown';
        }

        // Clean up the results (in case of fallback)
        $num_processors = trim( (string) $num_processors );
        $total_ram = trim( (string) $total_ram );

        // Set the status
        $status = ($num_processors !== 'Unknown' && $total_ram !== 'Unknown');

        // Return the result in the expected structure
        return [
            'function' => 'check_server_specs',
            'status' => true,
            'raw_value' => "Number of processors: $num_processors" . hws_format_resource_source_note( $cpu_source ) . ", Total RAM: $total_ram MB" . hws_format_resource_source_note( $memory_source )
        ];
    }}else
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_server_specs function is already declared", true);









if (!function_exists(__NAMESPACE__ . '\\check_wordfence_notification_email')) {
    function check_wordfence_notification_email() {
        global $wpdb;

        // Get the database prefix
        $prefix = $wpdb->prefix;

        // Construct the table name dynamically
        $table_name = $prefix . 'wfconfig';

        // Query the BLOB data from the `alertEmails` field in the dynamically generated table name
        $result = $wpdb->get_var($wpdb->prepare("SELECT `val` FROM `{$table_name}` WHERE `name` = %s", 'alertEmails'));

        // Debugging: Log the raw data
        write_log("Raw alertEmails data: " . print_r($result, true));

        // Check if the result is serialized or not
        $decoded_result = maybe_unserialize($result);

        // If it's serialized, decoded_result will be an array or string
        // If not, it will remain as is
        if ($decoded_result === false || is_string($decoded_result)) {
            $decoded_result = $result; // Use the original value if unserializing didn't work
        }

        // Debugging: Log the decoded result
        write_log("Decoded alertEmails data: " . print_r($decoded_result, true));

        // Handle both array and string cases
        if (is_array($decoded_result) && !empty($decoded_result)) {
            $emails = implode(', ', $decoded_result);
            write_log('Valid alert emails found: ' . $emails);
            return [
                'status' => true,
                'details' => $emails
            ];
        } elseif (is_string($decoded_result) && !empty($decoded_result)) {
            write_log('Valid single alert email found: ' . $decoded_result);
            return [
                'status' => true,
                'raw_value' => $decoded_result
            ];
        } else {
            write_log('No valid alert emails found.');
            return [
                'status' => false,
                'raw_value' => 'No valid alert emails found'
            ];
        }
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_wordfence_notification_email function is already declared",true);









if (!function_exists(__NAMESPACE__ . '\\check_wordpress_main_email')) {
    function check_wordpress_main_email() {

        $admin_email = get_option('admin_email');

        // Ensure the email is retrieved and is a valid email address
        $value = !empty($admin_email) && is_email($admin_email);

        // Set the status based on the email presence and validity
        $status = $value ? true : false;

        return [
            'function' => "check_wordpress_main_email",
            'status' => $status,


            'raw_value' => $value ? $admin_email : 'Undefined'
        ];
    }
} else {
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_wordpress_main_email function is already declared", true);
}
/*
if (!function_exists(__NAMESPACE__ . '\\check_redis_active')) {
    function check_redis_active() {
        // Check if the Redis PHP extension is loaded
        $redis_extension_loaded = extension_loaded('redis');
        write_log('Redis extension loaded: ' . ($redis_extension_loaded ? 'Yes' : 'No'), true);

        // Initialize variables
        $redis_in_use = false;
        $redis_connected = false;

        if ($redis_extension_loaded) {
            try {
                // Create a new Redis instance and attempt to connect
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379); // Adjust the IP and port as necessary

                // Test the Redis connection
                $redis_connected = $redis->ping() === '+PONG';
                write_log('Redis connection successful: ' . ($redis_connected ? 'Yes' : 'No'), true);

                // Check if LiteSpeed Cache is configured to use Redis
                if (defined('LSCWP_V') && class_exists('LiteSpeed\Litespeed')) {
                    $lscwp = LiteSpeed\Litespeed::config();
                    $redis_in_use = ($lscwp->get_option('object_cache') === 'redis');
                    write_log('LiteSpeed Cache configured to use Redis: ' . ($redis_in_use ? 'Yes' : 'No'), true);
                } else {
                    write_log('LiteSpeed Cache not detected or not configured.', true);
                }
            } catch (Exception $e) {
                write_log('Redis connection failed: ' . $e->getMessage(), true);
            }
        } else {
            write_log('Redis extension not loaded, skipping connection test.', true);
        }

        // Determine the final status
        $status = $redis_extension_loaded && $redis_connected && $redis_in_use;
        write_log('Final Redis status: ' . ($status ? 'Active' : 'Inactive'), true);

        return [
            'status' => $status,
            'details' => $status ? 'Yes' : 'No'
        ];
    }
} else {
    write_log("Warning: check_redis_active function is already declared", true);
}
*/if (!function_exists(__NAMESPACE__ . '\\check_caching_source')) {
    function check_caching_source() {
        $caching_plugins = [
            'LiteSpeed Cache' => 'litespeed-cache/litespeed-cache.php',
            'W3 Total Cache' => 'w3-total-cache/w3-total-cache.php',
            'WP Super Cache' => 'wp-super-cache/wp-cache.php',
            'WP Rocket' => 'wp-rocket/wp-rocket.php',
            'Cache Enabler' => 'cache-enabler/cache-enabler.php',
            'Comet Cache' => 'comet-cache/comet-cache.php',
            'Swift Performance' => 'swift-performance-lite/swift-performance-lite.php'
        ];

        foreach ($caching_plugins as $name => $plugin_path) {
            if (is_plugin_active($plugin_path)) {
                return [
                    'status' => true,
                    'raw_value' => $name
                ];
            }
        }

        if (defined('LITESPEED_SERVER')) {
            return [
                'status' => true,
                'raw_value' => 'LiteSpeed Server'
            ];
        }

        return [
            'status' => false,
            'raw_value' => 'None'
        ];
    }
} else {
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_caching_source function is already declared", true);
}


if (!function_exists(__NAMESPACE__ . '\\check_php_version')) {
    function check_php_version() {
        // Get the current PHP version
        $php_version = phpversion();

        // Define the minimum required PHP version
        $min_version = PluginMetadata::REQUIRES_PHP;

        // Check if the current PHP version is less than the minimum required version
        $status = version_compare($php_version, $min_version, '>=') ? true : false;

        // Return the status and the PHP version with as much detail as possible
        return [
            'status' => $status,
            'raw_value' => $php_version
        ];
    }
} else {
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_php_version function is already declared", true);
}

if (!function_exists(__NAMESPACE__ . '\\check_caching_source')) {
    function check_caching_source() {
        $caching_plugins = [
            'LiteSpeed Cache' => 'litespeed-cache/litespeed-cache.php',
            'W3 Total Cache' => 'w3-total-cache/w3-total-cache.php',
            'WP Super Cache' => 'wp-super-cache/wp-cache.php',
            'WP Rocket' => 'wp-rocket/wp-rocket.php',
            'Redis Cache' => 'redis-cache/redis-cache.php',
            'Cache Enabler' => 'cache-enabler/cache-enabler.php',
            'Comet Cache' => 'comet-cache/comet-cache.php',
            'Swift Performance' => 'swift-performance-lite/swift-performance-lite.php'
        ];

        foreach ($caching_plugins as $name => $plugin_path) {
            if (is_plugin_active($plugin_path)) {
                return [
                    'status' => true,
                    'details' => $name
                ];
            }
        }

        if (check_redis_active()['status']) {
            return [
                'status' => true,
                'details' => 'Redis'
            ];
        }

        if (defined('LITESPEED_SERVER')) {
            return [
                'status' => true,
                'details' => 'LiteSpeed Cache'
            ];
        }

        return [
            'status' => false,
            'details' => 'None'
        ];
    }
}






/**
 * Modify or insert DEFINE-style constants in wp-config.php,
 * but if a constant is marked as type "ini", insert ini_set(name, value) instead.
 *
 * @param array $constants_to_update  Associative array of constants to update.
 *                                    Each value can be either:
 *                                      - A scalar (string/number/bool) → treated as a define(...)
 *                                      - An array with keys:
 *                                          'value' => scalar,
 *                                          'type'  => 'ini' (to use ini_set)
 *
 * @return array ['status' => bool, 'message' => string]
 */
if ( ! function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
    /**
     * Safely modify wp-config.php constants with backup and validation
     *
     * CRITICAL SAFETY FEATURES:
     * 1. Creates backup before any modifications
     * 2. Validates content before writing (must contain <?php and DB_NAME)
     * 3. Checks for preg_replace NULL returns (regex errors)
     * 4. Minimum content length check
     * 5. Restores backup if anything goes wrong
     *
     * @param array $constants_to_update Associative array of constant => value
     * @return array ['status' => bool, 'message' => string]
     */
    function modify_wp_config_constants( $constants_to_update ) {
        return \Hexa\PluginCore\WpConfigFile\WpConfigFile::modify_constants(
            (array) $constants_to_update,
            ABSPATH . 'wp-config.php',
            [
                'backup_path'           => ABSPATH . 'wp-config.php.hws-backup-' . time(),
                'permanent_backup_path' => ABSPATH . 'wp-config.php.hws-last-backup',
            ]
        );
    }
}





/** CODE TO TOUCH UP END ***/

function convert_to_bytes($value) {
    // Extract the numeric part and the unit (if any)
    if (preg_match('/^(\d+)([KMG]?)$/i', $value, $matches)) {
        $numeric_value = (int) $matches[1];
        $unit = strtoupper($matches[2]);

        // Convert based on the unit
        switch ($unit) {
            case 'G':
                return $numeric_value * 1024 * 1024 * 1024; // Convert GB to bytes
            case 'M':
                return $numeric_value * 1024 * 1024; // Convert MB to bytes
            case 'K':
                return $numeric_value * 1024; // Convert KB to bytes
            default:
                return $numeric_value; // Already in bytes
        }
    }
    // If the value does not match the pattern, return as is (consider as bytes)
    return (int) $value;
}

    // Helper function to convert various units to kilobytes (KB)
    function convert_to_kb($input_value) {
        $value = floatval($input_value); // Extract the numeric part
        $unit = strtolower(preg_replace('/[^a-zA-Z]/', '', $input_value)); // Extract the unit and make it case-insensitive

        switch ($unit) {
            case 'b':   // Bytes
                return $value / 1024;
            case 'kb':  // Kilobytes
            case 'k':   // Kilobytes
                return $value;
            case 'mb':  // Megabytes
            case 'm':   // Megabytes
                return $value * 1024;
            case 'gb':  // Gigabytes
            case 'g':   // G (short form for GB)
                return $value * 1024 * 1024;
            default:    // If no unit or an unrecognized unit is provided, assume KB
                return $value;
        }
    }

function hws_ct_package_constant_value_for_checks($constant_name, $constant_value, $fail_criteria = null) {
    // Initialize the value and status
    $status = true;
    $value = $constant_value;

    // Convert constant value to KB for comparison
    $converted_value = convert_to_kb($value);
// Check if fail_criteria is provided and not null
if ($fail_criteria !== null) {
    // Check for listed values
    if (isset($fail_criteria['listed_values']) && is_array($fail_criteria['listed_values'])) {
        foreach ($fail_criteria['listed_values'] as $fail_value) {
            if ($value === $fail_value) {
                $status = false;
                break;
            }
        }
    }
}

        // Check for min or max values, where constraints are in KB (numbers only)
        if (isset($fail_criteria['min_value'])) {
            $min_value = floatval($fail_criteria['min_value']); // Directly use KB number
            if ($converted_value < $min_value) {
                $status = false;
            }
        }
        if (isset($fail_criteria['max_value'])) {
            $max_value = floatval($fail_criteria['max_value']); // Directly use KB number
            if ($converted_value > $max_value) {
                $status = false;
            }
        }


    return [
        'function' => "hws_ct_package_constant_value_for_checks-{$constant_name}",
        'status' => $status,
        'raw_value' => $value
    ];
}


if (!function_exists(__NAMESPACE__ . '\\check_wp_core_auto_update_status')) {
function check_wp_core_auto_update_status() {
    $wp_auto_update_status = check_wp_config_constant_status('WP_AUTO_UPDATE_CORE');
    return $wp_auto_update_status === 'true';
}}


if (!function_exists(__NAMESPACE__ . '\\is_plugin_auto_update_enabled')) {
    function is_plugin_auto_update_enabled($plugin_id) {
        // Check if site-wide auto-updates are enabled
        if (has_filter('auto_update_plugin', '__return_true') !== false) {
            return true;
        }

        // Get the list of plugins with auto-updates enabled
        $auto_update_plugins = get_site_option('auto_update_plugins', []);

        // Check if the specific plugin has auto-updates enabled
        return in_array($plugin_id, $auto_update_plugins);
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\is_plugin_auto_update_enabled function is already declared", true);


if (!function_exists(__NAMESPACE__ . '\\is_theme_auto_update_enabled')) {
    function is_theme_auto_update_enabled($theme_slug) {
        // Log entry into the function
        write_log("Checking auto-update status for theme: {$theme_slug}");

        // Check if site-wide theme auto-updates are enabled
        if (has_filter('auto_update_theme', '__return_true') !== false) {
            write_log("Site-wide theme auto-updates are enabled.");
            return true;
        }

        // Get the list of themes with auto-updates enabled
        $auto_update_themes = get_site_option('auto_update_themes', []);

        // Log the retrieved list of themes with auto-updates enabled
        write_log("Auto-update enabled themes: " . implode(', ', $auto_update_themes));

        // Check if the specific theme has auto-updates enabled
        $is_enabled = in_array($theme_slug, $auto_update_themes);
        write_log("Auto-update status for {$theme_slug}: " . ($is_enabled ? 'Enabled' : 'Disabled'));

        return $is_enabled;
    }
} else  write_log("⚠️ Warning: " . __NAMESPACE__ . "\\is_theme_auto_update_enabled function is already declared", true);



// Function to check if plugin auto-updates are enabled
function check_plugin_auto_update_status() {
    // We check if the filter has been added
    return has_filter('auto_update_plugin', '__return_true') !== false;
}

// Function to render the "Enable Plugin Auto Updates" button
function render_enable_plugin_auto_updates_button() {
    if (!check_plugin_auto_update_status()) {
        echo "<button id='enable-plugin-auto-updates' class='button'>Enable Plugin Auto Updates</button>";
    }


}




    if (!function_exists(__NAMESPACE__ . '\\get_wp_config_defined_constants')) {
    function get_wp_config_defined_constants() {
        return \Hexa\PluginCore\WpConfigFile\WpConfigFile::defined_constants();
    }
}

// Check if Cloudflare is active and get nameservers
function check_cloudflare_active() {
    $details = [];
    $is_active = false;

    // — Method 1 (most reliable): Check for Cloudflare headers in $_SERVER
    //   CF sets these on every proxied request regardless of nameserver config
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $is_active = true;
        $details[] = 'CF-Connecting-IP header present';
    }
    if ( ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
        $is_active = true;
        $details[] = 'CF-Ray: ' . sanitize_text_field( $_SERVER['HTTP_CF_RAY'] );
    }

    // — Method 2: Self-request to check response headers
    if ( ! $is_active ) {
        $response = wp_remote_get( home_url( '/' ), [
            'timeout'   => 5,
            'sslverify' => false,
            'headers'   => [ 'Cache-Control' => 'no-cache' ],
        ] );
        if ( ! is_wp_error( $response ) ) {
            $cf_ray    = wp_remote_retrieve_header( $response, 'cf-ray' );
            $cf_cache  = wp_remote_retrieve_header( $response, 'cf-cache-status' );
            $server_hd = wp_remote_retrieve_header( $response, 'server' );
            if ( $cf_ray ) {
                $is_active = true;
                $details[] = 'CF-Ray: ' . $cf_ray;
            }
            if ( $cf_cache ) {
                $details[] = 'Cache: ' . $cf_cache;
            }
            if ( stripos( $server_hd, 'cloudflare' ) !== false ) {
                $is_active = true;
                $details[] = 'Server: cloudflare';
            }
        }
    }

    // — Method 3 (fallback): Check nameservers
    if ( ! $is_active ) {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $ns_records = @dns_get_record( $domain, DNS_NS );
        $ns_list = [];
        if ( is_array( $ns_records ) ) {
            foreach ( $ns_records as $ns ) {
                $ns_list[] = $ns['target'] ?? '';
                if ( stripos( $ns['target'] ?? '', 'cloudflare' ) !== false ) {
                    $is_active = true;
                }
            }
        }
        if ( ! empty( $ns_list ) ) {
            $details[] = 'NS: ' . implode( ', ', $ns_list );
        }
    }

    return [
        'status'    => $is_active,
        'raw_value' => $is_active
            ? 'Cloudflare active — ' . implode( ' · ', $details )
            : 'Not detected' . ( ! empty( $details ) ? ' (' . implode( ', ', $details ) . ')' : '' ),
    ];
}
// Check the type of PHP (CloudLinux or other)
function check_php_type() {
    $php_sapi = php_sapi_name();
    return [
        'status' => true, // This status would always be true since it's just informational
        'raw_value' => "PHP SAPI: $php_sapi"
    ];
}

if (!function_exists(__NAMESPACE__ . '\\check_php_handler')) {
    function check_php_handler() {
        // Initialize variables
        $php_handler = 'Unknown';
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown Server Software';
        $sapi_name = php_sapi_name();

        // Determine if PHP-FPM is active
        if (strpos($sapi_name, 'fpm-fcgi') !== false) {
            $php_handler = 'PHP-FPM';
            $is_fpm_active = true;
        } else {
            $is_fpm_active = false;
        }

        // Determine if the server is LiteSpeed
        if (strpos($server_software, 'LiteSpeed') !== false) {
            $server = 'LiteSpeed';
        } elseif (strpos($server_software, 'Apache') !== false) {
            $server = 'Apache';
        } elseif (strpos($server_software, 'Nginx') !== false) {
            $server = 'Nginx';
        } else {
            $server = $server_software; // Use whatever is reported
        }

        // Additional checks if PHP-FPM is not active
        if (!$is_fpm_active) {
            if (strpos($sapi_name, 'cgi') !== false || strpos($sapi_name, 'fcgi') !== false) {
                $php_handler = 'FastCGI';
            } elseif (strpos($sapi_name, 'litespeed') !== false) {
                $php_handler = 'LiteSpeed PHP Handler';
            } else {
                $php_handler = 'Unknown/Other';
            }
        }

        // Determine status and details for reporting
        $status = $is_fpm_active;
        $details = $is_fpm_active
            ? "PHP-FPM is active with $server as the web server"
            : "$php_handler is active with $server as the web server, not PHP-FPM";

        // Log the results for debugging
        write_log("Server Software: $server_software");
        write_log("SAPI Name: $sapi_name");
        write_log("PHP Handler: $php_handler");

        // Return the status and details
        return [
            'status' => true,
            'raw_value' => $details
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\enable_auto_update_plugins')) {
    function enable_auto_update_plugins() {
        add_filter('auto_update_plugin', '__return_true');
    }
}

if (!function_exists(__NAMESPACE__ . '\\disable_litespeed_js_combine')) {
    function disable_litespeed_js_combine() {
        add_filter('litespeed_optm_js_comb_ext_inl', '__return_false');
    }
}



function check_wp_backup_status($backup_directory = 'wp-content/ai1wm-backups/') {
    // Log the start of the function
    write_log("Checking for WP All-in-One backups in $backup_directory", false);

    // Get the absolute path to ensure correct directory handling
    $absolute_backup_directory = ABSPATH . $backup_directory;
    write_log("Checking directory: $absolute_backup_directory", false);

    // Initialize the variables for result
    $has_backup = false;
    $backup_report = '';
    $backup_files = [];

    // Check if the backup directory exists and is readable
    if (is_dir($absolute_backup_directory) && is_readable($absolute_backup_directory)) {
        // Scan the directory for .wpress files
        $backup_files = glob($absolute_backup_directory . '*.wpress');
    } else {
        write_log("Directory not found or unreadable: $absolute_backup_directory", false);
        $backup_status = 'Directory not found or unreadable';
        return [
            'function' => 'check_wp_backup_status',
            'status' => true, // Return true if no backups or the directory is missing
            'raw_value' => "<span>No backups found or directory inaccessible: $backup_directory</span>",
            'variables' => [
                'backup_directory' => $absolute_backup_directory,
                'backup_status' => 'Directory not found',
                'fail_status' => true,
                'backup_files' => []
            ]
        ];
    }

    // Handle cases where there are no backup files
    if (empty($backup_files)) {
        write_log("No backups found in $absolute_backup_directory", false);
        $backup_status = 'No Backups';
        $status_display = "<span>No backups found in $backup_directory</span>";
        $fail_status = true;
    } else {
        // Backups found, prepare the report
        $backup_status = '<br />Backups Found<br/>';
        $fail_status = false;
        write_log("Backups found in $absolute_backup_directory", false);

        // Generate the report with backup filenames, file sizes, and dates
        foreach ($backup_files as $backup_file) {
            $file_size = filesize($backup_file) / 1024 / 1024; // Size in MB
            $file_size = round($file_size, 2); // Round to 2 decimal places
            $file_date = date("F d Y H:i:s", filemtime($backup_file)); // Get file creation time
            $file_url = site_url(str_replace(ABSPATH, '', $backup_file)); // Generate the file URL

            // Add the file info to the report (filename, URL, size, and date)
            $backup_report .= "- ".basename($backup_file) . " | URL: <a href='$file_url'>$file_url</a> | Size: $file_size MB | Created: $file_date<br>";
        }

        $status_display = "<span>$backup_status: <br>$backup_report</span>";
    }

    // Return the final report and status
    return [
        'function' => 'check_wp_backup_status',
        'status' => $fail_status, // If no backups, return true; if backups found, return false
        'raw_value' => $status_display, // The full report with HTML formatting for backups
        'variables' => [
            'backup_directory' => $absolute_backup_directory,
            'backup_status' => $backup_status,
            'fail_status' => $fail_status,
            'backup_files' => $backup_files
        ]
    ];
}








// Function to perform the PHP INI check and return the result
function perform_php_ini_check($setting_name, $on_values = [1, '1', 'On', 'on', true,"true"], $off_values = [0, '0', 'Off', 'off', false,"false"], $fail_criteria = []) {
    write_log("Performing PHP INI check for $setting_name", false);

    // Use the helper function to get the ini value or constant
    $current_value = get_php_ini_value($setting_name);

    // Handle unknown values (when ini_get fails)
    if ($current_value === 'unknown') {
        write_log("Warning: $setting_name is not found via ini_get or defined constants", false);
        $current_status = 'Unknown';  // More user-friendly message for unknown values
    } else {
        // Convert the current value to an integer or string for comparison
        $normalized_value = is_numeric($current_value) ? (int) $current_value : strtolower($current_value);

        // Handle both ON/ENABLED and OFF/DISABLED states
        if (in_array($normalized_value, $on_values, true)) {
            $current_status = 'ENABLED';
        } elseif (in_array($normalized_value, $off_values, true)) {
            $current_status = 'DISABLED';
        } else {
            $current_status = 'Unknown'; // If the value doesn't match any expected states
        }

        write_log("Current status for $setting_name: $current_status", false);
    }

    // Check if the current value matches any fail criteria
    $fail_status = false;
    if (in_array($normalized_value, $fail_criteria, true)) {
        write_log("Fail criteria matched for $setting_name: $current_value", false);
        $fail_status = true;
    }

    // Combine the actual value and the status (e.g., '1 (DISABLED)')
    $display_value = ($current_value !== 'unknown') ? "$current_value ($current_status)" : "Unknown value ($current_status)";

    // Determine the status for highlighting (red for enabled, per your requirement)
    $status_display = $fail_status
        ? "<span>$display_value</span>"
        : "<span>$display_value</span>";
/*
    // Create a toggle button for the setting
    $toggle_button = ($current_status === 'DISABLED')
        ? "<button class='button execute-function block' data-method='toggle_php_ini_value' data-variable='$setting_name' data-setting='$setting_name' data-state='1' data-loader='true'>Enable $setting_name</button><br>"
        : "<button class='button execute-function block' data-method='toggle_php_ini_value' data-variable='$setting_name' data-setting='$setting_name' data-state='0' data-loader='true'>Disable $setting_name</button><br>";
*/

// Assume $current_status is already set to 'ENABLED', 'DISABLED', or 'Unknown'
//
// Output a real <button> element (not just text) to toggle display_errors
$toggle_button = ($current_status === 'DISABLED')
    ? "<button class='button modify-wp-config' data-type='ini' data-constant='display_errors' data-value='On' data-target='ini-error-reporting'>Enable display_errors</button><br>"
    : "<button class='button modify-wp-config' data-type='ini' data-constant='display_errors' data-value='Off' data-target='ini-error-reporting'>Disable display_errors</button><br>";

// Make sure you echo it so that the browser sees the <button> tag:


    // Generate the report with the current status, the actual value, and the toggle button
    $report = "$status_display<br>$toggle_button";

    // Return the final report and status
    return [
        'function' => 'perform_php_ini_check',
        'status' => !$fail_status, // Return false if fail criteria matched, otherwise true
        'raw_value' => $report, // The full report with HTML formatting for buttons
        'variables' => [
            'setting_name' => $setting_name,
            'current_value' => $current_value,
            'current_status' => $current_status,
            'on_value' => $on_values,
            'off_value' => $off_values,
            'fail_status' => $fail_status
        ]
    ];
}



// Helper function to get the value of a PHP setting, considering wp-config.php overrides
function get_php_ini_value($setting_name) {
    return \Hexa\PluginCore\WpConfigFile\WpConfigFile::get_php_ini_value(
        (string) $setting_name,
        ABSPATH . 'wp-config.php',
        __NAMESPACE__ . '\\write_log'
    );
}






if (!function_exists(__NAMESPACE__ . '\\toggle_php_ini_value')) {
    function toggle_php_ini_value($setting_name, $new_value) {
        return \Hexa\PluginCore\WpConfigFile\WpConfigFile::toggle_php_ini_value(
            (string) $setting_name,
            (string) $new_value,
            ABSPATH . 'wp-config.php',
            __NAMESPACE__ . '\\write_log',
            [
                'backup_path'           => ABSPATH . 'wp-config.php.hws-backup-' . time(),
                'permanent_backup_path' => ABSPATH . 'wp-config.php.hws-last-backup',
            ]
        );
    }
} else {
    write_log("Warning: hws_base_tools\toggle_php_ini_value is already declared.", true);
}















/**
 * Generate a nicely styled HTML output for one or more ACF field groups,
 * listing each field (with nested sub-fields if it’s a “group”) and then
 * the display conditions, all wrapped in semantic tags and inline CSS.
 *
 * @param string|array $group_keys A single ACF group key (e.g., 'group_65a8b18d98147')
 *                                 or an array of keys.
 * @return string HTML string with improved styling:
 *                • <div> wrapper per group
 *                • <h2> for group title/key
 *                • <ul> for fields, <li> for each field
 *                • Nested <ul> for sub-fields
 *                • <h3> for “Display Conditions”
 *                • <ul> for condition sets, nested <ul> for individual rules
 */
/**
 * Generate a detailed, recursive HTML hierarchy for one or more ACF field groups.
 * Shows field name, label, key, and type. Handles nested groups, repeaters, and
 * flexible content layouts recursively to any depth.
 *
 * @param string|array $group_keys  Single group key or array of group keys
 * @param bool         $deprecated  If true, shows with red background (pending delete)
 * @return string  HTML output
 */
/**
 * Returns a CLOSURE that renders ACF field group structure(s) as HTML.
 *
 * Returning a closure (instead of a string) ensures the ACF field groups are
 * looked up lazily — at render time on the admin page — rather than eagerly
 * at array-construction time inside get_snippets(), when the groups may not
 * yet be registered.
 *
 * Both snippet renderers (settings-dashboard-snippets.php and
 * settings-dashboard-website-types.php) already handle callable 'info' values
 * via is_callable() + call_user_func().
 *
 * @param string|array $group_keys  One ACF group key or an array of keys.
 * @param bool         $deprecated  Whether to style the box as deprecated.
 * @return callable                 Closure that returns HTML string when invoked.
 */
function display_acf_structure( $group_keys, $deprecated = false ) {
    // — Return a closure so it's evaluated lazily at render time
    return function() use ( $group_keys, $deprecated ) {
        // — Bail if ACF isn't active
        if ( ! function_exists( 'acf_get_field_group' ) ) {
            return '<em style="color:#999;">ACF not active — cannot display field structure.</em>';
        }

        $keys   = is_array( $group_keys ) ? $group_keys : [ $group_keys ];
        $output = '';

        foreach ( $keys as $group_key ) {
            // — Fetch the group object using the correct singular API
            //   acf_get_field_group( $key ) returns the group array or false
            //   (acf_get_field_groups() with a key filter does NOT work reliably)
            $group = acf_get_field_group( $group_key );
            if ( empty( $group ) ) {
                // — Group not registered — show a helpful fallback instead of nothing
                $output .= '<div style="border:1px solid #dba617;border-radius:6px;padding:10px;margin-bottom:12px;background:#fff8e5;font-size:12px;color:#6a5400;">'
                         . '⚠️ ACF group <code>' . esc_html( $group_key ) . '</code> not found. '
                         . 'Enable the snippet that registers it, or verify the group key.'
                         . '</div>';
                continue;
            }

            // — Background color based on deprecation
            $bg     = $deprecated ? 'rgba(255,0,0,0.08)' : '#f8f9fa';
            $border = $deprecated ? '#d63638' : '#ddd';

            // — Open group container
            $output .= '<div style="border:1px solid ' . $border . ';border-radius:6px;padding:14px;margin-bottom:16px;font-family:-apple-system,sans-serif;background:' . $bg . ';font-size:13px;">';

            // — Group title + key
            $output .= '<div style="font-weight:700;font-size:14px;margin-bottom:10px;color:#1d2327;">'
                     . esc_html( $group['title'] )
                     . ' <code style="background:#e0e0e0;padding:2px 6px;border-radius:3px;font-size:11px;color:#555;font-weight:400;">'
                     . esc_html( $group_key )
                     . '</code></div>';

            // — Render fields recursively
            $fields = acf_get_fields( $group_key );
            if ( ! empty( $fields ) ) {
                $output .= hws_render_acf_fields_recursive( $fields, 0 );
            } else {
                $output .= '<div style="color:#999;font-size:12px;font-style:italic;">No fields found in this group.</div>';
            }

            // — Display conditions (location rules)
            if ( isset( $group['location'] ) && is_array( $group['location'] ) && ! empty( $group['location'] ) ) {
                $output .= '<div style="margin-top:10px;padding-top:8px;border-top:1px solid #ddd;">';
                $output .= '<div style="font-weight:600;color:#646970;font-size:12px;margin-bottom:4px;">📍 Display Conditions</div>';
                foreach ( $group['location'] as $si => $rules ) {
                    $parts = [];
                    foreach ( $rules as $rule ) {
                        $parts[] = esc_html( ( $rule['param'] ?? '' ) . ' ' . ( $rule['operator'] ?? '' ) . ' ' . ( $rule['value'] ?? '' ) );
                    }
                    $output .= '<div style="font-size:12px;color:#888;margin-left:12px;">Set ' . ( $si + 1 ) . ': ' . implode( ' AND ', $parts ) . '</div>';
                }
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        return $output;
    };
}


/**
 * Recursively render ACF fields as a nested HTML list.
 * Handles: group, repeater, flexible_content (with layouts), and all leaf types.
 *
 * @param array $fields  Array of ACF field definitions
 * @param int   $depth   Current nesting depth (for indentation)
 * @return string HTML output
 */
function hws_render_acf_fields_recursive( array $fields, int $depth = 0 ): string {
    if ( empty( $fields ) ) return '';

    $indent = $depth * 16;
    $output = '<div style="margin-left:' . $indent . 'px;">';

    foreach ( $fields as $field ) {
        $name  = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $key   = $field['key'] ?? '';
        $type  = $field['type'] ?? '';

        // — Type badge color
        $type_color = '#646970';
        if ( in_array( $type, [ 'group', 'repeater', 'flexible_content' ], true ) ) {
            $type_color = '#2271b1';
        }

        // — Field row
        $output .= '<div style="padding:3px 0;border-bottom:1px solid #f0f0f1;display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">';

        // — Expand indicator for parent fields
        if ( in_array( $type, [ 'group', 'repeater', 'flexible_content' ], true ) ) {
            $output .= '<span style="color:#2271b1;font-weight:700;">▼</span>';
        } else {
            $output .= '<span style="color:#ddd;margin-left:2px;">·</span>';
        }

        // — Field name (bold)
        $output .= '<span style="font-weight:600;color:#1d2327;">' . esc_html( $name ) . '</span>';

        // — Label
        if ( $label && $label !== $name ) {
            $output .= '<span style="color:#646970;"> — ' . esc_html( $label ) . '</span>';
        }

        // — Type badge
        $output .= '<code style="background:#f0f0f1;padding:1px 5px;border-radius:3px;font-size:11px;color:' . $type_color . ';">' . esc_html( $type ) . '</code>';

        // — Field key
        $output .= '<code style="font-size:10px;color:#999;">' . esc_html( $key ) . '</code>';

        $output .= '</div>';

        // — Recurse into sub_fields (group, repeater)
        if ( in_array( $type, [ 'group', 'repeater' ], true ) && ! empty( $field['sub_fields'] ) ) {
            $output .= hws_render_acf_fields_recursive( $field['sub_fields'], $depth + 1 );
        }

        // — Recurse into flexible content layouts
        if ( $type === 'flexible_content' && ! empty( $field['layouts'] ) ) {
            foreach ( $field['layouts'] as $layout ) {
                $layout_label = $layout['label'] ?? $layout['name'] ?? 'Layout';
                $layout_key   = $layout['key'] ?? '';
                $output .= '<div style="margin-left:' . ( ( $depth + 1 ) * 16 ) . 'px;padding:3px 0;color:#8c5e00;font-size:12px;">';
                $output .= '📐 <strong>' . esc_html( $layout_label ) . '</strong>';
                if ( $layout_key ) {
                    $output .= ' <code style="font-size:10px;color:#999;">' . esc_html( $layout_key ) . '</code>';
                }
                $output .= '</div>';
                if ( ! empty( $layout['sub_fields'] ) ) {
                    $output .= hws_render_acf_fields_recursive( $layout['sub_fields'], $depth + 2 );
                }
            }
        }
    }

    $output .= '</div>';
    return $output;
}

/**
 * Returns a CLOSURE that renders CPT (Custom Post Type) structure as HTML.
 *
 * Same lazy-evaluation pattern as display_acf_structure():
 * the post type is looked up at render time when the admin page displays,
 * not at array-construction time inside get_snippets().
 *
 * @param string $cpt_slug  The registered post type slug (e.g. 'team-member').
 * @return callable          Closure that returns HTML string when invoked.
 */
function display_cpt_structure( string $cpt_slug ) {
    // — Return a closure so CPT lookup happens lazily at render time
    return function() use ( $cpt_slug ) {
        // — Get the post type object; if not registered, show helpful fallback
        $pt_obj = get_post_type_object( $cpt_slug );
        if ( ! $pt_obj ) {
            return '<div style="border:1px solid #dba617;border-radius:6px;padding:10px;margin-bottom:12px;background:#fff8e5;font-size:12px;color:#6a5400;">'
                 . '⚠️ CPT <code>' . esc_html( $cpt_slug ) . '</code> not registered. '
                 . 'Enable the snippet that registers it.'
                 . '</div>';
        }

        $output = '';

        // — Open CPT container
        $output .= '<div style="'
                 . 'border:1px solid #666;'
                 . 'border-radius:4px;'
                 . 'padding:16px;'
                 . 'margin-bottom:24px;'
                 . 'font-family:Arial, sans-serif;'
                 . 'background-color:#f5f5f5;'
                 . '">';

        // — CPT title and slug
        $output .= '<h2 style="'
                 . 'margin:0 0 12px;'
                 . 'font-size:1.25em;'
                 . 'color:#222;'
                 . '">'
                 . esc_html( $pt_obj->labels->name )
                 . ' <code style="'
                 . 'background:#e0e0e0;'
                 . 'padding:2px 4px;'
                 . 'border-radius:3px;'
                 . 'font-size:0.9em;'
                 . 'color:#444;'
                 . '">'
                 . esc_html( $cpt_slug )
                 . '</code>'
                 . '</h2>';

        // — 1) Labels Section
        $output .= '<h3 style="'
                 . 'margin:12px 0 6px;'
                 . 'font-size:1.1em;'
                 . 'color:#333;'
                 . '">'
                 . 'Labels'
                 . '</h3>';
        $output .= '<ul style="'
                 . 'list-style-type:disc;'
                 . 'margin:0 0 16px 20px;'
                 . 'padding:0;'
                 . '">';
        $label_props = [
            'singular_name', 'add_new_item', 'edit_item', 'view_item',
            'all_items', 'menu_name', 'archives', 'attributes',
            'insert_into_item', 'uploaded_to_this_item', 'filter_items_list',
            'search_items', 'not_found', 'not_found_in_trash',
            'items_list', 'items_list_navigation'
        ];
        foreach ( $label_props as $prop ) {
            if ( isset( $pt_obj->labels->$prop ) && $pt_obj->labels->$prop !== '' ) {
                $output .= '<li style="margin-bottom:6px;">'
                         . '<span style="font-weight:bold; color:#222;">'
                         . esc_html( $prop )
                         . '</span>: '
                         . '<span style="color:#555;">'
                         . esc_html( $pt_obj->labels->$prop )
                         . '</span>'
                         . '</li>';
            }
        }
        $output .= '</ul>';

        // — 2) Supports Section
        if ( ! empty( $pt_obj->supports ) ) {
            $output .= '<h3 style="'
                     . 'margin:12px 0 6px;'
                     . 'font-size:1.1em;'
                     . 'color:#333;'
                     . '">'
                     . 'Supported Features'
                     . '</h3>';
            $output .= '<ul style="'
                     . 'list-style-type:disc;'
                     . 'margin:0 0 16px 20px;'
                     . 'padding:0;'
                     . '">';
            foreach ( $pt_obj->supports as $support ) {
                $output .= '<li style="margin-bottom:6px; color:#555;">'
                         . esc_html( $support )
                         . '</li>';
            }
            $output .= '</ul>';
        }

        // — 3) Taxonomies Section
        if ( ! empty( $pt_obj->taxonomies ) ) {
            $output .= '<h3 style="'
                     . 'margin:12px 0 6px;'
                     . 'font-size:1.1em;'
                     . 'color:#333;'
                     . '">'
                     . 'Attached Taxonomies'
                     . '</h3>';
            $output .= '<ul style="'
                     . 'list-style-type:disc;'
                     . 'margin:0 0 16px 20px;'
                     . 'padding:0;'
                     . '">';
            foreach ( $pt_obj->taxonomies as $tax ) {
                $output .= '<li style="margin-bottom:6px; color:#555;">'
                         . esc_html( $tax )
                         . '</li>';
            }
            $output .= '</ul>';
        }

        // — 4) Flags & Arguments Section
        $output .= '<h3 style="'
                 . 'margin:12px 0 6px;'
                 . 'font-size:1.1em;'
                 . 'color:#333;'
                 . '">'
                 . 'Settings & Flags'
                 . '</h3>';
        $output .= '<ul style="'
                 . 'list-style-type:disc;'
                 . 'margin:0 0 0 20px;'
                 . 'padding:0;'
                 . '">';
        // — Public
        $output .= '<li style="margin-bottom:6px; color:#555;">'
                 . '<strong>public</strong>: '
                 . ( $pt_obj->public ? 'true' : 'false' )
                 . '</li>';
        // — Show in REST
        $show_in_rest = isset( $pt_obj->show_in_rest ) ? $pt_obj->show_in_rest : false;
        $output     .= '<li style="margin-bottom:6px; color:#555;">'
                     . '<strong>show_in_rest</strong>: '
                     . ( $show_in_rest ? 'true' : 'false' )
                     . '</li>';
        // — Menu Icon
        $menu_icon = isset( $pt_obj->menu_icon ) && $pt_obj->menu_icon !== ''
                     ? esc_html( $pt_obj->menu_icon )
                     : '—';
        $output  .= '<li style="margin-bottom:6px; color:#555;">'
                  . '<strong>menu_icon</strong>: '
                  . $menu_icon
                  . '</li>';
        // — Delete with user
        $del_with_user = isset( $pt_obj->delete_with_user ) && $pt_obj->delete_with_user
                         ? 'true'
                         : 'false';
        $output     .= '<li style="margin-bottom:6px; color:#555;">'
                     . '<strong>delete_with_user</strong>: '
                     . $del_with_user
                     . '</li>';
        $output .= '</ul>';

        // — Close CPT container
        $output .= '</div>';

        return $output;
    };
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REUSABLE INSTRUCTION BOX RENDERER
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_render_instructions' ) ) {
function hws_render_instructions( $title, $steps, $icon = '📋', $collapsed = true ) {
    $uid     = 'hws-instr-' . substr( md5( $title . count( $steps ) ), 0, 8 );
    $display = $collapsed ? 'none' : 'block';
    $arrow   = $collapsed ? '▶' : '▼';
    $html  = '<div class="hws-instruction-box" style="margin-top:12px;border:1px solid #c3d9f0;border-radius:6px;background:#f0f7ff;">';
    $html .= '<div onclick="(function(el){var b=document.getElementById(\'' . $uid . '\');var a=el.querySelector(\'.hws-instr-arrow\');if(b.style.display===\'none\'){b.style.display=\'block\';a.textContent=\'▼\';}else{b.style.display=\'none\';a.textContent=\'▶\';}})(this)" '
           . 'style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;user-select:none;">'
           . '<span class="hws-instr-arrow">' . $arrow . '</span> ' . $icon . ' ' . esc_html( $title )
           . '</div>';
    $html .= '<div id="' . $uid . '" style="display:' . $display . ';padding:0 14px 12px;">';
    $html .= '<ol style="margin:0;padding-left:20px;font-size:12.5px;line-height:1.8;color:#1d2327;">';
    foreach ( $steps as $step ) {
        $html .= '<li style="margin-bottom:4px;">' . $step . '</li>';
    }
    $html .= '</ol></div></div>';
    return $html;
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PHP EXTENSION / LIBRARY CHECK FOR WORDPRESS
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_php_extensions' ) ) {
function hws_check_php_extensions() {
    return [
        [ 'name' => 'mysqli',    'loaded' => extension_loaded('mysqli'),       'purpose' => 'MySQL database (required)',       'required' => true ],
        [ 'name' => 'curl',      'loaded' => extension_loaded('curl'),         'purpose' => 'HTTP requests / API calls',      'required' => true ],
        [ 'name' => 'json',      'loaded' => extension_loaded('json'),         'purpose' => 'JSON parsing (required)',         'required' => true ],
        [ 'name' => 'mbstring',  'loaded' => extension_loaded('mbstring'),     'purpose' => 'Multibyte string support',       'required' => true ],
        [ 'name' => 'openssl',   'loaded' => extension_loaded('openssl'),      'purpose' => 'SSL/TLS encryption',             'required' => true ],
        [ 'name' => 'xml',       'loaded' => extension_loaded('xml'),          'purpose' => 'XML parsing (RSS, sitemaps)',     'required' => true ],
        [ 'name' => 'dom',       'loaded' => extension_loaded('dom'),          'purpose' => 'DOM manipulation',               'required' => true ],
        [ 'name' => 'fileinfo',  'loaded' => extension_loaded('fileinfo'),     'purpose' => 'File type detection',            'required' => true ],
        [ 'name' => 'tokenizer', 'loaded' => extension_loaded('tokenizer'),    'purpose' => 'PHP tokenizer (WP internals)',   'required' => true ],
        [ 'name' => 'imagick',   'loaded' => extension_loaded('imagick'),      'purpose' => 'Advanced image processing',      'required' => false ],
        [ 'name' => 'gd',        'loaded' => extension_loaded('gd'),           'purpose' => 'Image processing (fallback)',    'required' => false ],
        [ 'name' => 'zip',       'loaded' => extension_loaded('zip'),          'purpose' => 'Plugin/theme ZIP handling',      'required' => false ],
        [ 'name' => 'intl',      'loaded' => extension_loaded('intl'),         'purpose' => 'Internationalization',           'required' => false ],
        [ 'name' => 'exif',      'loaded' => extension_loaded('exif'),         'purpose' => 'Image EXIF metadata',            'required' => false ],
        [ 'name' => 'sodium',    'loaded' => extension_loaded('sodium'),       'purpose' => 'Modern cryptography (WP 5.2+)',  'required' => false ],
        [ 'name' => 'opcache',   'loaded' => extension_loaded('Zend OPcache'),'purpose' => 'PHP bytecode caching',           'required' => false ],
        [ 'name' => 'redis',     'loaded' => extension_loaded('redis'),        'purpose' => 'Redis object cache',             'required' => false ],
        [ 'name' => 'bcmath',    'loaded' => extension_loaded('bcmath'),       'purpose' => 'Arbitrary precision math',       'required' => false ],
        [ 'name' => 'iconv',     'loaded' => extension_loaded('iconv'),        'purpose' => 'Character encoding conversion',  'required' => false ],
        [ 'name' => 'simplexml', 'loaded' => extension_loaded('simplexml'),    'purpose' => 'Simple XML parsing',             'required' => false ],
        [ 'name' => 'xmlreader', 'loaded' => extension_loaded('xmlreader'),    'purpose' => 'XML stream reader',              'required' => false ],
        [ 'name' => 'zlib',      'loaded' => extension_loaded('zlib'),         'purpose' => 'Gzip compression',               'required' => false ],
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GOING LIVE CHECKLIST — RECOMMENDED SNIPPET IDS
 * ═══════════════════════════════════════════════════════════════════════════
 * @since 10.9.0
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_going_live_snippets' ) ) {
function hws_get_going_live_snippets() {
    return [
        'register_acf_website_settings',
        'register_user_custom_fields_2025',
        'register_user_custom_fields_additional_2025',
        'enable_website_settings_functionality',
        'enable_auto_update_plugins',
        'enable_auto_update_themes',
        'enable_elementor_social_icon_cleanup',
        'enable_wp_admin_logo',
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ROBUST REDIS STATUS CHECK
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reads LiteSpeed Cache's stored host/port config to connect (instead of
 * hardcoding 127.0.0.1:6379). Falls back to defaults if LiteSpeed not found.
 *
 * Returns detailed array with extension, connection, litespeed, server info.
 *
 * @since 10.9.1
 * @return array { active: bool, extension: bool, connected: bool, litespeed_enabled: bool, info: array, error: string }
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_redis_status' ) ) {
function hws_check_redis_status() {
    // — Default result with ALL keys guaranteed
    $result = [
        'active'             => false,
        'extension'          => false,
        'connected'          => false,
        'litespeed_enabled'  => false,
        'info'               => [],
        'error'              => '',
    ];

    // — Check PHP Redis extension
    $result['extension'] = extension_loaded( 'redis' );
    if ( ! $result['extension'] ) {
        $result['error'] = 'Redis PHP extension not installed';
        return $result;
    }

    // — Read LiteSpeed's stored Redis config from wp_options
    $ls_obj  = get_option( 'litespeed.conf.object', false );
    $ls_kind = get_option( 'litespeed.conf.object-kind', false );
    $ls_host = get_option( 'litespeed.conf.object-host', '' );
    $ls_port = (int) get_option( 'litespeed.conf.object-port', 6379 );
    $ls_db   = (int) get_option( 'litespeed.conf.object-db_id', 0 );
    $ls_user = get_option( 'litespeed.conf.object-user', '' );
    $ls_pswd = get_option( 'litespeed.conf.object-pswd', '' );

    // — LiteSpeed considers Redis enabled when: object=true + kind=true(Redis) + host is set
    $result['litespeed_enabled'] = ( (bool) $ls_obj && (bool) $ls_kind && ! empty( $ls_host ) );

    // — Determine connection target (use LS config, fall back to localhost)
    $host           = ! empty( $ls_host ) ? trim( (string) $ls_host ) : '127.0.0.1';
    $is_unix_socket = str_starts_with( $host, '/' ) || str_starts_with( $host, 'unix://' );
    $port           = $is_unix_socket ? 0 : ( $ls_port > 0 ? $ls_port : 6379 );
    $target         = $is_unix_socket ? $host : "{$host}:{$port}";

    // — Attempt connection using LiteSpeed's exact config
    try {
        $redis = new \Redis();
        $ok = @$redis->connect( $host, $port, 2.0 );
        if ( ! $ok ) {
            $result['error'] = "Connection refused ({$target})";
            return $result;
        }

        // — Authenticate if password set (matches LiteSpeed's auth logic)
        if ( ! empty( $ls_pswd ) ) {
            if ( ! empty( $ls_user ) ) {
                $redis->auth( [ $ls_user, $ls_pswd ] );
            } else {
                $redis->auth( $ls_pswd );
            }
        }

        // — Select database if specified
        if ( $ls_db > 0 ) {
            $redis->select( $ls_db );
        }

        // — Ping test (rawCommand matches LiteSpeed's own check)
        $redis->setOption( \Redis::OPT_READ_TIMEOUT, 2 );
        $pong = $redis->rawCommand( 'PING' );
        if ( $pong !== 'PONG' && $pong !== true && $pong !== '+PONG' ) {
            $result['error'] = 'Redis PING failed';
            return $result;
        }

        $result['connected'] = true;

        // — Gather server info
        $info = @$redis->info();
        if ( is_array( $info ) ) {
            $hits   = (int) ( $info['keyspace_hits'] ?? 0 );
            $misses = (int) ( $info['keyspace_misses'] ?? 0 );
            $total  = $hits + $misses;

            $result['info'] = [
                'version'     => $info['redis_version'] ?? 'unknown',
                'port'        => $info['tcp_port'] ?? $port,
                'host'        => $host,
                'db_index'    => $ls_db,
                'used_memory' => $info['used_memory_human'] ?? '0B',
                'peak_memory' => $info['used_memory_peak_human'] ?? '0B',
                'uptime_days' => isset( $info['uptime_in_seconds'] ) ? round( $info['uptime_in_seconds'] / 86400, 1 ) : 0,
                'total_keys'  => @$redis->dbSize() ?: 0,
                'hit_rate'    => $total > 0 ? round( ( $hits / $total ) * 100, 1 ) . '%' : 'N/A',
            ];
        }

        @$redis->close();
    } catch ( \RedisException $e ) {
        $result['error'] = 'Redis: ' . $e->getMessage();
        return $result;
    } catch ( \Exception $e ) {
        $result['error'] = $e->getMessage();
        return $result;
    }

    // — Active = extension loaded + connected + LiteSpeed has it enabled
    $result['active'] = $result['connected'] && $result['litespeed_enabled'];

    return $result;
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * BROTLI SUPPORT CHECK
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Brotli is a server-level feature on LiteSpeed/OpenLiteSpeed.
 * Detection: check Accept-Encoding header from client + check if
 * server advertises br via a self-request, or check ini settings.
 *
 * @since 10.9.1
 * @return array { enabled: bool, details: string }
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_check_brotli_support' ) ) {
function hws_check_brotli_support() {
    // — Method 1: Check if PHP brotli extension is loaded
    $php_ext = function_exists( 'brotli_compress' );

    // — Method 2: Check server response headers via self-request
    $server_br = false;
    $details   = [];
    $test_url  = home_url( '/' );

    $response = wp_remote_get( $test_url, [
        'timeout'   => 5,
        'headers'   => [ 'Accept-Encoding' => 'br, gzip, deflate' ],
        'sslverify' => false,
    ] );

    if ( ! is_wp_error( $response ) ) {
        $encoding = wp_remote_retrieve_header( $response, 'content-encoding' );
        if ( stripos( $encoding, 'br' ) !== false ) {
            $server_br = true;
            $details[] = 'Server responds with Content-Encoding: br';
        }

        // — Also check LiteSpeed-specific header
        $x_ls = wp_remote_retrieve_header( $response, 'x-litespeed-cache' );
        if ( $x_ls ) {
            $details[] = 'LiteSpeed cache header detected';
        }
    }

    // — Method 3: Check if LiteSpeed server is present (Brotli built-in)
    $server_sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
    if ( stripos( $server_sw, 'LiteSpeed' ) !== false ) {
        $details[] = 'LiteSpeed server (Brotli built-in)';
    }

    $enabled = $server_br || $php_ext;
    if ( $php_ext ) $details[] = 'PHP brotli extension loaded';

    return [
        'enabled' => $enabled,
        'details' => ! empty( $details ) ? implode( ' · ', $details ) : 'Not detected',
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LITESPEED CACHE INFO — READ ALL RELEVANT SETTINGS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reads LiteSpeed Cache settings directly from wp_options using the
 * plugin's storage format: get_option('litespeed.conf.{key}').
 *
 * @since 10.9.1
 * @return array|false  Settings array or false if plugin not active
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_litespeed_info' ) ) {
function hws_get_litespeed_info() {
    if ( ! is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
        return false;
    }

    // — Helper to read a LiteSpeed option
    $opt = function( $key, $default = false ) {
        return get_option( 'litespeed.conf.' . $key, $default );
    };

    return [
        // — Page Cache
        'cache_enabled'      => (bool) $opt( 'cache' ),
        'cache_private'      => (bool) $opt( 'cache-priv' ),
        'cache_browser'      => (bool) $opt( 'cache-browser' ),
        'cache_mobile'       => (bool) $opt( 'cache-mobile' ),
        'cache_rest'         => (bool) $opt( 'cache-rest' ),
        'cache_ttl_public'   => (int)  $opt( 'cache-ttl_pub', 0 ),
        'cache_ttl_browser'  => (int)  $opt( 'cache-ttl_browser', 0 ),

        // — CSS Optimization
        'css_minify'         => (bool) $opt( 'optm-css_min' ),
        'css_combine'        => (bool) $opt( 'optm-css_comb' ),
        'css_async'          => (bool) $opt( 'optm-css_async' ),
        'css_font_display'   => $opt( 'optm-css_font_display', false ),

        // — JS Optimization
        'js_minify'          => (bool) $opt( 'optm-js_min' ),
        'js_combine'         => (bool) $opt( 'optm-js_comb' ),
        'js_defer'           => $opt( 'optm-js_defer', false ),

        // — Object Cache (Redis)
        'object_enabled'     => (bool) $opt( 'object' ),
        'object_kind'        => $opt( 'object-kind' ) ? 'Redis' : 'Memcached',
        'object_host'        => $opt( 'object-host', '' ),
        'object_port'        => (int)  $opt( 'object-port', 0 ),
        'object_db_id'       => (int)  $opt( 'object-db_id', 0 ),
        'object_persistent'  => (bool) $opt( 'object-persistent' ),
        'object_admin'       => (bool) $opt( 'object-admin' ),
        'object_transients'  => (bool) $opt( 'object-transients' ),
    ];
}
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GOING LIVE CHECKLIST — SETTINGS & SERVER CHECKS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Returns an array of named checks each with: label, pass (bool), value (string).
 * Used by the GLC panel to show full site readiness.
 *
 * @since 10.9.1
 * @return array [ [ 'label' => '...', 'pass' => bool, 'value' => '...' ], ... ]
 */
if ( ! function_exists( __NAMESPACE__ . '\\hws_get_glc_settings_checks' ) ) {
function hws_get_glc_settings_checks() {
    $checks = [];

    // ─── WORDPRESS SETTINGS ────────────────────────────────────────────

    // — WP_MEMORY_LIMIT > 512MB
    $mem_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
    $mem_bytes = wp_convert_hr_to_bytes( $mem_limit );
    $checks[] = [
        'label' => 'WP Memory Limit > 512MB',
        'pass'  => $mem_bytes >= 536870912,
        'value' => $mem_limit,
    ];

    // — Comments disabled
    $comments_closed = get_option( 'default_comment_status' ) === 'closed';
    $checks[] = [
        'label' => 'Comments Disabled',
        'pass'  => $comments_closed,
        'value' => $comments_closed ? 'Closed' : 'Open',
    ];

    // — Pingbacks disabled
    $pings_closed = get_option( 'default_ping_status' ) === 'closed';
    $checks[] = [
        'label' => 'Pingbacks Disabled',
        'pass'  => $pings_closed,
        'value' => $pings_closed ? 'Closed' : 'Open',
    ];

    // — SMTP / Email Authentication active
    $smtp = function_exists( __NAMESPACE__ . '\\check_smtp_auth_status_and_mailer' )
        ? check_smtp_auth_status_and_mailer()
        : [ 'status' => false, 'mailer' => '', 'raw_value' => '' ];
    $checks[] = [
        'label' => 'Email / SMTP Authenticated',
        'pass'  => (bool) $smtp['status'],
        'value' => $smtp['status'] ? ucfirst( $smtp['mailer'] ) : ( $smtp['raw_value'] ?: 'Not configured' ),
    ];

    // — WP_DEBUG off
    $debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $checks[] = [
        'label' => 'WP_DEBUG Off',
        'pass'  => ! $debug_on,
        'value' => $debug_on ? 'ON' : 'Off',
    ];

    // — WP_DEBUG_DISPLAY off
    $debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $checks[] = [
        'label' => 'WP_DEBUG_DISPLAY Off',
        'pass'  => ! $debug_display,
        'value' => $debug_display ? 'ON' : 'Off',
    ];

    // — WP_DEBUG_LOG off
    $debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
    $checks[] = [
        'label' => 'WP_DEBUG_LOG Off',
        'pass'  => ! $debug_log,
        'value' => $debug_log ? 'ON' : 'Off',
    ];

    // — Wordfence alert email set
    if ( function_exists( __NAMESPACE__ . '\\check_wordfence_notification_email' ) ) {
        $wf = check_wordfence_notification_email();
        $checks[] = [
            'label' => 'Wordfence Alert Email Set',
            'pass'  => (bool) ( $wf['status'] ?? false ),
            'value' => ( $wf['raw_value'] ?? $wf['details'] ?? 'Not set' ),
        ];
    }

    // — display_errors off (should be off in production)
    $display_errors = ini_get( 'display_errors' );
    $de_off = ( ! $display_errors || $display_errors === '0' || strtolower( $display_errors ) === 'off' );
    $checks[] = [
        'label' => 'display_errors Off',
        'pass'  => $de_off,
        'value' => $de_off ? 'Off' : 'ON (' . $display_errors . ')',
    ];

    // — Individual log file checks (each < 10MB)
    $max_log = 10 * 1024 * 1024; // 10MB
    $log_files = [
        'debug.log'          => WP_CONTENT_DIR . '/debug.log',
        'error_log (root)'   => ABSPATH . 'error_log',
        'error_log (admin)'  => ABSPATH . 'wp-admin/error_log',
    ];
    foreach ( $log_files as $label => $path ) {
        $size = file_exists( $path ) ? filesize( $path ) : 0;
        $checks[] = [
            'label' => $label . ' < 10MB',
            'pass'  => $size < $max_log,
            'value' => file_exists( $path ) ? size_format( $size ) : 'Not found (good)',
        ];
    }

    // — DISABLE_WP_CRON (should be true for production with real cron)
    $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    $checks[] = [
        'label' => 'WP Cron Disabled (real cron)',
        'pass'  => $cron_disabled,
        'value' => $cron_disabled ? 'Disabled (good)' : 'WP Cron active',
    ];

    // ─── SERVER & PHP ──────────────────────────────────────────────────

    // — Cloudflare active (check headers + nameservers)
    if ( function_exists( __NAMESPACE__ . '\\check_cloudflare_active' ) ) {
        $cf = check_cloudflare_active();
        $checks[] = [
            'label' => 'Cloudflare Active',
            'pass'  => (bool) ( $cf['status'] ?? false ),
            'value' => $cf['raw_value'] ?? 'Unknown',
        ];
    }

    // — PHP SAPI = litespeed
    $sapi = php_sapi_name();
    $checks[] = [
        'label' => 'PHP SAPI: LiteSpeed',
        'pass'  => ( $sapi === 'litespeed' ),
        'value' => $sapi,
    ];

    // — PHP version >= 8.1
    $php_ver = phpversion();
    $checks[] = [
        'label' => 'PHP ≥ 8.1',
        'pass'  => version_compare( $php_ver, '8.1', '>=' ),
        'value' => $php_ver,
    ];

    // — Imagick available
    $imagick = extension_loaded( 'imagick' );
    $checks[] = [
        'label' => 'Imagick Library',
        'pass'  => $imagick,
        'value' => $imagick ? 'Available' : 'Missing',
    ];

    // — MyISAM tables (scoped to current WP prefix only)
    if ( function_exists( __NAMESPACE__ . '\\check_myisam_tables' ) ) {
        $myisam = check_myisam_tables();
        $checks[] = [
            'label' => 'No MyISAM Tables',
            'pass'  => (bool) ( $myisam['status'] ?? false ),
            'value' => $myisam['raw_value'] ?? 'Unknown',
        ];
    }

    // — Redis active (safe access with null-coalescing on every key)
    if ( function_exists( __NAMESPACE__ . '\\hws_check_redis_status' ) ) {
        $redis     = hws_check_redis_status();
        $r_active  = $redis['active'] ?? false;
        $r_error   = $redis['error'] ?? '';
        $r_info    = $redis['info'] ?? [];
        $redis_val = $r_active
            ? 'Active (v' . ( $r_info['version'] ?? '?' ) . ', ' . ( $r_info['used_memory'] ?? '' ) . ')'
            : ( $r_error ?: 'Inactive' );
        $checks[] = [
            'label' => 'Redis Active',
            'pass'  => (bool) $r_active,
            'value' => $redis_val,
        ];
    }

    // — post_max_size >= 128MB
    $post_max     = ini_get( 'post_max_size' );
    $post_max_b   = wp_convert_hr_to_bytes( $post_max );
    $checks[] = [
        'label' => 'post_max_size ≥ 128MB',
        'pass'  => $post_max_b >= 134217728,
        'value' => $post_max,
    ];

    // — upload_max_filesize >= 128MB
    $upload_max   = ini_get( 'upload_max_filesize' );
    $upload_max_b = wp_convert_hr_to_bytes( $upload_max );
    $checks[] = [
        'label' => 'upload_max_filesize ≥ 128MB',
        'pass'  => $upload_max_b >= 134217728,
        'value' => $upload_max,
    ];

    // — Brotli enabled
    $brotli = hws_check_brotli_support();
    $checks[] = [
        'label' => 'Brotli Compression',
        'pass'  => $brotli['enabled'],
        'value' => $brotli['details'],
    ];

    // ─── THEMES & PLUGINS ──────────────────────────────────────────────

    // — No more than 2 themes installed
    $all_themes  = wp_get_themes();
    $theme_count = count( $all_themes );
    $checks[] = [
        'label' => 'Max 2 Themes Installed',
        'pass'  => $theme_count <= 2,
        'value' => $theme_count . ' theme(s)',
    ];

    // — All themes updated
    $theme_updates = get_site_transient( 'update_themes' );
    $outdated_themes = ! empty( $theme_updates->response ) ? count( $theme_updates->response ) : 0;
    $checks[] = [
        'label' => 'All Themes Updated',
        'pass'  => $outdated_themes === 0,
        'value' => $outdated_themes > 0 ? $outdated_themes . ' update(s) available' : 'Up to date',
    ];

    // — All plugins updated
    $plugin_updates  = get_site_transient( 'update_plugins' );
    $outdated_plugins = ! empty( $plugin_updates->response ) ? count( $plugin_updates->response ) : 0;
    $checks[] = [
        'label' => 'All Plugins Updated',
        'pass'  => $outdated_plugins === 0,
        'value' => $outdated_plugins > 0 ? $outdated_plugins . ' update(s) available' : 'Up to date',
    ];

    // — Detect default Twenty* themes (should be removed)
    $twenty_themes = [];
    $twenty_slugs  = [ 'twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty', 'twentynineteen' ];
    foreach ( $all_themes as $slug => $theme ) {
        if ( in_array( $slug, $twenty_slugs, true ) ) {
            $twenty_themes[] = $theme->get( 'Name' );
        }
    }
    $checks[] = [
        'label' => 'No Default Twenty* Themes',
        'pass'  => empty( $twenty_themes ),
        'value' => empty( $twenty_themes )
            ? 'None found'
            : implode( ', ', $twenty_themes ),
    ];

    return $checks;
}
}
