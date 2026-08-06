<?php

namespace hws_base_tools;

use HWS\BaseTools\PluginRuntime\PluginMetadata;
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
if (!function_exists(__NAMESPACE__ . '\\check_caching_source')) {
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
