<?php namespace hws_base_tools;

/**
 * HWS Base Tools - Main Settings Dashboard
 * 
 * Features:
 * - Single-page tabs (no refresh)
 * - Debug controls at top
 * - Secret URL debug toggle
 * - Fatal error detection
 * - Quick Setup wizard
 * - Plugin status monitoring
 * - Backup detection with cron cleanup
 * 
 * @since 8.9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard Configuration
 */
class Dashboard_Config {
    // Secret URL keys
    const SECRET_DEBUG_KEY      = 'hws_debug';
    const SECRET_FATAL_KEY      = 'hws_fatal_log';
    const SECRET_SETUP_KEY      = 'hws_quick_setup';
    const SECRET_PERMALINKS_KEY = 'hws_purge_permalinks';
    const SECRET_KEY_VALUE      = 'hht112';
    
    // Options
    const OPT_SECRET_URLS_ENABLED       = 'hws_secret_urls_enabled';
    const OPT_SECRET_SETUP_ENABLED      = 'hws_secret_setup_enabled';
    const OPT_SECRET_PERMALINKS_ENABLED = 'hws_secret_permalinks_enabled';
    
    /**
     * Get secret URLs enabled status (default: false for security)
     */
    public static function are_secret_urls_enabled() {
        $val = get_option( self::OPT_SECRET_URLS_ENABLED, 'no' );
        // Handle both old boolean and new string format
        return $val === 'yes' || $val === true || $val === '1' || $val === 1;
    }
    
    /**
     * Get secret setup URL enabled status (default: false for security)
     */
    public static function is_secret_setup_enabled() {
        $val = get_option( self::OPT_SECRET_SETUP_ENABLED, 'no' );
        // Handle both old boolean and new string format
        return $val === 'yes' || $val === true || $val === '1' || $val === 1;
    }
    
    /**
     * Get secret permalinks purge URL enabled status (default: false for security)
     */
    public static function is_secret_permalinks_enabled() {
        $val = get_option( self::OPT_SECRET_PERMALINKS_ENABLED, 'no' );
        // Handle both old boolean and new string format
        return $val === 'yes' || $val === true || $val === '1' || $val === 1;
    }
}


/**
 * Handle secret URL debug toggle
 * Runs early on init to catch before any output
 */
function hws_handle_secret_debug_url() {
    // Handle debug toggle: /?hws_debug=hht112
    if ( Dashboard_Config::are_secret_urls_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_DEBUG_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_DEBUG_KEY ] === Dashboard_Config::SECRET_KEY_VALUE ) {
        
        // Toggle all debug settings
        $wp_config_path = ABSPATH . 'wp-config.php';
        if ( is_writable( $wp_config_path ) ) {
            $current_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
            $new_value = $current_debug ? 'false' : 'true';
            
            // Update all 4 debug constants
            $constants = [
                'WP_DEBUG'         => $new_value,
                'WP_DEBUG_DISPLAY' => $new_value,
                'WP_DEBUG_LOG'     => $new_value,
            ];
            
            if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
                modify_wp_config_constants( $constants );
            }
            
            // Also update display_errors ini
            @ini_set( 'display_errors', $new_value === 'true' ? '1' : '0' );
            
            // Redirect to remove query string
            wp_safe_redirect( remove_query_arg( Dashboard_Config::SECRET_DEBUG_KEY ) );
            exit;
        }
    }
    
    // Handle fatal log display: /?hws_fatal_log=hht112
    if ( Dashboard_Config::are_secret_urls_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_FATAL_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_FATAL_KEY ] === Dashboard_Config::SECRET_KEY_VALUE ) {
        hws_display_fatal_errors_page();
        exit;
    }
    
    // Handle quick setup: /?hws_quick_setup=hht112
    if ( Dashboard_Config::is_secret_setup_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_SETUP_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_SETUP_KEY ] === Dashboard_Config::SECRET_KEY_VALUE ) {
        hws_run_quick_setup_public();
        exit;
    }
    
    // Handle permalink purge: /?hws_purge_permalinks=hht112
    if ( Dashboard_Config::is_secret_permalinks_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_PERMALINKS_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_PERMALINKS_KEY ] === Dashboard_Config::SECRET_KEY_VALUE ) {
        hws_purge_permalinks_public();
        exit;
    }
}
add_action( 'init', __NAMESPACE__ . '\\hws_handle_secret_debug_url', 1 );


/**
 * Run quick setup from public URL (no admin required)
 * Supports ?format=text for plain text output (terminal friendly)
 */
function hws_run_quick_setup_public() {
    // Check if plain text output is requested
    $format = isset( $_GET['format'] ) ? $_GET['format'] : 'html';
    
    $log = hws_execute_quick_setup();
    
    // Plain text output for terminal/scripts
    if ( $format === 'text' ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo "HWS Quick Setup\n";
        echo "===============\n\n";
        // Strip HTML tags and convert entities for plain text
        $plain_log = strip_tags( $log );
        $plain_log = html_entity_decode( $plain_log );
        echo $plain_log;
        echo "\nCompleted: " . current_time( 'mysql' ) . "\n";
        return;
    }
    
    // HTML output for browser
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HWS Quick Setup</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #1d2327; color: #50c878; }
            h1 { color: #fff; }
            .log { background: #0a0a0a; padding: 20px; border-radius: 8px; white-space: pre-wrap; font-family: monospace; }
            .success { color: #00a32a; }
            .warning { color: #dba617; }
            .error { color: #d63638; }
            a { color: #4facfe; }
        </style>
    </head>
    <body>
        <h1>⚡ HWS Quick Setup</h1>
        <p><a href="<?php echo home_url(); ?>">← Back to site</a> | <a href="<?php echo admin_url(); ?>">Admin →</a></p>
        <div class="log"><?php echo $log; ?></div>
        <p style="color: #666; font-size: 12px; margin-top: 10px;">💡 Tip: Add <code>&format=text</code> to URL for plain text output (terminal friendly)</p>
        <p style="color: #999; font-size: 12px; margin-top: 20px;">Completed: <?php echo current_time( 'mysql' ); ?></p>
    </body>
    </html>
    <?php
}


/**
 * Purge/flush permalinks from public URL (no admin required)
 * Supports ?format=text for plain text output (terminal friendly)
 */
function hws_purge_permalinks_public() {
    // Check if plain text output is requested
    $format = isset( $_GET['format'] ) ? $_GET['format'] : 'html';
    
    // Flush rewrite rules
    global $wp_rewrite;
    $wp_rewrite->flush_rules( true );
    
    // Also update rewrite rules
    flush_rewrite_rules( true );
    
    // Get permalink info
    $permalink_structure = get_option( 'permalink_structure' ) ?: '(Default - Plain)';
    $rewrite_rules = get_option( 'rewrite_rules' );
    $rules_count = is_array( $rewrite_rules ) ? count( $rewrite_rules ) : 0;
    $timestamp = current_time( 'Y-m-d H:i:s' );
    
    // Plain text output for terminal/scripts
    if ( $format === 'text' ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo "HWS Purge Permalinks\n";
        echo "====================\n\n";
        echo "✅ Permalinks flushed successfully!\n\n";
        echo "Permalink Structure: {$permalink_structure}\n";
        echo "Rewrite Rules Count: {$rules_count} rules\n";
        echo "Site URL: " . home_url() . "\n";
        echo "WordPress URL: " . site_url() . "\n\n";
        echo "What this does:\n";
        echo "- Flushes WordPress rewrite rules\n";
        echo "- Regenerates .htaccess rules (if applicable)\n";
        echo "- Clears permalink cache\n";
        echo "- Useful after changing post types, taxonomies, or fixing 404 errors\n\n";
        echo "Timestamp: {$timestamp}\n";
        return;
    }
    
    // HTML output for browser
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HWS Purge Permalinks</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #1d2327; color: #50c878; }
            h1 { color: #fff; }
            .log { background: #0a0a0a; padding: 20px; border-radius: 8px; white-space: pre-wrap; font-family: monospace; }
            .success { color: #00a32a; }
            .info { color: #4facfe; }
            a { color: #4facfe; }
            table { border-collapse: collapse; margin: 15px 0; }
            td, th { padding: 8px 15px; text-align: left; border-bottom: 1px solid #333; }
            th { color: #999; }
        </style>
    </head>
    <body>
        <h1>🔄 HWS Purge Permalinks</h1>
        <p><a href="<?php echo home_url(); ?>">← Back to site</a> | <a href="<?php echo admin_url( 'options-permalink.php' ); ?>">Permalink Settings →</a></p>
        
        <div class="log">
<span class="success">✅ Permalinks flushed successfully!</span>

<table>
<tr><th>Setting</th><th>Value</th></tr>
<tr><td>Permalink Structure</td><td><?php echo esc_html( $permalink_structure ); ?></td></tr>
<tr><td>Rewrite Rules Count</td><td><?php echo $rules_count; ?> rules</td></tr>
<tr><td>Site URL</td><td><?php echo esc_html( home_url() ); ?></td></tr>
<tr><td>WordPress URL</td><td><?php echo esc_html( site_url() ); ?></td></tr>
</table>

<span class="info">ℹ️ What this does:</span>
- Flushes WordPress rewrite rules
- Regenerates .htaccess rules (if applicable)
- Clears permalink cache
- Useful after changing post types, taxonomies, or fixing 404 errors

<span class="info">💡 Tip:</span> Add <code>&format=text</code> to URL for plain text output (terminal friendly)

<span class="success">Timestamp: <?php echo $timestamp; ?></span>
        </div>
    </body>
    </html>
    <?php
}


/**
 * Display fatal errors page (for secret URL access)
 */
function hws_display_fatal_errors_page() {
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HWS Fatal Error Log</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #1d2327; color: #50c878; }
            h1 { color: #fff; }
            pre { background: #0a0a0a; padding: 20px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
            .error-line { color: #ff6b6b; font-weight: bold; }
            .warning-line { color: #ffd93d; }
            .notice-line { color: #6bcb77; }
            a { color: #4facfe; }
        </style>
    </head>
    <body>
        <h1>🔴 HWS Fatal Error Log</h1>
        <p><a href="<?php echo home_url(); ?>">← Back to site</a></p>
        <h2>Recent Fatal Errors & Warnings</h2>
        <pre><?php
        $errors = hws_get_recent_fatal_errors();
        if ( empty( $errors ) ) {
            echo "✅ No recent fatal errors found.";
        } else {
            foreach ( $errors as $error ) {
                $class = 'notice-line';
                if ( stripos( $error, 'fatal' ) !== false ) {
                    $class = 'error-line';
                } elseif ( stripos( $error, 'warning' ) !== false || stripos( $error, 'deprecated' ) !== false ) {
                    $class = 'warning-line';
                }
                echo '<span class="' . $class . '">' . htmlspecialchars( $error ) . '</span>' . "\n";
            }
        }
        ?></pre>
        <p style="color: #999; font-size: 12px;">Generated: <?php echo current_time( 'mysql' ); ?></p>
    </body>
    </html>
    <?php
}


/**
 * Get recent fatal errors from logs
 */
function hws_get_recent_fatal_errors( $limit = 100 ) {
    $errors = [];
    $log_files = [
        WP_CONTENT_DIR . '/debug.log',
        ABSPATH . 'error_log',
    ];
    
    $patterns = [
        '/fatal\s*error/i',
        '/parse\s*error/i',
        '/syntax\s*error/i',
        '/uncaught\s*(exception|error)/i',
        '/warning:/i',
        '/deprecated:/i',
    ];
    
    foreach ( $log_files as $log_file ) {
        if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
            continue;
        }
        
        $lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( ! $lines ) continue;
        
        // Get last 500 lines
        $lines = array_slice( $lines, -500 );
        
        foreach ( $lines as $line ) {
            foreach ( $patterns as $pattern ) {
                if ( preg_match( $pattern, $line ) ) {
                    $errors[] = $line;
                    break;
                }
            }
        }
    }
    
    // Return most recent, limited
    return array_slice( array_reverse( array_unique( $errors ) ), 0, $limit );
}


/**
 * Add settings menu
 */
function add_wp_admin_settings_page() {
    add_options_page(
        Config::$settings_page_name,
        Config::$settings_page_name,
        Config::$settings_page_capability,
        Config::$settings_page_slug,
        __NAMESPACE__ . '\\display_wp_admin_settings_page'
    );
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_wp_admin_settings_page' );


/**
 * Register AJAX handlers
 */
function hws_dashboard_register_ajax() {
    add_action( 'wp_ajax_hws_quick_setup', __NAMESPACE__ . '\\ajax_quick_setup' );
    add_action( 'wp_ajax_hws_toggle_secret_urls', __NAMESPACE__ . '\\ajax_toggle_secret_urls' );
    add_action( 'wp_ajax_hws_toggle_secret_setup', __NAMESPACE__ . '\\ajax_toggle_secret_setup' );
    add_action( 'wp_ajax_hws_toggle_secret_permalinks', __NAMESPACE__ . '\\ajax_toggle_secret_permalinks' );
    add_action( 'wp_ajax_hws_enable_all_auto_updates', __NAMESPACE__ . '\\ajax_enable_all_auto_updates' );
    // Note: hws_delete_backups is registered in settings-dashboard-backups.php
    // Note: hws_toggle_all_debug uses existing hws_base_tools_modify_wp_config_constants handler
}
add_action( 'init', __NAMESPACE__ . '\\hws_dashboard_register_ajax' );


/**
 * Main settings page display
 */
function display_wp_admin_settings_page() {
    if ( ob_get_level() == 0 ) ob_start();
    
    $tabs = [
        'overview'      => '📊 Overview',
        'system-checks' => '🔍 System Checks',
        'plugins'       => '🔌 Plugins',
        'snippets'      => '✂️ Snippets',
        'website-types' => '🌐 Website Types',
        'ui-cleanup'    => '🧹 UI Cleanup',
        'config'        => '⚙️ Configuration',
        'backups'       => '💾 Backups',
        'advanced'      => '🔧 Advanced',
        'comments'      => '💬 Comments',
    ];
    ?>
    <style>
        /* === GLOBAL STYLES === */
        #hws-base-tools { max-width: 1400px; }
        #hws-base-tools * { box-sizing: border-box; }
        
        /* Tabs - No Refresh */
        .hws-tabs-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            border-bottom: 2px solid #c3c4c7;
            margin-bottom: 0;
            background: #f0f0f1;
            padding: 10px 10px 0;
        }
        .hws-tab-btn {
            padding: 12px 20px;
            text-decoration: none;
            color: #50575e;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid transparent;
            border-bottom: none;
            background: transparent;
            margin-bottom: -2px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hws-tab-btn:hover { color: #2271b1; background: #fff; }
        .hws-tab-btn.active {
            color: #1d2327;
            background: #fff;
            border-color: #c3c4c7;
            border-bottom-color: #fff;
            border-radius: 4px 4px 0 0;
        }
        .hws-tab-content {
            display: none;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 20px;
        }
        .hws-tab-content.active { display: block; }
        
        /* Panels */
        .hws-panel {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: #fff;
        }
        .hws-panel-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: #f9f9f9;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px 6px 0 0;
        }
        .hws-panel-body { padding: 20px; }
        
        /* Status Cards */
        .hws-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .hws-status-card {
            background: #f6f7f7;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid #c3c4c7;
        }
        .hws-status-card.good { border-left-color: #00a32a; }
        .hws-status-card.bad { border-left-color: #d63638; }
        .hws-status-card.warn { border-left-color: #dba617; }
        .hws-status-card .value { font-size: 20px; font-weight: 600; color: #1d2327; }
        .hws-status-card .label { font-size: 11px; color: #646970; text-transform: uppercase; margin-top: 4px; }
        
        /* Debug Section */
        .hws-debug-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .hws-debug-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .hws-debug-toggle.on { background: #fcf0f1; border-color: #d63638; }
        .hws-debug-toggle.off { background: #edfaef; border-color: #00a32a; }
        
        /* Log Viewer */
        .hws-log-viewer {
            background: #1d2327;
            color: #b4b4b4;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        /* Log color coding */
        .hws-log-viewer .log-fatal,
        .hws-log-viewer .fatal { color: #ff6b6b; font-weight: bold; }
        .hws-log-viewer .log-warning,
        .hws-log-viewer .warning { color: #ffd93d; }
        .hws-log-viewer .log-notice { color: #5dade2; }
        
        /* Log tab buttons */
        .hws-log-tab { margin-right: 5px; }
        .hws-log-tab.active { background: #0073aa !important; color: #fff !important; }
        
        /* Log search highlight */
        .hws-search-highlight,
        mark.hws-search-highlight {
            background: #ffd93d;
            color: #1d2327;
            padding: 1px 3px;
            border-radius: 2px;
            font-weight: inherit;
        }
        .hws-search-highlight.hws-search-current,
        mark.hws-search-highlight.hws-search-current {
            background: #ff6b6b;
            color: #fff;
            box-shadow: 0 0 0 2px rgba(255, 107, 107, 0.4);
        }
        
        /* Summary Sections */
        .hws-summary-section { margin-bottom: 25px; }
        .hws-summary-section h3 { 
            font-size: 16px; 
            font-weight: 600; 
            color: #1d2327;
        }
        .hws-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .hws-summary-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        .hws-summary-card.warn {
            background: #fff8e5;
            border-color: #dba617;
        }
        .hws-summary-card h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #1d2327;
            font-weight: 600;
        }
        .hws-summary-card p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        /* Plugin Status Table */
        .hws-plugin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hws-plugin-table th,
        .hws-plugin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .hws-plugin-table th { background: #f9f9f9; font-weight: 600; }
        .hws-plugin-table tr:hover { background: #f9f9f9; }
        .status-ok { color: #00a32a; }
        .status-bad { color: #d63638; }
        .status-warn { color: #dba617; }
        
        /* Secret URL Box */
        .hws-secret-url-box {
            background: #f0f6fc;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .hws-secret-url-box code {
            background: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            display: block;
            margin: 10px 0;
            word-break: break-all;
            border: 1px solid #ddd;
        }
        
        /* Quick Setup */
        .hws-quick-setup-log {
            width: 100%;
            height: 200px;
            font-family: monospace;
            font-size: 12px;
            background: #1d2327;
            color: #50c878;
            padding: 15px;
            border-radius: 6px;
            border: none;
            resize: none;
        }
        
        /* Red Flag Alert */
        .hws-red-flag {
            background: #fcf0f1;
            border: 2px solid #d63638;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .hws-red-flag h4 { color: #d63638; margin: 0 0 10px; }
        
        /* Summary Cards */
        .hws-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .hws-summary-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
        }
        .hws-summary-card h4 { margin: 0 0 10px; font-size: 14px; }
        
        /* Buttons */
        .hws-btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: 1px solid #2271b1;
            background: #2271b1;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .hws-btn:hover { background: #135e96; }
        .hws-btn-secondary { background: #f6f7f7; color: #2271b1; }
        .hws-btn-secondary:hover { background: #f0f0f1; }
        .hws-btn-danger { background: #d63638; border-color: #d63638; }
        .hws-btn-danger:hover { background: #a82a2c; }
    </style>

    <div class="wrap" id="hws-base-tools">
        <h1><?php echo Config::$settings_page_display_title; ?></h1>
        
        <!-- Tab Navigation -->
        <nav class="hws-tabs-nav">
            <?php $first = true; foreach ( $tabs as $tab_id => $label ) : ?>
                <button type="button" class="hws-tab-btn <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo $tab_id; ?>">
                    <?php echo $label; ?>
                </button>
            <?php $first = false; endforeach; ?>
        </nav>
        
        <!-- Tab Contents - ALL LOADED AT ONCE -->
        <?php $first = true; foreach ( $tabs as $tab_id => $label ) : ?>
            <div id="tab-<?php echo $tab_id; ?>" class="hws-tab-content <?php echo $first ? 'active' : ''; ?>">
                <?php
                switch ( $tab_id ) {
                    case 'overview':
                        render_tab_overview();
                        break;
                    case 'system-checks':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_system_checks' ) ) {
                            display_settings_system_checks();
                        }
                        break;
                    case 'plugins':
                        render_tab_plugins();
                        break;
                    case 'snippets':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_snippets' ) ) {
                            display_settings_snippets();
                        }
                        break;
                    case 'website-types':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_website_types' ) ) {
                            display_settings_website_types();
                        }
                        break;
                    case 'ui-cleanup':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_ui_cleanup' ) ) {
                            display_settings_ui_cleanup();
                        }
                        break;
                    case 'config':
                        render_tab_config();
                        break;
                    case 'backups':
                        render_tab_backups();
                        break;
                    case 'comments':
                        if ( function_exists( __NAMESPACE__ . '\\display_settings_comments_dashboard' ) ) {
                            display_settings_comments_dashboard();
                        }
                        break;
                    case 'advanced':
                        render_tab_advanced();
                        break;
                }
                ?>
            </div>
        <?php $first = false; endforeach; ?>
    </div>

    <script>
    // Global nonce for all AJAX calls
    var hwsNonce = '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>';
    
    jQuery(document).ready(function($) {
        
        // Tab switching (no page refresh)
        $('.hws-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            $('.hws-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.hws-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        });
        
        // Quick Setup
        $('#hws-run-quick-setup').on('click', function() {
            var $btn = $(this);
            var $log = $('#hws-quick-setup-log');
            $btn.prop('disabled', true).text('Running...');
            $log.val('Starting Quick Setup...\n');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hws_quick_setup', nonce: hwsNonce },
                success: function(response) {
                    $btn.prop('disabled', false).text('▶️ Run Quick Setup');
                    if (response.success) {
                        $log.val($log.val() + response.data.log);
                        $log.val($log.val() + '\n✅ Quick Setup Complete!\n');
                    } else {
                        $log.val($log.val() + '\n❌ Error: ' + response.data + '\n');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('▶️ Run Quick Setup');
                    $log.val($log.val() + '\n❌ AJAX Error\n');
                }
            });
        });
        
        // Toggle Secret URLs
        $('#hws-toggle-secret-urls').on('change', function() {
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_urls',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });
        
        // Toggle Secret Setup URL
        $('#hws-toggle-secret-setup').on('change', function() {
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_setup',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });
        
        // Toggle Secret Permalinks Purge URL
        $('#hws-toggle-secret-permalinks').on('change', function() {
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_permalinks',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        });
        
        // Log file toggle
        $('.hws-toggle-log').on('click', function() {
            var target = $(this).data('target');
            $('#' + target).slideToggle();
        });
        
        // Delete backups
        $('.hws-delete-backup').on('click', function() {
            var file = $(this).data('file');
            var plugin = $(this).data('plugin');
            if (!confirm('Delete this backup file?')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.post(ajaxurl, {
                action: 'hws_delete_backups',
                files: [file],
                plugin: plugin,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('🗑️ Delete');
                }
            });
        });
        
        // Mass delete backups
        $('.hws-delete-all-backups').on('click', function() {
            var plugin = $(this).data('plugin');
            if (!confirm('Delete ALL backups for ' + plugin + '? This cannot be undone!')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.post(ajaxurl, {
                action: 'hws_delete_backups',
                delete_all: 1,
                plugin: plugin,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('🗑️ Delete All');
                }
            });
        });
        
        // WP-Config constant toggles - uses existing handler (for checkboxes)
        $('.modify-wp-config').filter('input[type="checkbox"]').on('change', function() {
            var $checkbox = $(this);
            var constant = $checkbox.data('constant');
            var value = $checkbox.is(':checked') ? 'true' : 'false';
            var $toggle = $checkbox.closest('.hws-debug-toggle');
            
            // Build constants object for the handler
            var constants = {};
            constants[constant] = value;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_modify_wp_config_constants',
                    constants: constants,
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $toggle.removeClass('on off').addClass(value === 'true' ? 'on' : 'off');
                    } else {
                        alert('Error: ' + (response.data?.message || response.data || 'Unknown error'));
                        $checkbox.prop('checked', !$checkbox.is(':checked'));
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $checkbox.prop('checked', !$checkbox.is(':checked'));
                }
            });
        });
        
        // WP-Config constant toggles - for BUTTONS (not checkboxes)
        $(document).on('click', 'button.modify-wp-config', function() {
            var $btn = $(this);
            var constant = $btn.data('constant');
            var value = $btn.data('value');
            
            if (!constant || value === undefined || value === null) {
                alert('Missing constant or value');
                return;
            }
            
            // Convert to string for AJAX
            value = String(value);
            
            $btn.prop('disabled', true).text('Updating...');
            
            // Build constants object for the handler
            var constants = {};
            constants[constant] = value;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_modify_wp_config_constants',
                    constants: constants,
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data?.message || response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Retry');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        });
        
        // Snippet toggle via button - for System Checks tab
        $(document).on('click', '.modify-snippet-via-button', function() {
            var $btn = $(this);
            var snippetId = $btn.data('snippet-id');
            var action = $btn.data('action'); // 'enable' or 'disable'
            var enable = (action === 'enable');
            
            if (!snippetId) {
                alert('Missing snippet ID');
                return;
            }
            
            $btn.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_toggle_snippet',
                    snippet_id: snippetId,
                    enable: enable,
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Retry');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        });
        
        // Enable ALL debug - uses existing handler
        $('#hws-enable-all-debug').on('click', function() {
            if (!confirm('Enable ALL debug settings? This will turn on WP_DEBUG, WP_DEBUG_DISPLAY, and WP_DEBUG_LOG.')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Enabling...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_modify_wp_config_constants',
                    constants: {
                        'WP_DEBUG': 'true',
                        'WP_DEBUG_DISPLAY': 'true',
                        'WP_DEBUG_LOG': 'true'
                    },
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data?.message || response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('🔴 Enable ALL Debug');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('🔴 Enable ALL Debug');
                }
            });
        });
        
        // Disable ALL debug - uses existing handler
        $('#hws-disable-all-debug').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Disabling...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_modify_wp_config_constants',
                    constants: {
                        'WP_DEBUG': 'false',
                        'WP_DEBUG_DISPLAY': 'false',
                        'WP_DEBUG_LOG': 'false'
                    },
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data?.message || response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('🟢 Disable ALL Debug');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('🟢 Disable ALL Debug');
                }
            });
        });
        
        // Delete debug.log
        $('#delete-debug-log').on('click', function() {
            if (!confirm('Delete debug.log?')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_debug_log',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Delete debug.log');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('Delete debug.log');
                }
            });
        });
        
        // Delete error_log
        $('#delete-error-log').on('click', function() {
            if (!confirm('Delete error_log?')) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_error_log',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Delete error_log');
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text('Delete error_log');
                }
            });
        });
    });
    </script>
    <?php
}


/**
 * Overview Tab
 */
function render_tab_overview() {
    // Get debug states
    $wp_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $wp_debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
    $display_errors_raw = ini_get( 'display_errors' );
    $display_errors = $display_errors_raw && $display_errors_raw !== '0' && strtolower($display_errors_raw) !== 'off';
    
    // WP-Config settings
    $disable_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    $wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not Set';
    
    $debug_log_path = WP_CONTENT_DIR . '/debug.log';
    $error_log_path = ABSPATH . 'error_log';
    $debug_log_size = file_exists( $debug_log_path ) ? size_format( filesize( $debug_log_path ) ) : 'N/A';
    $error_log_size = file_exists( $error_log_path ) ? size_format( filesize( $error_log_path ) ) : 'N/A';
    
    // Secret URLs
    $secret_urls_enabled = Dashboard_Config::are_secret_urls_enabled();
    $secret_setup_enabled = Dashboard_Config::is_secret_setup_enabled();
    $secret_permalinks_enabled = Dashboard_Config::is_secret_permalinks_enabled();
    $debug_url = add_query_arg( Dashboard_Config::SECRET_DEBUG_KEY, Dashboard_Config::SECRET_KEY_VALUE, home_url( '/' ) );
    $fatal_url = add_query_arg( Dashboard_Config::SECRET_FATAL_KEY, Dashboard_Config::SECRET_KEY_VALUE, home_url( '/' ) );
    $setup_url = add_query_arg( Dashboard_Config::SECRET_SETUP_KEY, Dashboard_Config::SECRET_KEY_VALUE, home_url( '/' ) );
    $permalinks_url = add_query_arg( Dashboard_Config::SECRET_PERMALINKS_KEY, Dashboard_Config::SECRET_KEY_VALUE, home_url( '/' ) );
    ?>
    
    <!-- Summary Section -->
    <div class="hws-panel">
        <div class="hws-panel-header">📋 Summary</div>
        <div class="hws-panel-body">
            <?php render_summary_section(); ?>
        </div>
    </div>
    
    <!-- Quick Setup -->
    <div class="hws-panel">
        <div class="hws-panel-header">⚡ Quick Setup</div>
        <div class="hws-panel-body">
            <p>Run this to quickly configure the site with optimal settings:</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0 15px;">
                <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                    <li>Disable all debug settings</li>
                    <li>Set WP_MEMORY_LIMIT to 4GB</li>
                    <li>Enable WP core auto-updates</li>
                    <li>Enable ALL plugin auto-updates</li>
                    <li>Enable ALL theme auto-updates</li>
                    <li>Delete log files</li>
                    <li>Delete all comments</li>
                </ul>
                <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                    <li>Delete backup files</li>
                    <li>Disable all comments (past & future)</li>
                    <li>Disable all pingbacks</li>
                    <li>Enable Redis object cache <em>(if available)</em></li>
                    <li>Activate LiteSpeed Cache <em>(if installed)</em></li>
                    <li>Activate Wordfence <em>(if installed)</em></li>
                </ul>
            </div>
            <button type="button" id="hws-run-quick-setup" class="hws-btn">▶️ Run Quick Setup</button>
            <textarea id="hws-quick-setup-log" class="hws-quick-setup-log" readonly placeholder="Setup log will appear here..."></textarea>
            
            <!-- Secret Quick Setup URL -->
            <div class="hws-secret-url-box" style="margin-top: 15px;">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-setup" <?php checked( $secret_setup_enabled ); ?>>
                    <strong>Enable Public Quick Setup URL</strong> (runs setup without admin login)
                </label>
                <?php if ( $secret_setup_enabled ) : ?>
                    <p style="margin: 10px 0 5px;"><strong>Quick Setup URL:</strong></p>
                    <code><?php echo esc_html( $setup_url ); ?></code>
                    <p style="color: #d63638; font-size: 12px; margin-top: 5px;">⚠️ Anyone with this URL can run Quick Setup. Disable when not needed.</p>
                <?php endif; ?>
            </div>
            
            <!-- Secret Permalinks Purge URL -->
            <div class="hws-secret-url-box" style="margin-top: 15px;">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-permalinks" <?php checked( $secret_permalinks_enabled ); ?>>
                    <strong>Enable Public Permalink Purge URL</strong> (flushes permalinks without admin login)
                </label>
                <?php if ( $secret_permalinks_enabled ) : ?>
                    <p style="margin: 10px 0 5px;"><strong>Purge Permalinks URL:</strong></p>
                    <code><?php echo esc_html( $permalinks_url ); ?></code>
                    <p style="color: #666; font-size: 12px; margin-top: 5px;">ℹ️ Use this URL to flush rewrite rules remotely (useful for terminal/scripts).</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Debug Controls -->
    <div class="hws-panel">
        <div class="hws-panel-header">🐛 Debug Settings</div>
        <div class="hws-panel-body">
            <!-- Master Toggle -->
            <div style="margin-bottom: 15px;">
                <button type="button" id="hws-enable-all-debug" class="hws-btn hws-btn-danger">🔴 Enable ALL Debug</button>
                <button type="button" id="hws-disable-all-debug" class="hws-btn" style="background: #00a32a; border-color: #00a32a;">🟢 Disable ALL Debug</button>
            </div>
            
            <div class="hws-debug-controls">
                <div class="hws-debug-toggle <?php echo $wp_debug ? 'on' : 'off'; ?>">
                    <span>WP_DEBUG: <?php echo $wp_debug ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG" data-value="<?php echo $wp_debug ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $wp_debug_display ? 'on' : 'off'; ?>">
                    <span>WP_DEBUG_DISPLAY: <?php echo $wp_debug_display ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG_DISPLAY" data-value="<?php echo $wp_debug_display ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug_display ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $wp_debug_log ? 'on' : 'off'; ?>">
                    <span>WP_DEBUG_LOG: <?php echo $wp_debug_log ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG_LOG" data-value="<?php echo $wp_debug_log ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug_log ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $display_errors ? 'on' : 'off'; ?>">
                    <span>display_errors: <?php echo $display_errors ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="ini_display_errors" data-value="<?php echo $display_errors ? '0' : '1'; ?>">
                        <?php echo $display_errors ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
            </div>
            
            <!-- Secret URLs -->
            <div class="hws-secret-url-box">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-urls" <?php checked( $secret_urls_enabled ); ?>>
                    <strong>Enable Secret Debug URLs</strong> (allows toggling debug via URL)
                </label>
                <?php if ( $secret_urls_enabled ) : ?>
                    <p style="margin: 10px 0 5px;"><strong>Toggle Debug On/Off:</strong></p>
                    <code><?php echo esc_html( $debug_url ); ?></code>
                    <p style="margin: 10px 0 5px;"><strong>View Fatal Errors (even when site is down):</strong></p>
                    <code><?php echo esc_html( $fatal_url ); ?></code>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- WP-Config Settings -->
    <div class="hws-panel">
        <div class="hws-panel-header">📝 WP-Config Settings</div>
        <div class="hws-panel-body">
            <div class="hws-debug-controls">
                <div class="hws-debug-toggle <?php echo $disable_cron ? 'off' : 'on'; ?>">
                    <span>DISABLE_WP_CRON: <?php echo $disable_cron ? 'TRUE (cron disabled - recommended)' : 'FALSE (cron enabled)'; ?></span>
                    <button class="button modify-wp-config" data-constant="DISABLE_WP_CRON" data-value="<?php echo $disable_cron ? 'false' : 'true'; ?>">
                        <?php echo $disable_cron ? 'Enable WP-Cron' : 'Disable WP-Cron'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle">
                    <span>WP_MEMORY_LIMIT: <?php echo esc_html( $wp_memory_limit ); ?></span>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="512M">Set to 512M</button>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="1024M">Set to 1G</button>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="4096M">Set to 4G</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Log Files - Clean 3-Panel View -->
    <div class="hws-panel">
        <div class="hws-panel-header">📄 Error Logs</div>
        <div class="hws-panel-body">
            <!-- Log Size Summary -->
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div>
                    <strong>debug.log:</strong> 
                    <span style="color: <?php echo $debug_log_size !== 'N/A' ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $debug_log_size; ?>
                    </span>
                </div>
                <div>
                    <strong>error_log:</strong> 
                    <span style="color: <?php echo $error_log_size !== 'N/A' ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $error_log_size; ?>
                    </span>
                </div>
                <div style="margin-left: auto;">
                    <button type="button" class="button button-secondary hws-btn-danger" id="delete-debug-log" style="padding: 2px 8px; font-size: 11px;">Delete debug.log</button>
                    <button type="button" class="button button-secondary hws-btn-danger" id="delete-error-log" style="padding: 2px 8px; font-size: 11px;">Delete error_log</button>
                </div>
            </div>
            
            <!-- Tab Buttons -->
            <div style="margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                <button type="button" class="button hws-log-tab active" data-log="fatal-syntax">🔴 Fatal & Syntax Errors</button>
                <button type="button" class="button hws-log-tab" data-log="debug">📝 debug.log</button>
                <button type="button" class="button hws-log-tab" data-log="error">📝 error_log</button>
            </div>
            
            <!-- Search Bar -->
            <div style="margin-bottom: 12px; display: flex; gap: 10px; align-items: center;">
                <div style="position: relative; flex: 1; max-width: 400px;">
                    <input type="text" id="hws-log-search" placeholder="Search logs..." 
                           style="width: 100%; padding: 6px 30px 6px 10px; border: 1px solid #8c8f94; border-radius: 4px;">
                    <span id="hws-log-search-clear" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; display: none;">&times;</span>
                </div>
                <span id="hws-log-search-count" style="font-size: 12px; color: #666;"></span>
                <button type="button" id="hws-log-search-prev" class="button button-small" style="display: none;" title="Previous match">↑</button>
                <button type="button" id="hws-log-search-next" class="button button-small" style="display: none;" title="Next match">↓</button>
            </div>
            
            <!-- Color Legend -->
            <div style="font-size: 11px; margin-bottom: 10px; color: #666;">
                <span style="color: #d63638; font-weight: bold;">■ Fatal/Syntax</span> &nbsp;
                <span style="color: #dba617; font-weight: bold;">■ Warning</span> &nbsp;
                <span style="color: #2271b1; font-weight: bold;">■ Notice/Deprecated</span>
            </div>
            
            <!-- Fatal & Syntax Errors Panel (Default) -->
            <div id="log-panel-fatal-syntax" class="hws-log-panel">
                <div class="hws-log-viewer" style="max-height: 400px;">
                    <?php
                    $fatal_syntax = hws_get_fatal_syntax_errors_with_source( 100 );
                    if ( empty( $fatal_syntax ) ) {
                        echo '<span style="color: #00a32a;">✅ No fatal or syntax errors found in either log.</span>';
                    } else {
                        foreach ( $fatal_syntax as $entry ) {
                            $source_badge = $entry['source'] === 'debug.log' 
                                ? '<span style="background: #2271b1; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 10px; margin-right: 8px;">debug.log</span>'
                                : '<span style="background: #8c5e00; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 10px; margin-right: 8px;">error_log</span>';
                            echo '<div class="fatal" style="margin-bottom: 5px;">' . $source_badge . htmlspecialchars( $entry['line'] ) . '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- debug.log Panel -->
            <div id="log-panel-debug" class="hws-log-panel" style="display: none;">
                <div class="hws-log-viewer" style="max-height: 400px;">
                    <?php 
                    if ( file_exists( $debug_log_path ) ) {
                        echo hws_highlight_log_errors( hws_get_log_tail( $debug_log_path, 150 ) ); 
                    } else {
                        echo '<span style="color: #666;">debug.log not found</span>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- error_log Panel -->
            <div id="log-panel-error" class="hws-log-panel" style="display: none;">
                <div class="hws-log-viewer" style="max-height: 400px;">
                    <?php 
                    if ( file_exists( $error_log_path ) ) {
                        echo hws_highlight_log_errors( hws_get_log_tail( $error_log_path, 150 ) ); 
                    } else {
                        echo '<span style="color: #666;">error_log not found</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Store original HTML for each log viewer (for search reset)
        var originalLogHTML = {};
        $('.hws-log-viewer').each(function() {
            var id = $(this).closest('.hws-log-panel').attr('id');
            originalLogHTML[id] = $(this).html();
        });
        
        // Log tab switching
        $('.hws-log-tab').on('click', function() {
            var logType = $(this).data('log');
            $('.hws-log-tab').removeClass('active').css('background', '');
            $(this).addClass('active').css('background', '#0073aa').css('color', '#fff');
            $('.hws-log-panel').hide();
            $('#log-panel-' + logType).show();
            // Re-apply search when switching tabs
            hwsLogSearch();
        });
        // Set initial active state
        $('.hws-log-tab[data-log="fatal-syntax"]').css('background', '#0073aa').css('color', '#fff');
        
        // Log Search Functionality
        var currentMatchIndex = 0;
        var $matches = $();
        
        function hwsLogSearch() {
            var query = $('#hws-log-search').val().trim();
            var $activePanel = $('.hws-log-panel:visible');
            var $viewer = $activePanel.find('.hws-log-viewer');
            var panelId = $activePanel.attr('id');
            
            // Reset to original HTML first
            if (originalLogHTML[panelId]) {
                $viewer.html(originalLogHTML[panelId]);
            }
            
            // Reset navigation
            currentMatchIndex = 0;
            $matches = $();
            
            if (query.length < 2) {
                $('#hws-log-search-count').text('');
                $('#hws-log-search-prev, #hws-log-search-next').hide();
                $('#hws-log-search-clear').hide();
                return;
            }
            
            $('#hws-log-search-clear').show();
            
            // Get HTML and do case-insensitive replace
            var html = $viewer.html();
            var queryLower = query.toLowerCase();
            var queryEscaped = escapeRegExp(query);
            
            // Find all matches (case-insensitive)
            var regex = new RegExp('(' + queryEscaped + ')', 'gi');
            var matchCount = (html.match(regex) || []).length;
            
            if (matchCount > 0) {
                // Replace with highlighted version
                html = html.replace(regex, '<mark class="hws-search-highlight">$1</mark>');
                $viewer.html(html);
                
                // Get all highlight elements
                $matches = $viewer.find('.hws-search-highlight');
                
                $('#hws-log-search-count').html('<span style="color: #2271b1;">' + $matches.length + ' match' + ($matches.length > 1 ? 'es' : '') + '</span>');
                if ($matches.length > 1) {
                    $('#hws-log-search-prev, #hws-log-search-next').show();
                } else {
                    $('#hws-log-search-prev, #hws-log-search-next').hide();
                }
                // Highlight first match
                hwsScrollToMatch(0);
            } else {
                $('#hws-log-search-count').html('<span style="color: #d63638;">No matches</span>');
                $('#hws-log-search-prev, #hws-log-search-next').hide();
            }
        }
        
        function hwsScrollToMatch(index) {
            if ($matches.length === 0) return;
            
            // Remove current highlight
            $matches.removeClass('hws-search-current');
            
            // Wrap index
            if (index < 0) index = $matches.length - 1;
            if (index >= $matches.length) index = 0;
            currentMatchIndex = index;
            
            // Highlight current and scroll
            var $current = $matches.eq(index);
            $current.addClass('hws-search-current');
            
            // Scroll into view
            var $viewer = $current.closest('.hws-log-viewer');
            var viewerTop = $viewer.offset().top;
            var viewerHeight = $viewer.height();
            var matchTop = $current.offset().top;
            var matchRelative = matchTop - viewerTop + $viewer.scrollTop();
            
            $viewer.animate({ 
                scrollTop: matchRelative - (viewerHeight / 2) 
            }, 150);
            
            $('#hws-log-search-count').html('<span style="color: #2271b1;">' + (index + 1) + ' / ' + $matches.length + '</span>');
        }
        
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // Debounced search
        var searchTimeout;
        $('#hws-log-search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(hwsLogSearch, 200);
        });
        
        // Clear search
        $('#hws-log-search-clear').on('click', function() {
            $('#hws-log-search').val('').trigger('input');
        });
        
        // Navigation
        $('#hws-log-search-next').on('click', function() {
            hwsScrollToMatch(currentMatchIndex + 1);
        });
        $('#hws-log-search-prev').on('click', function() {
            hwsScrollToMatch(currentMatchIndex - 1);
        });
        
        // Keyboard shortcuts
        $('#hws-log-search').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.shiftKey) {
                    hwsScrollToMatch(currentMatchIndex - 1);
                } else {
                    hwsScrollToMatch(currentMatchIndex + 1);
                }
            } else if (e.key === 'Escape') {
                $(this).val('').trigger('input');
            }
        });
    });
    </script>
    
    <!-- Log Cleaner -->
    <?php
    if ( function_exists( __NAMESPACE__ . '\\display_settings_log_cleaner' ) ) {
        display_settings_log_cleaner();
    }
    ?>
    
    <!-- Elementor Database Updater -->
    <?php
    if ( function_exists( __NAMESPACE__ . '\\display_elementor_db_updater_panel' ) ) {
        display_elementor_db_updater_panel();
    }
    ?>
    
    <!-- Plugin Info -->
    <?php
    if ( function_exists( __NAMESPACE__ . '\\hws_ct_display_plugin_info' ) ) {
        hws_ct_display_plugin_info();
    }
    ?>
    <?php
}


/**
 * Get log file tail
 */
function hws_get_log_tail( $path, $lines = 100 ) {
    if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
        return 'Log file not found or not readable.';
    }
    
    $content = file( $path, FILE_IGNORE_NEW_LINES );
    if ( ! $content ) {
        return 'Log file is empty.';
    }
    
    return implode( "\n", array_slice( $content, -$lines ) );
}


/**
 * Highlight errors in log content with color coding
 * - Fatal/Syntax: Red
 * - Warning: Yellow/Orange  
 * - Notice/Deprecated: Blue
 */
function hws_highlight_log_errors( $content ) {
    if ( empty( $content ) ) {
        return $content;
    }
    
    $lines = explode( "\n", $content );
    $output = [];
    
    foreach ( $lines as $line ) {
        $escaped_line = htmlspecialchars( $line );
        
        // Fatal errors (red)
        if ( preg_match( '/\b(fatal\s+error|fatal\s*:|php\s+fatal)/i', $line ) ) {
            $output[] = '<span class="log-fatal">' . $escaped_line . '</span>';
        }
        // Syntax/Parse errors (red)
        elseif ( preg_match( '/\b(syntax\s+error|parse\s+error)/i', $line ) ) {
            $output[] = '<span class="log-fatal">' . $escaped_line . '</span>';
        }
        // Warnings (yellow/orange)
        elseif ( preg_match( '/\b(warning\s*:|php\s+warning)/i', $line ) ) {
            $output[] = '<span class="log-warning">' . $escaped_line . '</span>';
        }
        // Notice/Deprecated (blue)
        elseif ( preg_match( '/\b(notice\s*:|deprecated\s*:|php\s+notice|php\s+deprecated)/i', $line ) ) {
            $output[] = '<span class="log-notice">' . $escaped_line . '</span>';
        }
        else {
            $output[] = $escaped_line;
        }
    }
    
    return implode( "\n", $output );
}


/**
 * Get fatal/syntax errors with source log identification
 */
function hws_get_fatal_syntax_errors_with_source( $limit = 100 ) {
    $errors = [];
    $log_files = [
        'debug.log' => WP_CONTENT_DIR . '/debug.log',
        'error_log' => ABSPATH . 'error_log',
    ];
    
    foreach ( $log_files as $name => $path ) {
        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            continue;
        }
        
        $content = file( $path, FILE_IGNORE_NEW_LINES );
        if ( ! $content ) {
            continue;
        }
        
        foreach ( $content as $line ) {
            if ( preg_match( '/\b(fatal\s+error|fatal\s*:|syntax\s+error|parse\s+error|php\s+fatal)/i', $line ) ) {
                $errors[] = [
                    'source' => $name,
                    'line'   => $line,
                ];
            }
        }
    }
    
    // Return most recent errors (end of array)
    return array_slice( $errors, -$limit );
}


/**
 * Render Summary Section
 */
function render_summary_section() {
    // ========================================
    // GATHER ALL DATA
    // ========================================
    
    // Red flag plugins
    $red_flag_plugins = [
        'wp-file-manager/file-manager.php' => 'WP File Manager',
        'duplicator/duplicator.php' => 'Duplicator',
    ];
    
    $red_flags = [];
    foreach ( $red_flag_plugins as $plugin_path => $plugin_name ) {
        if ( is_plugin_active( $plugin_path ) || file_exists( WP_PLUGIN_DIR . '/' . dirname( $plugin_path ) ) ) {
            $red_flags[] = $plugin_name;
        }
    }
    
    // Plugin data
    $all_plugins = get_plugins();
    $active_plugins = get_option( 'active_plugins', [] );
    $plugin_updates = get_plugin_updates();
    $auto_update_plugins = (array) get_site_option( 'auto_update_plugins', [] );
    $plugins_auto_update_enabled = count( $auto_update_plugins ) > 0;
    
    // Get missing monitored plugins
    $missing_plugins = [];
    if ( function_exists( __NAMESPACE__ . '\\hws_get_monitored_plugins' ) ) {
        $monitored = hws_get_monitored_plugins();
        foreach ( $monitored as $path => $config ) {
            if ( ! isset( $all_plugins[ $path ] ) && $config['should_be'] === 'active' ) {
                $missing_plugins[] = $config['name'];
            }
        }
    }
    
    // Theme data
    $all_themes = wp_get_themes();
    $theme_updates = get_theme_updates();
    $auto_update_themes = (array) get_site_option( 'auto_update_themes', [] );
    $themes_auto_update_enabled = count( $auto_update_themes ) > 0;
    
    // Check for Twenty-Twenty themes
    $twenty_themes = [];
    foreach ( $all_themes as $slug => $theme ) {
        if ( preg_match( '/^twenty(twenty|twentyone|twentytwo|twentythree|twentyfour|twentyfive)/i', $slug ) ) {
            $twenty_themes[] = $theme->get( 'Name' );
        }
    }
    
    // Core update
    $core_updates = get_core_updates();
    $core_update_available = ! empty( $core_updates ) && isset( $core_updates[0]->response ) && $core_updates[0]->response === 'upgrade';
    
    // Backups
    $backups_count = 0;
    if ( function_exists( __NAMESPACE__ . '\\hws_scan_backups' ) ) {
        $backups = hws_scan_backups();
        $backups_count = count( $backups );
    }
    
    // Comments
    global $wpdb;
    $comments_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
    
    // Log files
    $debug_log_path = WP_CONTENT_DIR . '/debug.log';
    $error_log_path = ABSPATH . 'error_log';
    $debug_log_size = file_exists( $debug_log_path ) ? filesize( $debug_log_path ) : 0;
    $error_log_size = file_exists( $error_log_path ) ? filesize( $error_log_path ) : 0;
    
    // MyISAM tables
    $myisam_tables = $wpdb->get_results( "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND ENGINE = 'MyISAM'" );
    $myisam_count = count( $myisam_tables );
    
    // WordPress config
    $wp_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $wp_debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
    $wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not set';
    $disable_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    
    // PHP info
    $php_version = phpversion();
    $post_max_size = ini_get( 'post_max_size' );
    $upload_max_filesize = ini_get( 'upload_max_filesize' );
    $memory_limit = ini_get( 'memory_limit' );
    
    // PHP Handler detection
    $php_handler = 'Unknown';
    if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
        if ( stripos( $_SERVER['SERVER_SOFTWARE'], 'litespeed' ) !== false ) {
            $php_handler = 'LiteSpeed';
        } elseif ( stripos( $_SERVER['SERVER_SOFTWARE'], 'apache' ) !== false ) {
            $php_handler = 'Apache';
        } elseif ( stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) {
            $php_handler = 'Nginx';
        }
    }
    if ( function_exists( 'php_sapi_name' ) ) {
        $sapi = php_sapi_name();
        if ( stripos( $sapi, 'litespeed' ) !== false ) {
            $php_handler = 'LiteSpeed SAPI';
        } elseif ( stripos( $sapi, 'fpm' ) !== false ) {
            $php_handler = $php_handler . ' + PHP-FPM';
        }
    }
    
    // Redis
    $redis_available = class_exists( 'Redis' );
    $redis_connected = false;
    if ( $redis_available ) {
        try {
            $redis = new \Redis();
            $redis_connected = @$redis->connect( '127.0.0.1', 6379, 1 );
            if ( $redis_connected ) {
                $redis->close();
            }
        } catch ( \Exception $e ) {
            $redis_connected = false;
        }
    }
    
    // Key plugins
    $wordfence_installed = file_exists( WP_PLUGIN_DIR . '/wordfence/wordfence.php' );
    $wordfence_active = is_plugin_active( 'wordfence/wordfence.php' );
    $litespeed_installed = file_exists( WP_PLUGIN_DIR . '/litespeed-cache/litespeed-cache.php' );
    $litespeed_active = is_plugin_active( 'litespeed-cache/litespeed-cache.php' );
    
    // Wordfence email
    $wordfence_email = '';
    if ( $wordfence_active ) {
        $wf_options = get_option( 'wordfence_email', '' );
        if ( empty( $wf_options ) ) {
            $wf_options = get_option( 'wf_alertEmails', '' );
        }
        $wordfence_email = $wf_options;
    }
    
    // ========================================
    // DISPLAY RED FLAGS
    // ========================================
    if ( ! empty( $red_flags ) ) {
        ?>
        <div class="hws-red-flag">
            <h4>🚨 Security Alert - Remove Immediately</h4>
            <ul>
                <?php foreach ( $red_flags as $plugin ) : ?>
                    <li><strong><?php echo esc_html( $plugin ); ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
    ?>
    
    <!-- ========================================
         SECTION: PLUGINS & THEMES STATUS
         ======================================== -->
    <div class="hws-summary-section">
        <h3 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #2271b1;">🔌 Plugins & Themes</h3>
        <div class="hws-summary-grid">
            <!-- Plugins Card -->
            <div class="hws-summary-card">
                <h4>Plugins</h4>
                <?php if ( ! empty( $missing_plugins ) ) : ?>
                    <p class="status-bad">❌ Missing: <?php echo implode( ', ', $missing_plugins ); ?></p>
                <?php endif; ?>
                <?php if ( count( $plugin_updates ) > 0 ) : ?>
                    <p class="status-warn">⚠️ <?php echo count( $plugin_updates ); ?> need updates</p>
                <?php else : ?>
                    <p class="status-ok">✅ All up to date</p>
                <?php endif; ?>
                <p class="<?php echo $plugins_auto_update_enabled ? 'status-ok' : 'status-warn'; ?>">
                    <?php echo $plugins_auto_update_enabled ? '✅' : '⚠️'; ?> Auto-updates: 
                    <?php echo $plugins_auto_update_enabled ? count( $auto_update_plugins ) . ' enabled' : 'Disabled'; ?>
                </p>
            </div>
            
            <!-- Themes Card -->
            <div class="hws-summary-card">
                <h4>Themes (<?php echo count( $all_themes ); ?> total)</h4>
                <?php if ( ! empty( $twenty_themes ) ) : ?>
                    <p class="status-warn">⚠️ Remove: <?php echo implode( ', ', $twenty_themes ); ?></p>
                <?php endif; ?>
                <?php if ( count( $theme_updates ) > 0 ) : ?>
                    <p class="status-warn">⚠️ <?php echo count( $theme_updates ); ?> need updates</p>
                <?php else : ?>
                    <p class="status-ok">✅ All up to date</p>
                <?php endif; ?>
                <p class="<?php echo $themes_auto_update_enabled ? 'status-ok' : 'status-warn'; ?>">
                    <?php echo $themes_auto_update_enabled ? '✅' : '⚠️'; ?> Auto-updates: 
                    <?php echo $themes_auto_update_enabled ? count( $auto_update_themes ) . ' enabled' : 'Disabled'; ?>
                </p>
            </div>
            
            <!-- Core Card -->
            <div class="hws-summary-card">
                <h4>WordPress Core</h4>
                <p>Version: <strong><?php global $wp_version; echo $wp_version; ?></strong></p>
                <?php if ( $core_update_available ) : ?>
                    <p class="status-warn">⚠️ Update: <?php echo $core_updates[0]->version; ?></p>
                <?php else : ?>
                    <p class="status-ok">✅ Up to date</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         SECTION: CLEANUP REQUIRED
         ======================================== -->
    <div class="hws-summary-section" style="margin-top: 20px;">
        <h3 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #d63638;">🧹 Cleanup Status</h3>
        <div class="hws-summary-grid">
            <div class="hws-summary-card <?php echo $backups_count > 0 ? 'warn' : ''; ?>">
                <h4>Backups</h4>
                <p class="<?php echo $backups_count > 0 ? 'status-warn' : 'status-ok'; ?>">
                    <?php echo $backups_count > 0 ? "⚠️ {$backups_count} backup files detected" : '✅ No backups'; ?>
                </p>
            </div>
            <div class="hws-summary-card <?php echo $comments_count > 0 ? 'warn' : ''; ?>">
                <h4>Comments</h4>
                <p class="<?php echo $comments_count > 0 ? 'status-warn' : 'status-ok'; ?>">
                    <?php echo $comments_count > 0 ? "⚠️ {$comments_count} comments" : '✅ No comments'; ?>
                </p>
            </div>
            <div class="hws-summary-card <?php echo ( $debug_log_size + $error_log_size ) > 0 ? 'warn' : ''; ?>">
                <h4>Log Files</h4>
                <p class="<?php echo $debug_log_size > 0 ? 'status-warn' : 'status-ok'; ?>">
                    debug.log: <?php echo $debug_log_size > 0 ? '⚠️ ' . size_format( $debug_log_size ) : '✅ None'; ?>
                </p>
                <p class="<?php echo $error_log_size > 0 ? 'status-warn' : 'status-ok'; ?>">
                    error_log: <?php echo $error_log_size > 0 ? '⚠️ ' . size_format( $error_log_size ) : '✅ None'; ?>
                </p>
            </div>
            <div class="hws-summary-card <?php echo $myisam_count > 0 ? 'warn' : ''; ?>">
                <h4>Database</h4>
                <p class="<?php echo $myisam_count > 0 ? 'status-warn' : 'status-ok'; ?>">
                    <?php echo $myisam_count > 0 ? "⚠️ {$myisam_count} MyISAM tables" : '✅ All InnoDB'; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         SECTION: WORDPRESS CONFIG
         ======================================== -->
    <div class="hws-summary-section" style="margin-top: 20px;">
        <h3 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #00a32a;">⚙️ WordPress Configuration</h3>
        <div class="hws-summary-grid">
            <div class="hws-summary-card">
                <h4>Debug Settings</h4>
                <p class="<?php echo $wp_debug ? 'status-warn' : 'status-ok'; ?>">
                    WP_DEBUG: <?php echo $wp_debug ? '⚠️ ON' : '✅ OFF'; ?>
                </p>
                <p class="<?php echo $wp_debug_log ? 'status-warn' : 'status-ok'; ?>">
                    WP_DEBUG_LOG: <?php echo $wp_debug_log ? '⚠️ ON' : '✅ OFF'; ?>
                </p>
                <p class="<?php echo $wp_debug_display ? 'status-bad' : 'status-ok'; ?>">
                    WP_DEBUG_DISPLAY: <?php echo $wp_debug_display ? '❌ ON' : '✅ OFF'; ?>
                </p>
            </div>
            <div class="hws-summary-card">
                <h4>Memory & Cron</h4>
                <p>WP_MEMORY_LIMIT: <strong><?php echo esc_html( $wp_memory_limit ); ?></strong></p>
                <p class="<?php echo $disable_cron ? 'status-ok' : 'status-warn'; ?>">
                    DISABLE_WP_CRON: <?php echo $disable_cron ? '✅ TRUE (using real cron)' : '⚠️ FALSE'; ?>
                </p>
            </div>
            <div class="hws-summary-card">
                <h4>Caching</h4>
                <p class="<?php echo $redis_connected ? 'status-ok' : ( $redis_available ? 'status-warn' : 'status-bad' ); ?>">
                    Redis: <?php 
                    if ( $redis_connected ) echo '✅ Connected';
                    elseif ( $redis_available ) echo '⚠️ Available but not connected';
                    else echo '❌ Not available';
                    ?>
                </p>
                <p class="<?php echo $litespeed_active ? 'status-ok' : ( $litespeed_installed ? 'status-warn' : 'status-bad' ); ?>">
                    LiteSpeed: <?php 
                    if ( $litespeed_active ) echo '✅ Active';
                    elseif ( $litespeed_installed ) echo '⚠️ Installed but inactive';
                    else echo '❌ Not installed';
                    ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         SECTION: SECURITY
         ======================================== -->
    <div class="hws-summary-section" style="margin-top: 20px;">
        <h3 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #8c5e00;">🔒 Security</h3>
        <div class="hws-summary-grid">
            <div class="hws-summary-card">
                <h4>Wordfence</h4>
                <p class="<?php echo $wordfence_active ? 'status-ok' : ( $wordfence_installed ? 'status-warn' : 'status-bad' ); ?>">
                    <?php 
                    if ( $wordfence_active ) echo '✅ Active';
                    elseif ( $wordfence_installed ) echo '⚠️ Installed but inactive';
                    else echo '❌ Not installed';
                    ?>
                </p>
                <?php if ( $wordfence_active && ! empty( $wordfence_email ) ) : ?>
                    <p style="font-size: 12px;">Email: <?php echo esc_html( $wordfence_email ); ?></p>
                <?php elseif ( $wordfence_active ) : ?>
                    <p class="status-warn" style="font-size: 12px;">⚠️ No alert email configured</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         SECTION: PHP & SERVER
         ======================================== -->
    <div class="hws-summary-section" style="margin-top: 20px;">
        <h3 style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #666;">🖥️ PHP & Server</h3>
        <div class="hws-summary-grid">
            <div class="hws-summary-card">
                <h4>PHP Info</h4>
                <p>Version: <strong><?php echo esc_html( $php_version ); ?></strong></p>
                <p>Handler: <strong><?php echo esc_html( $php_handler ); ?></strong></p>
                <p>Memory Limit: <strong><?php echo esc_html( $memory_limit ); ?></strong></p>
            </div>
            <div class="hws-summary-card">
                <h4>Upload Limits</h4>
                <p>post_max_size: <strong><?php echo esc_html( $post_max_size ); ?></strong></p>
                <p>upload_max_filesize: <strong><?php echo esc_html( $upload_max_filesize ); ?></strong></p>
            </div>
            <div class="hws-summary-card" style="grid-column: span 2;">
                <h4>PHP Extensions</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px 20px;">
                    <?php
                    // Define extensions with proper labels and checks
                    $extensions = [
                        'imagick' => [
                            'label'    => 'Imagick (Image Processing)',
                            'check'    => extension_loaded( 'imagick' ),
                            'required' => false,
                        ],
                        'gd' => [
                            'label'    => 'GD (Image Processing)',
                            'check'    => extension_loaded( 'gd' ),
                            'required' => true,
                        ],
                        'curl' => [
                            'label'    => 'cURL (HTTP Requests)',
                            'check'    => extension_loaded( 'curl' ),
                            'required' => true,
                        ],
                        'opcache' => [
                            'label'    => 'OPcache (PHP Accelerator)',
                            'check'    => extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' ),
                            'required' => false,
                        ],
                        'brotli' => [
                            'label'    => 'Brotli (Compression)',
                            'check'    => extension_loaded( 'brotli' ),
                            'required' => false,
                        ],
                        'zip' => [
                            'label'    => 'Zip (Archive Handling)',
                            'check'    => extension_loaded( 'zip' ),
                            'required' => true,
                        ],
                        'mbstring' => [
                            'label'    => 'mbstring (Multibyte Strings)',
                            'check'    => extension_loaded( 'mbstring' ),
                            'required' => true,
                        ],
                        'xml' => [
                            'label'    => 'XML (XML Processing)',
                            'check'    => extension_loaded( 'xml' ),
                            'required' => true,
                        ],
                        'intl' => [
                            'label'    => 'intl (Internationalization)',
                            'check'    => extension_loaded( 'intl' ),
                            'required' => false,
                        ],
                        'exif' => [
                            'label'    => 'EXIF (Image Metadata)',
                            'check'    => extension_loaded( 'exif' ),
                            'required' => false,
                        ],
                        'openssl' => [
                            'label'    => 'OpenSSL (Encryption)',
                            'check'    => extension_loaded( 'openssl' ),
                            'required' => true,
                        ],
                        'sodium' => [
                            'label'    => 'Sodium (Modern Encryption)',
                            'check'    => extension_loaded( 'sodium' ),
                            'required' => false,
                        ],
                    ];
                    
                    foreach ( $extensions as $ext_key => $ext ) :
                        $is_ok = $ext['check'];
                        $status_class = $is_ok ? 'status-ok' : ( $ext['required'] ? 'status-bad' : 'status-warn' );
                        $icon = $is_ok ? '✅' : ( $ext['required'] ? '❌' : '⚠️' );
                    ?>
                        <p class="<?php echo $status_class; ?>" style="display: flex; justify-content: space-between;">
                            <span><?php echo esc_html( $ext['label'] ); ?></span>
                            <span><?php echo $icon; ?></span>
                        </p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}


/**
 * Execute Quick Setup (shared function for AJAX and public URL)
 * 
 * @return string Log output
 */
function hws_execute_quick_setup() {
    $log = '';
    $step = 1;
    
    // 1. Disable debug settings
    $log .= "<span class='success'>[Step {$step}]</span> Disabling debug settings...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
        modify_wp_config_constants( [
            'WP_DEBUG'         => 'false',
            'WP_DEBUG_DISPLAY' => 'false',
            'WP_DEBUG_LOG'     => 'false',
        ] );
        $log .= "  ✓ WP_DEBUG, WP_DEBUG_DISPLAY, WP_DEBUG_LOG set to false\n";
    }
    @ini_set( 'display_errors', '0' );
    $log .= "  ✓ display_errors disabled\n";
    
    // 2. Set WP_MEMORY_LIMIT to 4GB
    $log .= "<span class='success'>[Step {$step}]</span> Setting WordPress memory limit to 4GB...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
        modify_wp_config_constants( [ 'WP_MEMORY_LIMIT' => '4096M' ] );
        $log .= "  ✓ WP_MEMORY_LIMIT set to 4096M (4GB)\n";
    }
    
    // 3. Enable WP core auto-updates
    $log .= "<span class='success'>[Step {$step}]</span> Enabling WordPress core auto-updates...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
        modify_wp_config_constants( [ 'WP_AUTO_UPDATE_CORE' => 'true' ] );
        $log .= "  ✓ WP_AUTO_UPDATE_CORE enabled\n";
    }
    
    // 3. Enable ALL plugin auto-updates
    $log .= "<span class='success'>[Step {$step}]</span> Enabling auto-updates for ALL plugins...\n";
    $step++;
    $all_plugins = array_keys( get_plugins() );
    update_option( 'auto_update_plugins', $all_plugins );
    update_option( 'enable_auto_update_plugins', true );
    $log .= "  ✓ Auto-updates enabled for " . count( $all_plugins ) . " plugins\n";
    
    // 4. Enable ALL theme auto-updates
    $log .= "<span class='success'>[Step {$step}]</span> Enabling auto-updates for ALL themes...\n";
    $step++;
    $all_themes = array_keys( wp_get_themes() );
    update_option( 'auto_update_themes', $all_themes );
    update_option( 'enable_auto_update_themes', true );
    $log .= "  ✓ Auto-updates enabled for " . count( $all_themes ) . " themes\n";
    
    // 5. Delete log files
    $log .= "<span class='success'>[Step {$step}]</span> Deleting log files...\n";
    $step++;
    $debug_log = WP_CONTENT_DIR . '/debug.log';
    $error_log = ABSPATH . 'error_log';
    if ( file_exists( $debug_log ) && is_writable( $debug_log ) ) {
        $size = size_format( filesize( $debug_log ) );
        @unlink( $debug_log );
        $log .= "  ✓ Deleted debug.log ({$size})\n";
    } else {
        $log .= "  - debug.log not found or not writable\n";
    }
    if ( file_exists( $error_log ) && is_writable( $error_log ) ) {
        $size = size_format( filesize( $error_log ) );
        @unlink( $error_log );
        $log .= "  ✓ Deleted error_log ({$size})\n";
    } else {
        $log .= "  - error_log not found or not writable\n";
    }
    
    // 6. Delete old backups
    $log .= "<span class='success'>[Step {$step}]</span> Cleaning up backup files...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\hws_scan_backups' ) ) {
        $backups = hws_scan_backups();
        $deleted_count = 0;
        foreach ( $backups as $backup ) {
            if ( file_exists( $backup['path'] ) && is_writable( $backup['path'] ) ) {
                @unlink( $backup['path'] );
                $deleted_count++;
            }
        }
        if ( $deleted_count > 0 ) {
            $log .= "  ✓ Deleted {$deleted_count} backup file(s)\n";
        } else {
            $log .= "  - No backup files found\n";
        }
    }
    
    // 7. Disable all comments (past and future)
    $log .= "<span class='success'>[Step {$step}]</span> Disabling all comments...\n";
    $step++;
    // Future comments
    update_option( 'default_comment_status', 'closed' );
    update_option( 'default_ping_status', 'closed' );
    $log .= "  ✓ Default comment status set to closed\n";
    // Past comments - use direct SQL for efficiency
    global $wpdb;
    $updated_comments = $wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed' WHERE comment_status = 'open'" );
    $log .= "  ✓ Closed comments on {$updated_comments} posts\n";
    
    // 8. Delete ALL comments
    $log .= "<span class='success'>[Step {$step}]</span> Deleting all comments...\n";
    $step++;
    $deleted_comments = $wpdb->query( "DELETE FROM {$wpdb->comments}" );
    $wpdb->query( "DELETE FROM {$wpdb->commentmeta}" );
    $log .= "  ✓ Deleted {$deleted_comments} comments and all comment meta\n";
    
    // 9. Disable all pingbacks
    $log .= "<span class='success'>[Step {$step}]</span> Disabling all pingbacks...\n";
    $step++;
    $updated_pings = $wpdb->query( "UPDATE {$wpdb->posts} SET ping_status = 'closed' WHERE ping_status = 'open'" );
    $log .= "  ✓ Closed pingbacks on {$updated_pings} posts\n";
    
    // 9. Enable Redis if available
    $log .= "<span class='success'>[Step {$step}]</span> Checking Redis...\n";
    $step++;
    if ( class_exists( 'Redis' ) ) {
        try {
            $redis = new \Redis();
            if ( @$redis->connect( '127.0.0.1', 6379, 2 ) ) {
                $log .= "  ✓ Redis is available and connected\n";
                if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
                    modify_wp_config_constants( [ 'LSCWP_OBJECT_CACHE' => 'true' ] );
                    $log .= "  ✓ LSCWP_OBJECT_CACHE enabled\n";
                }
                $redis->close();
            } else {
                $log .= "  <span class='warning'>⚠ Redis service not running</span>\n";
            }
        } catch ( \Exception $e ) {
            $log .= "  <span class='warning'>⚠ Redis error: " . $e->getMessage() . "</span>\n";
        }
    } else {
        $log .= "  <span class='warning'>⚠ Redis PHP extension not installed</span>\n";
    }
    
    // 10. Check/activate LiteSpeed Cache
    $log .= "<span class='success'>[Step {$step}]</span> Checking LiteSpeed Cache...\n";
    $step++;
    $litespeed_plugin = 'litespeed-cache/litespeed-cache.php';
    if ( file_exists( WP_PLUGIN_DIR . '/litespeed-cache/litespeed-cache.php' ) ) {
        if ( ! is_plugin_active( $litespeed_plugin ) ) {
            $result = activate_plugin( $litespeed_plugin );
            if ( is_wp_error( $result ) ) {
                $log .= "  <span class='warning'>⚠ Could not activate LiteSpeed: " . $result->get_error_message() . "</span>\n";
            } else {
                $log .= "  ✓ LiteSpeed Cache activated\n";
            }
        } else {
            $log .= "  ✓ LiteSpeed Cache already active\n";
        }
    } else {
        $log .= "  <span class='warning'>⚠ LiteSpeed Cache not installed</span>\n";
    }
    
    // 11. Check/activate Wordfence
    $log .= "<span class='success'>[Step {$step}]</span> Checking Wordfence...\n";
    $step++;
    $wordfence_plugin = 'wordfence/wordfence.php';
    if ( file_exists( WP_PLUGIN_DIR . '/wordfence/wordfence.php' ) ) {
        if ( ! is_plugin_active( $wordfence_plugin ) ) {
            $result = activate_plugin( $wordfence_plugin );
            if ( is_wp_error( $result ) ) {
                $log .= "  <span class='warning'>⚠ Could not activate Wordfence: " . $result->get_error_message() . "</span>\n";
            } else {
                $log .= "  ✓ Wordfence activated\n";
            }
        } else {
            $log .= "  ✓ Wordfence already active\n";
        }
    } else {
        $log .= "  <span class='warning'>⚠ Wordfence not installed</span>\n";
    }
    
    $log .= "\n<span class='success'>═══════════════════════════════════════</span>\n";
    $log .= "<span class='success'>✅ Quick Setup Complete!</span>\n";
    
    return $log;
}


/**
 * AJAX: Quick Setup
 */
function ajax_quick_setup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $log = hws_execute_quick_setup();
    
    // Strip HTML tags for AJAX response (will be shown in textarea)
    $log = strip_tags( $log );
    
    wp_send_json_success( [ 'log' => $log ] );
}


/**
 * AJAX: Toggle secret URLs
 */
function ajax_toggle_secret_urls() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;
    update_option( Dashboard_Config::OPT_SECRET_URLS_ENABLED, $enabled ? 'yes' : 'no' );
    
    wp_send_json_success( [ 'enabled' => $enabled ] );
}


/**
 * AJAX: Toggle secret setup URL
 */
function ajax_toggle_secret_setup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;
    update_option( Dashboard_Config::OPT_SECRET_SETUP_ENABLED, $enabled ? 'yes' : 'no' );
    
    wp_send_json_success( [ 'enabled' => $enabled ] );
}


/**
 * AJAX: Toggle secret permalinks purge URL
 */
function ajax_toggle_secret_permalinks() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;
    update_option( Dashboard_Config::OPT_SECRET_PERMALINKS_ENABLED, $enabled ? 'yes' : 'no' );
    
    wp_send_json_success( [ 'enabled' => $enabled ] );
}


/**
 * AJAX: Enable auto-updates for ALL plugins
 */
function ajax_enable_all_auto_updates() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    // Get ALL plugins
    $all_plugins = array_keys( get_plugins() );
    
    // Set auto-updates for all plugins
    update_option( 'auto_update_plugins', $all_plugins );
    
    // Also enable the snippet option
    update_option( 'enable_auto_update_plugins', true );
    
    wp_send_json_success( [ 
        'message' => 'Auto-updates enabled for ' . count( $all_plugins ) . ' plugins',
        'count'   => count( $all_plugins ),
    ] );
}


/**
 * AJAX: Toggle all debug settings at once
 */
function ajax_toggle_all_debug() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    $enable = isset( $_POST['enable'] ) && $_POST['enable'] === '1';
    $value = $enable ? 'true' : 'false';
    
    $constants = [
        'WP_DEBUG'         => $value,
        'WP_DEBUG_DISPLAY' => $value,
        'WP_DEBUG_LOG'     => $value,
    ];
    
    if ( function_exists( __NAMESPACE__ . '\\modify_wp_config_constants' ) ) {
        $result = modify_wp_config_constants( $constants );
        if ( $result ) {
            wp_send_json_success( [
                'message' => 'All debug settings ' . ( $enable ? 'enabled' : 'disabled' ),
            ] );
        } else {
            wp_send_json_error( 'Failed to modify wp-config.php' );
        }
    } else {
        wp_send_json_error( 'modify_wp_config_constants function not found' );
    }
}
