<?php namespace hws_base_tools;

// Define the write_log function only if it isn't already defined
if (!function_exists(__NAMESPACE__ . '\\write_log')) {
    function write_log($log, $full_debug = false) {
        if (WP_DEBUG && WP_DEBUG_LOG && $full_debug) {
            // Get the backtrace
            $backtrace = debug_backtrace();
            
            // Extract the last function that called this one
            $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'N/A';
            
            // Extract the file and line number where the caller is located
            $caller_file = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'N/A';
            $caller_line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 'N/A';
            
            // Prepare the log message
            $log_message = is_array($log) || is_object($log) ? print_r($log, true) : $log;
            $log_message .= "\n\n[Called by: $caller]\n[In file: $caller_file at line $caller_line]\n---";
            
            // Write to the log
            error_log($log_message);
        }
    } 
}

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
    function check_smtp_auth_status_and_mailer() {
        $status = false;
        $mailer = '';
        $details = 'No details available';
    
        if (is_plugin_active('wp-mail-smtp/wp_mail_smtp.php')) {
            $wp_mail_smtp_options = get_option('wp_mail_smtp');
            $mailer = $wp_mail_smtp_options['mail']['mailer'] ?? 'Unknown';
            
            if ($mailer === 'smtp' || $mailer === 'sendinblue') {
                $status = true;
                $details = $wp_mail_smtp_options['mail']['from_email'] ?? 'Unknown';
            } else {
                $details = 'Authenticated domain could not be determined for the mailer: ' . $mailer;
            }
        }
    
        // Always return the structure with status, mailer, and details
        return [
            'status' => $status,
            'mailer' => $mailer,
            'raw_value' => $details." - ".$mailer
        ];
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
        if (defined($constant_name)) {
            $constant_value = constant($constant_name);

            // Check for booleans and handle them explicitly
            if (is_bool($constant_value)) {
                $log_value = $constant_value ? 'true' : 'false';
            } else {
                $log_value = $constant_value;
            }

    
            // Return 'true' or 'false' if the constant is a boolean, otherwise return its value
            return $log_value;
        } else return 'undefined';  
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
        $prefix_info = get_database_table_prefix();
        $current_prefix = isset($prefix_info['raw_value']) ? $prefix_info['raw_value'] : '';

        // Check for valid prefix
        if (empty($current_prefix)) {
            return [
                'function' => "check_myisam_tables",
                'status' => false,
                'raw_value' => 'Unable to determine current database prefix.'
            ];
        }

        // Get all MyISAM tables
        $myisam_tables = $wpdb->get_results("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND ENGINE = 'MyISAM'
        ");
        
        $current_prefix_tables = [];
        $additional_prefixes = [];
        
        foreach ($myisam_tables as $table) {
            $table_name = $table->TABLE_NAME;
            if (strpos($table_name, $current_prefix) === 0) {
                $current_prefix_tables[] = $table_name;
            } else {
                $prefix = explode('_', $table_name)[0];
                $additional_prefixes[$prefix][] = $table_name;
            }
        }

        // Determine status
        $status = empty($current_prefix_tables) && empty($additional_prefixes);

        // Prepare details
        $details = '';
        if (!empty($current_prefix_tables)) {
            $details .= 'MyISAM tables found for current WordPress install: ' . implode(', ', $current_prefix_tables);
        }
        if (!empty($additional_prefixes)) {
            if (!empty($details)) {
                $details .= ' | ';
            }
            $details .= 'Additional database prefixes detected: ';
            foreach ($additional_prefixes as $prefix => $tables) {
                $details .= $prefix . '_ - MyISAM tables found: ' . implode(', ', $tables) . ' | ';
            }
            // Remove trailing ' | ' if any
            $details = rtrim($details, ' | ');
        }

        // Check if details are still empty
        if (empty($details)) {
            $details = 'No MyISAM tables found.';
        }

        return [
            'function' => "check_myisam_tables",
            'status' => $status, // True if no MyISAM tables are found
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
        $total_ram = 0;

        if (function_exists('shell_exec')) {
            $total_ram = trim(shell_exec("free -b | awk '/^Mem:/{print $2}'")); // Get total RAM in bytes
        }

        $status = $total_ram >= 4 * 1024 * 1024 * 1024; // Check if RAM is at least 4GB

        return [
            'status' => $status,
            'raw_value' => $total_ram ? size_format($total_ram) : 'Not available'
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

                // Attempt to connect to Redis server
                if ($redis->connect('127.0.0.1', 6379)) {
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
                    $details = 'Redis connection failed';
                }
            } catch (Exception $e) {
                $details = 'Exception: ' . $e->getMessage();
            }
        }

        // Log the results for debugging purposes
        write_log('Redis check: ' . $details);

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
        $total_ram = 0;

        if (function_exists('shell_exec')) {
            $total_ram = trim(shell_exec("free -m | awk '/^Mem:/{print $2}'")) * 1024 * 1024; // Convert MB to bytes
        }

        $status = $total_ram >= 4 * 1024 * 1024 * 1024; // Check if RAM is at least 4GB

        return [
            'status' => $status,
            'details' => $total_ram ? size_format($total_ram) : 'Not available'
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




if (!function_exists(__NAMESPACE__ . '\\custom_wp_admin_logo')) {
    function custom_wp_admin_logo() {
        // Check if this is being called in the correct context (login page)
        if (did_action('login_enqueue_scripts')) {
            $logo_url = get_site_icon_url(); // Fetch the site icon URL from the WordPress settings
            write_log("adding custom logo: ". $logo_url, true);
            if ($logo_url) {
                write_log("adding custom logo WITH URL: ". $logo_url, true);
                echo '
                <style type="text/css">
                    #login h1 a, .login h1 a { 
                        background-image: url("' . esc_url($logo_url) . '");
                        width: 250px;
                        height: 50px;
                        padding: 30px;
                        background-size: contain;
                        background-repeat: no-repeat;
                    }
                </style>';
            }
        }
    }
    add_action('login_enqueue_scripts', 'hws_base_tools\custom_wp_admin_logo', 20); // Increased priority
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\custom_wp_admin_logo function is already declared",true);


if (!function_exists(__NAMESPACE__ . '\\custom_wp_admin_logo_link')) {
    function custom_wp_admin_logo_link() {
        return false;
    }
    add_filter('login_headerurl', 'hws_base_tools\custom_wp_admin_logo_link');
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\custom_wp_admin_logo_link function is already declared",true);








if(!function_exists('hws_base_tools\check_server_specs')) {
    function check_server_specs() {
        // Initialize variables
        $num_processors = function_exists('shell_exec') ? shell_exec('nproc') : 'Unknown';
        $total_ram = function_exists('shell_exec') ? shell_exec("free -m | awk '/^Mem:/{print $2}'") : 'Unknown';

        // Clean up the results
        $num_processors = trim($num_processors);
        $total_ram = trim($total_ram);

        // Set the status
        $status = ($num_processors !== 'Unknown' && $total_ram !== 'Unknown');

        // Return the result in the expected structure
        return [
            'function' => 'check_server_specs',
            'status' => true,
            'raw_value' => "Number of processors: $num_processors, Total RAM: $total_ram MB"
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
        $min_version = '8.3.0';

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


/** CODE TO TOUCH UP ***/
if (!function_exists(__NAMESPACE__ . '\\modify_wp_config_constants')) {
    function modify_wp_config_constants($constants_to_update) {
        $wp_config_path = ABSPATH . 'wp-config.php';

        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            return ['status' => false, 'message' => 'wp-config.php does not exist or is not writable.'];
        }

        $config_content = file_get_contents($wp_config_path);

        foreach ($constants_to_update as $constant => $value) {
            // Convert string "true" and "false" to booleans
            if (is_string($value)) {
                if (strtolower($value) === 'true') {
                    $value = true;
                } elseif (strtolower($value) === 'false') {
                    $value = false;
                }
            }

            // Handle the boolean and string values appropriately
            if (is_bool($value)) {
                $new_constant = $value ? "define('$constant', true);" : "define('$constant', false);";
            } elseif (is_numeric($value)) {
                $new_constant = "define('$constant', $value);";
            } else {
                $new_constant = "define('$constant', '$value');";
            }

            // Remove any existing definition of the constant
            $config_content = preg_replace(
                "/define\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*.*?\);\s*/",
                '',
                $config_content
            );

            // Insert the new constant definition at the beginning of the file
            $config_content = "<?php\n$new_constant\n" . ltrim($config_content, "<?php\n");
        }

        // Write the updated content back to wp-config.php
        if (file_put_contents($wp_config_path, $config_content)) {
            return ['status' => true, 'message' => 'Constants updated successfully.'];
        } else {
            return ['status' => false, 'message' => 'Failed to update wp-config.php.'];
        }
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
        // List of constants to exclude (security-sensitive)
        $exclude_constants = [
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
            'DB_HOST',
            'DB_CHARSET',
            'DB_COLLATE',
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        // Get all defined constants
        $all_constants = get_defined_constants(true);

        // Filter out the excluded constants
        $filtered_constants = array_filter($all_constants['user'], function($key) use ($exclude_constants) {
            return !in_array($key, $exclude_constants);
        }, ARRAY_FILTER_USE_KEY);

        return $filtered_constants;
    }
}

// Check if Cloudflare is active and get nameservers
function check_cloudflare_active() {
    // Get the domain from the server name
    $domain = $_SERVER['SERVER_NAME'];
    
    // Get the nameservers for the domain
    $nameservers = dns_get_record($domain, DNS_NS);
    $nameserver_list = [];

    foreach ($nameservers as $ns) {
        $nameserver_list[] = $ns['target'];
    }

    // Check if any of the nameservers indicate Cloudflare
    $is_active = false;
    foreach ($nameserver_list as $ns) {
        if (strpos($ns, 'cloudflare') !== false) {
            $is_active = true;
            break;
        }
    }

    return [
        'status' => $is_active,
        'raw_value' => $is_active ? 'Cloudflare is active. Nameservers: ' . implode(', ', $nameserver_list) : 'Cloudflare is not active. Nameservers: ' . implode(', ', $nameserver_list)
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



if (!function_exists(__NAMESPACE__ . '\\disable_rankmath_sitemap_caching')) {
    function disable_rankmath_sitemap_caching() {
        add_filter('rank_math/sitemap/enable_caching', '__return_false');
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

    // Create a toggle button for the setting
    $toggle_button = ($current_status === 'DISABLED')
        ? "<button class='button execute-function block' data-method='toggle_php_ini_value' data-variable='$setting_name' data-setting='$setting_name' data-state='1' data-loader='true'>Enable $setting_name</button><br>"
        : "<button class='button execute-function block' data-method='toggle_php_ini_value' data-variable='$setting_name' data-setting='$setting_name' data-state='0' data-loader='true'>Disable $setting_name</button><br>";

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
    // Step 1: Try getting the value from ini_get
    $value = ini_get($setting_name);

    // Log the value from ini_get
   // write_log("ini_get value for $setting_name: " . var_export($value, true), true);

    // Step 2: Try reading wp-config.php for ini_set() overrides
    $wp_config_path = ABSPATH . 'wp-config.php';
    if (file_exists($wp_config_path)) {
        $config_content = file_get_contents($wp_config_path);

        // Check if there's an ini_set line for this setting in wp-config.php
        $pattern = "/ini_set\(\s*['\"]{$setting_name}['\"]\s*,\s*['\"](.*?)['\"]\s*\);/";
        if (preg_match($pattern, $config_content, $matches)) {
            $value = $matches[1]; // Override the value with what's found in wp-config.php
            write_log("Overriding ini_get with value from wp-config.php for $setting_name: " . var_export($value, true), false);
        }
    }

    return $value !== false ? $value : 'unknown'; // Return 'unknown' if nothing works
}






if (!function_exists(__NAMESPACE__ . '\\toggle_php_ini_value')) {
    function toggle_php_ini_value($setting_name, $new_value) {
        // Attempt to set the ini value dynamically
        $result = ini_set($setting_name, $new_value);

        // Log whether the dynamic ini_set succeeded or failed
        if ($result === false) {
            write_log("Error: Failed to update {$setting_name} to {$new_value}.", true);
            return 'fail';
        }

        // Proceed to update wp-config.php for persistence
        $wp_config_path = ABSPATH . 'wp-config.php';

        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            write_log("Error: wp-config.php is either missing or not writable.", true);
            return 'fail';
        }

        // Read the current content of wp-config.php
        $config_content = file_get_contents($wp_config_path);

        // Check if the ini_set already exists and update or append the setting
        if (strpos($config_content, "ini_set('{$setting_name}'") !== false) {
            // Update the existing ini_set line
            $config_content = preg_replace(
                "/ini_set\('{$setting_name}',\s*'(.*)'\);/",
                "ini_set('{$setting_name}', '{$new_value}');",
                $config_content
            );
            write_log("Info: Updated existing {$setting_name} to {$new_value} in wp-config.php.", true);
        } else {
            // Append the new ini_set line before the final comment line
            $new_line = "ini_set('{$setting_name}', '{$new_value}');\n";
            $config_content = preg_replace('/(\/\* That\'s all, stop editing! Happy publishing\. \*\/)/', $new_line . "$1", $config_content);
            write_log("Info: Added new ini_set for {$setting_name} with value {$new_value} in wp-config.php.", true);
        }

        // Write the updated content back to wp-config.php
        if (file_put_contents($wp_config_path, $config_content)) {
            write_log("Success: Changes to {$setting_name} have been persisted in wp-config.php.", true);
            return 'success';
        } else {
            write_log("Error: Failed to write changes  {$setting_name} with value {$new_value} to wp-config.php.", true);
            return 'fail';
        }
    }
} else {
    write_log("Warning: hws_base_tools\toggle_php_ini_value is already declared.", true);
}