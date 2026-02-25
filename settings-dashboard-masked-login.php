<?php namespace hws_base_tools;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * HWS Base Tools — Masked Login Dashboard Tab
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Dashboard tab for managing the login masking feature with:
 *   • Full status overview of all masking settings
 *   • Activity log (login attempts, blocks, emergency actions)
 *   • Public URLs (no login required) with JSON reporting:
 *     - /?hws_login_status=<secret>        — Status check (JSON)
 *     - /?hws_login_disable=<secret>       — Temporarily disable masking
 *     - /?hws_login_enable=<secret>        — Re-enable masking
 *     - /?hws_login_flush=<secret>         — Force flush permalinks
 *
 * @since 10.8.0
 * ═══════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════

/** Option key for enabling masked login public URLs */
define( __NAMESPACE__ . '\\HWS_OPT_LOGIN_URLS_ENABLED', 'hws_login_urls_enabled' );

/** Option key for the activity log */
define( __NAMESPACE__ . '\\HWS_OPT_LOGIN_LOG', 'hws_login_mask_log' );

/** Max log entries to keep */
define( __NAMESPACE__ . '\\HWS_LOGIN_LOG_MAX', 200 );

/**
 * Get the master secret key (same as update center — centralized)
 */
function hws_login_secret(): string {
    if ( class_exists( __NAMESPACE__ . '\\Dashboard_Config' ) ) {
        return Dashboard_Config::get_secret_key();
    }
    $val = get_option( 'hws_master_secret_key', '' );
    return ( is_string( $val ) && $val !== '' ) ? $val : 'hexa2000!';
}

/**
 * Check if masked login public URLs are enabled (default: YES)
 */
function are_login_urls_enabled(): bool {
    $val = get_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_URLS_ENABLED' ), 'yes' );
    return $val === 'yes' || $val === true || $val === '1' || $val === 1;
}

/**
 * All public URL keys for masked login
 */
function get_login_url_keys(): array {
    return [
        'hws_login_status'  => [
            'label'    => 'Check Login Mask Status',
            'callback' => __NAMESPACE__ . '\\run_login_status',
        ],
        'hws_login_disable' => [
            'label'    => 'Temporarily Disable Masking',
            'callback' => __NAMESPACE__ . '\\run_login_disable',
        ],
        'hws_login_enable'  => [
            'label'    => 'Re-enable Masking',
            'callback' => __NAMESPACE__ . '\\run_login_enable',
        ],
        'hws_login_flush'   => [
            'label'    => 'Force Flush Permalinks',
            'callback' => __NAMESPACE__ . '\\run_login_flush',
        ],
    ];
}


// ═══════════════════════════════════════════════════════════════════════════
// LOGGING SYSTEM
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Append an entry to the masked login activity log
 *
 * @param string $type    Entry type: 'info', 'success', 'warning', 'error', 'block'
 * @param string $message Description of the event
 * @param array  $extra   Additional context (IP, user agent, etc.)
 */
function hws_login_log( string $type, string $message, array $extra = [] ): void {
    $log = get_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_LOG' ), [] );
    if ( ! is_array( $log ) ) $log = [];

    $entry = [
        'time'    => current_time( 'c' ),
        'type'    => $type,
        'message' => $message,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ];
    if ( ! empty( $extra ) ) {
        $entry = array_merge( $entry, $extra );
    }

    // — Prepend (newest first), trim to max
    array_unshift( $log, $entry );
    $max = constant( __NAMESPACE__ . '\\HWS_LOGIN_LOG_MAX' );
    if ( count( $log ) > $max ) {
        $log = array_slice( $log, 0, $max );
    }

    update_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_LOG' ), $log, false );
}

/**
 * Get the activity log
 */
function hws_login_log_get( int $limit = 100 ): array {
    $log = get_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_LOG' ), [] );
    if ( ! is_array( $log ) ) return [];
    return array_slice( $log, 0, $limit );
}

/**
 * Clear the activity log
 */
function hws_login_log_clear(): void {
    update_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_LOG' ), [], false );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECRET URL HANDLER — runs on init priority 1
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', function() {
    if ( ! are_login_urls_enabled() ) return;

    $secret = hws_login_secret();
    $keys   = get_login_url_keys();

    foreach ( $keys as $param => $config ) {
        if ( isset( $_GET[ $param ] ) && $_GET[ $param ] === $secret ) {
            $format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'json';
            $result = call_user_func( $config['callback'] );
            hws_login_render_output( $config['label'], $result, $format );
            exit;
        }
    }
}, 1 );


// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC URL RUNNERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Return current login mask status as structured data
 */
function run_login_status(): array {
    $class_exists = class_exists( __NAMESPACE__ . '\\Login_Masking' );
    $opts         = $class_exists ? Login_Masking::opts() : [];
    $slug         = $class_exists ? Login_Masking::slug() : 'unknown';

    hws_login_log( 'info', 'Status check via public URL' );

    return [
        'ok'      => true,
        'action'  => 'status_check',
        'masking' => [
            'class_loaded'   => $class_exists,
            'enabled'        => ! empty( $opts['enabled'] ),
            'slug'           => $slug,
            'login_url'      => $class_exists ? Login_Masking::login_url() : home_url( '/' . $slug . '/' ),
            'hide_wp_admin'  => ! empty( $opts['hide_wp_admin'] ),
            'well_known'     => ! empty( $opts['well_known'] ),
            'wp_toolkit'     => ! empty( $opts['compat_wptoolkit'] ),
            'allowlist_ips'  => $opts['allowlist_ips'] ?? '',
        ],
        'maintenance_file' => file_exists( ABSPATH . '.maintenance' ),
        'timestamp'        => current_time( 'c' ),
    ];
}

/**
 * Temporarily disable login masking
 * Sets enabled=false in the options array
 */
function run_login_disable(): array {
    if ( ! class_exists( __NAMESPACE__ . '\\Login_Masking' ) ) {
        hws_login_log( 'error', 'Disable attempt failed — Login_Masking class not loaded' );
        return [ 'ok' => false, 'error' => 'Login_Masking class not available' ];
    }

    $opts = Login_Masking::opts();
    $opts['enabled'] = false;
    update_option( Login_Masking::OPT_KEY, $opts );

    // — Flush rewrite rules so /wp-login.php is accessible again
    flush_rewrite_rules( true );

    hws_login_log( 'warning', 'Login masking DISABLED via public URL' );

    return [
        'ok'        => true,
        'action'    => 'masking_disabled',
        'message'   => 'Login masking has been temporarily disabled. Use /wp-login.php to access your site.',
        're_enable' => home_url( '/?hws_login_enable=' . hws_login_secret() ),
        'timestamp' => current_time( 'c' ),
    ];
}

/**
 * Re-enable login masking
 */
function run_login_enable(): array {
    if ( ! class_exists( __NAMESPACE__ . '\\Login_Masking' ) ) {
        hws_login_log( 'error', 'Enable attempt failed — Login_Masking class not loaded' );
        return [ 'ok' => false, 'error' => 'Login_Masking class not available' ];
    }

    $opts = Login_Masking::opts();
    $opts['enabled'] = true;
    update_option( Login_Masking::OPT_KEY, $opts );

    // — Flush rewrite rules so the masked slug is active
    flush_rewrite_rules( true );

    hws_login_log( 'success', 'Login masking RE-ENABLED via public URL' );

    return [
        'ok'        => true,
        'action'    => 'masking_enabled',
        'message'   => 'Login masking has been re-enabled.',
        'login_url' => Login_Masking::login_url(),
        'slug'      => Login_Masking::slug(),
        'timestamp' => current_time( 'c' ),
    ];
}

/**
 * Force flush rewrite rules and purge caches
 */
function run_login_flush(): array {
    flush_rewrite_rules( true );

    // — Best-effort cache purge
    if ( function_exists( 'wp_cache_flush' ) ) @wp_cache_flush();
    if ( function_exists( 'rocket_clean_domain' ) ) @rocket_clean_domain();
    do_action( 'litespeed_purge_all' );

    hws_login_log( 'success', 'Permalinks flushed + caches purged via public URL' );

    return [
        'ok'        => true,
        'action'    => 'permalinks_flushed',
        'message'   => 'Rewrite rules flushed and caches purged successfully.',
        'timestamp' => current_time( 'c' ),
    ];
}


// ═══════════════════════════════════════════════════════════════════════════
// OUTPUT RENDERER — JSON (default), HTML, or text
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Render public URL output
 */
function hws_login_render_output( string $title, array $data, string $format = 'json' ) {
    // — JSON (default)
    if ( $format === 'json' ) {
        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return;
    }

    // — Plain text
    if ( $format === 'text' ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo "HWS Login Masking: {$title}\n";
        echo str_repeat( '=', 40 ) . "\n\n";
        foreach ( $data as $k => $v ) {
            if ( is_array( $v ) ) {
                echo "{$k}:\n";
                foreach ( $v as $k2 => $v2 ) {
                    echo "  {$k2}: " . ( is_bool( $v2 ) ? ( $v2 ? 'true' : 'false' ) : $v2 ) . "\n";
                }
            } else {
                echo "{$k}: " . ( is_bool( $v ) ? ( $v ? 'true' : 'false' ) : $v ) . "\n";
            }
        }
        return;
    }

    // — HTML
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HWS Login Masking: <?php echo esc_html( $title ); ?></title>
        <style>
            body { font-family: -apple-system, sans-serif; margin: 20px; background: #1d2327; color: #50c878; }
            h1 { color: #fff; }
            pre { background: #0a0a0a; padding: 20px; border-radius: 8px; font-size: 14px; overflow-x: auto; }
            a { color: #4facfe; }
        </style>
    </head>
    <body>
        <h1>🔐 HWS Login Masking: <?php echo esc_html( $title ); ?></h1>
        <p><a href="<?php echo home_url(); ?>">← Back to site</a></p>
        <pre><?php echo esc_html( json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
    </body>
    </html>
    <?php
}


// ═══════════════════════════════════════════════════════════════════════════
// AJAX HANDLERS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_hws_toggle_login_urls', __NAMESPACE__ . '\\ajax_toggle_login_urls' );
add_action( 'wp_ajax_hws_clear_login_log', __NAMESPACE__ . '\\ajax_clear_login_log' );
add_action( 'wp_ajax_hws_login_mask_action', __NAMESPACE__ . '\\ajax_login_mask_action' );

/**
 * AJAX: Toggle login mask public URLs on/off
 */
function ajax_toggle_login_urls() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;
    update_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_URLS_ENABLED' ), $enabled ? 'yes' : 'no' );
    wp_send_json_success( [ 'enabled' => $enabled ] );
}

/**
 * AJAX: Clear the login activity log
 */
function ajax_clear_login_log() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    hws_login_log_clear();
    wp_send_json_success( [ 'message' => 'Log cleared' ] );
}

/**
 * AJAX: Run a login mask action from the dashboard
 */
function ajax_login_mask_action() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $action_type = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : '';

    switch ( $action_type ) {
        case 'disable':
            $result = run_login_disable();
            break;
        case 'enable':
            $result = run_login_enable();
            break;
        case 'flush':
            $result = run_login_flush();
            break;
        case 'status':
            $result = run_login_status();
            break;
        default:
            wp_send_json_error( 'Unknown action: ' . $action_type );
            return;
    }

    wp_send_json_success( $result );
}


// ═══════════════════════════════════════════════════════════════════════════
// LOG INTEGRATION — Hook into Login_Masking events
// ═══════════════════════════════════════════════════════════════════════════

// — Log blocked access attempts
add_action( 'template_redirect', function() {
    // — Only log if masking is active and this is a blocked request
    if ( ! class_exists( __NAMESPACE__ . '\\Login_Masking' ) ) return;
    $opts = Login_Masking::opts();
    if ( empty( $opts['enabled'] ) ) return;

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // — Log wp-login.php blocks
    if ( strpos( $uri, 'wp-login.php' ) !== false && ! is_user_logged_in() ) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        // — Don't log WP Toolkit if compatibility is on
        if ( ! empty( $opts['compat_wptoolkit'] ) && stripos( $ua, 'WP Toolkit' ) !== false ) return;
        hws_login_log( 'block', 'Blocked wp-login.php access', [ 'ua' => substr( $ua, 0, 100 ) ] );
    }

    // — Log wp-admin blocks (non-ajax)
    if ( ! empty( $opts['hide_wp_admin'] ) && strpos( $uri, '/wp-admin' ) !== false
         && strpos( $uri, 'admin-ajax.php' ) === false
         && strpos( $uri, 'async-upload.php' ) === false
         && ! is_user_logged_in() ) {
        hws_login_log( 'block', 'Blocked wp-admin access' );
    }
}, 0 );

// — Log successful masked login page loads
add_action( 'login_init', function() {
    if ( ! class_exists( __NAMESPACE__ . '\\Login_Masking' ) ) return;
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $slug = Login_Masking::slug();
    if ( strpos( $uri, $slug ) !== false ) {
        hws_login_log( 'info', 'Masked login page accessed (slug: ' . $slug . ')' );
    }
} );

// — Log emergency bypass/repair
add_action( 'init', function() {
    if ( isset( $_GET['hws'] ) ) {
        $action = sanitize_key( $_GET['hws'] );
        if ( in_array( $action, [ 'bypass', 'repair' ], true ) ) {
            hws_login_log( 'warning', 'Emergency action triggered: ' . $action );
        }
    }
}, 0 );


// ═══════════════════════════════════════════════════════════════════════════
// DASHBOARD TAB RENDERER
// ═══════════════════════════════════════════════════════════════════════════

function display_settings_masked_login() {
    // — Get Login_Masking data
    $class_ok = class_exists( __NAMESPACE__ . '\\Login_Masking' );
    $opts     = $class_ok ? Login_Masking::opts() : [];
    $slug     = $class_ok ? Login_Masking::slug() : 'unknown';
    $login_url = $class_ok ? Login_Masking::login_url() : home_url( '/' . $slug . '/' );
    $urls_on   = are_login_urls_enabled();
    $secret    = hws_login_secret();
    $url_keys  = get_login_url_keys();
    $site      = home_url( '/' );
    $log       = hws_login_log_get( 100 );
    $settings_link = admin_url( 'options-general.php?page=hws-login-masking' );
    $json_url  = home_url( '/.well-known/hws-login.json' );
    ?>

    <style>
        /* — Masked Login Tab Styles — */
        .hws-ml-intro { background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); color: #fff; padding: 20px 25px; border-radius: 8px; margin-bottom: 25px; }
        .hws-ml-intro h3 { margin: 0 0 8px; color: #fff; font-size: 18px; }
        .hws-ml-intro p { margin: 0; opacity: .9; font-size: 14px; }
        .hws-ml-section { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
        .hws-ml-section-header { background: #f8f9fa; padding: 14px 20px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .hws-ml-section-header h4 { margin: 0; font-size: 15px; font-weight: 600; }
        .hws-ml-section-body { padding: 15px 20px; }
        .hws-ml-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .hws-ml-stat { padding: 10px 14px; background: #f8f9fa; border-radius: 6px; font-size: 13px; }
        .hws-ml-stat-label { font-weight: 600; color: #1d2327; margin-bottom: 3px; }
        .hws-ml-stat-value { color: #646970; word-break: break-all; }
        .hws-ml-stat-value a { color: #0073aa; }
        .hws-ml-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .hws-ml-badge-on { background: #d4edda; color: #155724; }
        .hws-ml-badge-off { background: #f8d7da; color: #721c24; }
        .hws-ml-btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .hws-ml-btn-primary { background: #6c5ce7; color: #fff; }
        .hws-ml-btn-primary:hover { background: #5a4bd1; }
        .hws-ml-btn-danger { background: #d63638; color: #fff; }
        .hws-ml-btn-danger:hover { background: #b32d2e; }
        .hws-ml-btn-success { background: #00a32a; color: #fff; }
        .hws-ml-btn-success:hover { background: #008a20; }
        .hws-ml-btn:disabled { opacity: .6; cursor: not-allowed; }
        .hws-ml-log { background: #1d2327; color: #ccc; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; line-height: 1.8; }
        .hws-ml-log-entry { padding: 2px 0; border-bottom: 1px solid #333; }
        .hws-ml-log-time { color: #888; margin-right: 8px; }
        .hws-ml-log-type-info { color: #4facfe; }
        .hws-ml-log-type-success { color: #50c878; }
        .hws-ml-log-type-warning { color: #f39c12; }
        .hws-ml-log-type-error { color: #e74c3c; }
        .hws-ml-log-type-block { color: #e74c3c; font-weight: bold; }
        .hws-ml-log-ip { color: #888; font-size: 11px; }
        .hws-ml-url-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px; }
        .hws-ml-url-row code { background: #f0f0f1; padding: 4px 8px; border-radius: 3px; font-size: 12px; word-break: break-all; flex: 1; }
        .hws-ml-url-row a { white-space: nowrap; }
        .hws-ml-action-status { font-size: 13px; margin-top: 8px; }
    </style>

    <!-- Intro -->
    <div class="hws-ml-intro">
        <h3>🔐 Masked Login</h3>
        <p>Controls WordPress login URL masking, emergency bypass, and access logging. Full settings at <a href="<?php echo esc_url( $settings_link ); ?>" style="color:#fff;text-decoration:underline;">Settings → Login Masking</a>.</p>
    </div>

    <!-- ──────── Status Overview ──────── -->
    <div class="hws-ml-section">
        <div class="hws-ml-section-header">
            <h4>📊 Status Overview</h4>
            <span class="hws-ml-badge <?php echo ! empty( $opts['enabled'] ) ? 'hws-ml-badge-on' : 'hws-ml-badge-off'; ?>">
                <?php echo ! empty( $opts['enabled'] ) ? '✅ ACTIVE' : '❌ DISABLED'; ?>
            </span>
        </div>
        <div class="hws-ml-section-body">
            <div class="hws-ml-grid">
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">🔗 Login URL</div>
                    <div class="hws-ml-stat-value"><a href="<?php echo esc_url( $login_url ); ?>" target="_blank"><?php echo esc_html( $login_url ); ?></a></div>
                </div>
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">📝 Slug</div>
                    <div class="hws-ml-stat-value"><code><?php echo esc_html( $slug ); ?></code></div>
                </div>
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">🚫 Hide /wp-admin/</div>
                    <div class="hws-ml-stat-value"><?php echo ! empty( $opts['hide_wp_admin'] ) ? '✅ Yes (404 for guests)' : '❌ No'; ?></div>
                </div>
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">🔍 .well-known Discovery</div>
                    <div class="hws-ml-stat-value"><?php echo ! empty( $opts['well_known'] ) ? '✅ <a href="' . esc_url( $json_url ) . '" target="_blank">View JSON</a>' : '❌ Disabled'; ?></div>
                </div>
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">🛠️ WP Toolkit Compat</div>
                    <div class="hws-ml-stat-value"><?php echo ! empty( $opts['compat_wptoolkit'] ) ? '✅ Enabled' : '❌ Disabled'; ?></div>
                </div>
                <div class="hws-ml-stat">
                    <div class="hws-ml-stat-label">📋 IP Allowlist</div>
                    <div class="hws-ml-stat-value"><?php echo ! empty( $opts['allowlist_ips'] ) ? '<code>' . esc_html( $opts['allowlist_ips'] ) . '</code>' : '<em>None set</em>'; ?></div>
                </div>
            </div>

            <!-- Emergency URLs -->
            <div style="margin-top: 15px; padding: 12px; background: #fff8e5; border: 1px solid #f0c36d; border-radius: 6px;">
                <strong style="font-size: 13px;">🚨 Emergency URLs (always work, no password needed):</strong>
                <div style="margin-top: 8px; font-size: 13px;">
                    <div style="margin-bottom: 4px;"><code><a href="<?php echo esc_url( home_url( '/?hws=bypass' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/?hws=bypass' ) ); ?></a></code> — Access native login</div>
                    <div><code><a href="<?php echo esc_url( home_url( '/?hws=repair' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/?hws=repair' ) ); ?></a></code> — Fix rewrites & purge caches</div>
                </div>
            </div>

            <p style="margin:12px 0 0;font-size:12px;color:#646970;">🔧 <a href="<?php echo esc_url( $settings_link ); ?>">Full settings →</a> | <code>define('HWS_DISABLE_LOGIN_MASKING', true);</code> in wp-config.php to disable completely.</p>
        </div>
    </div>

    <!-- ──────── Quick Actions ──────── -->
    <div class="hws-ml-section">
        <div class="hws-ml-section-header">
            <h4>⚡ Quick Actions</h4>
        </div>
        <div class="hws-ml-section-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="hws-ml-btn hws-ml-btn-danger" data-ml-action="disable">🔓 Temporarily Disable Masking</button>
                <button type="button" class="hws-ml-btn hws-ml-btn-success" data-ml-action="enable">🔒 Re-enable Masking</button>
                <button type="button" class="hws-ml-btn hws-ml-btn-primary" data-ml-action="flush">🔄 Flush Permalinks</button>
                <button type="button" class="hws-ml-btn hws-ml-btn-primary" data-ml-action="status">📊 Check Status</button>
            </div>
            <div id="hws-ml-action-status" class="hws-ml-action-status"></div>
        </div>
    </div>

    <!-- ──────── Public URLs ──────── -->
    <div class="hws-ml-section">
        <div class="hws-ml-section-header">
            <h4>🔗 Public URLs (No Login Required)</h4>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input type="checkbox" id="hws-ml-urls-toggle" <?php checked( $urls_on ); ?>> Enabled
            </label>
        </div>
        <div class="hws-ml-section-body">
            <p style="font-size:13px;color:#646970;margin:0 0 12px;">These URLs use the master secret password and return JSON by default. Append <code>&format=html</code> or <code>&format=text</code> for other formats.</p>
            <div>
                <?php foreach ( $url_keys as $param => $cfg ) :
                    $full_url = $site . '?' . $param . '=' . $secret;
                ?>
                    <div class="hws-ml-url-row">
                        <strong style="min-width:220px;"><?php echo esc_html( $cfg['label'] ); ?>:</strong>
                        <code><?php echo esc_html( $full_url ); ?></code>
                        <a href="<?php echo esc_url( $full_url ); ?>" target="_blank">Open ↗</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ──────── Activity Log ──────── -->
    <div class="hws-ml-section">
        <div class="hws-ml-section-header">
            <h4>📋 Activity Log (<?php echo count( $log ); ?> entries)</h4>
            <button type="button" class="hws-ml-btn hws-ml-btn-danger" id="hws-ml-clear-log" style="font-size:12px;padding:4px 10px;">🗑️ Clear Log</button>
        </div>
        <div class="hws-ml-section-body">
            <?php if ( empty( $log ) ) : ?>
                <p style="color:#646970;font-style:italic;">No activity logged yet. Events like blocked access attempts, emergency actions, and public URL usage will appear here.</p>
            <?php else : ?>
                <div class="hws-ml-log">
                    <?php foreach ( $log as $entry ) :
                        $type_class = 'hws-ml-log-type-' . ( $entry['type'] ?? 'info' );
                        $time = isset( $entry['time'] ) ? date( 'M j H:i:s', strtotime( $entry['time'] ) ) : '?';
                        $ip   = $entry['ip'] ?? '';
                        $ua   = isset( $entry['ua'] ) ? ' | UA: ' . esc_html( $entry['ua'] ) : '';
                    ?>
                        <div class="hws-ml-log-entry">
                            <span class="hws-ml-log-time"><?php echo esc_html( $time ); ?></span>
                            <span class="<?php echo esc_attr( $type_class ); ?>">[<?php echo esc_html( strtoupper( $entry['type'] ?? 'INFO' ) ); ?>]</span>
                            <?php echo esc_html( $entry['message'] ?? '' ); ?>
                            <?php if ( $ip ) : ?><span class="hws-ml-log-ip">(<?php echo esc_html( $ip ); ?><?php echo $ua; ?>)</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ──────── Inline JS ──────── -->
    <script>
    jQuery(document).ready(function($) {
        var $status = $('#hws-ml-action-status');

        // — Quick action buttons
        $(document).on('click', '[data-ml-action]', function() {
            var $btn = $(this);
            var action = $btn.data('ml-action');
            var origText = $btn.text();

            $btn.prop('disabled', true).text('Working...');
            $status.html('<span style="color:#646970;">⏳ Processing...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_login_mask_action',
                    action_type: action,
                    nonce: hwsNonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).text(origText);
                    if (response.success) {
                        var d = response.data;
                        var msg = d.message || JSON.stringify(d);
                        $status.html('<span style="color:#00a32a;">✅ ' + msg + '</span>');
                        // — Reload after 1.5s to update status display
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $status.html('<span style="color:#d63638;">❌ ' + (response.data || 'Failed') + '</span>');
                    }
                },
                error: function(xhr, st, err) {
                    $btn.prop('disabled', false).text(origText);
                    $status.html('<span style="color:#d63638;">❌ AJAX Error: ' + err + '</span>');
                }
            });
        });

        // — Toggle public URLs
        $('#hws-ml-urls-toggle').on('change', function() {
            var enabled = $(this).prop('checked') ? 1 : 0;
            $.post(ajaxurl, {
                action: 'hws_toggle_login_urls',
                enabled: enabled,
                nonce: hwsNonce
            });
        });

        // — Clear log
        $('#hws-ml-clear-log').on('click', function() {
            if (!confirm('Clear all login activity log entries?')) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'hws_clear_login_log',
                nonce: hwsNonce
            }, function(response) {
                if (response.success) location.reload();
                else $btn.prop('disabled', false);
            });
        });
    });
    </script>

    <?php
}
