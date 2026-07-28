<?php namespace hws_base_tools;

use HWS\BaseTools\Security\RemoteActionPolicy;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * HWS Base Tools — Update Center
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Provides a centralized dashboard for WordPress core, plugin, and theme
 * updates with the following features:
 *
 *   • AJAX-powered updates from the admin dashboard tab
 *   • Individual or bulk update for plugins and themes
 *   • Secret public URLs for triggering updates without login
 *   • Fault-tolerant: never creates .maintenance, skips failures
 *   • Detailed per-item logging with success/fail status
 *
 * SECRET URLs (all use the DB-backed master secret):
 *   /?hws_update_wp=<secret>           — Update WordPress core
 *   /?hws_update_plugins=<secret>      — Update all plugins
 *   /?hws_update_themes=<secret>       — Update all themes
 *   /?hws_update_all=<secret>          — Update WP + plugins + themes + delete .maintenance
 *   /?hws_delete_maintenance=<secret>  — Force delete .maintenance file
 *
 * @since 10.8.0
 * ═══════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════

/** Option key controlling whether update secret URLs are active */
define( __NAMESPACE__ . '\\HWS_OPT_UPDATE_URLS_ENABLED', 'hws_update_urls_enabled' );

/**
 * Get the secret key for URL authentication
 * Uses the centralized master password from Dashboard_Config if available,
 * otherwise falls back to DB option directly (for frontend access where
 * Dashboard_Config may not be loaded yet)
 */
function hws_update_secret(): string {
    // — Try centralized Dashboard_Config first (available in admin)
    if ( class_exists( __NAMESPACE__ . '\\Dashboard_Config' ) ) {
        return Dashboard_Config::get_secret_key();
    }

    return hws_get_master_secret();
}

/** All secret URL keys for the update center */
function get_update_url_keys(): array {
    return [
        'hws_update_wp'           => [
            'label'    => 'Update WordPress Core',
            'callback' => __NAMESPACE__ . '\\run_update_wp',
        ],
        'hws_update_plugins'      => [
            'label'    => 'Update All Plugins',
            'callback' => __NAMESPACE__ . '\\run_update_plugins',
        ],
        'hws_update_themes'       => [
            'label'    => 'Update All Themes',
            'callback' => __NAMESPACE__ . '\\run_update_themes',
        ],
        'hws_update_all'          => [
            'label'    => 'Update Everything + Delete .maintenance',
            'callback' => __NAMESPACE__ . '\\run_update_all',
        ],
        'hws_delete_maintenance'  => [
            'label'    => 'Force Delete .maintenance File',
            'callback' => __NAMESPACE__ . '\\run_delete_maintenance',
        ],
    ];
}

/**
 * Check if update center secret URLs are enabled.
 */
function are_update_urls_enabled(): bool {
    return RemoteActionPolicy::option_enabled( constant( __NAMESPACE__ . '\\HWS_OPT_UPDATE_URLS_ENABLED' ) );
}

// ═══════════════════════════════════════════════════════════════════════════
// SECRET URL HANDLER — runs on init priority 1
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', function() {
    // — Only process if update URLs are enabled
    if ( ! are_update_urls_enabled() ) return;

    $secret = hws_update_secret();
    $keys   = get_update_url_keys();

    foreach ( $keys as $param => $config ) {
        $provided = isset( $_GET[ $param ] ) && ! is_array( $_GET[ $param ] )
            ? (string) wp_unslash( $_GET[ $param ] )
            : '';

        if ( '' !== $provided && hash_equals( $secret, $provided ) ) {
            // — Support ?format=html or ?format=text (default: JSON for automation)
            $format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'json';
            $log    = call_user_func( $config['callback'] );
            hws_update_render_output( $config['label'], $log, $format );
            exit;
        }
    }
}, 1 );


// ═══════════════════════════════════════════════════════════════════════════
// AJAX HANDLERS — admin dashboard triggers
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_hws_update_center', __NAMESPACE__ . '\\ajax_update_center' );

/**
 * Unified AJAX handler for all update center actions
 * Expects POST: { action: 'hws_update_center', update_type: '...', slug: '...' }
 */
function ajax_update_center() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $type = isset( $_POST['update_type'] ) ? sanitize_key( $_POST['update_type'] ) : '';
    $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';

    $log = '';
    switch ( $type ) {
        case 'wp':
            $log = run_update_wp();
            break;
        case 'plugins':
            $log = run_update_plugins();
            break;
        case 'plugins_single':
            $log = run_update_single_plugin( $slug );
            break;
        case 'themes':
            $log = run_update_themes();
            break;
        case 'themes_single':
            $log = run_update_single_theme( $slug );
            break;
        case 'all':
            $log = run_update_all();
            break;
        case 'delete_maintenance':
            $log = run_delete_maintenance();
            break;
        case 'refresh':
            // — Return fresh outdated data as JSON
            wp_send_json_success( get_outdated_data() );
            return;
        default:
            wp_send_json_error( 'Unknown update type: ' . $type );
            return;
    }

    wp_send_json_success( [ 'log' => $log ] );
}

add_action( 'wp_ajax_hws_toggle_update_urls', __NAMESPACE__ . '\\ajax_toggle_update_urls' );

/**
 * AJAX: Toggle update center secret URLs on/off
 */
function ajax_toggle_update_urls() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;

    if ( $enabled && ! RemoteActionPolicy::legacy_get_routes_allowed() ) {
        wp_send_json_error(
            [ 'message' => 'Legacy public update URLs are security-disabled. Use authenticated AJAX or the native WordPress updater.' ],
            403
        );
    }

    update_option( constant( __NAMESPACE__ . '\\HWS_OPT_UPDATE_URLS_ENABLED' ), $enabled ? 'yes' : 'no' );
    wp_send_json_success( [ 'enabled' => $enabled ] );
}


// ═══════════════════════════════════════════════════════════════════════════
// UPDATE RUNNERS — fault-tolerant, no .maintenance file
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Bootstrap the WordPress upgrader system
 * Loads all required files once, suppresses .maintenance creation
 */
function hws_update_bootstrap() {
    // — Load the upgrader classes if not already loaded
    if ( ! function_exists( 'wp_update_core' ) ) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    }
    if ( ! class_exists( 'WP_Upgrader' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( ! function_exists( 'wp_update_themes' ) ) {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }
    if ( ! function_exists( 'request_filesystem_credentials' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    // — CRITICAL: Prevent WP from creating .maintenance file during upgrades
    add_filter( 'upgrader_pre_install', __NAMESPACE__ . '\\hws_prevent_maintenance', 999, 2 );
    add_filter( 'enable_maintenance_mode', '__return_false', 999 );

    // — Force refresh update transients so we have current data
    wp_update_plugins();
    wp_update_themes();
}

/**
 * Filter callback: prevent .maintenance file creation
 */
function hws_prevent_maintenance( $response, $extra ) {
    // — Force-delete .maintenance if it was somehow created
    $maint = ABSPATH . '.maintenance';
    if ( file_exists( $maint ) ) {
        @unlink( $maint );
    }
    return $response;
}

/**
 * Force-delete .maintenance file and return log
 */
function run_delete_maintenance(): string {
    $maint = ABSPATH . '.maintenance';
    $log   = hws_log_header( 'Delete .maintenance File' );

    if ( file_exists( $maint ) ) {
        if ( @unlink( $maint ) ) {
            $log .= hws_log_line( '✅', '.maintenance file deleted successfully' );
        } else {
            $log .= hws_log_line( '❌', 'Failed to delete .maintenance file — check permissions' );
        }
    } else {
        $log .= hws_log_line( 'ℹ️', 'No .maintenance file exists — nothing to delete' );
    }

    return $log;
}

/**
 * Update WordPress core
 */
function run_update_wp(): string {
    hws_update_bootstrap();
    $log = hws_log_header( 'WordPress Core Update' );

    // — Check current version
    global $wp_version;
    $log .= hws_log_line( 'ℹ️', "Current version: {$wp_version}" );

    // — Get available update
    $updates = get_core_updates();
    if ( empty( $updates ) || ( isset( $updates[0]->response ) && $updates[0]->response === 'latest' ) ) {
        $log .= hws_log_line( '✅', 'WordPress is already up to date' );
        return $log;
    }

    $update = $updates[0];
    $log .= hws_log_line( '🔄', "Updating to version {$update->version}..." );

    try {
        $upgrader = new \Core_Upgrader( new \Automatic_Upgrader_Skin() );
        $result   = $upgrader->upgrade( $update );

        // — Clean up .maintenance just in case
        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );

        if ( is_wp_error( $result ) ) {
            $log .= hws_log_line( '❌', 'Failed: ' . $result->get_error_message() );
        } else {
            $log .= hws_log_line( '✅', "Updated to {$update->version} successfully" );
        }
    } catch ( \Throwable $e ) {
        $log .= hws_log_line( '❌', 'Exception: ' . $e->getMessage() );
        // — Clean up .maintenance on failure
        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );
    }

    return $log;
}

/**
 * Update all outdated plugins (fault-tolerant, skips failures)
 */
function run_update_plugins(): string {
    hws_update_bootstrap();
    $log = hws_log_header( 'Plugin Updates' );

    $update_data = get_site_transient( 'update_plugins' );
    if ( empty( $update_data->response ) ) {
        $log .= hws_log_line( '✅', 'All plugins are up to date' );
        return $log;
    }

    $plugins = $update_data->response;
    $total   = count( $plugins );
    $success = 0;
    $failed  = 0;

    $log .= hws_log_line( 'ℹ️', "{$total} plugin(s) have updates available" );

    foreach ( $plugins as $plugin_file => $plugin_data ) {
        $name = hws_get_plugin_name( $plugin_file );
        $new  = $plugin_data->new_version ?? 'unknown';
        $log .= hws_log_line( '🔄', "Updating {$name} → {$new}..." );

        try {
            $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
            $result   = $upgrader->upgrade( $plugin_file );

            // — Clean up .maintenance after each
            $maint = ABSPATH . '.maintenance';
            if ( file_exists( $maint ) ) @unlink( $maint );

            if ( is_wp_error( $result ) ) {
                $log .= hws_log_line( '❌', "  Failed: " . $result->get_error_message() );
                $failed++;
            } elseif ( $result === false ) {
                $log .= hws_log_line( '❌', "  Failed: Upgrader returned false (filesystem error)" );
                $failed++;
            } else {
                $log .= hws_log_line( '✅', "  {$name} updated to {$new}" );
                $success++;
            }
        } catch ( \Throwable $e ) {
            $log .= hws_log_line( '❌', "  Exception: " . $e->getMessage() );
            $failed++;
            // — Clean up .maintenance on failure and continue
            $maint = ABSPATH . '.maintenance';
            if ( file_exists( $maint ) ) @unlink( $maint );
        }
    }

    $log .= hws_log_line( '📊', "Results: {$success} succeeded, {$failed} failed out of {$total}" );
    return $log;
}

/**
 * Update a single plugin by file path
 */
function run_update_single_plugin( string $plugin_file ): string {
    hws_update_bootstrap();
    $name = hws_get_plugin_name( $plugin_file );
    $log  = hws_log_header( "Update Plugin: {$name}" );

    try {
        $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
        $result   = $upgrader->upgrade( $plugin_file );

        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );

        if ( is_wp_error( $result ) ) {
            $log .= hws_log_line( '❌', 'Failed: ' . $result->get_error_message() );
        } elseif ( $result === false ) {
            $log .= hws_log_line( '❌', 'Failed: Upgrader returned false' );
        } else {
            $log .= hws_log_line( '✅', "{$name} updated successfully" );
        }
    } catch ( \Throwable $e ) {
        $log .= hws_log_line( '❌', 'Exception: ' . $e->getMessage() );
        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );
    }

    return $log;
}

/**
 * Update all outdated themes (fault-tolerant, skips failures)
 */
function run_update_themes(): string {
    hws_update_bootstrap();
    $log = hws_log_header( 'Theme Updates' );

    $update_data = get_site_transient( 'update_themes' );
    if ( empty( $update_data->response ) ) {
        $log .= hws_log_line( '✅', 'All themes are up to date' );
        return $log;
    }

    $themes  = $update_data->response;
    $total   = count( $themes );
    $success = 0;
    $failed  = 0;

    $log .= hws_log_line( 'ℹ️', "{$total} theme(s) have updates available" );

    foreach ( $themes as $theme_slug => $theme_data ) {
        $name = wp_get_theme( $theme_slug )->get( 'Name' ) ?: $theme_slug;
        $new  = $theme_data['new_version'] ?? 'unknown';
        $log .= hws_log_line( '🔄', "Updating {$name} → {$new}..." );

        try {
            $upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
            $result   = $upgrader->upgrade( $theme_slug );

            $maint = ABSPATH . '.maintenance';
            if ( file_exists( $maint ) ) @unlink( $maint );

            if ( is_wp_error( $result ) ) {
                $log .= hws_log_line( '❌', "  Failed: " . $result->get_error_message() );
                $failed++;
            } elseif ( $result === false ) {
                $log .= hws_log_line( '❌', "  Failed: Upgrader returned false" );
                $failed++;
            } else {
                $log .= hws_log_line( '✅', "  {$name} updated to {$new}" );
                $success++;
            }
        } catch ( \Throwable $e ) {
            $log .= hws_log_line( '❌', "  Exception: " . $e->getMessage() );
            $failed++;
            $maint = ABSPATH . '.maintenance';
            if ( file_exists( $maint ) ) @unlink( $maint );
        }
    }

    $log .= hws_log_line( '📊', "Results: {$success} succeeded, {$failed} failed out of {$total}" );
    return $log;
}

/**
 * Update a single theme by slug
 */
function run_update_single_theme( string $theme_slug ): string {
    hws_update_bootstrap();
    $name = wp_get_theme( $theme_slug )->get( 'Name' ) ?: $theme_slug;
    $log  = hws_log_header( "Update Theme: {$name}" );

    try {
        $upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
        $result   = $upgrader->upgrade( $theme_slug );

        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );

        if ( is_wp_error( $result ) ) {
            $log .= hws_log_line( '❌', 'Failed: ' . $result->get_error_message() );
        } elseif ( $result === false ) {
            $log .= hws_log_line( '❌', 'Failed: Upgrader returned false' );
        } else {
            $log .= hws_log_line( '✅', "{$name} updated successfully" );
        }
    } catch ( \Throwable $e ) {
        $log .= hws_log_line( '❌', 'Exception: ' . $e->getMessage() );
        $maint = ABSPATH . '.maintenance';
        if ( file_exists( $maint ) ) @unlink( $maint );
    }

    return $log;
}

/**
 * Update everything: WP core + all plugins + all themes + delete .maintenance
 */
function run_update_all(): string {
    $log  = hws_log_header( 'Update Everything' );
    $log .= run_update_wp();
    $log .= run_update_plugins();
    $log .= run_update_themes();
    $log .= run_delete_maintenance();
    $log .= hws_log_line( '🏁', 'All update operations completed @ ' . current_time( 'Y-m-d H:i:s' ) );
    return $log;
}


// ═══════════════════════════════════════════════════════════════════════════
// HELPERS — logging, data retrieval, output rendering
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get plugin display name from plugin file path
 */
function hws_get_plugin_name( string $plugin_file ): string {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all = get_plugins();
    return isset( $all[ $plugin_file ]['Name'] ) ? $all[ $plugin_file ]['Name'] : basename( $plugin_file, '.php' );
}

/**
 * Build a log header line
 */
function hws_log_header( string $title ): string {
    $ts = current_time( 'Y-m-d H:i:s' );
    return "\n═══ {$title} [{$ts}] ═══\n";
}

/**
 * Build a single log line with icon prefix
 */
function hws_log_line( string $icon, string $message ): string {
    return "{$icon} {$message}\n";
}

/**
 * Get all outdated plugins + themes + WP core status as structured data
 */
function get_outdated_data(): array {
    // — Ensure update data is loaded
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( ! function_exists( 'wp_update_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    }

    // — WordPress core
    global $wp_version;
    $core_updates  = get_core_updates();
    $core_outdated = false;
    $core_new      = $wp_version;
    if ( ! empty( $core_updates ) && isset( $core_updates[0]->response ) && $core_updates[0]->response !== 'latest' ) {
        $core_outdated = true;
        $core_new      = $core_updates[0]->version;
    }

    // — Plugins
    $plugin_updates = get_site_transient( 'update_plugins' );
    $plugins_out    = [];
    if ( ! empty( $plugin_updates->response ) ) {
        $all_plugins = get_plugins();
        foreach ( $plugin_updates->response as $file => $data ) {
            $plugins_out[] = [
                'file'        => $file,
                'name'        => $all_plugins[ $file ]['Name'] ?? basename( $file, '.php' ),
                'current'     => $all_plugins[ $file ]['Version'] ?? '?',
                'new_version' => $data->new_version ?? '?',
            ];
        }
    }

    // — Themes
    $theme_updates = get_site_transient( 'update_themes' );
    $themes_out    = [];
    if ( ! empty( $theme_updates->response ) ) {
        foreach ( $theme_updates->response as $slug => $data ) {
            $theme       = wp_get_theme( $slug );
            $themes_out[] = [
                'slug'        => $slug,
                'name'        => $theme->get( 'Name' ) ?: $slug,
                'current'     => $theme->get( 'Version' ) ?: '?',
                'new_version' => $data['new_version'] ?? '?',
            ];
        }
    }

    // — .maintenance file
    $maint_exists = file_exists( ABSPATH . '.maintenance' );

    return [
        'wp_version'     => $wp_version,
        'wp_new_version' => $core_new,
        'wp_outdated'    => $core_outdated,
        'plugins'        => $plugins_out,
        'themes'         => $themes_out,
        'maintenance'    => $maint_exists,
    ];
}

/**
 * Render secret URL output (JSON default, HTML or plain text via ?format=)
 *
 * JSON is default so automated tools / cURL get structured status data.
 * Append ?format=html for a styled page, ?format=text for plain text.
 */
function hws_update_render_output( string $title, string $log, string $format = 'json' ) {
    // — JSON output (default): structured data for automation & reporting
    if ( $format === 'json' ) {
        header( 'Content-Type: application/json; charset=utf-8' );

        // — Parse log lines into structured entries
        $entries   = [];
        $lines     = array_filter( explode( "\n", $log ), 'strlen' );
        $success   = 0;
        $failed    = 0;
        $skipped   = 0;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;

            // — Detect status from icon prefix
            $status = 'info';
            if ( strpos( $line, '✅' ) !== false ) { $status = 'success'; $success++; }
            elseif ( strpos( $line, '❌' ) !== false ) { $status = 'error'; $failed++; }
            elseif ( strpos( $line, '🔄' ) !== false ) { $status = 'updating'; }
            elseif ( strpos( $line, '📊' ) !== false ) { $status = 'summary'; }
            elseif ( strpos( $line, '🏁' ) !== false ) { $status = 'complete'; }
            elseif ( strpos( $line, '═' ) !== false ) { $status = 'header'; }

            $entries[] = [
                'status'  => $status,
                'message' => preg_replace( '/^[^\w\s]*\s*/', '', $line ), // — Strip emoji prefix
            ];
        }

        echo json_encode( [
            'ok'        => true,
            'action'    => $title,
            'timestamp' => current_time( 'c' ),
            'summary'   => [
                'success' => $success,
                'failed'  => $failed,
                'total'   => $success + $failed,
            ],
            'log'       => $entries,
            'raw'       => trim( $log ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        return;
    }

    // — Plain text output
    if ( $format === 'text' ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo "HWS Update Center: {$title}\n";
        echo str_repeat( '=', 40 ) . "\n\n";
        echo strip_tags( html_entity_decode( $log ) );
        return;
    }

    // — HTML output (styled page)
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HWS Update Center: <?php echo esc_html( $title ); ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #1d2327; color: #50c878; }
            h1 { color: #fff; }
            .log { background: #0a0a0a; padding: 20px; border-radius: 8px; white-space: pre-wrap; font-family: monospace; font-size: 14px; line-height: 1.6; }
            a { color: #4facfe; }
            .ts { color: #999; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1>⚡ HWS Update Center: <?php echo esc_html( $title ); ?></h1>
        <p><a href="<?php echo home_url(); ?>">← Back to site</a> | <a href="<?php echo admin_url(); ?>">Admin →</a></p>
        <div class="log"><?php echo esc_html( $log ); ?></div>
        <p class="ts">💡 Tip: Default output is JSON. Use <code>&format=html</code> for this view, <code>&format=text</code> for plain text.</p>
        <p class="ts">Completed: <?php echo current_time( 'mysql' ); ?></p>
    </body>
    </html>
    <?php
}


// ═══════════════════════════════════════════════════════════════════════════
// DASHBOARD TAB RENDERER
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Render the Update Center tab content
 */
function display_settings_update_center() {
    $data       = get_outdated_data();
    $urls_on    = are_update_urls_enabled();
    $secret     = $urls_on ? hws_update_secret() : '';
    $url_keys   = get_update_url_keys();
    $site       = home_url( '/' );
    ?>

    <style>
        /* — Update Center Styles — */
        .hws-uc-intro { background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: #fff; padding: 20px 25px; border-radius: 8px; margin-bottom: 25px; }
        .hws-uc-intro h3 { margin: 0 0 8px; color: #fff; font-size: 18px; }
        .hws-uc-intro p { margin: 0; opacity: .9; font-size: 14px; }
        .hws-uc-section { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
        .hws-uc-section-header { background: #f8f9fa; padding: 14px 20px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .hws-uc-section-header h4 { margin: 0; font-size: 15px; font-weight: 600; }
        .hws-uc-section-body { padding: 15px 20px; }
        .hws-uc-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .hws-uc-item:last-child { border-bottom: none; }
        .hws-uc-item-name { font-weight: 500; font-size: 14px; }
        .hws-uc-item-ver { font-size: 12px; color: #646970; }
        .hws-uc-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .hws-uc-badge-ok { background: #d4edda; color: #155724; }
        .hws-uc-badge-out { background: #fff3cd; color: #856404; }
        .hws-uc-badge-err { background: #f8d7da; color: #721c24; }
        .hws-uc-btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .hws-uc-btn-primary { background: #0073aa; color: #fff; }
        .hws-uc-btn-primary:hover { background: #005f8d; }
        .hws-uc-btn-sm { padding: 4px 10px; font-size: 12px; }
        .hws-uc-btn:disabled { opacity: .6; cursor: not-allowed; }
        .hws-uc-log { background: #1d2327; color: #50c878; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; white-space: pre-wrap; max-height: 400px; overflow-y: auto; display: none; margin-top: 15px; line-height: 1.6; }
        .hws-uc-log.active { display: block; }
        .hws-uc-urls { margin-top: 15px; }
        .hws-uc-url-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px; }
        .hws-uc-url-row code { background: #f0f0f1; padding: 4px 8px; border-radius: 3px; font-size: 12px; word-break: break-all; flex: 1; }
        .hws-uc-url-row a { white-space: nowrap; }
        .hws-uc-empty { color: #646970; font-style: italic; padding: 10px 0; }
    </style>

    <!-- Intro -->
    <div class="hws-uc-intro">
        <h3>🔄 Update Center</h3>
        <p>Manage WordPress core, plugin, and theme updates. Fault-tolerant — never creates .maintenance files, skips failures gracefully.</p>
    </div>

    <!-- ──────── WordPress Core ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>🔷 WordPress Core</h4>
            <button type="button" class="hws-uc-btn hws-uc-btn-primary" data-uc-action="wp">
                <?php echo $data['wp_outdated'] ? 'Update to ' . esc_html( $data['wp_new_version'] ) : '✅ Up to Date'; ?>
            </button>
        </div>
        <div class="hws-uc-section-body">
            <div class="hws-uc-item">
                <div>
                    <div class="hws-uc-item-name">WordPress</div>
                    <div class="hws-uc-item-ver">Current: <?php echo esc_html( $data['wp_version'] ); ?></div>
                </div>
                <span class="hws-uc-badge <?php echo $data['wp_outdated'] ? 'hws-uc-badge-out' : 'hws-uc-badge-ok'; ?>">
                    <?php echo $data['wp_outdated'] ? esc_html( $data['wp_new_version'] ) . ' available' : 'Latest'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ──────── Plugins ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>🔌 Plugins <span id="hws-uc-plugin-count">(<?php echo count( $data['plugins'] ); ?> update<?php echo count( $data['plugins'] ) !== 1 ? 's' : ''; ?>)</span></h4>
            <button type="button" class="hws-uc-btn hws-uc-btn-primary" data-uc-action="plugins" <?php echo empty( $data['plugins'] ) ? 'disabled' : ''; ?>>
                Update All Plugins
            </button>
        </div>
        <div class="hws-uc-section-body" id="hws-uc-plugins-list">
            <?php if ( empty( $data['plugins'] ) ) : ?>
                <div class="hws-uc-empty">All plugins are up to date.</div>
            <?php else : ?>
                <?php foreach ( $data['plugins'] as $p ) : ?>
                    <div class="hws-uc-item" data-plugin-file="<?php echo esc_attr( $p['file'] ); ?>">
                        <div>
                            <div class="hws-uc-item-name"><?php echo esc_html( $p['name'] ); ?></div>
                            <div class="hws-uc-item-ver"><?php echo esc_html( $p['current'] ); ?> → <?php echo esc_html( $p['new_version'] ); ?></div>
                        </div>
                        <button type="button" class="hws-uc-btn hws-uc-btn-primary hws-uc-btn-sm" data-uc-action="plugins_single" data-uc-slug="<?php echo esc_attr( $p['file'] ); ?>">
                            Update
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ──────── Themes ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>🎨 Themes <span id="hws-uc-theme-count">(<?php echo count( $data['themes'] ); ?> update<?php echo count( $data['themes'] ) !== 1 ? 's' : ''; ?>)</span></h4>
            <button type="button" class="hws-uc-btn hws-uc-btn-primary" data-uc-action="themes" <?php echo empty( $data['themes'] ) ? 'disabled' : ''; ?>>
                Update All Themes
            </button>
        </div>
        <div class="hws-uc-section-body" id="hws-uc-themes-list">
            <?php if ( empty( $data['themes'] ) ) : ?>
                <div class="hws-uc-empty">All themes are up to date.</div>
            <?php else : ?>
                <?php foreach ( $data['themes'] as $t ) : ?>
                    <div class="hws-uc-item" data-theme-slug="<?php echo esc_attr( $t['slug'] ); ?>">
                        <div>
                            <div class="hws-uc-item-name"><?php echo esc_html( $t['name'] ); ?></div>
                            <div class="hws-uc-item-ver"><?php echo esc_html( $t['current'] ); ?> → <?php echo esc_html( $t['new_version'] ); ?></div>
                        </div>
                        <button type="button" class="hws-uc-btn hws-uc-btn-primary hws-uc-btn-sm" data-uc-action="themes_single" data-uc-slug="<?php echo esc_attr( $t['slug'] ); ?>">
                            Update
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ──────── .maintenance Status ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>🧹 .maintenance File</h4>
            <button type="button" class="hws-uc-btn hws-uc-btn-primary" data-uc-action="delete_maintenance">
                Force Delete
            </button>
        </div>
        <div class="hws-uc-section-body">
            <div class="hws-uc-item">
                <div class="hws-uc-item-name">Status</div>
                <span id="hws-uc-maint-badge" class="hws-uc-badge <?php echo $data['maintenance'] ? 'hws-uc-badge-err' : 'hws-uc-badge-ok'; ?>">
                    <?php echo $data['maintenance'] ? '⚠️ File exists — site may be stuck' : '✅ Clean — no file'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ──────── Log Output ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>📋 Update Log</h4>
            <button type="button" class="hws-uc-btn hws-uc-btn-primary" data-uc-action="all">🚀 Update Everything</button>
        </div>
        <div class="hws-uc-section-body">
            <div id="hws-uc-log" class="hws-uc-log"></div>
            <div class="hws-uc-empty" id="hws-uc-log-empty">Run an update to see log output here.</div>
        </div>
    </div>

    <!-- ──────── Secret URLs ──────── -->
    <div class="hws-uc-section">
        <div class="hws-uc-section-header">
            <h4>🔗 Public Update URLs</h4>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input type="checkbox" id="hws-uc-urls-toggle" <?php checked( $urls_on ); ?>> Enabled
            </label>
        </div>
        <div class="hws-uc-section-body">
            <p style="font-size:13px;color:#646970;margin:0 0 12px;">Legacy unauthenticated GET routes are disabled by default because URL secrets leak into browser history, access logs, and analytics. Use the authenticated controls above or the native WordPress Updates screen.</p>
            <?php if ( $urls_on ) : ?>
            <div class="hws-uc-urls">
                <?php foreach ( $url_keys as $param => $cfg ) :
                    $full_url = $site . '?' . $param . '=' . $secret;
                ?>
                    <div class="hws-uc-url-row">
                        <strong style="min-width:200px;"><?php echo esc_html( $cfg['label'] ); ?>:</strong>
                        <code><?php echo esc_html( $full_url ); ?></code>
                        <a href="<?php echo esc_url( $full_url ); ?>" target="_blank">Open ↗</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
                <p class="hws-uc-empty">Public update routes are inactive.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    if ( function_exists( __NAMESPACE__ . '\\hws_ct_display_plugin_info' ) ) {
        hws_ct_display_plugin_info();
    }
    ?>

    <!-- ──────── Inline JS ──────── -->
    <script>
    jQuery(document).ready(function($) {
        var $log      = $('#hws-uc-log');
        var $logEmpty = $('#hws-uc-log-empty');

        // — Append text to the log panel
        function logAppend(text) {
            $logEmpty.hide();
            $log.addClass('active').append(text);
            // — Auto-scroll to bottom
            $log[0].scrollTop = $log[0].scrollHeight;
        }

        // — Generic update action handler
        function runUpdate($btn, type, slug) {
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Updating...');
            logAppend('\n⏳ Starting: ' + origText + '...\n');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 300000, // — 5 minute timeout for large updates
                data: {
                    action: 'hws_update_center',
                    update_type: type,
                    slug: slug || '',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        logAppend(response.data.log);
                        // — Refresh the outdated lists after update
                        refreshData();
                    } else {
                        logAppend('❌ Error: ' + (response.data || 'Unknown error') + '\n');
                    }
                    $btn.prop('disabled', false).text(origText);
                },
                error: function(xhr, status, err) {
                    logAppend('❌ AJAX Error: ' + status + ' — ' + err + '\n');
                    $btn.prop('disabled', false).text(origText);
                }
            });
        }

        // — Refresh outdated plugin/theme lists dynamically
        function refreshData() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_update_center',
                    update_type: 'refresh',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (!response.success) return;
                    var d = response.data;

                    // — Update plugin list
                    var $pl = $('#hws-uc-plugins-list');
                    $pl.empty();
                    if (!d.plugins.length) {
                        $pl.html('<div class="hws-uc-empty">All plugins are up to date.</div>');
                        $('[data-uc-action="plugins"]').prop('disabled', true);
                    } else {
                        d.plugins.forEach(function(p) {
                            $pl.append(
                                '<div class="hws-uc-item" data-plugin-file="' + p.file + '">' +
                                '  <div><div class="hws-uc-item-name">' + p.name + '</div>' +
                                '  <div class="hws-uc-item-ver">' + p.current + ' → ' + p.new_version + '</div></div>' +
                                '  <button type="button" class="hws-uc-btn hws-uc-btn-primary hws-uc-btn-sm" data-uc-action="plugins_single" data-uc-slug="' + p.file + '">Update</button>' +
                                '</div>'
                            );
                        });
                        $('[data-uc-action="plugins"]').prop('disabled', false);
                    }
                    $('#hws-uc-plugin-count').text('(' + d.plugins.length + ' update' + (d.plugins.length !== 1 ? 's' : '') + ')');

                    // — Update theme list
                    var $tl = $('#hws-uc-themes-list');
                    $tl.empty();
                    if (!d.themes.length) {
                        $tl.html('<div class="hws-uc-empty">All themes are up to date.</div>');
                        $('[data-uc-action="themes"]').prop('disabled', true);
                    } else {
                        d.themes.forEach(function(t) {
                            $tl.append(
                                '<div class="hws-uc-item" data-theme-slug="' + t.slug + '">' +
                                '  <div><div class="hws-uc-item-name">' + t.name + '</div>' +
                                '  <div class="hws-uc-item-ver">' + t.current + ' → ' + t.new_version + '</div></div>' +
                                '  <button type="button" class="hws-uc-btn hws-uc-btn-primary hws-uc-btn-sm" data-uc-action="themes_single" data-uc-slug="' + t.slug + '">Update</button>' +
                                '</div>'
                            );
                        });
                        $('[data-uc-action="themes"]').prop('disabled', false);
                    }
                    $('#hws-uc-theme-count').text('(' + d.themes.length + ' update' + (d.themes.length !== 1 ? 's' : '') + ')');

                    // — Update .maintenance badge
                    var $mb = $('#hws-uc-maint-badge');
                    if (d.maintenance) {
                        $mb.removeClass('hws-uc-badge-ok').addClass('hws-uc-badge-err').text('⚠️ File exists — site may be stuck');
                    } else {
                        $mb.removeClass('hws-uc-badge-err').addClass('hws-uc-badge-ok').text('✅ Clean — no file');
                    }

                    // — Update WP core button
                    var $wpBtn = $('[data-uc-action="wp"]');
                    if (d.wp_outdated) {
                        $wpBtn.text('Update to ' + d.wp_new_version).prop('disabled', false);
                    } else {
                        $wpBtn.text('✅ Up to Date');
                    }
                }
            });
        }

        // — Click handler: delegate for dynamically-added buttons too
        $(document).on('click', '[data-uc-action]', function() {
            var $btn  = $(this);
            var type  = $btn.data('uc-action');
            var slug  = $btn.data('uc-slug') || '';
            runUpdate($btn, type, slug);
        });

        // — Toggle secret URLs
        $('#hws-uc-urls-toggle').on('change', function() {
            var enabled = $(this).prop('checked') ? 1 : 0;
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_toggle_update_urls',
                    enabled: enabled,
                    nonce: hwsNonce
                },
                success: function(r) {
                    if (!r.success) console.error('Failed to toggle update URLs');
                }
            });
        });
    });
    </script>

    <?php
}
