<?php namespace hws_base_tools;

// Define the write_log function only if it isn't already defined
if (!function_exists('hws_base_tools\write_log')) {
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
            $log_message .= "\n[Called by: $caller]\n[In file: $caller_file at line $caller_line]";
            
            // Write to the log
            error_log($log_message);
        }
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
 
 if (!function_exists('hws_base_tools\check_plugin_status')) {
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
} else {
    write_log("Warning: hws_base_tools/check_plugin_status function is already declared", true);
}


/**
 * Check if a user exists by login name.
 * 
 * @param string $login The login name of the user.
 * @return bool True if the user exists, false otherwise.
 */
if (!function_exists('hws_base_tools\does_user_exist')) {
    function does_user_exist($login) {
        return get_user_by('login', $login) !== false;
    }
} else write_log("Warning: hws_base_tools/does_user_exist function is already declared",true);

/**
 * Check if a custom post type exists.
 * 
 * @param string $post_type The custom post type name.
 * @return bool True if the post type exists, false otherwise.
 */
if (!function_exists('hws_base_tools\does_post_type_exist')) {
    function does_post_type_exist($post_type) {
        return post_type_exists($post_type);
    }
} else write_log("Warning: hws_base_tools/does_post_type_exist function is already declared",true);


/**
 * Check if a specified theme is currently active.
 * 
 * @param string $theme_name The name of the theme.
 * @return bool True if the theme is active, false otherwise.
 */
if (!function_exists('hws_base_tools\is_theme_active')) {
    function is_theme_active($theme_name) {
        return wp_get_theme()->get('Name') === $theme_name;
    }
} else write_log("Warning: hws_base_tools/is_theme_active function is already declared",true);


/**
 * Check if auto-updates are enabled for a specified theme.
 * 
 * @param string $theme_name The name of the theme.
 * @return bool True if auto-updates are enabled, false otherwise.
 */
if (!function_exists('hws_base_tools\is_theme_auto_update_enabled')) {
    function is_theme_auto_update_enabled($theme_name) {
        $theme_updates = get_option('auto_update_themes', []);
        return in_array($theme_name, $theme_updates);
    }
} else write_log("Warning: hws_base_tools/is_theme_auto_update_enabled function is already declared",true);

/**
 * Display the status of a condition with a message and colored icon.
 * 
 * @param bool $condition The condition to evaluate.
 * @param string $message The message to display.
 */
if (!function_exists('hws_base_tools\display_check_status')) {
    function display_check_status($condition, $message) {
        $color = $condition ? 'green' : 'red';
        $icon = $condition ? '&#x2705;' : '&#x274C;';
        echo "<div style='color: $color;'>$icon $message</div>";
    }
} else write_log("Warning: hws_base_tools/display_check_status function is already declared",true);


/**
 * Check if a taxonomy exists.
 * 
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the taxonomy exists, false otherwise.
 */
if (!function_exists('hws_base_tools\does_taxonomy_exist')) {
    function does_taxonomy_exist($taxonomy) {
        return taxonomy_exists($taxonomy);
    }
} else write_log("Warning: hws_base_tools/does_taxonomy_exist function is already declared",true);


/**
 * Check if a term exists within a specified taxonomy.
 * 
 * @param string $term The term to check.
 * @param string $taxonomy The taxonomy name.
 * @return bool True if the term exists in the taxonomy, false otherwise.
 */
if (!function_exists('hws_base_tools\does_term_exist')) {
    function does_term_exist($term, $taxonomy) {
        $term_exists = term_exists($term, $taxonomy);
        return $term_exists !== 0 && $term_exists !== null;
    }
} else write_log("Warning: hws_base_tools/does_term_exist function is already declared",true);




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
if (!function_exists('hws_base_tools\is_acf_field_group_imported')) {
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
if (!function_exists('hws_base_tools\add_settings_menu')) {
    function add_settings_menu($page_title, $menu_title, $capability, $menu_slug, $callback_function) {
        add_options_page(
            $page_title,      // Page title
            $menu_title,      // Menu title
            $capability,      // Capability required to access this page
            $menu_slug,       // Menu slug
            $callback_function // Callback function to display the page content
        );
    }
} else write_log("Warning: hws_base_tools/add_settings_menu function is already declared",true);













if (!function_exists('hws_base_tools\check_smtp_auth_status_and_mailer')) {
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
            'details' => $details
        ];
    }
} else write_log("Warning: hws_base_tools/check_smtp_auth_status_and_mailer function is already declared",true);


if (!function_exists('hws_base_tools\enable_auto_update_themes')) {
    function enable_auto_update_themes() {
        add_filter('auto_update_theme', '__return_true');
        return [
            'status' => true,
            'details' => 'Theme auto-updates are enabled.'
        ];
    }
} else {
    write_log("Warning: enable_auto_update_themes function is already declared", true);
}



if (!function_exists('hws_base_tools\get_smtp_sending_domain')) {
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
} else write_log("Warning: hws_base_tools/get_smtp_sending_domain function is already declared",true);





if (!function_exists('hws_base_tools\hws_ct_highlight_based_on_criteria')) {
    function hws_ct_highlight_based_on_criteria($check_result, $criteria = 'true') {
        if ($check_result['status']) {
            $value = $check_result['details'];

            // Check if the value meets the criteria for highlighting in red
            if ($value === $criteria) {
                return "<span style='color: red;'>{$value}</span>";
            } else {
                return $value;
            }
        } else {
            return "<span style='color: red;'>{$check_result['details']}</span>";
        }
    }
} else {
    write_log("Warning: hws_base_tools/hws_ct_highlight_based_on_criteria function is already declared", true);
}




if (!function_exists('hws_base_tools\check_wp_config_constant_status')) {
    function check_wp_config_constant_status($constant_name) {
        if (defined($constant_name)) {
            $constant_value = constant($constant_name);
            $status = !empty($constant_value); // Check if the constant is not empty (covers boolean, string, number)
            $details = is_bool($constant_value) ? ($constant_value ? 'true' : 'false') : $constant_value;

            return [
                'status' => $status,
                'details' => $details
            ];
        } else {
            return [
                'status' => false,
                'details' => 'Constant not defined'
            ];
        }
    }
} else write_log("Warning: hws_base_tools/check_wp_config_constant_status function is already declared", true);

if (!function_exists('hws_base_tools\check_wordpress_memory_limit')) {
    function check_wordpress_memory_limit() {
        $memory_limit = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined';
        $memory_limit_bytes = $memory_limit !== 'Not defined' ? wp_convert_hr_to_bytes($memory_limit) : 0;

        // Check if the memory limit is at least 1GB (1024M)
        $status = $memory_limit_bytes >= 1 * 1024 * 1024 * 1024; 

        return [
            'status' => $status,
            'details' => $memory_limit
        ];
    }
} else write_log("Warning: hws_base_tools/check_wordpress_memory_limit function is already declared",true);


if (!function_exists('hws_base_tools\get_database_table_prefix')) {
    function get_database_table_prefix() {
        global $table_prefix;

        $status = !empty($table_prefix);

        return [
            'status' => $status,
            'details' => $status ? $table_prefix : 'Not available'
        ];
    }
} else {
    write_log("Warning: get_database_table_prefix function is already declared", true);
}



function check_myisam_tables() {
    global $wpdb;
    $myisam_tables = $wpdb->get_results("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND ENGINE = 'MyISAM'");
    
    $status = empty($myisam_tables);
    
    return [
        'status' => $status,
        'details' => $status ? 'All tables are InnoDB' : 'There are MyISAM tables present: ' . implode(', ', array_column($myisam_tables, 'TABLE_NAME'))
    ];
}



if (!function_exists('hws_base_tools\check_server_memory_limit')) {
    function check_server_memory_limit() {
        $total_ram = 0;

        if (function_exists('shell_exec')) {
            $total_ram = trim(shell_exec("free -b | awk '/^Mem:/{print $2}'")); // Get total RAM in bytes
        }

        $status = $total_ram >= 4 * 1024 * 1024 * 1024; // Check if RAM is at least 4GB

        return [
            'status' => $status,
            'details' => $total_ram ? size_format($total_ram) : 'Not available'
        ];
    }
} else write_log("Warning: hws_base_tools/check_server_memory_limit function is already declared",true);




if (!function_exists('hws_base_tools\check_redis_active')) {
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
            'details' => $details
        ];
    }
} else {
    write_log("Warning: check_redis_active function is already declared", true);
}




if (!function_exists('hws_base_tools\check_server_ram')) {
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
} else write_log("Warning: hws_base_tools/check_server_ram function is already declared",true);


if (!function_exists('hws_base_tools\check_wp_debug_disabled')) {
    function check_wp_debug_disabled() {
        return defined('WP_DEBUG') && !WP_DEBUG;
    }
} else write_log("Warning: hws_base_tools/check_wp_debug_disabled function is already declared",true);


if (!function_exists('hws_base_tools\check_log_file_sizes')) {
    function check_log_file_sizes() {
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $error_log_path = ABSPATH . 'error_log';

        // Check if the log files exist and get their sizes or show "Not Found"
        $debug_log_size = file_exists($debug_log_path) ? filesize($debug_log_path) : 'Not Found';
        $error_log_size = file_exists($error_log_path) ? filesize($error_log_path) : 'Not Found';

        // Determine the status based on whether the log files exceed 20MB
        $debug_log_status = is_numeric($debug_log_size) && $debug_log_size <= 20 * 1024 * 1024;
        $error_log_status = is_numeric($error_log_size) && $error_log_size <= 20 * 1024 * 1024;

        return [
            'debug_log' => [
                'status' => $debug_log_status,
                'details' => is_numeric($debug_log_size) ? size_format($debug_log_size) : 'Not Found'
            ],
            'error_log' => [
                'status' => $error_log_status,
                'details' => is_numeric($error_log_size) ? size_format($error_log_size) : 'Not Found'
            ]
        ];
    }
} else write_log("Warning: hws_base_tools/check_log_file_sizes function is already declared",true);



if (!function_exists('hws_base_tools\check_server_is_litespeed')) {
    function check_server_is_litespeed() {
        return strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }
} else write_log("Warning: hws_base_tools/check_server_is_litespeed function is already declared",true);


if (!function_exists('hws_base_tools\check_php_version')) {
    function check_php_version() {
        return version_compare(PHP_VERSION, '8.3', '>=');
    }
} else write_log("Warning: hws_base_tools/check_php_version function is already declared",true);


if (!function_exists('hws_base_tools\check_php_sapi_is_litespeed')) {
    function check_php_sapi_is_litespeed() {
        return strpos(php_sapi_name(), 'litespeed') !== false;
    }
} else write_log("Warning: hws_base_tools/check_php_sapi_is_litespeed function is already declared",true);


if (!function_exists('hws_base_tools\display_precheck_result')) {
    function display_precheck_result($label, $status, $details = '') {
        $color = $status ? 'green' : 'red';
        $icon = $status ? '&#x2705;' : '&#x274C;';
        $details_html = $details ? "<span style='color: gray; font-size: 12px;'>$details</span>" : '';
        echo "<div style='color: $color; margin-bottom: 10px;'><strong>$label:</strong> $icon $details_html</div>";
    }
} else write_log("Warning: hws_base_tools/display_precheck_result function is already declared",true);




if (!function_exists('hws_base_tools\check_imagick_available')) {
    function check_imagick_available() {
        return extension_loaded('imagick');
    }
} else write_log("Warning: hws_base_tools/check_imagick_available function is already declared",false);


if (!function_exists('hws_base_tools\check_query_monitor_status')) {
    function check_query_monitor_status() {
        // Check the status of Query Monitor plugin using the generic function
        list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status('query-monitor/query-monitor.php');
        return [
            'is_installed' => $is_installed,
            'is_active' => $is_active,
        ];
    }
} else write_log("Warning: hws_base_tools/check_query_monitor_status function is already declared",true);




if (!function_exists('hws_base_tools\custom_wp_admin_logo')) {
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
} else write_log("Warning: hws_base_tools/custom_wp_admin_logo function is already declared",true);


if (!function_exists('hws_base_tools\custom_wp_admin_logo_link')) {
    function custom_wp_admin_logo_link() {
        return false;
    }
    add_filter('login_headerurl', 'hws_base_tools\custom_wp_admin_logo_link');
} else write_log("Warning: hws_base_tools/custom_wp_admin_logo_link function is already declared",true);








if (!function_exists('hws_base_tools\check_server_specs')) {
    function check_server_specs() {
        $num_processors = function_exists('shell_exec') ? shell_exec('nproc') : 'Unknown';
        $total_ram = function_exists('shell_exec') ? shell_exec("free -m | awk '/^Mem:/{print $2}'") : 'Unknown';

        return [
            'num_processors' => trim($num_processors),
            'total_ram' => trim($total_ram) . ' MB'
        ];
    }
} else write_log("Warning: hws_base_tools/check_server_specs function is already declared",true);












if (!function_exists('hws_base_tools\check_wordfence_notification_email')) {
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
                'details' => $decoded_result
            ];
        } else {
            write_log('No valid alert emails found.');
            return [
                'status' => false,
                'details' => 'No valid alert emails found'
            ];
        }
    }
} else write_log("Warning: hws_base_tools/check_wordfence_notification_email function is already declared",true);










if (!function_exists('hws_base_tools\check_wordpress_main_email')) {
    function check_wordpress_main_email() {
        $admin_email = get_option('admin_email');

        // Ensure the email is retrieved and is a valid email address
        $status = !empty($admin_email) && is_email($admin_email);

        return [
            'status' => $status,
            'details' => $status ? $admin_email : 'Not set'
        ];
    }
} else write_log("Warning: hws_base_tools/check_wordpress_main_email function is already declared", true);


if (!function_exists('hws_base_tools\check_cloudlinux_config')) {
    function check_cloudlinux_config() {
        $lve_enabled = function_exists('lve_get_limits');
        if (!$lve_enabled) {
            write_log('lve_get_limits function does not exist or is not accessible.', true);
        }
        return $lve_enabled;
    }
} else write_log("Warning: hws_base_tools/check_cloudlinux_config function is already declared", true);

/*
if (!function_exists('hws_base_tools\check_redis_active')) {
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
*/if (!function_exists('hws_base_tools\check_caching_source')) {
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
                    'details' => $name
                ];
            }
        }

        if (defined('LITESPEED_SERVER')) {
            return [
                'status' => true,
                'details' => 'LiteSpeed Server'
            ];
        }

        return [
            'status' => false,
            'details' => 'None'
        ];
    }
} else {
    write_log("Warning: hws_base_tools/check_caching_source function is already declared", true);
}




if (!function_exists('hws_base_tools\check_caching_source')) {
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



if (!function_exists('hws_base_tools\modify_wp_config_constants')) {
    function modify_wp_config_constants($constants_to_update) {
        $wp_config_path = ABSPATH . 'wp-config.php';

        if (!file_exists($wp_config_path) || !is_writable($wp_config_path)) {
            return ['status' => false, 'message' => 'wp-config.php does not exist or is not writable.'];
        }

        $config_content = file_get_contents($wp_config_path);

        foreach ($constants_to_update as $constant => $value) {
            // Prepare the constant definition
            $value = is_bool($value) ? ($value ? 'true' : 'false') : "'$value'";
            $new_constant = "define('$constant', $value); // Added/Modified by HWS Core Tools plugin";

            // Remove any existing definition of the constant, along with any existing comment
            $config_content = preg_replace(
                "/define\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*.*?\);\s*\/\/.*\n?/",
                '',
                $config_content
            );

            // Also, check for any duplicate constants without comments and remove them
            $config_content = preg_replace(
                "/define\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*.*?\);\s*/",
                '',
                $config_content
            );

            // Insert the new constant definition in the correct location
            if ($constant === 'WP_DEBUG' || $constant === 'WP_DEBUG_DISPLAY' || $constant === 'WP_DEBUG_LOG') {
                // Insert these debug-related constants after the 'WP_DEBUG' section
                $debug_position = strpos($config_content, "define('WP_DEBUG',");
                if ($debug_position !== false) {
                    $end_of_debug_section = strpos($config_content, "\n", $debug_position) + 1;
                    $config_content = substr_replace($config_content, "$new_constant\n", $end_of_debug_section, 0);
                } else {
                    // Fallback to inserting at the beginning if WP_DEBUG is not found
                    $config_content = "<?php\n$new_constant\n" . ltrim($config_content, "<?php\n");
                }
            } else {
                // Default behavior: insert at the beginning of the file
                $config_content = "<?php\n$new_constant\n" . ltrim($config_content, "<?php\n");
            }
        }

        // Write the updated content back to wp-config.php
        if (file_put_contents($wp_config_path, $config_content)) {
            return ['status' => true, 'message' => 'Constants updated successfully.'];
        } else {
            return ['status' => false, 'message' => 'Failed to update wp-config.php.'];
        }
    }
}












function hws_ct_update_wp_config() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user');
    }

    $constants_to_update = isset($_POST['constants']) ? $_POST['constants'] : [];
    $result = hws_ct_modify_wp_config_constants($constants_to_update);

    if ($result['status']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
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

if (!function_exists('hws_base_tools\get_constant_value_from_wp_config')) {
    function get_constant_value_from_wp_config($constant_name) {
        $wp_config_path = ABSPATH . 'wp-config.php';
        $constant_value = 'Not defined';

        // Check if the wp-config.php file exists
        if (file_exists($wp_config_path)) {
            $config_content = file_get_contents($wp_config_path);

            // Regex pattern to match the constant definition regardless of single or double quotes
            $pattern = "/define\(\s*['\"]" . preg_quote($constant_name, '/') . "['\"]\s*,\s*['\"]?(true|false)['\"]?\s*\)\s*;/i";

            if (preg_match($pattern, $config_content, $matches)) {
                $constant_value = $matches[1] === 'true' ? 'true' : 'false';
            }
        }

        return $constant_value;
    }
}

if (!function_exists('hws_base_tools\check_wp_core_auto_update_status')) {
function check_wp_core_auto_update_status() {
    $wp_auto_update_status = get_constant_value_from_wp_config('WP_AUTO_UPDATE_CORE');
    return $wp_auto_update_status === 'true';
}}


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


if (!function_exists('hws_base_tools\hws_add_wp_admin_settings_page')) {
function hws_add_wp_admin_settings_page($page_title, $menu_title, $capability, $menu_slug, $callback_function) {
    add_options_page(
        $page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $callback_function
    );
}}


    if (!function_exists('hws_base_tools\get_wp_config_defined_constants')) {
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
        'details' => $is_active ? 'Cloudflare is active. Nameservers: ' . implode(', ', $nameserver_list) : 'Cloudflare is not active. Nameservers: ' . implode(', ', $nameserver_list)
    ];
}
// Check the type of PHP (CloudLinux or other)
function check_php_type() {
    $php_sapi = php_sapi_name();
    return [
        'status' => true, // This status would always be true since it's just informational
        'details' => "PHP SAPI: $php_sapi"
    ];
}

if (!function_exists('hws_base_tools\check_php_handler')) {
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
            'status' => $status,
            'details' => $details
        ];
    }
}

if (!function_exists('hws_base_tools\enable_auto_update_plugins')) {
    function enable_auto_update_plugins() {
        add_filter('auto_update_plugin', '__return_true');
    }
}

if (!function_exists('hws_base_tools\disable_litespeed_js_combine')) {
    function disable_litespeed_js_combine() {
        add_filter('litespeed_optm_js_comb_ext_inl', '__return_false');
    }
}



if (!function_exists('hws_base_tools\disable_rankmath_sitemap_caching')) {
    function disable_rankmath_sitemap_caching() {
        add_filter('rank_math/sitemap/enable_caching', '__return_false');
    }
}

