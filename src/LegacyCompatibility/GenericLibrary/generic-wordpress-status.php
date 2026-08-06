<?php

namespace hws_base_tools;
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
