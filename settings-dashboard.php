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
    
    /** DB option key for the master secret password */
    const OPT_MASTER_SECRET     = 'hws_master_secret_key';
    
    /**
     * Get the master secret key from DB-backed runtime storage.
     * Used by ALL secret/public URLs across the plugin
     */
    public static function get_secret_key(): string {
        return hws_get_master_secret();
    }
    
    // Options
    const OPT_SECRET_URLS_ENABLED       = 'hws_secret_urls_enabled';
    const OPT_SECRET_SETUP_ENABLED      = 'hws_secret_setup_enabled';
    const OPT_SECRET_PERMALINKS_ENABLED = 'hws_secret_permalinks_enabled';
    
    /**
     * Get secret URLs enabled status (default: false for security)
     */
    public static function are_secret_urls_enabled() {
        return hws_option_is_enabled( self::OPT_SECRET_URLS_ENABLED, false );
    }
    
    /**
     * Get secret setup URL enabled status (default: false for security)
     */
    public static function is_secret_setup_enabled() {
        return hws_option_is_enabled( self::OPT_SECRET_SETUP_ENABLED, false );
    }
    
    /**
     * Get secret permalinks purge URL enabled status (default: false for security)
     */
    public static function is_secret_permalinks_enabled() {
        return hws_option_is_enabled( self::OPT_SECRET_PERMALINKS_ENABLED, false );
    }
}


/**
 * Handle secret URL debug toggle
 * Runs early on init to catch before any output
 */
function hws_handle_secret_debug_url() {
    // Handle debug toggle: /?hws_debug=<secret>
    if ( Dashboard_Config::are_secret_urls_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_DEBUG_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_DEBUG_KEY ] === Dashboard_Config::get_secret_key() ) {
        
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
    
    // Handle fatal log display: /?hws_fatal_log=<secret>
    if ( Dashboard_Config::are_secret_urls_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_FATAL_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_FATAL_KEY ] === Dashboard_Config::get_secret_key() ) {
        hws_display_fatal_errors_page();
        exit;
    }
    
    // Handle quick setup: /?hws_quick_setup=<secret>
    if ( Dashboard_Config::is_secret_setup_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_SETUP_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_SETUP_KEY ] === Dashboard_Config::get_secret_key() ) {
        hws_run_quick_setup_public();
        exit;
    }
    
    // Handle permalink purge: /?hws_purge_permalinks=<secret>
    if ( Dashboard_Config::is_secret_permalinks_enabled() &&
         isset( $_GET[ Dashboard_Config::SECRET_PERMALINKS_KEY ] ) && 
         $_GET[ Dashboard_Config::SECRET_PERMALINKS_KEY ] === Dashboard_Config::get_secret_key() ) {
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
        ABSPATH . 'wp-admin/error_log',
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

// — Enqueue WP media uploader scripts on our settings page (for favicon upload)
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'hws-core-tools' ) !== false ) {
        wp_enqueue_media();
    }
});


/**
 * Register AJAX handlers
 */
function hws_dashboard_register_ajax() {
    add_action( 'wp_ajax_hws_quick_setup', __NAMESPACE__ . '\\ajax_quick_setup' );
    add_action( 'wp_ajax_hws_get_overview_state', __NAMESPACE__ . '\\ajax_get_overview_state' );
    add_action( 'wp_ajax_hws_toggle_secret_urls', __NAMESPACE__ . '\\ajax_toggle_secret_urls' );
    add_action( 'wp_ajax_hws_toggle_secret_setup', __NAMESPACE__ . '\\ajax_toggle_secret_setup' );
    add_action( 'wp_ajax_hws_toggle_secret_permalinks', __NAMESPACE__ . '\\ajax_toggle_secret_permalinks' );
    add_action( 'wp_ajax_hws_enable_all_auto_updates', __NAMESPACE__ . '\\ajax_enable_all_auto_updates' );
    add_action( 'wp_ajax_hws_save_master_secret', __NAMESPACE__ . '\\ajax_save_master_secret' );
    add_action( 'wp_ajax_hws_save_site_basics', __NAMESPACE__ . '\\ajax_save_site_basics' );
    add_action( 'wp_ajax_hws_test_site_basics', __NAMESPACE__ . '\\ajax_test_site_basics' );
    add_action( 'wp_ajax_hws_copy_favicon', __NAMESPACE__ . '\\ajax_copy_favicon' );
    add_action( 'wp_ajax_hws_save_brand_asset', __NAMESPACE__ . '\\ajax_save_brand_asset' );
    add_action( 'wp_ajax_hws_clear_brand_asset', __NAMESPACE__ . '\\ajax_clear_brand_asset' );
    // Note: hws_delete_backups is registered in settings-dashboard-backups.php
    // Note: hws_toggle_all_debug uses existing hws_base_tools_modify_wp_config_constants handler
}
add_action( 'init', __NAMESPACE__ . '\\hws_dashboard_register_ajax' );

function hws_is_display_errors_enabled(): bool {
    $display_errors_raw = ini_get( 'display_errors' );

    return (bool) ( $display_errors_raw && $display_errors_raw !== '0' && strtolower( (string) $display_errors_raw ) !== 'off' );
}

function hws_get_overview_state_payload(): array {
    $secret = Dashboard_Config::get_secret_key();
    $debug_log_path = WP_CONTENT_DIR . '/debug.log';
    $error_log_path = ABSPATH . 'error_log';
    $admin_log_path = ABSPATH . 'wp-admin/error_log';

    return [
        'secret' => $secret,
        'config' => [
            'WP_DEBUG'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
            'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'ini_display_errors' => hws_is_display_errors_enabled(),
            'DISABLE_WP_CRON'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'WP_MEMORY_LIMIT'  => defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : 'Not Set',
        ],
        'secret_toggles' => [
            'debug'      => Dashboard_Config::are_secret_urls_enabled(),
            'setup'      => Dashboard_Config::is_secret_setup_enabled(),
            'permalinks' => Dashboard_Config::is_secret_permalinks_enabled(),
        ],
        'secret_urls' => [
            'debug'      => add_query_arg( Dashboard_Config::SECRET_DEBUG_KEY, $secret, home_url( '/' ) ),
            'fatal'      => add_query_arg( Dashboard_Config::SECRET_FATAL_KEY, $secret, home_url( '/' ) ),
            'setup'      => add_query_arg( Dashboard_Config::SECRET_SETUP_KEY, $secret, home_url( '/' ) ),
            'permalinks' => add_query_arg( Dashboard_Config::SECRET_PERMALINKS_KEY, $secret, home_url( '/' ) ),
        ],
        'logs' => [
            'debug' => [
                'exists' => file_exists( $debug_log_path ),
                'size'   => file_exists( $debug_log_path ) ? size_format( filesize( $debug_log_path ) ) : 'N/A',
            ],
            'error' => [
                'exists' => file_exists( $error_log_path ),
                'size'   => file_exists( $error_log_path ) ? size_format( filesize( $error_log_path ) ) : 'N/A',
            ],
            'admin_error' => [
                'exists' => file_exists( $admin_log_path ),
                'size'   => file_exists( $admin_log_path ) ? size_format( filesize( $admin_log_path ) ) : 'N/A',
            ],
        ],
        'site_basics' => hws_get_site_basics_state(),
    ];
}

function hws_get_site_basics_state(): array {
    $title          = (string) get_option( 'blogname', '' );
    $tagline        = (string) get_option( 'blogdescription', '' );
    $indexable      = (string) get_option( 'blog_public', '1' ) === '1';
    $site_icon_id   = (int) get_option( 'site_icon', 0 );
    $site_icon_url  = $site_icon_id ? get_site_icon_url( 512 ) : '';
    $favicon_path   = ABSPATH . 'favicon.ico';
    $favicon_exists = file_exists( $favicon_path );

    return [
        'title'          => $title,
        'tagline'        => $tagline,
        'indexable'      => $indexable,
        'site_icon_id'   => $site_icon_id,
        'site_icon_url'  => $site_icon_url,
        'favicon_exists' => $favicon_exists,
        'favicon_url'    => home_url( '/favicon.ico' ),
        'document_title' => trim( $title . ( $tagline !== '' ? ' - ' . $tagline : '' ) ),
    ];
}

function hws_test_site_basics_state(): array {
    $state  = hws_get_site_basics_state();
    $checks = [
        'indexable' => [
            'pass'    => (bool) $state['indexable'],
            'message' => (bool) $state['indexable'] ? 'Search engines are allowed by blog_public.' : 'Search engines are discouraged by blog_public.',
        ],
        'site_icon' => [
            'pass'    => ! empty( $state['site_icon_id'] ),
            'message' => ! empty( $state['site_icon_id'] ) ? 'WordPress site icon is set.' : 'WordPress site icon is not set.',
        ],
        'favicon' => [
            'pass'    => (bool) $state['favicon_exists'],
            'message' => (bool) $state['favicon_exists'] ? '/favicon.ico exists.' : '/favicon.ico is missing.',
        ],
        'title' => [
            'pass'    => trim( (string) $state['title'] ) !== '',
            'message' => trim( (string) $state['title'] ) !== '' ? 'Website title is set.' : 'Website title is empty.',
        ],
    ];

    return [
        'passed' => ! in_array( false, wp_list_pluck( $checks, 'pass' ), true ),
        'state'  => $state,
        'checks' => $checks,
        'ran_at' => current_time( 'mysql' ),
    ];
}


/**
 * Main settings page display
 */
function hws_render_dashboard_tab( string $tab_id ): void {
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
        case 'features':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_features' ) ) {
                display_settings_features();
            }
            break;
        case 'brand-assets':
            render_tab_brand_assets();
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
        case 'update-center':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_update_center' ) ) {
                display_settings_update_center();
            }
            break;
        case 'masked-login':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_masked_login' ) ) {
                display_settings_masked_login();
            }
            break;
        case 'footer-text':
            if ( function_exists( __NAMESPACE__ . '\\display_settings_footer_text' ) ) {
                display_settings_footer_text();
            }
            break;
    }
}

function display_wp_admin_settings_page() {
    if ( ob_get_level() == 0 ) ob_start();
    
    $tabs = [
        'overview'      => '📊 Overview',
        'system-checks' => '🔍 System Checks',
        'plugins'       => '🔌 Plugins',
        'features'      => '✨ Features',
        'brand-assets'  => '🖼️ Brand Assets',
    ];

    if ( function_exists( __NAMESPACE__ . '\\hws_is_footer_text_module_enabled' ) && hws_is_footer_text_module_enabled() ) {
        $tabs['footer-text'] = '🦶 Footer Text';
    }

    $tabs += [
        'website-types' => '🌐 Website Types',
        'ui-cleanup'    => '🧹 UI Cleanup',
        'config'        => '⚙️ Configuration',
        'backups'       => '💾 Backups',
        'advanced'      => '🔧 Advanced',
        'comments'      => '💬 Comments',
        'update-center' => '🔄 Update Center',
        'masked-login'  => '🔐 Masked Login',
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
        /* — Panel status modifiers (same pattern as cron-unhealthy) */
        .hws-panel.panel-needs-attention {
            border-color: #d63638;
            background: #fcf0f1;
        }
        .hws-panel.panel-needs-attention .hws-panel-header {
            background: #fce4e4;
            border-bottom-color: #d63638;
        }
        .hws-panel.panel-warning {
            border-color: #dba617;
            background: #fff8e5;
        }
        .hws-panel.panel-warning .hws-panel-header {
            background: #fef3d0;
            border-bottom-color: #dba617;
        }
        .hws-panel.panel-healthy {
            border-color: #00a32a;
        }
        .hws-panel.panel-healthy .hws-panel-header {
            border-bottom-color: #00a32a;
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
        
        <?php
        // — Determine active tab from ?tab= query string (default: first tab)
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
        if ( 'snippets' === $active_tab ) {
            $active_tab = 'features';
        }
        // — Validate the tab exists in our tabs array; fall back to first tab if invalid
        if ( ! array_key_exists( $active_tab, $tabs ) ) {
            $active_tab = array_key_first( $tabs );
        }
        ?>
        
        <!-- Tab Navigation -->
        <nav class="hws-tabs-nav">
            <?php foreach ( $tabs as $tab_id => $label ) : ?>
                <button type="button" class="hws-tab-btn <?php echo $tab_id === $active_tab ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>">
                    <?php echo $label; ?>
                </button>
            <?php endforeach; ?>
        </nav>
        
        <!-- Only render the selected tab. Loading every tab eagerly makes this page slow on larger sites. -->
        <div id="tab-<?php echo esc_attr( $active_tab ); ?>" class="hws-tab-content active">
            <?php hws_render_dashboard_tab( $active_tab ); ?>
        </div>
    </div>

    <script>
    // Global nonce for all AJAX calls
    var hwsNonce = '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>';
    var hwsDashboardConfig = {
        homeUrl: <?php echo wp_json_encode( home_url( '/' ) ); ?>
    };

    /**
     * Abstract: Install a plugin from WordPress.org and activate it.
     * Reuses the existing hws_install_plugin AJAX handler.
     *
     * @param {string}      slug  Plugin slug (e.g. 'wordfence', 'wp-mail-smtp')
     * @param {HTMLElement}  btn   The button element (for UI feedback)
     */
    function hwsInstallPlugin( slug, btn ) {
        if ( ! confirm( 'Install and activate "' + slug + '" from WordPress.org?' ) ) return;
        var $btn = jQuery( btn );
        var origText = $btn.text();
        $btn.prop( 'disabled', true ).text( 'Installing…' );

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'hws_install_plugin', slug: slug, nonce: hwsNonce },
            success: function( response ) {
                if ( response.success ) {
                    $btn.text( '✅ Installed' ).prop( 'disabled', true );
                } else {
                    alert( 'Install failed: ' + ( response.data || 'Unknown error' ) );
                    $btn.prop( 'disabled', false ).text( origText );
                }
            },
            error: function() {
                alert( 'AJAX error during installation.' );
                $btn.prop( 'disabled', false ).text( origText );
            }
        });
    }

    /**
     * Abstract: Activate an already-installed plugin.
     * Reuses the new hws_activate_plugin AJAX handler.
     *
     * @param {string}      pluginFile  Plugin file path (e.g. 'wordfence/wordfence.php')
     * @param {HTMLElement}  btn         The button element (for UI feedback)
     */
    function hwsActivatePlugin( pluginFile, btn ) {
        var $btn = jQuery( btn );
        var origText = $btn.text();
        $btn.prop( 'disabled', true ).text( 'Activating…' );

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'hws_activate_plugin', plugin_file: pluginFile, nonce: hwsNonce },
            success: function( response ) {
                if ( response.success ) {
                    $btn.text( '✅ Activated' ).prop( 'disabled', true );
                } else {
                    alert( 'Activation failed: ' + ( response.data || 'Unknown error' ) );
                    $btn.prop( 'disabled', false ).text( origText );
                }
            },
            error: function() {
                alert( 'AJAX error during activation.' );
                $btn.prop( 'disabled', false ).text( origText );
            }
        });
    }
    
    jQuery(document).ready(function($) {
        
        // — Tab navigation uses a full request so only the selected tab is rendered.
        $('.hws-tab-btn').on('click', function() {
            var tabId = $(this).data('tab');
            if ($(this).hasClass('active')) {
                return;
            }

            var url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.location.assign(url.toString());
        });

        function getAjaxErrorMessage(response, fallback) {
            if (response && response.data) {
                if (typeof response.data === 'string') {
                    return response.data;
                }

                if (response.data.message) {
                    return response.data.message;
                }
            }

            return fallback || 'Unknown error';
        }

        function setStatusHtml($target, html) {
            if ($target && $target.length) {
                $target.html(html);
            }
        }

        function formatConfigSummary(constant, value) {
            switch (constant) {
                case 'WP_DEBUG':
                case 'WP_DEBUG_LOG':
                    return {
                        className: value ? 'status-warn' : 'status-ok',
                        html: constant + ': ' + (value ? '⚠️ ON' : '✅ OFF')
                    };
                case 'WP_DEBUG_DISPLAY':
                    return {
                        className: value ? 'status-bad' : 'status-ok',
                        html: constant + ': ' + (value ? '❌ ON' : '✅ OFF')
                    };
                case 'DISABLE_WP_CRON':
                    return {
                        className: value ? 'status-ok' : 'status-warn',
                        html: 'DISABLE_WP_CRON: ' + (value ? '✅ TRUE (using real cron)' : '⚠️ FALSE')
                    };
                case 'WP_MEMORY_LIMIT':
                    return {
                        className: '',
                        html: 'WP_MEMORY_LIMIT: <strong>' + value + '</strong>'
                    };
                default:
                    return null;
            }
        }

        function updateSummarySetting(constant, value) {
            var summary = formatConfigSummary(constant, value);
            var $summary = $('[data-summary-setting="' + constant + '"]');

            if (!summary || !$summary.length) {
                return;
            }

            $summary.removeClass('status-ok status-warn status-bad');
            if (summary.className) {
                $summary.addClass(summary.className);
            }
            $summary.html(summary.html);
        }

        function updateConfigToggle(constant, rawValue) {
            var value = rawValue;
            var $toggle = $('[data-config-toggle="' + constant + '"]').first();

            if (!$toggle.length) {
                updateSummarySetting(constant, value);
                return;
            }

            if (constant === 'WP_MEMORY_LIMIT') {
                $toggle.find('[data-config-label="' + constant + '"]').text('WP_MEMORY_LIMIT: ' + value);
                $toggle.find('button.modify-wp-config[data-constant="WP_MEMORY_LIMIT"]').removeClass('button-primary');
                $toggle.find('button.modify-wp-config[data-constant="WP_MEMORY_LIMIT"][data-value="' + value + '"]').addClass('button-primary');
                updateSummarySetting(constant, value);
                return;
            }

            if (constant === 'ini_display_errors') {
                value = !!value;
                $toggle.removeClass('on off').addClass(value ? 'on' : 'off');
                $toggle.find('[data-config-label="' + constant + '"]').text('display_errors: ' + (value ? 'ON' : 'OFF'));
                $toggle.find('button.modify-wp-config[data-constant="' + constant + '"]')
                    .text(value ? 'Disable' : 'Enable')
                    .attr('data-value', value ? '0' : '1');
                $('[data-config-summary="ini_display_errors"]')
                    .text(value ? 'ON' : 'Off')
                    .css('color', value ? '#d63638' : '#00a32a');
                return;
            }

            if (constant === 'DISABLE_WP_CRON') {
                value = !!value;
                $toggle.removeClass('on off').addClass(value ? 'off' : 'on');
                $toggle.find('[data-config-label="' + constant + '"]').text(
                    'DISABLE_WP_CRON: ' + (value ? 'TRUE (cron disabled - recommended)' : 'FALSE (cron enabled)')
                );
                $toggle.find('button.modify-wp-config[data-constant="' + constant + '"]')
                    .text(value ? 'Enable WP-Cron' : 'Disable WP-Cron')
                    .attr('data-value', value ? 'false' : 'true');
                updateSummarySetting(constant, value);
                return;
            }

            value = !!value;
            $toggle.removeClass('on off').addClass(value ? 'on' : 'off');
            $toggle.find('[data-config-label="' + constant + '"]').text(constant + ': ' + (value ? 'ON' : 'OFF'));
            $toggle.find('button.modify-wp-config[data-constant="' + constant + '"]')
                .text(value ? 'Disable' : 'Enable')
                .attr('data-value', value ? 'false' : 'true');
            updateSummarySetting(constant, value);
        }

        function updateSecretDetails(state) {
            if (!state || !state.secret_urls || !state.secret_toggles) {
                return;
            }

            $('#hws-master-secret').val(state.secret || $('#hws-master-secret').val());

            $('#hws-toggle-secret-urls').prop('checked', !!state.secret_toggles.debug);
            $('#hws-secret-debug-details').toggle(!!state.secret_toggles.debug);
            $('#hws-secret-debug-url').text(state.secret_urls.debug || '');
            $('#hws-secret-fatal-url').text(state.secret_urls.fatal || '');

            $('#hws-toggle-secret-setup').prop('checked', !!state.secret_toggles.setup);
            $('#hws-secret-setup-details').toggle(!!state.secret_toggles.setup);
            $('#hws-secret-setup-url').text(state.secret_urls.setup || '');

            $('#hws-toggle-secret-permalinks').prop('checked', !!state.secret_toggles.permalinks);
            $('#hws-secret-permalinks-details').toggle(!!state.secret_toggles.permalinks);
            $('#hws-secret-permalinks-url').text(state.secret_urls.permalinks || '');
        }

        function updateLogState(logKey, logState) {
            var labels = {
                debug: 'debug.log',
                error: 'error_log'
            };
            var $size = $('[data-log-size="' + logKey + '"]');
            var $summary = $('[data-summary-log="' + logKey + '"]');
            var label = labels[logKey];

            if ($size.length) {
                $size.text(logState.size).css('color', logState.exists ? '#d63638' : '#00a32a');
            }

            if ($summary.length && label) {
                $summary
                    .removeClass('status-ok status-warn status-bad')
                    .addClass(logState.exists ? 'status-warn' : 'status-ok')
                    .text(label + ': ' + (logState.exists ? '⚠️ ' + logState.size : '✅ None'));
            }
        }

        function applyOverviewState(state) {
            if (!state) {
                return;
            }

            if (state.config) {
                Object.keys(state.config).forEach(function(constant) {
                    updateConfigToggle(constant, state.config[constant]);
                });
            }

            if (state.logs) {
                updateLogState('debug', state.logs.debug);
                updateLogState('error', state.logs.error);
                updateLogState('admin_error', state.logs.admin_error);

                var hasProblemLogs = !!((state.logs.debug && state.logs.debug.exists) || (state.logs.error && state.logs.error.exists));
                $('#hws-summary-log-card').toggleClass('warn', hasProblemLogs);
            }

            updateSecretDetails(state);

            if (window.hwsUpdateLogPanels && typeof window.hwsUpdateLogPanels === 'function') {
                window.hwsUpdateLogPanels(state.logs || {});
            }
        }

        function refreshOverviewState() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hws_get_overview_state',
                    nonce: hwsNonce
                }
            }).done(function(response) {
                if (response.success) {
                    applyOverviewState(response.data);
                }
            });
        }
        
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
                        refreshOverviewState();
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
            var $checkbox = $(this);
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_urls',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    refreshOverviewState();
                } else {
                    $checkbox.prop('checked', !enabled);
                    alert(getAjaxErrorMessage(response, 'Failed to update secret debug URLs.'));
                }
            }).fail(function() {
                $checkbox.prop('checked', !enabled);
                alert('AJAX error');
            });
        });
        
        // Toggle Secret Setup URL
        $('#hws-toggle-secret-setup').on('change', function() {
            var $checkbox = $(this);
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_setup',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    refreshOverviewState();
                } else {
                    $checkbox.prop('checked', !enabled);
                    alert(getAjaxErrorMessage(response, 'Failed to update public quick setup URL.'));
                }
            }).fail(function() {
                $checkbox.prop('checked', !enabled);
                alert('AJAX error');
            });
        });
        
        // Toggle Secret Permalinks Purge URL
        $('#hws-toggle-secret-permalinks').on('change', function() {
            var $checkbox = $(this);
            var enabled = $(this).is(':checked');
            $.post(ajaxurl, {
                action: 'hws_toggle_secret_permalinks',
                enabled: enabled ? 1 : 0,
                nonce: hwsNonce
            }, function(response) {
                if (response.success) {
                    refreshOverviewState();
                } else {
                    $checkbox.prop('checked', !enabled);
                    alert(getAjaxErrorMessage(response, 'Failed to update public permalink purge URL.'));
                }
            }).fail(function() {
                $checkbox.prop('checked', !enabled);
                alert('AJAX error');
            });
        });
        
        // Save Master Secret Password
        $('#hws-save-master-secret').on('click', function() {
            var $btn = $(this);
            var secret = $('#hws-master-secret').val().trim();
            var $status = $('#hws-master-secret-status');
            
            if (secret.length < 6) {
                $status.html('<span style="color:#d63638;">❌ Password must be at least 6 characters</span>');
                return;
            }
            
            $btn.prop('disabled', true).text('Saving...');
            $.post(ajaxurl, {
                action: 'hws_save_master_secret',
                secret: secret,
                nonce: hwsNonce
            }, function(response) {
                $btn.prop('disabled', false).text('💾 Save Password');
                if (response.success) {
                    $status.html('<span style="color:#00a32a;">✅ ' + response.data.message + '</span>');
                    refreshOverviewState();
                } else {
                    $status.html('<span style="color:#d63638;">❌ ' + getAjaxErrorMessage(response, 'Failed to save') + '</span>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('💾 Save Password');
                $status.html('<span style="color:#d63638;">❌ AJAX error</span>');
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
                    var $row = $btn.closest('tr');
                    $row.fadeOut(200, function() {
                        $(this).remove();
                        if (window.hwsBackupUi && typeof window.hwsBackupUi.refreshSummary === 'function') {
                            window.hwsBackupUi.refreshSummary();
                        }
                    });
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
                    $('[data-backup-plugin-panel="' + plugin + '"]').remove();
                    if (window.hwsBackupUi && typeof window.hwsBackupUi.refreshSummary === 'function') {
                        window.hwsBackupUi.refreshSummary();
                    }
                    $btn.text('✅ Deleted').prop('disabled', true);
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
                        refreshOverviewState();
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
            var originalText = $btn.text();
            
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
                        refreshOverviewState();
                        $btn.prop('disabled', false).text(originalText);
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('AJAX error');
                    $btn.prop('disabled', false).text(originalText);
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
                        $btn.prop('disabled', false).text(action === 'enable' ? 'Enabled' : 'Disabled');
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
                        refreshOverviewState();
                        $btn.prop('disabled', false).text('🔴 Enable ALL Debug');
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
                        refreshOverviewState();
                        $btn.prop('disabled', false).text('🟢 Disable ALL Debug');
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
                        refreshOverviewState();
                        $btn.prop('disabled', false).text('Delete debug.log');
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
                        refreshOverviewState();
                        $btn.prop('disabled', false).text('Delete error_log');
                    } else {
                        alert('Error: ' + getAjaxErrorMessage(response));
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
function render_system_basics_panel() {
    $state = hws_get_site_basics_state();
    $test  = hws_test_site_basics_state();
    ?>
    <div class="hws-panel <?php echo $test['passed'] ? 'panel-healthy' : 'panel-warning'; ?>" id="hws-system-basics-panel">
        <div class="hws-panel-header">System Basics</div>
        <div class="hws-panel-body">
            <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,0.7fr);gap:18px;align-items:start;">
                <div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <label>
                            <strong style="display:block;margin-bottom:4px;">Website Title</strong>
                            <input type="text" id="hws-site-title" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $state['title'] ); ?>">
                        </label>
                        <label>
                            <strong style="display:block;margin-bottom:4px;">Tagline</strong>
                            <input type="text" id="hws-site-tagline" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $state['tagline'] ); ?>">
                        </label>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                        <input type="checkbox" id="hws-site-indexable" <?php checked( $state['indexable'] ); ?>>
                        <strong>Allow search engines to index this site</strong>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="button button-primary" id="hws-save-site-basics">Save System Basics</button>
                        <button type="button" class="button" id="hws-test-site-basics">Run Basics Test</button>
                        <span id="hws-site-basics-status" style="font-size:13px;" aria-live="polite"></span>
                    </div>
                </div>

                <div>
                    <div style="display:grid;gap:8px;font-size:13px;" id="hws-site-basics-checks">
                        <?php foreach ( $test['checks'] as $check ) : ?>
                            <div style="display:flex;gap:8px;align-items:flex-start;">
                                <span><?php echo $check['pass'] ? '✅' : '⚠️'; ?></span>
                                <span><?php echo esc_html( $check['message'] ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #dcdcde;font-size:12.5px;color:#50575e;">
                        <div><strong>Document title:</strong> <?php echo esc_html( $state['document_title'] ); ?></div>
                        <div><strong>Site icon:</strong> <?php echo $state['site_icon_id'] ? 'set' : 'missing'; ?></div>
                        <div><strong>/favicon.ico:</strong> <?php echo $state['favicon_exists'] ? esc_html( $state['favicon_url'] ) : 'missing'; ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    jQuery(function($) {
        function renderBasicsResult(data) {
            if (!data || !data.checks) return;
            var html = '';
            Object.keys(data.checks).forEach(function(key) {
                var check = data.checks[key];
                html += '<div style="display:flex;gap:8px;align-items:flex-start;"><span>' + (check.pass ? '✅' : '⚠️') + '</span><span>' + $('<div>').text(check.message || '').html() + '</span></div>';
            });
            $('#hws-site-basics-checks').html(html);
            $('#hws-system-basics-panel').toggleClass('panel-healthy', !!data.passed).toggleClass('panel-warning', !data.passed);
        }

        function basicsPayload(action) {
            return {
                action: action,
                nonce: hwsNonce,
                title: $('#hws-site-title').val() || '',
                tagline: $('#hws-site-tagline').val() || '',
                indexable: $('#hws-site-indexable').is(':checked') ? 1 : 0
            };
        }

        $('#hws-save-site-basics').on('click', function() {
            var $button = $(this);
            var $status = $('#hws-site-basics-status');
            $button.prop('disabled', true);
            $status.text('Saving...');
            $.post(ajaxurl, basicsPayload('hws_save_site_basics'), function(response) {
                if (!response || !response.success) {
                    $status.text('Save failed.');
                    return;
                }
                renderBasicsResult(response.data || {});
                $status.text('Saved and tested.');
            }, 'json').fail(function() {
                $status.text('AJAX error.');
            }).always(function() {
                $button.prop('disabled', false);
            });
        });

        $('#hws-test-site-basics').on('click', function() {
            var $button = $(this);
            var $status = $('#hws-site-basics-status');
            $button.prop('disabled', true);
            $status.text('Testing...');
            $.post(ajaxurl, basicsPayload('hws_test_site_basics'), function(response) {
                if (!response || !response.success) {
                    $status.text('Test failed.');
                    return;
                }
                renderBasicsResult(response.data || {});
                $status.text(response.data && response.data.passed ? 'All basics passed.' : 'Basics need attention.');
            }, 'json').fail(function() {
                $status.text('AJAX error.');
            }).always(function() {
                $button.prop('disabled', false);
            });
        });

    });
    </script>
    <?php
}

function render_tab_overview() {
    // Get debug states
    $wp_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    $wp_debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    $wp_debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
    $display_errors = hws_is_display_errors_enabled();
    
    // WP-Config settings
    $disable_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    $wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not Set';
    
    $debug_log_path = WP_CONTENT_DIR . '/debug.log';
    $error_log_path = ABSPATH . 'error_log';
    $admin_log_path = ABSPATH . 'wp-admin/error_log';
    $debug_log_size = file_exists( $debug_log_path ) ? size_format( filesize( $debug_log_path ) ) : 'N/A';
    $error_log_size = file_exists( $error_log_path ) ? size_format( filesize( $error_log_path ) ) : 'N/A';
    $admin_log_size = file_exists( $admin_log_path ) ? size_format( filesize( $admin_log_path ) ) : 'N/A';
    
    // Secret URLs
    $secret_urls_enabled = Dashboard_Config::are_secret_urls_enabled();
    $secret_setup_enabled = Dashboard_Config::is_secret_setup_enabled();
    $secret_permalinks_enabled = Dashboard_Config::is_secret_permalinks_enabled();
    $debug_url = add_query_arg( Dashboard_Config::SECRET_DEBUG_KEY, Dashboard_Config::get_secret_key(), home_url( '/' ) );
    $fatal_url = add_query_arg( Dashboard_Config::SECRET_FATAL_KEY, Dashboard_Config::get_secret_key(), home_url( '/' ) );
    $setup_url = add_query_arg( Dashboard_Config::SECRET_SETUP_KEY, Dashboard_Config::get_secret_key(), home_url( '/' ) );
    $permalinks_url = add_query_arg( Dashboard_Config::SECRET_PERMALINKS_KEY, Dashboard_Config::get_secret_key(), home_url( '/' ) );
    ?>
    
    <?php render_system_basics_panel(); ?>

    <!-- Summary Section -->
    <div class="hws-panel">
        <div class="hws-panel-header">📋 Summary</div>
        <div class="hws-panel-body">
            <?php render_summary_section(); ?>
        </div>
    </div>
    
    <!-- ═══════════════════════════════════════════════════════════════════
         GOING LIVE CHECKLIST (GLC)
         Checks recommended snippets + essential plugins are active.
         @since 10.9.0
    ═══════════════════════════════════════════════════════════════════ -->
    <?php render_going_live_checklist(); ?>
    
    <!-- Master Secret Password -->
    <div class="hws-panel">
        <div class="hws-panel-header">🔑 Master Secret Password</div>
        <div class="hws-panel-body">
            <p style="font-size:13px;color:#646970;margin:0 0 12px;">This password is used by ALL public/secret URLs across the plugin (debug, quick setup, update center, masked login, etc). Change it here to update everywhere at once.</p>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <input type="text" id="hws-master-secret" value="<?php echo esc_attr( Dashboard_Config::get_secret_key() ); ?>" class="regular-text" style="font-family:monospace;font-size:14px;">
                <button type="button" id="hws-save-master-secret" class="hws-btn" style="white-space:nowrap;">💾 Save Password</button>
            </div>
            <div id="hws-master-secret-status" style="font-size:13px;"></div>
            <p style="font-size:12px;color:#d63638;margin:8px 0 0;">⚠️ Anyone with this password can trigger public URLs. Use a strong, unique password.</p>
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
                    <li>Delete log files &amp; backup files</li>
                    <li>Delete all comments &amp; pingbacks</li>
                </ul>
                <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                    <li><strong>Enable all recommended snippets</strong></li>
                    <li><strong>Install &amp; activate essential plugins</strong> <em>(skips pro)</em></li>
                    <li>Enable Redis object cache <em>(if available)</em></li>
                    <li>Activate LiteSpeed Cache <em>(if installed)</em></li>
                    <li>Activate Wordfence <em>(if installed)</em></li>
                </ul>
            </div>
            <button type="button" id="hws-run-quick-setup" class="hws-btn">▶️ Run Quick Setup</button>
            <textarea id="hws-quick-setup-log" class="hws-quick-setup-log" readonly placeholder="Setup log will appear here..."></textarea>
            
            <!-- Secret Quick Setup URL -->
            <div class="hws-secret-url-box" id="hws-secret-setup-box" style="margin-top: 15px;">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-setup" <?php checked( $secret_setup_enabled ); ?>>
                    <strong>Enable Public Quick Setup URL</strong> (runs setup without admin login)
                </label>
                <div id="hws-secret-setup-details" style="<?php echo $secret_setup_enabled ? '' : 'display:none;'; ?>">
                    <p style="margin: 10px 0 5px;"><strong>Quick Setup URL:</strong></p>
                    <code id="hws-secret-setup-url"><?php echo esc_html( $setup_url ); ?></code>
                    <p style="color: #d63638; font-size: 12px; margin-top: 5px;">⚠️ Anyone with this URL can run Quick Setup. Disable when not needed.</p>
                </div>
            </div>
            
            <!-- Secret Permalinks Purge URL -->
            <div class="hws-secret-url-box" id="hws-secret-permalinks-box" style="margin-top: 15px;">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-permalinks" <?php checked( $secret_permalinks_enabled ); ?>>
                    <strong>Enable Public Permalink Purge URL</strong> (flushes permalinks without admin login)
                </label>
                <div id="hws-secret-permalinks-details" style="<?php echo $secret_permalinks_enabled ? '' : 'display:none;'; ?>">
                    <p style="margin: 10px 0 5px;"><strong>Purge Permalinks URL:</strong></p>
                    <code id="hws-secret-permalinks-url"><?php echo esc_html( $permalinks_url ); ?></code>
                    <p style="color: #666; font-size: 12px; margin-top: 5px;">ℹ️ Use this URL to flush rewrite rules remotely (useful for terminal/scripts).</p>
                </div>
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
                <div class="hws-debug-toggle <?php echo $wp_debug ? 'on' : 'off'; ?>" data-config-toggle="WP_DEBUG">
                    <span data-config-label="WP_DEBUG">WP_DEBUG: <?php echo $wp_debug ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG" data-value="<?php echo $wp_debug ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $wp_debug_display ? 'on' : 'off'; ?>" data-config-toggle="WP_DEBUG_DISPLAY">
                    <span data-config-label="WP_DEBUG_DISPLAY">WP_DEBUG_DISPLAY: <?php echo $wp_debug_display ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG_DISPLAY" data-value="<?php echo $wp_debug_display ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug_display ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $wp_debug_log ? 'on' : 'off'; ?>" data-config-toggle="WP_DEBUG_LOG">
                    <span data-config-label="WP_DEBUG_LOG">WP_DEBUG_LOG: <?php echo $wp_debug_log ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="WP_DEBUG_LOG" data-value="<?php echo $wp_debug_log ? 'false' : 'true'; ?>">
                        <?php echo $wp_debug_log ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle <?php echo $display_errors ? 'on' : 'off'; ?>" data-config-toggle="ini_display_errors">
                    <span data-config-label="ini_display_errors">display_errors: <?php echo $display_errors ? 'ON' : 'OFF'; ?></span>
                    <button class="button modify-wp-config" data-constant="ini_display_errors" data-value="<?php echo $display_errors ? '0' : '1'; ?>">
                        <?php echo $display_errors ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
            </div>
            
            <!-- Secret URLs -->
            <div class="hws-secret-url-box" id="hws-secret-debug-box">
                <label>
                    <input type="checkbox" id="hws-toggle-secret-urls" <?php checked( $secret_urls_enabled ); ?>>
                    <strong>Enable Secret Debug URLs</strong> (allows toggling debug via URL)
                </label>
                <div id="hws-secret-debug-details" style="<?php echo $secret_urls_enabled ? '' : 'display:none;'; ?>">
                    <p style="margin: 10px 0 5px;"><strong>Toggle Debug On/Off:</strong></p>
                    <code id="hws-secret-debug-url"><?php echo esc_html( $debug_url ); ?></code>
                    <p style="margin: 10px 0 5px;"><strong>View Fatal Errors (even when site is down):</strong></p>
                    <code id="hws-secret-fatal-url"><?php echo esc_html( $fatal_url ); ?></code>
                </div>
            </div>
        </div>
    </div>
    
    <!-- WP-Config Settings -->
    <div class="hws-panel">
        <div class="hws-panel-header">📝 WP-Config Settings</div>
        <div class="hws-panel-body">
            <div class="hws-debug-controls">
                <div class="hws-debug-toggle <?php echo $disable_cron ? 'off' : 'on'; ?>" data-config-toggle="DISABLE_WP_CRON">
                    <span data-config-label="DISABLE_WP_CRON">DISABLE_WP_CRON: <?php echo $disable_cron ? 'TRUE (cron disabled - recommended)' : 'FALSE (cron enabled)'; ?></span>
                    <button class="button modify-wp-config" data-constant="DISABLE_WP_CRON" data-value="<?php echo $disable_cron ? 'false' : 'true'; ?>">
                        <?php echo $disable_cron ? 'Enable WP-Cron' : 'Disable WP-Cron'; ?>
                    </button>
                </div>
                <div class="hws-debug-toggle" data-config-toggle="WP_MEMORY_LIMIT">
                    <span data-config-label="WP_MEMORY_LIMIT">WP_MEMORY_LIMIT: <?php echo esc_html( $wp_memory_limit ); ?></span>
                    <button class="button modify-wp-config <?php echo (string) $wp_memory_limit === '512M' ? 'button-primary' : ''; ?>" data-constant="WP_MEMORY_LIMIT" data-value="512M">Set to 512M</button>
                    <button class="button modify-wp-config <?php echo (string) $wp_memory_limit === '1024M' ? 'button-primary' : ''; ?>" data-constant="WP_MEMORY_LIMIT" data-value="1024M">Set to 1G</button>
                    <button class="button modify-wp-config <?php echo (string) $wp_memory_limit === '4096M' ? 'button-primary' : ''; ?>" data-constant="WP_MEMORY_LIMIT" data-value="4096M">Set to 4G</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SMTP / Brevo Email -->
    <?php render_smtp_status_panel(); ?>
    
    <!-- Wordfence Security -->
    <?php render_wordfence_status_panel(); ?>
    
    <!-- Log Files - Clean 3-Panel View -->
    <div class="hws-panel">
        <div class="hws-panel-header">📄 Error Logs</div>
        <div class="hws-panel-body">
            <!-- Log Size Summary -->
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div>
                    <strong>debug.log:</strong> 
                    <span data-log-size="debug" style="color: <?php echo $debug_log_size !== 'N/A' ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $debug_log_size; ?>
                    </span>
                </div>
                <div>
                    <strong>error_log:</strong> 
                    <span data-log-size="error" style="color: <?php echo $error_log_size !== 'N/A' ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $error_log_size; ?>
                    </span>
                </div>
                <div>
                    <strong>wp-admin/error_log:</strong> 
                    <span data-log-size="admin_error" style="color: <?php echo $admin_log_size !== 'N/A' ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $admin_log_size; ?>
                    </span>
                </div>
                <div>
                    <strong>display_errors:</strong> 
                    <span data-config-summary="ini_display_errors" style="color: <?php echo $display_errors ? '#d63638' : '#00a32a'; ?>;">
                        <?php echo $display_errors ? 'ON' : 'Off'; ?>
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
                <button type="button" class="button hws-log-tab" data-log="admin-error">📝 wp-admin/error_log</button>
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
            
            <!-- wp-admin/error_log Panel -->
            <div id="log-panel-admin-error" class="hws-log-panel" style="display: none;">
                <div class="hws-log-viewer" style="max-height: 400px;">
                    <?php 
                    if ( file_exists( $admin_log_path ) ) {
                        echo hws_highlight_log_errors( hws_get_log_tail( $admin_log_path, 150 ) ); 
                    } else {
                        echo '<span style="color: #666;">wp-admin/error_log not found</span>';
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

        window.hwsUpdateLogPanels = function(logs) {
            function updatePanel(panelId, exists, emptyHtml) {
                if (typeof exists === 'undefined') {
                    return;
                }

                if (!exists) {
                    $('#' + panelId + ' .hws-log-viewer').html(emptyHtml);
                    originalLogHTML[panelId] = emptyHtml;
                }
            }

            if (!logs) {
                return;
            }

            updatePanel('log-panel-debug', logs.debug && logs.debug.exists, '<span style="color: #666;">debug.log not found</span>');
            updatePanel('log-panel-error', logs.error && logs.error.exists, '<span style="color: #666;">error_log not found</span>');
            updatePanel('log-panel-admin-error', logs.admin_error && logs.admin_error.exists, '<span style="color: #666;">wp-admin/error_log not found</span>');

            if (logs.debug && logs.error && !logs.debug.exists && !logs.error.exists) {
                var fatalEmpty = '<span style="color: #00a32a;">✅ No fatal or syntax errors found in either log.</span>';
                $('#log-panel-fatal-syntax .hws-log-viewer').html(fatalEmpty);
                originalLogHTML['log-panel-fatal-syntax'] = fatalEmpty;
            }
        };
        
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
    
    <!-- LiteSpeed Cache Status -->
    <?php render_litespeed_panel(); ?>
    
    <!-- PHP & Server Extensions -->
    <?php render_php_extensions_panel(); ?>
    
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
function hws_get_log_tail( $path, $lines = 100, $max_bytes = 524288 ) {
    if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
        return 'Log file not found or not readable.';
    }

    $lines     = max( 1, (int) $lines );
    $max_bytes = max( 4096, (int) $max_bytes );
    $size      = (int) filesize( $path );

    if ( $size <= 0 ) {
        return 'Log file is empty.';
    }

    $handle = fopen( $path, 'rb' );
    if ( ! $handle ) {
        return 'Log file not readable.';
    }

    $read_bytes = min( $size, $max_bytes );
    if ( $read_bytes < $size ) {
        fseek( $handle, -$read_bytes, SEEK_END );
    }

    $content = stream_get_contents( $handle );
    fclose( $handle );

    if ( false === $content || '' === $content ) {
        return 'Log file is empty.';
    }

    $content_lines = preg_split( "/\r\n|\n|\r/", trim( $content ) );
    if ( false === $content_lines || empty( $content_lines ) ) {
        return 'Log file is empty.';
    }

    if ( $read_bytes < $size && count( $content_lines ) > 1 ) {
        array_shift( $content_lines );
    }

    return implode( "\n", array_slice( $content_lines, -$lines ) );
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
        
        $content = hws_get_log_tail( $path, max( 1000, $limit * 20 ), 1048576 );
        if ( ! $content || strpos( $content, 'Log file ' ) === 0 ) {
            continue;
        }
        
        foreach ( preg_split( "/\r\n|\n|\r/", $content ) as $line ) {
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
            <div class="hws-summary-card <?php echo ( $debug_log_size + $error_log_size ) > 0 ? 'warn' : ''; ?>" id="hws-summary-log-card">
                <h4>Log Files</h4>
                <p class="<?php echo $debug_log_size > 0 ? 'status-warn' : 'status-ok'; ?>" data-summary-log="debug">
                    debug.log: <?php echo $debug_log_size > 0 ? '⚠️ ' . size_format( $debug_log_size ) : '✅ None'; ?>
                </p>
                <p class="<?php echo $error_log_size > 0 ? 'status-warn' : 'status-ok'; ?>" data-summary-log="error">
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
                <p class="<?php echo $wp_debug ? 'status-warn' : 'status-ok'; ?>" data-summary-setting="WP_DEBUG">
                    WP_DEBUG: <?php echo $wp_debug ? '⚠️ ON' : '✅ OFF'; ?>
                </p>
                <p class="<?php echo $wp_debug_log ? 'status-warn' : 'status-ok'; ?>" data-summary-setting="WP_DEBUG_LOG">
                    WP_DEBUG_LOG: <?php echo $wp_debug_log ? '⚠️ ON' : '✅ OFF'; ?>
                </p>
                <p class="<?php echo $wp_debug_display ? 'status-bad' : 'status-ok'; ?>" data-summary-setting="WP_DEBUG_DISPLAY">
                    WP_DEBUG_DISPLAY: <?php echo $wp_debug_display ? '❌ ON' : '✅ OFF'; ?>
                </p>
            </div>
            <div class="hws-summary-card">
                <h4>Memory & Cron</h4>
                <p data-summary-setting="WP_MEMORY_LIMIT">WP_MEMORY_LIMIT: <strong><?php echo esc_html( $wp_memory_limit ); ?></strong></p>
                <p class="<?php echo $disable_cron ? 'status-ok' : 'status-warn'; ?>" data-summary-setting="DISABLE_WP_CRON">
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
    
    // 12. Enable all recommended snippets
    $log .= "<span class='success'>[Step {$step}]</span> Enabling recommended snippets...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\hws_get_going_live_snippets' ) ) {
        $glc_snippets = hws_get_going_live_snippets();
        $enabled_count = 0;
        $already_count = 0;
        foreach ( $glc_snippets as $snippet_id ) {
            if ( get_option( $snippet_id, false ) ) {
                $already_count++;
            } else {
                update_option( $snippet_id, true );
                $enabled_count++;
            }
        }
        $log .= "  ✓ Enabled {$enabled_count} snippet(s), {$already_count} already active\n";
    }
    
    // 13. Install & activate essential plugins (skip pro plugins)
    $log .= "<span class='success'>[Step {$step}]</span> Installing essential plugins...\n";
    $step++;
    if ( function_exists( __NAMESPACE__ . '\\hws_get_monitored_plugins' ) ) {
        $monitored = hws_get_monitored_plugins();
        // — Need WordPress plugin installer functions
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        foreach ( $monitored as $plugin_path => $info ) {
            // — Skip non-essential, optional, and pro plugins
            if ( ( $info['category'] ?? '' ) !== 'essential' ) continue;
            if ( ! empty( $info['pro'] ) ) {
                $log .= "  - Skipped {$info['name']} (pro/manual install)\n";
                continue;
            }
            // — Already active?
            if ( is_plugin_active( $plugin_path ) ) {
                $log .= "  ✓ {$info['name']} already active\n";
                continue;
            }
            // — Installed but not active? Activate it
            if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
                $result = activate_plugin( $plugin_path );
                if ( is_wp_error( $result ) ) {
                    $log .= "  <span class='warning'>⚠ Could not activate {$info['name']}: " . $result->get_error_message() . "</span>\n";
                } else {
                    $log .= "  ✓ Activated {$info['name']}\n";
                }
                continue;
            }
            // — Not installed: download from wordpress.org slug
            $download = $info['download'] ?? 'manual';
            if ( $download === 'manual' ) {
                $log .= "  <span class='warning'>⚠ {$info['name']} not installed (manual download required)</span>\n";
                continue;
            }
            // — Extract slug from download URL or use plugin folder name
            $slug = basename( dirname( $plugin_path ) );
            $api  = plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
            if ( is_wp_error( $api ) ) {
                $log .= "  <span class='warning'>⚠ Could not find {$info['name']} in repository</span>\n";
                continue;
            }
            $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
            $installed = $upgrader->install( $api->download_link );
            if ( $installed && ! is_wp_error( $installed ) ) {
                $activate_result = activate_plugin( $plugin_path );
                if ( is_wp_error( $activate_result ) ) {
                    $log .= "  ✓ Installed {$info['name']} (activation failed: " . $activate_result->get_error_message() . ")\n";
                } else {
                    $log .= "  ✓ Installed & activated {$info['name']}\n";
                }
            } else {
                $log .= "  <span class='warning'>⚠ Failed to install {$info['name']}</span>\n";
            }
        }
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

    hws_require_ajax_nonce_or_error();
    
    $log = hws_execute_quick_setup();
    
    // Strip HTML tags for AJAX response (will be shown in textarea)
    $log = strip_tags( $log );
    
    wp_send_json_success( [ 'log' => $log ] );
}

function ajax_get_overview_state() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success( hws_get_overview_state_payload() );
}

function ajax_save_site_basics() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    $title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $tagline   = isset( $_POST['tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['tagline'] ) ) : '';
    $indexable = ! empty( $_POST['indexable'] );

    update_option( 'blogname', $title );
    update_option( 'blogdescription', $tagline );
    update_option( 'blog_public', $indexable ? '1' : '0' );

    wp_send_json_success( hws_test_site_basics_state() );
}

function ajax_test_site_basics() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success( hws_test_site_basics_state() );
}


/**
 * AJAX: Toggle secret URLs
 */
function ajax_toggle_secret_urls() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();
    
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

    hws_require_ajax_nonce_or_error();
    
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

    hws_require_ajax_nonce_or_error();
    
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

    hws_require_ajax_nonce_or_error();
    
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

    hws_require_ajax_nonce_or_error();
    
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


/**
 * AJAX: Save the master secret password
 */
function ajax_save_master_secret() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();
    
    $new_secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
    
    // — Validate: must be at least 6 characters
    if ( strlen( $new_secret ) < 6 ) {
        wp_send_json_error( 'Password must be at least 6 characters' );
    }
    
    update_option( Dashboard_Config::OPT_MASTER_SECRET, $new_secret );
    
    wp_send_json_success( [
        'message' => 'Master password updated successfully',
        'secret'  => $new_secret,
    ] );
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * SMTP / BREVO EMAIL STATUS PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reports WP Mail SMTP plugin status with Brevo (Sendinblue) integration details:
 *   - Plugin installed/activated status
 *   - Active mailer (Brevo, SMTP, etc.)
 *   - From email and sending domain
 *   - Brevo API key status
 *   - Link to settings page
 *
 * @since 10.8.2
 */
function render_smtp_status_panel() {
    // — Check if WP Mail SMTP plugin is installed and active
    $plugin_file     = 'wp-mail-smtp/wp_mail_smtp.php';
    $plugin_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
    $plugin_active    = is_plugin_active( $plugin_file );
    $settings_url     = admin_url( 'admin.php?page=wp-mail-smtp' );

    // — Get SMTP options if plugin is active
    $smtp_options = $plugin_active ? get_option( 'wp_mail_smtp', [] ) : [];
    $mailer       = $smtp_options['mail']['mailer'] ?? 'none';
    $from_email   = $smtp_options['mail']['from_email'] ?? '';
    $from_name    = $smtp_options['mail']['from_name'] ?? '';
    $is_brevo     = $mailer === 'sendinblue';

    // — Get Brevo-specific info
    $brevo_api_key  = '';
    $brevo_domain   = '';
    if ( $is_brevo ) {
        $brevo_api_key = $smtp_options['sendinblue']['api_key'] ?? '';
        if ( $from_email ) {
            $brevo_domain = substr( strrchr( $from_email, '@' ), 1 );
        }
    }

    // — Use existing helper functions if available
    $smtp_check = function_exists( __NAMESPACE__ . '\\check_smtp_auth_status_and_mailer' )
                  ? check_smtp_auth_status_and_mailer()
                  : [ 'status' => false, 'mailer' => '', 'raw_value' => '' ];

    // — Mailer display names
    $mailer_names = [
        'sendinblue' => 'Brevo (Sendinblue)',
        'smtp'       => 'Other SMTP',
        'mail'       => 'PHP mail()',
        'gmail'      => 'Gmail',
        'outlook'    => 'Outlook',
        'sendgrid'   => 'SendGrid',
        'mailgun'    => 'Mailgun',
        'sparkpost'  => 'SparkPost',
        'postmark'   => 'Postmark',
        'sendlayer'  => 'SendLayer',
        'none'       => 'Not configured',
    ];
    $mailer_display = $mailer_names[ $mailer ] ?? ucfirst( $mailer );

    // — Determine panel health status
    //   Healthy: plugin active + using authenticated mailer (smtp/sendinblue/sendgrid/etc)
    //   Needs attention: plugin missing, inactive, or using PHP mail()
    $is_authenticated = $plugin_active && $smtp_check['status'];
    $is_php_mail      = $plugin_active && $mailer === 'mail';

    // — Panel CSS class based on status
    //   Red = not installed / not active / no mailer
    //   Yellow = active but using PHP mail() (not authenticated)
    //   Default = fully authenticated
    $panel_class = 'hws-panel';
    if ( ! $plugin_active ) {
        $panel_class .= ' panel-needs-attention';
    } elseif ( $is_php_mail || ! $is_authenticated ) {
        $panel_class .= ' panel-needs-attention';
    }
    ?>
    <div class="<?php echo esc_attr( $panel_class ); ?>">
        <div class="hws-panel-header">📧 Email / SMTP Authentication</div>
        <div class="hws-panel-body">
            <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:start;">

                <!-- Status Icon -->
                <div style="text-align:center;">
                    <?php if ( $is_authenticated ) : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#d4edda;display:flex;align-items:center;justify-content:center;font-size:28px;">✅</div>
                        <div style="font-size:11px;color:#00a32a;margin-top:4px;">Authenticated</div>
                    <?php elseif ( $plugin_active ) : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#fff3cd;display:flex;align-items:center;justify-content:center;font-size:28px;">⚠️</div>
                        <div style="font-size:11px;color:#dba617;margin-top:4px;">Needs Setup</div>
                    <?php else : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#f8d7da;display:flex;align-items:center;justify-content:center;font-size:28px;">❌</div>
                        <div style="font-size:11px;color:#d63638;margin-top:4px;"><?php echo $plugin_installed ? 'Inactive' : 'Not Installed'; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Status Grid -->
                <div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">
                        <!-- Plugin Status -->
                        <div style="padding:10px 14px;background:<?php echo $plugin_active ? '#f8f9fa' : 'rgba(214,54,56,0.06)'; ?>;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">WP Mail SMTP Plugin</div>
                            <?php if ( $plugin_active ) : ?>
                                <span style="color:#00a32a;font-size:13px;">✅ Active</span>
                            <?php elseif ( $plugin_installed ) : ?>
                                <span style="color:#dba617;font-size:13px;">⚠️ Installed but not activated</span>
                                <div style="font-size:12px;margin-top:3px;">
                                    <button type="button" class="hws-btn" style="font-size:11px;padding:4px 10px;" onclick="hwsActivatePlugin('wp-mail-smtp/wp_mail_smtp.php', this);">Activate Now</button>
                                </div>
                            <?php else : ?>
                                <span style="color:#d63638;font-size:13px;">❌ Not installed</span>
                                <div style="font-size:12px;margin-top:3px;">
                                    <button type="button" class="hws-btn" style="font-size:11px;padding:4px 10px;" onclick="hwsInstallPlugin('wp-mail-smtp', this);">Install & Activate</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Mailer -->
                        <div style="padding:10px 14px;background:<?php echo ( $plugin_active && ! $is_authenticated ) ? 'rgba(214,54,56,0.06)' : '#f8f9fa'; ?>;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">Active Mailer</div>
                            <?php if ( $plugin_active ) : ?>
                                <span style="color:<?php echo $is_authenticated ? '#00a32a' : '#d63638'; ?>;font-size:13px;">
                                    <?php echo $is_authenticated ? '✅' : '⚠️'; ?> <?php echo esc_html( $mailer_display ); ?>
                                </span>
                                <?php if ( $is_php_mail ) : ?>
                                    <div style="font-size:11px;color:#d63638;margin-top:2px;">PHP mail() is unreliable — configure an API mailer like Brevo</div>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>

                        <!-- From Email -->
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">From Email</div>
                            <?php if ( $from_email ) : ?>
                                <code style="font-size:12px;"><?php echo esc_html( $from_email ); ?></code>
                                <?php if ( $from_name ) : ?>
                                    <div style="font-size:11px;color:#646970;margin-top:2px;">Name: <?php echo esc_html( $from_name ); ?></div>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>

                        <!-- Sending Domain -->
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">Sending Domain</div>
                            <?php if ( $brevo_domain ) : ?>
                                <code style="font-size:12px;"><?php echo esc_html( $brevo_domain ); ?></code>
                            <?php elseif ( $from_email ) : ?>
                                <code style="font-size:12px;"><?php echo esc_html( substr( strrchr( $from_email, '@' ), 1 ) ); ?></code>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $is_brevo ) : ?>
                        <!-- Brevo API Key Status -->
                        <div style="padding:10px 14px;background:<?php echo empty( $brevo_api_key ) ? 'rgba(214,54,56,0.06)' : '#f0f7ff'; ?>;border:1px solid <?php echo empty( $brevo_api_key ) ? '#d63638' : '#c3d9f0'; ?>;border-radius:6px;margin-bottom:12px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">🔑 Brevo API Key</div>
                            <?php if ( ! empty( $brevo_api_key ) ) : ?>
                                <span style="color:#00a32a;font-size:13px;">✅ Configured</span>
                                <code style="font-size:11px;color:#888;margin-left:8px;"><?php echo esc_html( substr( $brevo_api_key, 0, 8 ) . '••••••••' . substr( $brevo_api_key, -4 ) ); ?></code>
                            <?php else : ?>
                                <span style="color:#d63638;font-size:13px;">❌ Missing — add your Brevo API key in WP Mail SMTP settings</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if ( $plugin_active ) : ?>
                            <a href="<?php echo esc_url( $settings_url ); ?>" class="hws-btn" style="text-decoration:none;">⚙️ SMTP Settings</a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mail-smtp-tools&tab=test' ) ); ?>" class="hws-btn" style="text-decoration:none;background:#2271b1;border-color:#2271b1;">📤 Send Test Email</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * WORDFENCE SECURITY STATUS PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reports Wordfence plugin status and key security configuration:
 *   - Plugin installed/activated status (one-click install/activate)
 *   - Firewall protection status
 *   - Security alert email configuration
 *   - License key status
 *   - Links to Wordfence settings pages
 *
 * Uses abstract hwsInstallPlugin() / hwsActivatePlugin() JS helpers
 * and the existing hws_install_plugin / hws_activate_plugin AJAX handlers.
 *
 * @since 10.8.4
 */
function render_wordfence_status_panel() {
    // — Check if Wordfence is installed and active
    $plugin_file      = 'wordfence/wordfence.php';
    $plugin_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
    $plugin_active    = is_plugin_active( $plugin_file );

    // — Wordfence configuration (from wfconfig DB table)
    $alert_emails     = '';
    $alert_email_list = [];
    $has_license      = false;
    $license_type     = '';
    $firewall_enabled = false;
    $firewall_mode    = '';
    $waf_status       = '';

    if ( $plugin_active ) {
        // — Alert emails: reuse existing check_wordfence_notification_email()
        if ( function_exists( __NAMESPACE__ . '\\check_wordfence_notification_email' ) ) {
            $wf_email_check = check_wordfence_notification_email();
            if ( ! empty( $wf_email_check['status'] ) ) {
                $alert_emails = $wf_email_check['details'] ?? $wf_email_check['raw_value'] ?? '';
            }
        }

        // — Read Wordfence config from its DB table
        global $wpdb;
        $wf_table = $wpdb->prefix . 'wfconfig';

        // — License key check
        $api_key = $wpdb->get_var( $wpdb->prepare(
            "SELECT `val` FROM `{$wf_table}` WHERE `name` = %s", 'apiKey'
        ) );
        $has_license = ! empty( $api_key ) && strlen( $api_key ) > 10;

        // — License type (free vs premium)
        $is_premium = $wpdb->get_var( $wpdb->prepare(
            "SELECT `val` FROM `{$wf_table}` WHERE `name` = %s", 'isPaid'
        ) );
        $license_type = $is_premium ? 'Premium' : 'Free';

        // — Firewall mode
        $waf_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT `val` FROM `{$wf_table}` WHERE `name` = %s", 'wafStatus'
        ) );
        $firewall_enabled = ! empty( $waf_status ) && $waf_status !== 'disabled';
        $firewall_mode = $waf_status === 'enabled' ? 'Extended Protection' : ( $waf_status === 'learning-mode' ? 'Learning Mode' : ucfirst( $waf_status ?: 'Unknown' ) );
    }

    // — Determine overall panel health
    //   Red: not installed or not active
    //   Yellow: active but missing alerts email or firewall disabled
    //   Green: all good
    $panel_class = 'hws-panel';
    if ( ! $plugin_active ) {
        $panel_class .= ' panel-needs-attention';
    } elseif ( empty( $alert_emails ) || ! $firewall_enabled ) {
        $panel_class .= ' panel-warning';
    }
    ?>
    <div class="<?php echo esc_attr( $panel_class ); ?>">
        <div class="hws-panel-header">🛡️ Wordfence Security</div>
        <div class="hws-panel-body">
            <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:start;">

                <!-- Status Icon -->
                <div style="text-align:center;">
                    <?php if ( $plugin_active && $firewall_enabled && ! empty( $alert_emails ) ) : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#d4edda;display:flex;align-items:center;justify-content:center;font-size:28px;">🛡️</div>
                        <div style="font-size:11px;color:#00a32a;margin-top:4px;">Protected</div>
                    <?php elseif ( $plugin_active ) : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#fff3cd;display:flex;align-items:center;justify-content:center;font-size:28px;">⚠️</div>
                        <div style="font-size:11px;color:#dba617;margin-top:4px;">Needs Setup</div>
                    <?php else : ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:#f8d7da;display:flex;align-items:center;justify-content:center;font-size:28px;">❌</div>
                        <div style="font-size:11px;color:#d63638;margin-top:4px;"><?php echo $plugin_installed ? 'Inactive' : 'Not Installed'; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Status Grid -->
                <div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">

                        <!-- Plugin Status -->
                        <div style="padding:10px 14px;background:<?php echo $plugin_active ? '#f8f9fa' : 'rgba(214,54,56,0.06)'; ?>;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">Wordfence Plugin</div>
                            <?php if ( $plugin_active ) : ?>
                                <span style="color:#00a32a;font-size:13px;">✅ Active</span>
                            <?php elseif ( $plugin_installed ) : ?>
                                <span style="color:#dba617;font-size:13px;">⚠️ Installed but not active</span>
                                <div style="margin-top:5px;">
                                    <button type="button" class="hws-btn" style="font-size:11px;padding:4px 10px;" onclick="hwsActivatePlugin('wordfence/wordfence.php', this);">Activate Now</button>
                                </div>
                            <?php else : ?>
                                <span style="color:#d63638;font-size:13px;">❌ Not installed</span>
                                <div style="margin-top:5px;">
                                    <button type="button" class="hws-btn" style="font-size:11px;padding:4px 10px;" onclick="hwsInstallPlugin('wordfence', this);">Install & Activate</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Firewall Status -->
                        <div style="padding:10px 14px;background:<?php echo ( $plugin_active && ! $firewall_enabled ) ? 'rgba(214,54,56,0.06)' : '#f8f9fa'; ?>;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">Firewall Protection</div>
                            <?php if ( $plugin_active ) : ?>
                                <?php if ( $firewall_enabled ) : ?>
                                    <span style="color:#00a32a;font-size:13px;">✅ <?php echo esc_html( $firewall_mode ); ?></span>
                                <?php else : ?>
                                    <span style="color:#d63638;font-size:13px;">❌ Disabled</span>
                                    <div style="font-size:11px;color:#d63638;margin-top:2px;">Enable the WAF in Wordfence → Firewall</div>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>

                        <!-- License Key -->
                        <div style="padding:10px 14px;background:#f8f9fa;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">License</div>
                            <?php if ( $plugin_active ) : ?>
                                <?php if ( $has_license ) : ?>
                                    <span style="color:#00a32a;font-size:13px;">✅ <?php echo esc_html( $license_type ); ?></span>
                                <?php else : ?>
                                    <span style="color:#dba617;font-size:13px;">⚠️ No license key</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>

                        <!-- Security Alerts Email -->
                        <div style="padding:10px 14px;background:<?php echo ( $plugin_active && empty( $alert_emails ) ) ? 'rgba(219,166,23,0.08)' : '#f8f9fa'; ?>;border-radius:6px;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">Security Alert Emails</div>
                            <?php if ( $plugin_active ) : ?>
                                <?php if ( ! empty( $alert_emails ) ) : ?>
                                    <span style="color:#00a32a;font-size:13px;">✅ Active</span>
                                    <div style="font-size:11px;color:#646970;margin-top:2px;">
                                        <code style="font-size:11px;"><?php echo esc_html( $alert_emails ); ?></code>
                                    </div>
                                <?php else : ?>
                                    <span style="color:#dba617;font-size:13px;">⚠️ No alert email configured</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#999;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if ( $plugin_active ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=WordfenceOptions' ) ); ?>" class="hws-btn" style="text-decoration:none;" target="_blank">⚙️ Wordfence Settings</a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=WordfenceOptions#wf-option-alertEmails' ) ); ?>" class="hws-btn" style="text-decoration:none;background:#2271b1;border-color:#2271b1;" target="_blank">📧 Alert Email Settings</a>
                            <a href="https://www.wordfence.com/manage-wordfence-api-keys/" class="hws-btn hws-btn-secondary" style="text-decoration:none;" target="_blank">🔑 Manage License Key</a>
                        <?php endif; ?>
                    </div>

                    <!-- Setup Instructions (reusable instruction box) -->
                    <?php echo hws_render_instructions(
                        'Wordfence Setup Instructions',
                        [
                            'Install and activate Wordfence from the Plugins tab or Quick Setup.',
                            'During initial setup wizard, <strong>select the Free version</strong>.',
                            'Send your free license key to <code>contact+wordfence@michaelperes.com</code>',
                            'When prompted <em>"Would you like WordPress security and vulnerability alerts sent to you via email?"</em> — select <strong>Yes</strong>.',
                            'Set the alert email to <code>contact@michaelperes.com</code>',
                            'Verify the email address when the confirmation email arrives.',
                            'Confirm the Firewall is enabled under <strong>Wordfence → Firewall</strong>.',
                        ],
                        '🛡️'
                    ); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}


function render_tab_brand_assets() {
    render_site_icon_panel();
    render_brand_logo_assets_panel();
}

function hws_asset_external_link( string $url ): string {
    if ( '' === $url ) {
        return '<span style="color:#8c8f94;">Not set</span>';
    }

    return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" style="display:inline-flex;gap:5px;align-items:center;max-width:100%;"><code style="white-space:normal;word-break:break-all;">' . esc_html( $url ) . '</code><span aria-hidden="true">↗</span></a>';
}

function hws_get_brand_asset_payload( string $key ): array {
    $key           = function_exists( __NAMESPACE__ . '\\hws_normalize_brand_asset_key' ) ? hws_normalize_brand_asset_key( $key ) : sanitize_key( $key );
    $definition    = hws_get_brand_asset_definition( $key );
    $attachment_id = hws_get_brand_asset_attachment_id( $key );
    $full_url      = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
    $thumb_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

    return [
        'key'           => $key,
        'label'         => $definition['label'] ?? $key,
        'attachment_id' => $attachment_id,
        'url'           => $full_url ?: '',
        'thumbnail_url' => $thumb_url ?: ( $full_url ?: '' ),
        'shortcodes'    => [
            'image' => '[site_logo key="' . $key . '" size="medium"]',
            'url'   => '[site_logo key="' . $key . '" size="full" output="url"]',
            'custom_size' => '[site_logo key="' . $key . '" size="300x120"]',
        ],
    ];
}

function render_site_icon_panel() {
    $has_icon       = has_site_icon();
    $icon_id        = (int) get_option( 'site_icon', 0 );
    $icon_url       = $has_icon ? get_site_icon_url( 512 ) : '';
    $favicon_path   = ABSPATH . 'favicon.ico';
    $favicon_exists = file_exists( $favicon_path );
    $favicon_size   = $favicon_exists ? size_format( filesize( $favicon_path ) ) : '';
    $favicon_url    = home_url( '/favicon.ico' );
    $letter         = strtoupper( substr( sanitize_title( get_bloginfo( 'name' ) ), 0, 1 ) ?: 'H' );
    ?>
    <div class="hws-panel" id="hws-brand-favicon-panel">
        <div class="hws-panel-header">Site Icon / Favicon</div>
        <div class="hws-panel-body">
            <div style="display:grid;grid-template-columns:120px minmax(0,1fr);gap:20px;align-items:start;">
                <div>
                    <?php if ( $icon_url ) : ?>
                        <img id="hws-favicon-preview" src="<?php echo esc_url( $icon_url ); ?>" alt="Site icon preview" style="width:96px;height:96px;object-fit:contain;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;">
                    <?php else : ?>
                        <div id="hws-favicon-preview" style="width:96px;height:96px;display:flex;align-items:center;justify-content:center;border:1px dashed #b8bcc2;border-radius:6px;background:#f6f7f7;color:#646970;">No icon</div>
                    <?php endif; ?>
                    <div style="font-size:11px;color:#646970;margin-top:6px;">WordPress Site Icon</div>
                </div>

                <div>
                    <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:12px;margin-bottom:14px;">
                        <div style="padding:14px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;">
                            <strong style="display:block;margin-bottom:10px;">Current favicon files</strong>
                            <div style="display:grid;gap:12px;">
                                <div>
                                    <label style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;">Uploaded PNG source</label>
                                    <div id="hws-favicon-png-url"><?php echo hws_asset_external_link( $icon_url ); ?></div>
                                    <div style="font-size:12px;color:#646970;margin-top:6px;">Attachment ID: <span id="hws-favicon-attachment-id"><?php echo $icon_id ? (int) $icon_id : 'none'; ?></span></div>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;">Physical ICO file</label>
                                    <div id="hws-favicon-ico-url"><?php echo $favicon_exists ? hws_asset_external_link( $favicon_url ) : '<span style="color:#d63638;">Missing</span>'; ?></div>
                                    <div id="hws-favicon-ico-meta" style="font-size:12px;color:#646970;margin-top:6px;"><?php echo $favicon_exists ? 'Exists: ' . esc_html( $favicon_size ) : 'Generated at /favicon.ico'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <button type="button" id="hws-upload-favicon" class="button button-primary">Upload / Replace Site Icon PNG</button>
                        <button type="button" id="hws-copy-favicon" class="button">Create / Update .ico from PNG</button>
                        <a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ); ?>" class="button" target="_blank" rel="noopener">Open WP Site Icon ↗</a>
                    </div>

                    <div style="margin-top:14px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;">
                        <strong style="display:block;margin-bottom:8px;">Generate favicon from one letter</strong>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="hws-favicon-letter" maxlength="1" style="width:42px;text-align:center;text-transform:uppercase;font-weight:700;" value="<?php echo esc_attr( $letter ); ?>">
                            <input type="text" id="hws-favicon-bg" value="#111827" style="width:90px;" aria-label="Background color">
                            <input type="text" id="hws-favicon-fg" value="#ffffff" style="width:90px;" aria-label="Foreground color">
                            <button type="button" id="hws-create-letter-favicon" class="button">Generate PNG + ICO</button>
                        </div>
                    </div>

                    <div id="hws-favicon-status" style="font-size:13px;margin-top:10px;" aria-live="polite"></div>
                    <p style="font-size:12px;color:#646970;margin:10px 0 0;">Uploaded favicon images are center-cropped to a clean 512x512 PNG before syncing to WordPress Site Icon and generating the real <code>/favicon.ico</code>. Logo assets are managed separately below.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function render_brand_logo_assets_panel() {
    $definitions = function_exists( __NAMESPACE__ . '\\hws_get_brand_asset_definitions' ) ? hws_get_brand_asset_definitions() : [];
    ?>
    <div class="hws-panel" id="hws-brand-logo-panel">
        <div class="hws-panel-header">Logo Assets</div>
        <div class="hws-panel-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:14px;">
                <?php foreach ( $definitions as $key => $definition ) : ?>
                    <?php $payload = hws_get_brand_asset_payload( $key ); ?>
                    <div class="hws-brand-asset-card" data-brand-key="<?php echo esc_attr( $key ); ?>" style="border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:14px;">
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div class="hws-brand-preview-wrap" style="width:72px;min-width:72px;">
                                <?php if ( $payload['thumbnail_url'] ) : ?>
                                    <img class="hws-brand-preview" src="<?php echo esc_url( $payload['thumbnail_url'] ); ?>" alt="" style="width:72px;height:72px;object-fit:contain;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;">
                                <?php else : ?>
                                    <div class="hws-brand-preview-empty" style="width:72px;height:72px;display:flex;align-items:center;justify-content:center;border:1px dashed #b8bcc2;border-radius:6px;background:#f6f7f7;color:#646970;font-size:11px;">No file</div>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:0;flex:1;">
                                <strong class="hws-brand-label" style="display:block;font-size:14px;margin-bottom:4px;"><?php echo esc_html( $definition['label'] ); ?></strong>
                                <div style="font-size:12px;color:#646970;margin-bottom:8px;"><?php echo esc_html( $definition['description'] ); ?></div>
                                <?php if ( ! empty( $definition['core'] ) ) : ?>
                                    <div style="font-size:11px;color:#2271b1;margin-bottom:8px;">Syncs to: WordPress Custom Logo</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top:10px;">
                            <label style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;">URL</label>
                            <div class="hws-brand-url"><?php echo hws_asset_external_link( $payload['url'] ); ?></div>
                        </div>

                        <div style="margin-top:10px;display:grid;gap:5px;font-size:12px;">
                            <code><?php echo esc_html( $payload['shortcodes']['image'] ); ?></code>
                            <code><?php echo esc_html( $payload['shortcodes']['url'] ); ?></code>
                            <code><?php echo esc_html( $payload['shortcodes']['custom_size'] ); ?></code>
                        </div>

                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;">
                            <button type="button" class="button hws-brand-upload" data-brand-key="<?php echo esc_attr( $key ); ?>">Upload / Replace</button>
                            <button type="button" class="button hws-brand-clear" data-brand-key="<?php echo esc_attr( $key ); ?>" <?php disabled( ! $payload['attachment_id'] ); ?>>Clear</button>
                            <span class="hws-brand-status" style="font-size:12px;" aria-live="polite"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var faviconPreviewStyle = 'width:96px;height:96px;object-fit:contain;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;';
        var brandPreviewStyle = 'width:72px;height:72px;object-fit:contain;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;';
        var brandEmptyPreview = '<div class="hws-brand-preview-empty" style="width:72px;height:72px;display:flex;align-items:center;justify-content:center;border:1px dashed #b8bcc2;border-radius:6px;background:#f6f7f7;color:#646970;font-size:11px;">No file</div>';

        function escapeText(value) {
            return $('<div>').text(value || '').html();
        }

        function setStatus($target, message, ok) {
            $target.html('<span style="color:' + (ok ? '#00a32a' : '#d63638') + ';">' + escapeText(message) + '</span>');
        }

        function assetExternalLink(url) {
            if (!url) {
                return '<span style="color:#8c8f94;">Not set</span>';
            }

            var escapedUrl = escapeText(url);
            return '<a href="' + escapedUrl + '" target="_blank" rel="noopener" style="display:inline-flex;gap:5px;align-items:center;max-width:100%;"><code style="white-space:normal;word-break:break-all;">' + escapedUrl + '</code><span aria-hidden="true">↗</span></a>';
        }

        function updateFaviconPreview(url) {
            if (!url) {
                return;
            }

            var $preview = $('#hws-favicon-preview');
            var $img = $('<img>', {
                id: 'hws-favicon-preview',
                src: url,
                alt: 'Site icon preview'
            }).attr('style', faviconPreviewStyle);

            if ($preview.length) {
                $preview.replaceWith($img);
            }
        }

        function updateFaviconPanel(data) {
            if (!data) {
                return;
            }

            if (data.icon_url !== undefined) {
                $('#hws-favicon-png-url').html(assetExternalLink(data.icon_url));
                updateFaviconPreview(data.icon_url);
            }

            if (data.favicon_url !== undefined) {
                $('#hws-favicon-ico-url').html(assetExternalLink(data.favicon_url));
            }

            if (data.attachment_id !== undefined) {
                $('#hws-favicon-attachment-id').text(data.attachment_id ? data.attachment_id : 'none');
            }

            if (data.favicon_size !== undefined) {
                $('#hws-favicon-ico-meta').text(data.favicon_size ? 'Exists: ' + data.favicon_size : 'Generated at /favicon.ico');
            }
        }

        function updateBrandCard($card, data) {
            if (!data) {
                return;
            }

            if (data.key) {
                $card.attr('data-brand-key', data.key);
                $card.find('.hws-brand-upload,.hws-brand-clear').attr('data-brand-key', data.key).data('brand-key', data.key);
            }

            if (data.url !== undefined) {
                $card.find('.hws-brand-url').html(assetExternalLink(data.url));
            }

            if (data.thumbnail_url || data.url) {
                var $img = $('<img>', {
                    class: 'hws-brand-preview',
                    src: data.thumbnail_url || data.url,
                    alt: ''
                }).attr('style', brandPreviewStyle);
                $card.find('.hws-brand-preview-wrap').html($img);
            } else if (data.url !== undefined || data.attachment_id === 0) {
                $card.find('.hws-brand-preview-wrap').html(brandEmptyPreview);
            }

            if (data.attachment_id !== undefined) {
                $card.find('.hws-brand-clear').prop('disabled', !data.attachment_id);
            }
        }

        $('#hws-upload-favicon').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({ title: 'Select Site Icon PNG', button: { text: 'Use as Site Icon' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#hws-favicon-status').text('Uploading and cropping...');
                $.post(ajaxurl, { action: 'hws_copy_favicon', nonce: hwsNonce, source: 'upload', attachment_id: attachment.id }, function(response) {
                    if (response && response.success) {
                        setStatus($('#hws-favicon-status'), response.data.message, true);
                        updateFaviconPanel(response.data);
                    } else {
                        setStatus($('#hws-favicon-status'), response && response.data ? response.data : 'Upload failed', false);
                    }
                }, 'json');
            });
            frame.open();
        });

        $('#hws-copy-favicon').on('click', function() {
            $('#hws-favicon-status').text('Creating ICO...');
            $.post(ajaxurl, { action: 'hws_copy_favicon', nonce: hwsNonce, source: 'site_icon' }, function(response) {
                if (response && response.success) {
                    setStatus($('#hws-favicon-status'), response.data.message, true);
                    updateFaviconPanel(response.data);
                } else {
                    setStatus($('#hws-favicon-status'), response && response.data ? response.data : 'ICO creation failed', false);
                }
            }, 'json');
        });

        $('#hws-create-letter-favicon').on('click', function() {
            $('#hws-favicon-status').text('Generating...');
            $.post(ajaxurl, {
                action: 'hws_copy_favicon',
                nonce: hwsNonce,
                source: 'letter',
                letter: $('#hws-favicon-letter').val() || 'H',
                background: $('#hws-favicon-bg').val() || '#111827',
                foreground: $('#hws-favicon-fg').val() || '#ffffff'
            }, function(response) {
                if (response && response.success) {
                    setStatus($('#hws-favicon-status'), response.data.message, true);
                    updateFaviconPanel(response.data);
                } else {
                    setStatus($('#hws-favicon-status'), response && response.data ? response.data : 'Generation failed', false);
                }
            }, 'json');
        });

        $('.hws-brand-upload').on('click', function(e) {
            e.preventDefault();
            var key = $(this).data('brand-key');
            var $card = $(this).closest('.hws-brand-asset-card');
            var frame = wp.media({ title: 'Select Logo Asset', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                setStatus($card.find('.hws-brand-status'), 'Saving...', true);
                $.post(ajaxurl, { action: 'hws_save_brand_asset', nonce: hwsNonce, key: key, attachment_id: attachment.id }, function(response) {
                    if (response && response.success) {
                        setStatus($card.find('.hws-brand-status'), 'Saved.', true);
                        updateBrandCard($card, response.data);
                    } else {
                        setStatus($card.find('.hws-brand-status'), response && response.data ? response.data : 'Save failed.', false);
                    }
                }, 'json');
            });
            frame.open();
        });

        $('.hws-brand-clear').on('click', function() {
            var key = $(this).data('brand-key');
            var $card = $(this).closest('.hws-brand-asset-card');
            setStatus($card.find('.hws-brand-status'), 'Clearing...', true);
            $.post(ajaxurl, { action: 'hws_clear_brand_asset', nonce: hwsNonce, key: key }, function(response) {
                if (response && response.success) {
                    setStatus($card.find('.hws-brand-status'), 'Cleared.', true);
                    updateBrandCard($card, response.data);
                } else {
                    setStatus($card.find('.hws-brand-status'), response && response.data ? response.data : 'Clear failed.', false);
                }
            }, 'json');
        });
    });
    </script>
    <?php
}


/**
 * AJAX: Copy site icon to /favicon.ico or set a new icon from upload
 */
function hws_brand_asset_requires_square_crop( string $key ): bool {
    $key = function_exists( __NAMESPACE__ . '\\hws_normalize_brand_asset_key' ) ? hws_normalize_brand_asset_key( $key ) : sanitize_key( $key );
    return in_array( $key, [ 'logo_1x1', 'logo_dark_1x1' ], true );
}

function hws_create_square_brand_asset_attachment( int $source_attachment_id, string $key ) {
    $source_path = get_attached_file( $source_attachment_id );
    if ( ! $source_path || ! file_exists( $source_path ) ) {
        return new \WP_Error( 'hws_brand_asset_source_missing', 'Could not locate the selected image file.' );
    }

    $editor = wp_get_image_editor( $source_path );
    if ( is_wp_error( $editor ) ) {
        return $editor;
    }

    $resized = $editor->resize( 512, 512, true );
    if ( is_wp_error( $resized ) ) {
        return $resized;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['error'] ) ) {
        return new \WP_Error( 'hws_brand_asset_upload_dir', $uploads['error'] );
    }

    $filename = wp_unique_filename( $uploads['path'], 'brand-' . sanitize_file_name( $key ) . '-' . time() . '.png' );
    $dest     = trailingslashit( $uploads['path'] ) . $filename;
    $saved    = $editor->save( $dest, 'image/png' );

    if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
        return new \WP_Error( 'hws_brand_asset_save_failed', 'Could not save the cropped PNG asset.' );
    }

    $attachment_id = wp_insert_attachment(
        [
            'guid'           => trailingslashit( $uploads['url'] ) . basename( $saved['path'] ),
            'post_mime_type' => 'image/png',
            'post_title'     => 'Brand ' . str_replace( '_', ' ', sanitize_key( $key ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ],
        $saved['path']
    );

    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $saved['path'] ) );

    return (int) $attachment_id;
}

function hws_sync_brand_asset_to_wp_core( string $key, int $attachment_id ): string {
    $definition = hws_get_brand_asset_definition( $key );
    $core       = $definition['core'] ?? '';

    if ( $core === 'custom_logo' ) {
        set_theme_mod( 'custom_logo', $attachment_id );
        return 'Synced to WordPress Custom Logo.';
    }

    return 'Saved.';
}

function ajax_save_brand_asset() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $key           = isset( $_POST['key'] ) ? hws_normalize_brand_asset_key( (string) wp_unslash( $_POST['key'] ) ) : '';
    $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
    $definition    = hws_get_brand_asset_definition( $key );

    if ( ! $definition ) {
        wp_send_json_error( 'Unknown brand asset slot.' );
    }

    if ( ! $attachment_id || get_post_type( $attachment_id ) !== 'attachment' ) {
        wp_send_json_error( 'Select a valid image attachment.' );
    }

    $stored_attachment_id = $attachment_id;
    if ( hws_brand_asset_requires_square_crop( $key ) ) {
        $cropped = hws_create_square_brand_asset_attachment( $attachment_id, $key );
        if ( is_wp_error( $cropped ) ) {
            wp_send_json_error( $cropped->get_error_message() );
        }
        $stored_attachment_id = (int) $cropped;
    }

    update_option( $definition['option'], $stored_attachment_id );
    $message = hws_sync_brand_asset_to_wp_core( $key, $stored_attachment_id );

    $payload = hws_get_brand_asset_payload( $key );
    $payload['message'] = $message;

    wp_send_json_success( $payload );
}

function ajax_clear_brand_asset() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $key        = isset( $_POST['key'] ) ? hws_normalize_brand_asset_key( (string) wp_unslash( $_POST['key'] ) ) : '';
    $definition = hws_get_brand_asset_definition( $key );

    if ( ! $definition ) {
        wp_send_json_error( 'Unknown brand asset slot.' );
    }

    $attachment_id = hws_get_brand_asset_attachment_id( $key );
    delete_option( $definition['option'] );
    foreach ( (array) ( $definition['legacy_options'] ?? [] ) as $legacy_option ) {
        delete_option( $legacy_option );
    }

    if ( ( $definition['core'] ?? '' ) === 'custom_logo' && (int) get_theme_mod( 'custom_logo' ) === $attachment_id ) {
        remove_theme_mod( 'custom_logo' );
    }

    $payload = hws_get_brand_asset_payload( $key );
    $payload['message'] = 'Cleared.';

    wp_send_json_success( $payload );
}

function ajax_copy_favicon() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';

    // — Source: upload — set as WP site icon + create resized favicon.ico
    if ( $source === 'upload' ) {
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        if ( ! $attachment_id ) {
            wp_send_json_error( 'No attachment selected' );
        }

        $cropped = hws_create_square_brand_asset_attachment( $attachment_id, 'favicon' );
        if ( is_wp_error( $cropped ) ) {
            wp_send_json_error( $cropped->get_error_message() );
        }

        $attachment_id = (int) $cropped;
        update_option( 'site_icon', $attachment_id );

        $file_path = get_attached_file( $attachment_id );
        $result    = hws_create_resized_favicon( $file_path );

        if ( strpos( $result, 'Could not' ) !== false ) {
            wp_send_json_error( $result );
        }

        wp_send_json_success( [
            'message'       => 'Site icon PNG uploaded and cropped. ' . $result,
            'attachment_id' => $attachment_id,
            'icon_url'      => wp_get_attachment_image_url( $attachment_id, 'full' ),
            'favicon_url'   => home_url( '/favicon.ico' ),
            'favicon_size'  => file_exists( ABSPATH . 'favicon.ico' ) ? size_format( filesize( ABSPATH . 'favicon.ico' ) ) : '',
        ] );
        return;
    }

    // — Source: site_icon — resize existing WP site icon to /favicon.ico
    if ( $source === 'site_icon' ) {
        $icon_id = (int) get_option( 'site_icon', 0 );
        if ( ! $icon_id ) {
            wp_send_json_error( 'No WordPress site icon is set. Set one first via Customizer.' );
        }

        // — Get the original file path
        $file_path = get_attached_file( $icon_id );

        // — Fallback: download from URL if local file missing
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            $icon_url = get_site_icon_url( 512 );
            if ( ! $icon_url ) {
                wp_send_json_error( 'Could not locate site icon file' );
            }

            $tmp = download_url( $icon_url, 10 );
            if ( is_wp_error( $tmp ) ) {
                wp_send_json_error( 'Could not download site icon: ' . $tmp->get_error_message() );
            }
            $file_path = $tmp;
        }

        $result = hws_create_resized_favicon( $file_path );

        // — Clean up temp file if we downloaded
        if ( isset( $tmp ) && file_exists( $tmp ) ) @unlink( $tmp );

        if ( strpos( $result, 'error' ) !== false || strpos( $result, 'Could not' ) !== false ) {
            wp_send_json_error( $result );
        }
        wp_send_json_success( [
            'message'       => $result,
            'attachment_id' => $icon_id,
            'icon_url'      => get_site_icon_url( 512 ),
            'favicon_url'   => home_url( '/favicon.ico' ),
            'favicon_size'  => file_exists( ABSPATH . 'favicon.ico' ) ? size_format( filesize( ABSPATH . 'favicon.ico' ) ) : '',
        ] );
        return;
    }

    if ( $source === 'letter' ) {
        $letter     = isset( $_POST['letter'] ) ? sanitize_text_field( wp_unslash( $_POST['letter'] ) ) : '';
        $background = isset( $_POST['background'] ) ? sanitize_hex_color( wp_unslash( $_POST['background'] ) ) : '#111827';
        $foreground = isset( $_POST['foreground'] ) ? sanitize_hex_color( wp_unslash( $_POST['foreground'] ) ) : '#ffffff';

        $result = hws_create_letter_site_icon( $letter, $background ?: '#111827', $foreground ?: '#ffffff' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( [
            'message'       => 'Letter icon created. ' . $result['message'],
            'attachment_id' => $result['attachment_id'],
            'icon_url'      => $result['icon_url'],
            'favicon_url'   => home_url( '/favicon.ico' ),
            'favicon_size'  => file_exists( ABSPATH . 'favicon.ico' ) ? size_format( filesize( ABSPATH . 'favicon.ico' ) ) : '',
        ] );
        return;
    }

    wp_send_json_error( 'Unknown source type' );
}


/**
 * Create a real ICO file at /favicon.ico from a source image.
 *
 * @param string|null $source_path Path to the source image.
 * @return string Status message.
 */
function hws_create_resized_favicon( ?string $source_path ): string {
    if ( ! $source_path || ! file_exists( $source_path ) ) {
        return 'Could not locate source image file';
    }

    $dest      = ABSPATH . 'favicon.ico';
    $tmp_files = [];
    $entries   = [];

    foreach ( [ 256, 32 ] as $size ) {
        $editor = wp_get_image_editor( $source_path );
        if ( is_wp_error( $editor ) ) {
            hws_delete_temp_files( $tmp_files );
            return 'Could not create /favicon.ico — image editor unavailable: ' . $editor->get_error_message();
        }

        $resized = $editor->resize( $size, $size, true );
        if ( is_wp_error( $resized ) ) {
            hws_delete_temp_files( $tmp_files );
            return 'Could not resize favicon image — ' . $resized->get_error_message();
        }

        $editor->set_quality( 90 );
        $tmp_file = trailingslashit( get_temp_dir() ) . 'hws-favicon-' . $size . '-' . wp_generate_password( 8, false ) . '.png';
        $saved    = $editor->save( $tmp_file, 'image/png' );

        if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
            hws_delete_temp_files( $tmp_files );
            return 'Could not save resized favicon image.';
        }

        $tmp_files[] = $saved['path'];
        $entries[]   = [
            'width'  => $size,
            'height' => $size,
            'data'   => file_get_contents( $saved['path'] ),
        ];
    }

    $ico = hws_build_ico_file_bytes( $entries );
    hws_delete_temp_files( $tmp_files );

    if ( '' === $ico ) {
        return 'Could not build ICO data.';
    }

    if ( false === @file_put_contents( $dest, $ico ) ) {
        return 'Could not write to ' . ABSPATH . 'favicon.ico — check file permissions';
    }

    return '/favicon.ico created as ICO (' . size_format( filesize( $dest ) ) . ')';
}

function hws_build_ico_file_bytes( array $entries ): string {
    $entries = array_values( array_filter( $entries, function( $entry ) {
        return ! empty( $entry['width'] ) && ! empty( $entry['height'] ) && isset( $entry['data'] ) && $entry['data'] !== '';
    } ) );

    if ( empty( $entries ) ) {
        return '';
    }

    $count      = count( $entries );
    $header     = pack( 'vvv', 0, 1, $count );
    $directory  = '';
    $image_data = '';
    $offset     = 6 + ( 16 * $count );

    foreach ( $entries as $entry ) {
        $data   = (string) $entry['data'];
        $width  = (int) $entry['width'];
        $height = (int) $entry['height'];

        $directory .= pack(
            'CCCCvvVV',
            $width >= 256 ? 0 : $width,
            $height >= 256 ? 0 : $height,
            0,
            0,
            1,
            32,
            strlen( $data ),
            $offset
        );

        $image_data .= $data;
        $offset     += strlen( $data );
    }

    return $header . $directory . $image_data;
}

function hws_delete_temp_files( array $paths ): void {
    foreach ( $paths as $path ) {
        if ( is_string( $path ) && file_exists( $path ) ) {
            @unlink( $path );
        }
    }
}

function hws_create_letter_site_icon( string $letter, string $background = '#111827', string $foreground = '#ffffff' ) {
    $letter = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $letter ), 0, 1 ) );
    if ( '' === $letter ) {
        return new \WP_Error( 'hws_favicon_letter_invalid', 'Enter one letter or number.' );
    }

    if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
        return new \WP_Error( 'hws_favicon_gd_missing', 'PHP GD is required to generate a letter favicon.' );
    }

    $tmp_file = trailingslashit( get_temp_dir() ) . 'favicon-' . strtolower( $letter ) . '-' . time() . '.png';
    $image    = imagecreatetruecolor( 512, 512 );

    imagealphablending( $image, true );
    imagesavealpha( $image, true );

    $bg = hws_allocate_hex_color( $image, $background );
    $fg = hws_allocate_hex_color( $image, $foreground );

    imagefilledrectangle( $image, 0, 0, 512, 512, $bg );

    $font = hws_get_letter_icon_font_path();
    if ( $font && function_exists( 'imagettftext' ) ) {
        $font_size = 378;
        $box       = imagettfbbox( $font_size, 0, $font, $letter );
        $width     = abs( $box[2] - $box[0] );
        $height    = abs( $box[7] - $box[1] );
        $x         = (int) round( ( 512 - $width ) / 2 - $box[0] );
        $y         = (int) round( ( 512 - $height ) / 2 - $box[7] );

        imagettftext( $image, $font_size, 0, $x, $y, $fg, $font, $letter );
    } else {
        $font_size = 5;
        $width     = imagefontwidth( $font_size ) * strlen( $letter );
        $height    = imagefontheight( $font_size );
        imagestring( $image, $font_size, ( 512 - $width ) / 2, ( 512 - $height ) / 2, $letter, $fg );
    }

    if ( ! imagepng( $image, $tmp_file ) ) {
        imagedestroy( $image );
        return new \WP_Error( 'hws_favicon_png_failed', 'Could not create the letter icon PNG.' );
    }
    imagedestroy( $image );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file_array = [
        'name'     => 'favicon-' . strtolower( $letter ) . '-' . time() . '.png',
        'tmp_name' => $tmp_file,
    ];

    $attachment_id = media_handle_sideload( $file_array, 0, 'Generated favicon ' . $letter );

    if ( is_wp_error( $attachment_id ) ) {
        if ( file_exists( $tmp_file ) ) {
            @unlink( $tmp_file );
        }
        return $attachment_id;
    }

    update_option( 'site_icon', (int) $attachment_id );

    $source_path = get_attached_file( $attachment_id );
    $message     = hws_create_resized_favicon( $source_path );

    if ( strpos( $message, 'Could not' ) !== false ) {
        return new \WP_Error( 'hws_favicon_ico_failed', $message );
    }

    return [
        'attachment_id' => (int) $attachment_id,
        'icon_url'      => wp_get_attachment_url( $attachment_id ),
        'message'       => $message,
    ];
}

function hws_get_letter_icon_font_path(): string {
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
    ];

    foreach ( $candidates as $path ) {
        if ( is_readable( $path ) ) {
            return $path;
        }
    }

    return '';
}

function hws_allocate_hex_color( $image, string $hex ): int {
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
        $hex = '111827';
    }

    return (int) imagecolorallocate(
        $image,
        hexdec( substr( $hex, 0, 2 ) ),
        hexdec( substr( $hex, 2, 2 ) ),
        hexdec( substr( $hex, 4, 2 ) )
    );
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GOING LIVE CHECKLIST PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Verifies that all recommended snippets are active and essential plugins
 * are installed/activated. Shows green/red status for each item.
 *
 * @since 10.9.0
 */
function render_going_live_checklist() {
    // ═════════════════════════════════════════════════════════════════════
    // SECTION 1: Recommended Snippets
    // ═════════════════════════════════════════════════════════════════════
    $glc_snippets = function_exists( __NAMESPACE__ . '\hws_get_going_live_snippets' )
        ? hws_get_going_live_snippets() : [];

    $snippet_names = [];
    if ( function_exists( __NAMESPACE__ . '\get_snippets' ) ) {
        foreach ( [ '', 'admin', 'non_admin' ] as $type ) {
            foreach ( get_snippets( $type ) as $s ) {
                $snippet_names[ $s['id'] ] = $s['name'];
            }
        }
    }

    $snippet_statuses = [];
    $snippets_ok = 0;
    foreach ( $glc_snippets as $sid ) {
        $active = (bool) get_option( $sid, false );
        $snippet_statuses[] = [
            'id' => $sid, 'name' => $snippet_names[ $sid ] ?? $sid, 'active' => $active,
        ];
        if ( $active ) $snippets_ok++;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 2: Essential Plugins
    // ═════════════════════════════════════════════════════════════════════
    $monitored = function_exists( __NAMESPACE__ . '\hws_get_monitored_plugins' )
        ? hws_get_monitored_plugins() : [];
    $essential_plugins = [];
    $plugins_ok = 0;
    foreach ( $monitored as $path => $info ) {
        if ( ( $info['category'] ?? '' ) !== 'essential' ) continue;
        $active    = is_plugin_active( $path );
        $installed = file_exists( WP_PLUGIN_DIR . '/' . $path );
        $essential_plugins[] = [
            'name' => $info['name'], 'active' => $active,
            'installed' => $installed, 'pro' => $info['pro'] ?? false,
        ];
        if ( $active ) $plugins_ok++;
    }

    // ═════════════════════════════════════════════════════════════════════
    // SECTION 3: Settings & Server Checks
    // ═════════════════════════════════════════════════════════════════════
    $settings_checks = function_exists( __NAMESPACE__ . '\hws_get_glc_settings_checks' )
        ? hws_get_glc_settings_checks() : [];
    $settings_ok = count( array_filter( $settings_checks, fn( $c ) => $c['pass'] ) );

    // ═════════════════════════════════════════════════════════════════════
    // TOTALS
    // ═════════════════════════════════════════════════════════════════════
    $total_checks = count( $glc_snippets ) + count( $essential_plugins ) + count( $settings_checks );
    $total_ok     = $snippets_ok + $plugins_ok + $settings_ok;
    $all_good     = ( $total_ok === $total_checks );

    $panel_class = 'hws-panel';
    if ( ! $all_good ) {
        $panel_class .= ( $total_ok < $total_checks / 2 ) ? ' panel-needs-attention' : ' panel-warning';
    }
    ?>
    <div class="<?php echo esc_attr( $panel_class ); ?>">
        <div class="hws-panel-header">
            🚀 Going Live Checklist
            <span style="font-weight:400;font-size:12px;color:#646970;margin-left:8px;">
                (<?php echo $total_ok; ?>/<?php echo $total_checks; ?> ready)
            </span>
        </div>
        <div class="hws-panel-body">
            <?php if ( $all_good ) : ?>
                <p style="color:#00a32a;font-weight:600;font-size:14px;margin:0 0 12px;">✅ All checks passed — site is ready to go live!</p>
            <?php else : ?>
                <p style="color:#dba617;font-size:13px;margin:0 0 12px;">⚠️ <?php echo ( $total_checks - $total_ok ); ?> items need attention. Run <strong>Quick Setup</strong> below to fix what can be automated.</p>
            <?php endif; ?>

            <!-- ─── THREE-COLUMN GRID ─── -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

                <!-- Column 1: Recommended Snippets -->
                <div>
                    <h4 style="margin:0 0 8px;font-size:13px;color:#1d2327;border-bottom:2px solid #2271b1;padding-bottom:4px;">
                        📝 Snippets <span style="font-weight:400;color:#646970;">(<?php echo $snippets_ok; ?>/<?php echo count($glc_snippets); ?>)</span>
                    </h4>
                    <?php foreach ( $snippet_statuses as $ss ) : ?>
                        <div style="padding:4px 0;font-size:12px;border-bottom:1px solid #f0f0f0;">
                            <?php echo $ss['active'] ? '✅' : '❌'; ?>
                            <span style="color:<?php echo $ss['active'] ? '#1d2327' : '#d63638'; ?>;">
                                <?php echo esc_html( $ss['name'] ); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Column 2: Essential Plugins -->
                <div>
                    <h4 style="margin:0 0 8px;font-size:13px;color:#1d2327;border-bottom:2px solid #2271b1;padding-bottom:4px;">
                        🔌 Plugins <span style="font-weight:400;color:#646970;">(<?php echo $plugins_ok; ?>/<?php echo count($essential_plugins); ?>)</span>
                    </h4>
                    <?php foreach ( $essential_plugins as $ep ) : ?>
                        <div style="padding:4px 0;font-size:12px;border-bottom:1px solid #f0f0f0;">
                            <?php echo $ep['active'] ? '✅' : '❌'; ?>
                            <span style="color:<?php echo $ep['active'] ? '#1d2327' : '#d63638'; ?>;">
                                <?php echo esc_html( $ep['name'] ); ?>
                            </span>
                            <?php if ( $ep['pro'] ) : ?>
                                <span style="background:#8c5e00;color:#fff;font-size:9px;padding:1px 4px;border-radius:3px;margin-left:3px;">PRO</span>
                            <?php endif; ?>
                            <?php if ( ! $ep['installed'] && ! $ep['pro'] ) : ?>
                                <span style="color:#d63638;font-size:10px;margin-left:3px;">(missing)</span>
                            <?php elseif ( ! $ep['active'] && $ep['installed'] ) : ?>
                                <span style="color:#dba617;font-size:10px;margin-left:3px;">(inactive)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Column 3: Settings & Server Checks -->
                <div>
                    <h4 style="margin:0 0 8px;font-size:13px;color:#1d2327;border-bottom:2px solid #2271b1;padding-bottom:4px;">
                        ⚙️ Settings & Server <span style="font-weight:400;color:#646970;">(<?php echo $settings_ok; ?>/<?php echo count($settings_checks); ?>)</span>
                    </h4>
                    <?php foreach ( $settings_checks as $chk ) : ?>
                        <div style="padding:4px 0;font-size:12px;border-bottom:1px solid #f0f0f0;display:flex;align-items:flex-start;gap:4px;" title="<?php echo esc_attr( $chk['value'] ); ?>">
                            <span style="flex-shrink:0;"><?php echo $chk['pass'] ? '✅' : '❌'; ?></span>
                            <span style="color:<?php echo $chk['pass'] ? '#1d2327' : '#d63638'; ?>;">
                                <?php echo esc_html( $chk['label'] ); ?>
                                <?php if ( ! $chk['pass'] ) : ?>
                                    <span style="color:#999;font-size:10px;display:block;"><?php echo esc_html( $chk['value'] ); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PHP & SERVER EXTENSIONS PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Displays all PHP extensions important for WordPress with loaded status.
 * Required extensions shown in red if missing, recommended in yellow.
 *
 * @since 10.9.0
 */
function render_php_extensions_panel() {
    // — Get extension data from helper function
    $extensions = function_exists( __NAMESPACE__ . '\\hws_check_php_extensions' )
        ? hws_check_php_extensions()
        : [];

    if ( empty( $extensions ) ) return;

    // — Count stats
    $total    = count( $extensions );
    $loaded   = count( array_filter( $extensions, fn( $e ) => $e['loaded'] ) );
    $missing_required = count( array_filter( $extensions, fn( $e ) => $e['required'] && ! $e['loaded'] ) );

    // — Panel health
    $panel_class = 'hws-panel';
    if ( $missing_required > 0 ) {
        $panel_class .= ' panel-needs-attention';
    }
    ?>
    <div class="<?php echo esc_attr( $panel_class ); ?>">
        <div class="hws-panel-header">🖥️ PHP & Server Extensions <span style="font-weight:400;font-size:12px;color:#646970;margin-left:8px;">(<?php echo $loaded; ?>/<?php echo $total; ?> loaded)</span></div>
        <div class="hws-panel-body">
            <p style="font-size:13px;color:#646970;margin:0 0 12px;">
                PHP <?php echo phpversion(); ?> — Extensions required and recommended for WordPress.
                <?php if ( $missing_required > 0 ) : ?>
                    <span style="color:#d63638;font-weight:600;"><?php echo $missing_required; ?> required extension(s) missing!</span>
                <?php endif; ?>
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:6px;">
                <?php foreach ( $extensions as $ext ) :
                    // — Color coding: red if required+missing, yellow if optional+missing, green if loaded
                    $bg    = $ext['loaded'] ? '#f8f9fa' : ( $ext['required'] ? 'rgba(214,54,56,0.06)' : 'rgba(219,166,23,0.06)' );
                    $color = $ext['loaded'] ? '#00a32a' : ( $ext['required'] ? '#d63638' : '#dba617' );
                    $icon  = $ext['loaded'] ? '✅' : ( $ext['required'] ? '❌' : '⚠️' );
                ?>
                    <div style="padding:6px 10px;background:<?php echo $bg; ?>;border-radius:4px;font-size:12px;display:flex;align-items:center;gap:6px;">
                        <span><?php echo $icon; ?></span>
                        <code style="font-size:11px;font-weight:600;"><?php echo esc_html( $ext['name'] ); ?></code>
                        <span style="color:#646970;font-size:11px;">— <?php echo esc_html( $ext['purpose'] ); ?></span>
                        <?php if ( $ext['required'] ) : ?>
                            <span style="background:#d63638;color:#fff;font-size:9px;padding:1px 4px;border-radius:2px;margin-left:auto;">REQ</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LITESPEED CACHE STATUS PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Displays detailed LiteSpeed Cache configuration: page cache, CSS/JS
 * optimization, Redis object cache, Brotli compression. Links directly
 * to the relevant LiteSpeed settings pages.
 *
 * @since 10.9.1
 */
function render_litespeed_panel() {
    // — Get LiteSpeed info from helper (returns false if plugin not active)
    $ls = function_exists( __NAMESPACE__ . '\hws_get_litespeed_info' )
        ? hws_get_litespeed_info()
        : false;

    if ( $ls === false ) {
        ?>
        <div class="hws-panel panel-needs-attention">
            <div class="hws-panel-header">⚡ LiteSpeed Cache</div>
            <div class="hws-panel-body">
                <p style="color:#d63638;">❌ LiteSpeed Cache plugin is not active.</p>
            </div>
        </div>
        <?php
        return;
    }

    // — Get Redis status (with full null-safety)
    $redis = function_exists( __NAMESPACE__ . '\hws_check_redis_status' )
        ? hws_check_redis_status()
        : [];
    $r_active    = $redis['active'] ?? false;
    $r_connected = $redis['connected'] ?? false;
    $r_extension = $redis['extension'] ?? false;
    $r_ls_on     = $redis['litespeed_enabled'] ?? false;
    $r_error     = $redis['error'] ?? '';
    $r_info      = $redis['info'] ?? [];

    // — Get Brotli status
    $brotli = function_exists( __NAMESPACE__ . '\hws_check_brotli_support' )
        ? hws_check_brotli_support()
        : [ 'enabled' => false, 'details' => '' ];

    // — LiteSpeed admin page URLs
    $ls_cache_url   = admin_url( 'admin.php?page=litespeed-cache' );
    $ls_optm_url    = admin_url( 'admin.php?page=litespeed-page_optm' );
    $ls_object_url  = admin_url( 'admin.php?page=litespeed-cache#object' );
    $ls_general_url = admin_url( 'admin.php?page=litespeed' );

    // — Panel health
    $issues = 0;
    if ( ! ( $ls['cache_enabled'] ?? false ) ) $issues += 2;
    if ( ! ( $ls['object_enabled'] ?? false ) ) $issues++;
    if ( ! $r_active ) $issues++;
    $panel_class = 'hws-panel';
    if ( $issues > 2 ) $panel_class .= ' panel-needs-attention';
    elseif ( $issues > 0 ) $panel_class .= ' panel-warning';

    // — Status badge helper
    $badge = function( $on, $label_on = 'ON', $label_off = 'OFF' ) {
        $bg    = $on ? '#00a32a' : '#d63638';
        $label = $on ? $label_on : $label_off;
        return '<span style="background:' . $bg . ';color:#fff;font-size:10px;padding:2px 6px;border-radius:3px;">' . $label . '</span>';
    };

    ?>
    <div class="<?php echo esc_attr( $panel_class ); ?>">
        <div class="hws-panel-header">⚡ LiteSpeed Cache</div>
        <div class="hws-panel-body">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">

                <!-- COLUMN 1: Page Cache -->
                <div style="background:#f8f9fa;border-radius:6px;padding:12px;">
                    <h4 style="margin:0 0 8px;font-size:12px;color:#1d2327;">📄 Page Cache</h4>
                    <div style="font-size:12px;line-height:2;">
                        <div>Cache: <?php echo $badge( $ls['cache_enabled'] ?? false ); ?></div>
                        <div>Private Cache: <?php echo $badge( $ls['cache_private'] ?? false ); ?></div>
                        <div>Browser Cache: <?php echo $badge( $ls['cache_browser'] ?? false ); ?></div>
                        <div>Mobile Cache: <?php echo $badge( $ls['cache_mobile'] ?? false ); ?></div>
                        <div>REST Cache: <?php echo $badge( $ls['cache_rest'] ?? false ); ?></div>
                        <?php $ttl = $ls['cache_ttl_public'] ?? 0; if ( $ttl ) : ?>
                            <div style="color:#646970;font-size:11px;">TTL: <?php echo number_format( $ttl ); ?>s</div>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url( $ls_cache_url ); ?>" target="_blank" style="display:inline-block;margin-top:8px;font-size:11px;">⚙️ Cache Settings →</a>
                </div>

                <!-- COLUMN 2: CSS/JS Optimization -->
                <div style="background:#f8f9fa;border-radius:6px;padding:12px;">
                    <h4 style="margin:0 0 8px;font-size:12px;color:#1d2327;">🎨 CSS</h4>
                    <div style="font-size:12px;line-height:2;">
                        <div>Minify: <?php echo $badge( $ls['css_minify'] ?? false ); ?></div>
                        <div>Combine: <?php echo $badge( $ls['css_combine'] ?? false ); ?></div>
                        <div>Load Async: <?php echo $badge( $ls['css_async'] ?? false ); ?></div>
                        <div>Font Display: <?php echo $badge( (bool) ( $ls['css_font_display'] ?? false ) ); ?></div>
                    </div>
                    <h4 style="margin:12px 0 8px;font-size:12px;color:#1d2327;">📜 JS</h4>
                    <div style="font-size:12px;line-height:2;">
                        <div>Minify: <?php echo $badge( $ls['js_minify'] ?? false ); ?></div>
                        <div>Combine: <?php echo $badge( $ls['js_combine'] ?? false ); ?></div>
                        <div>Defer: <?php echo $badge( (bool) ( $ls['js_defer'] ?? false ) ); ?></div>
                    </div>
                    <a href="<?php echo esc_url( $ls_optm_url ); ?>" target="_blank" style="display:inline-block;margin-top:8px;font-size:11px;">⚙️ Optimization Settings →</a>
                </div>

                <!-- COLUMN 3: Redis / Object Cache -->
                <div style="background:<?php echo $r_active ? '#f0faf0' : '#fef7f0'; ?>;border-radius:6px;padding:12px;">
                    <h4 style="margin:0 0 8px;font-size:12px;color:#1d2327;">🗄️ Redis / Object Cache</h4>
                    <div style="font-size:12px;line-height:2;">
                        <div>Object Cache: <?php echo $badge( $ls['object_enabled'] ?? false ); ?></div>
                        <div>Driver: <?php echo $badge( ( $ls['object_kind'] ?? '' ) === 'Redis', 'Redis', $ls['object_kind'] ?? 'N/A' ); ?></div>
                        <div>Connection: <?php echo $badge( $r_connected ); ?></div>
                        <div>Extension: <?php echo $badge( $r_extension ); ?></div>
                        <?php $obj_host = $ls['object_host'] ?? ''; if ( $obj_host ) : ?>
                            <div style="color:#646970;font-size:11px;">Host: <?php echo esc_html( $obj_host ); ?>:<?php echo (int) ( $ls['object_port'] ?? 0 ); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ( $r_connected && ! empty( $r_info ) ) : ?>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e0e0e0;font-size:11px;color:#646970;line-height:1.8;">
                            <div>Version: <strong><?php echo esc_html( $r_info['version'] ?? '?' ); ?></strong></div>
                            <div>Memory: <?php echo esc_html( $r_info['used_memory'] ?? '?' ); ?> (peak: <?php echo esc_html( $r_info['peak_memory'] ?? '?' ); ?>)</div>
                            <div>DB: <?php echo (int) ( $r_info['db_index'] ?? 0 ); ?> · Keys: <?php echo number_format( (int) ( $r_info['total_keys'] ?? 0 ) ); ?></div>
                            <?php if ( ! empty( $r_info['hit_rate'] ) && $r_info['hit_rate'] !== 'N/A' ) : ?>
                                <div>Hit Rate: <strong><?php echo esc_html( $r_info['hit_rate'] ); ?></strong></div>
                            <?php endif; ?>
                            <div>Uptime: <?php echo esc_html( $r_info['uptime_days'] ?? 0 ); ?> days</div>
                        </div>
                    <?php elseif ( $r_error ) : ?>
                        <div style="margin-top:8px;font-size:11px;color:#d63638;">⚠️ <?php echo esc_html( $r_error ); ?></div>
                    <?php endif; ?>

                    <div style="margin-top:8px;font-size:11px;">
                        Persistent: <?php echo $badge( $ls['object_persistent'] ?? false ); ?>
                        · Admin: <?php echo $badge( $ls['object_admin'] ?? false ); ?>
                        · Transients: <?php echo $badge( $ls['object_transients'] ?? false ); ?>
                    </div>
                    <a href="<?php echo esc_url( $ls_object_url ); ?>" target="_blank" style="display:inline-block;margin-top:8px;font-size:11px;">⚙️ Object Cache Settings →</a>
                </div>

                <!-- COLUMN 4: Brotli & General -->
                <div style="background:#f8f9fa;border-radius:6px;padding:12px;">
                    <h4 style="margin:0 0 8px;font-size:12px;color:#1d2327;">🗜️ Brotli Compression</h4>
                    <div style="font-size:12px;line-height:2;">
                        <div>Brotli: <?php echo $badge( $brotli['enabled'] ?? false ); ?></div>
                    </div>
                    <?php $br_details = $brotli['details'] ?? ''; if ( $br_details ) : ?>
                        <div style="margin-top:4px;font-size:11px;color:#646970;">
                            <?php echo esc_html( $br_details ); ?>
                        </div>
                    <?php endif; ?>

                    <h4 style="margin:16px 0 8px;font-size:12px;color:#1d2327;">ℹ️ General</h4>
                    <div style="font-size:11px;color:#646970;line-height:1.8;">
                        <div>PHP: <?php echo phpversion(); ?></div>
                        <div>SAPI: <?php echo php_sapi_name(); ?></div>
                        <div>Server: <?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ); ?></div>
                    </div>
                    <a href="<?php echo esc_url( $ls_general_url ); ?>" target="_blank" style="display:inline-block;margin-top:8px;font-size:11px;">⚙️ LiteSpeed Dashboard →</a>
                </div>
            </div>

        </div>
    </div>
    <?php
}
