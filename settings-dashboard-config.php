<?php namespace hws_base_tools;

/**
 * Configuration Tab - System Settings
 * 
 * @since 8.9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Configuration Tab
 */
function render_tab_config() {
    ?>
    <!-- WP Config Constants -->
    <div class="hws-panel">
        <div class="hws-panel-header">📝 WP-Config Constants</div>
        <div class="hws-panel-body">
            <div style="margin-bottom: 15px;">
                <button type="button" class="hws-btn hws-btn-secondary" id="toggle-wp-config-view">View wp-config.php</button>
                <button type="button" class="hws-btn hws-btn-secondary" id="toggle-wp-constants">Show All Constants</button>
            </div>
            
            <div id="wp-config-view" style="display: none; margin-top: 15px;">
                <pre style="background: #1d2327; color: #50c878; padding: 15px; border-radius: 6px; max-height: 400px; overflow: auto; font-size: 12px;">
<?php
$wp_config_path = ABSPATH . 'wp-config.php';
$exclude = [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];

if ( file_exists( $wp_config_path ) ) {
    $lines = file( $wp_config_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    foreach ( $lines as $line ) {
        $skip = false;
        foreach ( $exclude as $const ) {
            if ( strpos( $line, $const ) !== false ) {
                $skip = true;
                break;
            }
        }
        if ( ! $skip ) {
            echo htmlspecialchars( $line ) . "\n";
        }
    }
} else {
    echo 'wp-config.php not found';
}
?>
                </pre>
            </div>
            
            <div id="wp-constants-view" style="display: none; margin-top: 15px;">
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; max-height: 300px; overflow: auto;">
                    <?php
                    $constants = get_wp_config_defined_constants();
                    foreach ( $constants as $name => $value ) {
                        $display_value = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value;
                        echo '<p><code>' . esc_html( $name ) . '</code> = <strong>' . esc_html( $display_value ) . '</strong></p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Checks -->
    <div class="hws-panel">
        <div class="hws-panel-header">🔍 System Checks</div>
        <div class="hws-panel-body">
            <?php render_system_checks(); ?>
        </div>
    </div>
    
    <!-- PHP Info -->
    <div class="hws-panel">
        <div class="hws-panel-header">🐘 PHP Environment</div>
        <div class="hws-panel-body">
            <?php render_php_info(); ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#toggle-wp-config-view').on('click', function() {
            $('#wp-config-view').slideToggle();
        });
        $('#toggle-wp-constants').on('click', function() {
            $('#wp-constants-view').slideToggle();
        });
    });
    </script>
    <?php
}


/**
 * Render system checks
 */
function render_system_checks() {
    // Get Redis and Cloudflare status
    $redis_status = hws_check_redis_status();
    $cloudflare_status = hws_check_cloudflare_status();
    $myisam_status = hws_check_myisam_tables();
    
    $checks = [
        'WordPress Auto Updates' => [
            'value' => defined( 'WP_AUTO_UPDATE_CORE' ) && WP_AUTO_UPDATE_CORE,
            'good'  => true,
        ],
        'Plugin Auto Updates' => [
            'value' => has_filter( 'auto_update_plugin', '__return_true' ) || get_option( 'enable_auto_update_plugins', false ),
            'good'  => true,
        ],
        'Theme Auto Updates' => [
            'value' => has_filter( 'auto_update_theme', '__return_true' ) || get_option( 'enable_auto_update_themes', false ),
            'good'  => true,
        ],
        'WP_CACHE' => [
            'value' => defined( 'WP_CACHE' ) && WP_CACHE,
            'good'  => true,
        ],
        'Redis Object Cache' => [
            'value' => $redis_status['status'] === true,
            'good'  => true,
        ],
        'Cloudflare' => [
            'value' => $cloudflare_status['status'] === true,
            'good'  => true,
        ],
        'MyISAM Tables' => [
            'value' => $myisam_status['status'] === false, // No MyISAM = good
            'good'  => true,
        ],
    ];
    
    echo '<div class="hws-status-grid">';
    foreach ( $checks as $label => $check ) :
        $is_good = ( $check['good'] && $check['value'] ) || ( ! $check['good'] && ! $check['value'] );
        $class = $is_good ? 'good' : 'warn';
        $icon = $is_good ? '✅' : '⚠️';
    ?>
        <div class="hws-status-card <?php echo $class; ?>">
            <div class="value"><?php echo $icon; ?></div>
            <div class="label"><?php echo esc_html( $label ); ?></div>
        </div>
    <?php endforeach;
    echo '</div>';
    
    // Show detailed status messages
    echo '<div style="margin-top: 15px;">';
    
    // Redis with full details
    echo '<p><strong>Redis:</strong> ' . esc_html( $redis_status['message'] );
    if ( ! empty( $redis_status['details'] ) ) {
        echo '<br><small style="color: #666;">' . esc_html( $redis_status['details'] ) . '</small>';
    }
    echo ' - <a href="' . esc_url( admin_url( 'admin.php?page=litespeed-cache' ) ) . '" target="_blank">View in LiteSpeed</a></p>';
    
    // Cloudflare with nameserver info
    echo '<p><strong>Cloudflare:</strong> ' . esc_html( $cloudflare_status['message'] );
    if ( ! empty( $cloudflare_status['nameservers'] ) ) {
        echo '<br><small style="color: #666;">Nameservers: ' . esc_html( implode( ', ', $cloudflare_status['nameservers'] ) ) . '</small>';
    }
    echo '</p>';
    
    // MyISAM - show RED if tables found, include link
    $myisam_color = $myisam_status['status'] ? 'color: red; font-weight: bold;' : '';
    echo '<p><strong>MyISAM:</strong> <span style="' . $myisam_color . '">' . esc_html( $myisam_status['message'] ) . '</span>';
    echo ' - <a href="' . esc_url( admin_url( 'admin.php?page=litespeed-db_optm' ) ) . '" target="_blank">View More in LiteSpeed</a></p>';
    echo '</div>';
}


/**
 * Check MyISAM tables - ONLY for current WordPress prefix
 */
function hws_check_myisam_tables() {
    global $wpdb;
    
    // Use current WordPress prefix only
    $prefix = $wpdb->prefix;
    
    $myisam_tables = $wpdb->get_results( $wpdb->prepare( "
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND ENGINE = 'MyISAM'
        AND TABLE_NAME LIKE %s
    ", $prefix . '%' ) );
    
    if ( empty( $myisam_tables ) ) {
        return [ 'status' => false, 'message' => 'No MyISAM tables found (good!)' ];
    }
    
    $table_names = array_map( function( $t ) { return $t->TABLE_NAME; }, $myisam_tables );
    return [ 
        'status' => true, // true = bad (MyISAM found)
        'message' => count( $myisam_tables ) . ' MyISAM tables found: ' . implode( ', ', array_slice( $table_names, 0, 5 ) ) . ( count( $table_names ) > 5 ? '...' : '' )
    ];
}


/**
 * Check Redis status properly - with full details
 */
function hws_check_redis_status() {
    // Check if Redis PHP extension is loaded
    if ( ! extension_loaded( 'redis' ) ) {
        return [ 'status' => false, 'message' => 'Redis extension not installed', 'details' => '' ];
    }
    
    // Check if we can connect
    try {
        $redis = new \Redis();
        $connected = @$redis->connect( '127.0.0.1', 6379, 2 ); // 2 second timeout
        
        if ( ! $connected ) {
            return [ 'status' => false, 'message' => 'Redis service not running', 'details' => '' ];
        }
        
        // Get detailed Redis info
        $info = $redis->info();
        $details = '';
        
        if ( $info ) {
            $server_version = isset( $info['redis_version'] ) ? $info['redis_version'] : 'Unknown';
            $port = isset( $info['tcp_port'] ) ? $info['tcp_port'] : '6379';
            $used_memory = isset( $info['used_memory_human'] ) ? $info['used_memory_human'] : 'Unknown';
            $peak_memory = isset( $info['used_memory_peak_human'] ) ? $info['used_memory_peak_human'] : 'Unknown';
            $uptime = isset( $info['uptime_in_seconds'] ) ? $info['uptime_in_seconds'] : 0;
            $connections = isset( $info['total_connections_received'] ) ? number_format( $info['total_connections_received'] ) : 'Unknown';
            $commands = isset( $info['total_commands_processed'] ) ? number_format( $info['total_commands_processed'] ) : 'Unknown';
            $db_index = 0;
            
            // Format uptime
            $uptime_days = floor( $uptime / 86400 );
            $uptime_str = $uptime_days . ' days';
            
            $details = "Server: $server_version | Port: $port | DB: $db_index | Memory: $used_memory | Peak: $peak_memory | Uptime: $uptime_str | Connections: $connections | Commands: $commands";
        }
        
        // Check if LiteSpeed object cache is using Redis
        $lscwp_redis = defined( 'LSCWP_OBJECT_CACHE' ) && LSCWP_OBJECT_CACHE;
        
        $redis->close();
        
        if ( $lscwp_redis ) {
            return [ 
                'status' => true, 
                'message' => 'Redis active + LiteSpeed Object Cache enabled',
                'details' => $details
            ];
        } else {
            return [ 
                'status' => 'partial', 
                'message' => 'Redis available but Object Cache not enabled',
                'details' => $details
            ];
        }
    } catch ( \Exception $e ) {
        return [ 'status' => false, 'message' => 'Redis error: ' . $e->getMessage(), 'details' => '' ];
    }
}


/**
 * Check Cloudflare status properly - with nameservers
 */
function hws_check_cloudflare_status() {
    $nameservers = [];
    
    // Get nameservers first
    $site_domain = parse_url( home_url(), PHP_URL_HOST );
    $site_domain = preg_replace( '/^www\./', '', $site_domain );
    $ns_records = @dns_get_record( $site_domain, DNS_NS );
    
    if ( $ns_records ) {
        $nameservers = array_column( $ns_records, 'target' );
    }
    
    // Method 1: Check HTTP headers
    if ( isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $cf_ray = isset( $_SERVER['HTTP_CF_RAY'] ) ? $_SERVER['HTTP_CF_RAY'] : '';
        return [ 
            'status' => true, 
            'message' => 'Cloudflare active (CF headers present)' . ( $cf_ray ? " - Ray ID: $cf_ray" : '' ),
            'nameservers' => $nameservers
        ];
    }
    
    // Method 2: Check nameservers for Cloudflare
    if ( $ns_records ) {
        foreach ( $ns_records as $record ) {
            if ( isset( $record['target'] ) && stripos( $record['target'], 'cloudflare' ) !== false ) {
                return [ 
                    'status' => true, 
                    'message' => 'Cloudflare nameservers detected',
                    'nameservers' => $nameservers
                ];
            }
        }
    }
    
    // Method 3: Check for Cloudflare plugin
    if ( is_plugin_active( 'cloudflare/cloudflare.php' ) ) {
        return [ 
            'status' => 'partial', 
            'message' => 'Cloudflare plugin active (proxy status unknown)',
            'nameservers' => $nameservers
        ];
    }
    
    return [ 
        'status' => false, 
        'message' => 'Not using Cloudflare',
        'nameservers' => $nameservers
    ];
}


/**
 * Render PHP info (cleaned up - removed specified extensions)
 */
function render_php_info() {
    $php_version = phpversion();
    $required = '8.1.0';
    $is_modern = version_compare( $php_version, $required, '>=' );
    
    // Extensions to check (removed: MYSQLND, MEMCACHE, MEMCACHED, HTTP, OAUTH, IMAP)
    $extensions = [
        'Redis'     => extension_loaded( 'redis' ),
        'Imagick'   => extension_loaded( 'imagick' ),
        'MySQLi'    => extension_loaded( 'mysqli' ),
        'OPcache'   => extension_loaded( 'opcache' ),
        'DOM'       => extension_loaded( 'dom' ),
        'XMLWriter' => extension_loaded( 'xmlwriter' ),
        'XMLReader' => extension_loaded( 'xmlreader' ),
        'BZ2'       => extension_loaded( 'bz2' ),
        'Brotli'    => extension_loaded( 'brotli' ),
    ];
    ?>
    <div class="hws-status-grid">
        <div class="hws-status-card <?php echo $is_modern ? 'good' : 'bad'; ?>">
            <div class="value"><?php echo esc_html( $php_version ); ?></div>
            <div class="label">PHP Version</div>
        </div>
        <?php foreach ( $extensions as $name => $loaded ) : ?>
            <div class="hws-status-card <?php echo $loaded ? 'good' : ''; ?>">
                <div class="value"><?php echo $loaded ? '✅' : '❌'; ?></div>
                <div class="label"><?php echo esc_html( $name ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <h4 style="margin-top: 20px;">PHP INI Settings</h4>
    <table class="hws-plugin-table">
        <tr>
            <td><strong>memory_limit</strong></td>
            <td><?php echo ini_get( 'memory_limit' ); ?></td>
        </tr>
        <tr>
            <td><strong>max_execution_time</strong></td>
            <td><?php echo ini_get( 'max_execution_time' ); ?>s</td>
        </tr>
        <tr>
            <td><strong>upload_max_filesize</strong></td>
            <td><?php echo ini_get( 'upload_max_filesize' ); ?></td>
        </tr>
        <tr>
            <td><strong>post_max_size</strong></td>
            <td><?php echo ini_get( 'post_max_size' ); ?></td>
        </tr>
        <tr>
            <td><strong>max_input_vars</strong></td>
            <td><?php echo ini_get( 'max_input_vars' ); ?></td>
        </tr>
    </table>
    <?php
}


/**
 * Render Advanced Tab
 */
function render_tab_advanced() {
    if ( function_exists( __NAMESPACE__ . '\\display_settings_seo_reporting' ) ) {
        display_settings_seo_reporting();
    }
    if ( get_option( 'enable_custom_rss_functionality', false ) && function_exists( __NAMESPACE__ . '\\display_settings_rss_dashboard' ) ) {
        display_settings_rss_dashboard();
    }
    if ( function_exists( __NAMESPACE__ . '\\display_shortcode_tests' ) ) {
        display_shortcode_tests();
    }
}
