<?php

namespace hws_base_tools;
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

        $policy              = ( new \Hexa\PluginCore\WordPressOperations\AutoUpdatePolicy() )->status();
        $auto_update_plugins = (array) ( $policy['items']['plugins'] ?? [] );

        // Check if the specific plugin has auto-updates enabled
        return in_array( (string) $plugin_id, $auto_update_plugins, true );
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

        $policy             = ( new \Hexa\PluginCore\WordPressOperations\AutoUpdatePolicy() )->status();
        $auto_update_themes = (array) ( $policy['items']['themes'] ?? [] );

        // Log the retrieved list of themes with auto-updates enabled
        write_log("Auto-update enabled themes: " . implode(', ', $auto_update_themes));

        // Check if the specific theme has auto-updates enabled
        $is_enabled = in_array( (string) $theme_slug, $auto_update_themes, true );
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
