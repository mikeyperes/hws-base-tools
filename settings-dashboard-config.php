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
    // — Use the robust helpers from generic-functions.php (safe null-coalescing)
    $redis_status      = function_exists( __NAMESPACE__ . '\hws_check_redis_status' ) ? hws_check_redis_status() : [];
    $cloudflare_status = function_exists( __NAMESPACE__ . '\check_cloudflare_active' ) ? check_cloudflare_active() : [ 'status' => false, 'raw_value' => 'N/A' ];
    $myisam_status     = function_exists( __NAMESPACE__ . '\check_myisam_tables' ) ? check_myisam_tables() : [ 'status' => true, 'raw_value' => 'N/A' ];

    // — Build checks array using standardized return formats
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
            'value' => (bool) ( $redis_status['active'] ?? false ),
            'good'  => true,
        ],
        'Cloudflare' => [
            'value' => (bool) ( $cloudflare_status['status'] ?? false ),
            'good'  => true,
        ],
        'MyISAM Tables' => [
            'value' => (bool) ( $myisam_status['status'] ?? true ), // status=true means no MyISAM = good
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

    // — Detailed status messages
    echo '<div style="margin-top: 15px;">';

    // Redis details from robust checker
    $r_info = $redis_status['info'] ?? [];
    $r_msg  = ( $redis_status['active'] ?? false )
        ? 'Active (v' . ( $r_info['version'] ?? '?' ) . ', ' . ( $r_info['used_memory'] ?? '' ) . ')'
        : ( $redis_status['error'] ?? 'Inactive' );
    echo '<p><strong>Redis:</strong> ' . esc_html( $r_msg );
    if ( ! empty( $r_info ) ) {
        echo '<br><small style="color:#666;">Host: ' . esc_html( $r_info['host'] ?? '' ) . ':' . ( $r_info['port'] ?? '' )
           . ' | DB: ' . ( $r_info['db_index'] ?? 0 ) . ' | Keys: ' . number_format( (int)( $r_info['total_keys'] ?? 0 ) )
           . ' | Hit Rate: ' . ( $r_info['hit_rate'] ?? 'N/A' ) . '</small>';
    }
    echo ' - <a href="' . esc_url( admin_url( 'admin.php?page=litespeed-cache' ) ) . '" target="_blank">View in LiteSpeed</a></p>';

    // Cloudflare
    echo '<p><strong>Cloudflare:</strong> ' . esc_html( $cloudflare_status['raw_value'] ?? 'Unknown' ) . '</p>';

    // MyISAM
    $has_myisam = ! ( $myisam_status['status'] ?? true );
    $myisam_style = $has_myisam ? 'color:red;font-weight:bold;' : '';
    echo '<p><strong>MyISAM:</strong> <span style="' . $myisam_style . '">' . esc_html( $myisam_status['raw_value'] ?? 'N/A' ) . '</span>';
    echo ' - <a href="' . esc_url( admin_url( 'admin.php?page=litespeed-db_optm' ) ) . '" target="_blank">View in LiteSpeed</a></p>';
    echo '</div>';
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
