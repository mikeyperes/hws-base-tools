<?php namespace hws_base_tools;

use HWS\BaseTools\Security\RemoteActionPolicy;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

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

    return hws_get_master_secret();
}

/**
 * Check if masked login public URLs are enabled (default: YES)
 */
function are_login_urls_enabled(): bool {
    return RemoteActionPolicy::option_enabled( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_URLS_ENABLED' ) );
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
        $provided = isset( $_GET[ $param ] ) && ! is_array( $_GET[ $param ] )
            ? (string) wp_unslash( $_GET[ $param ] )
            : '';

        if ( '' !== $provided && hash_equals( $secret, $provided ) ) {
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
        <h1>HWS Login Masking: <?php echo esc_html( $title ); ?></h1>
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
add_action( 'wp_ajax_hws_get_login_mask_state', __NAMESPACE__ . '\\ajax_get_login_mask_state' );

/**
 * Build the dashboard state payload for the masked login tab.
 */
function hws_get_login_mask_state_payload(): array {
    $class_ok      = class_exists( __NAMESPACE__ . '\\Login_Masking' );
    $opts          = $class_ok ? Login_Masking::opts() : [];
    $slug          = $class_ok ? Login_Masking::slug() : 'unknown';
    $login_url     = $class_ok ? Login_Masking::login_url() : home_url( '/' . $slug . '/' );
    $urls_on       = are_login_urls_enabled();
    $secret        = $urls_on ? hws_login_secret() : '';
    $log           = hws_login_log_get( 100 );
    $settings_link = admin_url( 'options-general.php?page=hws-core-tools&tab=masked-login' );
    $json_url      = home_url( '/.well-known/hws-login.json' );
    $url_rows      = [];

    foreach ( $urls_on ? get_login_url_keys() : [] as $param => $cfg ) {
        $full_url    = home_url( '/' ) . '?' . $param . '=' . $secret;
        $url_rows[] = [
            'label' => $cfg['label'],
            'url'   => $full_url,
        ];
    }

    return [
        'enabled'           => ! empty( $opts['enabled'] ),
        'login_url'         => $login_url,
        'slug'              => $slug,
        'hide_wp_admin'     => ! empty( $opts['hide_wp_admin'] ),
        'well_known'        => ! empty( $opts['well_known'] ),
        'json_url'          => $json_url,
        'compat_wptoolkit'  => ! empty( $opts['compat_wptoolkit'] ),
        'allowlist_ips'     => $opts['allowlist_ips'] ?? '',
        'urls_enabled'      => $urls_on,
        'url_rows'          => $url_rows,
        'log_entries'       => array_values( $log ),
        'settings_link'     => $settings_link,
        'emergency_bypass'  => home_url( '/?hws=bypass' ),
        'emergency_repair'  => home_url( '/?hws=repair' ),
    ];
}

/**
 * AJAX: Return fresh masked login dashboard state.
 */
function ajax_get_login_mask_state() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success( hws_get_login_mask_state_payload() );
}

/**
 * AJAX: Toggle login mask public URLs on/off
 */
function ajax_toggle_login_urls() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    hws_require_ajax_nonce_or_error();
    $enabled = isset( $_POST['enabled'] ) && intval( $_POST['enabled'] ) === 1;
    if ( $enabled && ! RemoteActionPolicy::legacy_get_routes_allowed() ) {
        wp_send_json_error( [ 'message' => 'Legacy public routes are security-disabled.' ], 403 );
    }

    update_option( constant( __NAMESPACE__ . '\\HWS_OPT_LOGIN_URLS_ENABLED' ), $enabled ? 'yes' : 'no' );
    wp_send_json_success( [ 'enabled' => $enabled ] );
}

/**
 * AJAX: Clear the login activity log
 */
function ajax_clear_login_log() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    hws_require_ajax_nonce_or_error();
    hws_login_log_clear();
    wp_send_json_success( [ 'message' => 'Log cleared' ] );
}

/**
 * AJAX: Run a login mask action from the dashboard
 */
function ajax_login_mask_action() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    hws_require_ajax_nonce_or_error();

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
    $state         = hws_get_login_mask_state_payload();
    $mask_options  = class_exists( __NAMESPACE__ . '\\Login_Masking' ) ? Login_Masking::opts() : [];

    ob_start();
    CoreUi::render_assets();
    echo ob_get_clean();
    ?>

    <style>
        .hws-ml-shell{max-width:100%;min-width:0}.hws-ml-header{align-items:flex-start;background:#f7f9fc;border:1px solid var(--hpc-line);border-radius:8px;display:flex;gap:13px;justify-content:space-between;margin-bottom:14px;padding:16px}.hws-ml-header-main{align-items:flex-start;display:flex;gap:11px;min-width:0}.hws-ml-header .dashicons{color:var(--hpc-blue);font-size:24px;height:24px;width:24px}.hws-ml-header h3{font-size:17px;margin:0 0 5px}.hws-ml-header p{margin:0}.hws-ml-config-grid,.hws-ml-grid{display:grid;gap:0 18px;grid-template-columns:repeat(2,minmax(0,1fr))}.hws-ml-config-grid{gap:14px 18px}.hws-ml-config-grid .hpc-field{margin:0}.hws-ml-config-grid .hpc-field small{color:var(--hpc-muted);display:block;font-size:11px;line-height:1.45;margin-top:6px}.hws-ml-stat{border-bottom:1px solid #edf1f6;display:grid;gap:5px;padding:10px 0}.hws-ml-stat-label{color:var(--hpc-muted);font-size:11px;font-weight:750;text-transform:uppercase}.hws-ml-stat-value{font-size:12px;overflow-wrap:anywhere}.hws-ml-state{align-items:center;display:inline-flex;gap:6px}.hws-ml-state:before{background:var(--hpc-red);border-radius:999px;content:"";height:7px;width:7px}.hws-ml-state.is-on:before{background:var(--hpc-green)}.hws-ml-emergency{background:#fff9e8;border:1px solid #ead38b;border-radius:7px;margin-top:14px;padding:11px 12px}.hws-ml-emergency strong{display:block;font-size:12px;margin-bottom:7px}.hws-ml-emergency-row{display:grid;gap:8px;grid-template-columns:110px minmax(0,1fr);margin-top:5px}.hws-ml-emergency-row code{overflow-wrap:anywhere;white-space:normal}.hws-ml-url-row{align-items:center;border-bottom:1px solid #edf1f6;display:grid;font-size:12px;gap:8px;grid-template-columns:minmax(150px,220px) minmax(0,1fr) auto;padding:9px 0}.hws-ml-url-row code{overflow-wrap:anywhere;white-space:normal}.hws-ml-log{background:#172033;border-radius:7px;color:#dbe3ee;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;line-height:1.7;max-height:380px;overflow:auto;padding:12px}.hws-ml-log-entry{border-bottom:1px solid #2b374a;padding:4px 0}.hws-ml-log-time,.hws-ml-log-ip{color:#8f9db1}.hws-ml-log-time{margin-right:7px}.hws-ml-log-type-info{color:#7ab8ff}.hws-ml-log-type-success{color:#72d79a}.hws-ml-log-type-warning{color:#f4c96b}.hws-ml-log-type-error,.hws-ml-log-type-block{color:#ff8e9d}.hws-ml-action-status{font-size:12px;margin-top:9px}.hws-ml-message{font-weight:650}.hws-ml-message.success{color:var(--hpc-green)}.hws-ml-message.error{color:var(--hpc-red)}.hws-ml-message.pending{color:var(--hpc-muted)}.hws-ml-section-actions{align-items:center;display:flex;flex-wrap:wrap;gap:9px;justify-content:space-between;margin-bottom:12px}@media(max-width:760px){.hws-ml-header,.hws-ml-section-actions{display:grid}.hws-ml-config-grid,.hws-ml-grid{grid-template-columns:1fr}.hws-ml-url-row,.hws-ml-emergency-row{grid-template-columns:1fr}}
    </style>

    <div class="hpc-ui hws-ml-shell">
        <header class="hws-ml-header">
            <div class="hws-ml-header-main"><span class="dashicons dashicons-shield" aria-hidden="true"></span><div><h3>Masked Login</h3><p>Manage login URL masking, emergency access, remote controls, and the security activity log.</p></div></div>
            <a class="hpc-button secondary hpc-external" href="<?php echo esc_url( $state['login_url'] ); ?>" target="_blank" rel="noopener noreferrer">Open current login</a>
        </header>

        <?php ob_start(); ?>
        <form method="post" action="options.php" class="hws-ml-settings-form">
            <?php settings_fields( 'hws_login_mask_group' ); ?>
            <div class="hws-ml-config-grid">
                <div class="hpc-field"><span>Masked login</span><?php echo CoreUi::toggle( Login_Masking::OPT_KEY . '[enabled]', ! empty( $mask_options['enabled'] ), 'Enable masked login', [ 'id' => 'hws-ml-setting-enabled' ] ); ?><small>Routes WordPress login through the configured masked slug.</small></div>
                <label class="hpc-field" for="hws-ml-setting-slug"><span>Masked slug</span><input id="hws-ml-setting-slug" type="text" name="<?php echo esc_attr( Login_Masking::OPT_KEY ); ?>[slug]" value="<?php echo esc_attr( Login_Masking::slug() ); ?>" pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and dashes only"><small>Current path: <code><?php echo esc_html( home_url( '/' ) ); ?><span id="hws-ml-slug-preview"><?php echo esc_html( Login_Masking::slug() ); ?></span>/</code></small></label>
                <div class="hpc-field"><span>WordPress admin</span><?php echo CoreUi::toggle( Login_Masking::OPT_KEY . '[hide_wp_admin]', ! empty( $mask_options['hide_wp_admin'] ), 'Hide /wp-admin/ from guests', [ 'id' => 'hws-ml-setting-hide-admin' ] ); ?><small>Returns a 404 to logged-out visitors while preserving AJAX and async uploads.</small></div>
                <div class="hpc-field"><span>WP Toolkit</span><?php echo CoreUi::toggle( Login_Masking::OPT_KEY . '[compat_wptoolkit]', ! empty( $mask_options['compat_wptoolkit'] ), 'Allow WP Toolkit login redirects', [ 'id' => 'hws-ml-setting-toolkit' ] ); ?><small>Allows a valid WP Toolkit request to reach the masked login URL.</small></div>
                <label class="hpc-field" for="hws-ml-setting-allowlist"><span>IP allowlist</span><input id="hws-ml-setting-allowlist" type="text" name="<?php echo esc_attr( Login_Masking::OPT_KEY ); ?>[allowlist_ips]" value="<?php echo esc_attr( (string) ( $mask_options['allowlist_ips'] ?? '' ) ); ?>" placeholder="127.0.0.1, 10.0.0.0/8"><small>Comma-separated IP addresses or CIDR ranges permitted to follow the masked redirect.</small></label>
                <div class="hpc-field"><span>Discovery</span><?php echo CoreUi::toggle( Login_Masking::OPT_KEY . '[well_known]', ! empty( $mask_options['well_known'] ), 'Serve the .well-known discovery document', [ 'id' => 'hws-ml-setting-well-known' ] ); ?><small>Publishes <code>/.well-known/hws-login.json</code> with the current login metadata.</small></div>
            </div>
            <div class="hpc-actions hpc-actions-bottom"><button type="submit" class="hpc-button">Save Masked Login Settings</button></div>
        </form>
        <?php $configuration_body = (string) ob_get_clean(); echo CoreUi::collapsible( [ 'title' => 'Configuration', 'body_html' => $configuration_body, 'open' => true, 'persist_key' => 'hws-masked-login-configuration', 'query_state' => false ] ); ?>

        <?php ob_start(); ?>
        <div class="hws-ml-section-actions"><p class="hpc-small">Current login masking state and recovery paths.</p><span id="hws-ml-status-badge" class="hpc-pill <?php echo $state['enabled'] ? 'success' : 'danger'; ?>"><?php echo $state['enabled'] ? 'Active' : 'Disabled'; ?></span></div>
        <div class="hws-ml-grid">
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">Login URL</div><div id="hws-ml-login-url" class="hws-ml-stat-value"><a href="<?php echo esc_url( $state['login_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $state['login_url'] ); ?></a></div></div>
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">Slug</div><div id="hws-ml-slug" class="hws-ml-stat-value"><code><?php echo esc_html( $state['slug'] ); ?></code></div></div>
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">Hide /wp-admin/</div><div id="hws-ml-hide-admin" class="hws-ml-stat-value hws-ml-state <?php echo $state['hide_wp_admin'] ? 'is-on' : ''; ?>"><?php echo $state['hide_wp_admin'] ? 'Enabled; guests receive a 404' : 'Disabled'; ?></div></div>
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">Discovery document</div><div id="hws-ml-well-known" class="hws-ml-stat-value hws-ml-state <?php echo $state['well_known'] ? 'is-on' : ''; ?>"><?php echo $state['well_known'] ? '<a href="' . esc_url( $state['json_url'] ) . '" target="_blank" rel="noopener noreferrer">Enabled; view JSON</a>' : 'Disabled'; ?></div></div>
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">WP Toolkit compatibility</div><div id="hws-ml-toolkit" class="hws-ml-stat-value hws-ml-state <?php echo $state['compat_wptoolkit'] ? 'is-on' : ''; ?>"><?php echo $state['compat_wptoolkit'] ? 'Enabled' : 'Disabled'; ?></div></div>
            <div class="hws-ml-stat"><div class="hws-ml-stat-label">IP allowlist</div><div id="hws-ml-allowlist" class="hws-ml-stat-value"><?php echo ! empty( $state['allowlist_ips'] ) ? '<code>' . esc_html( $state['allowlist_ips'] ) . '</code>' : '<em>None set</em>'; ?></div></div>
        </div>
        <div class="hws-ml-emergency"><strong>Emergency recovery URLs</strong><div class="hws-ml-emergency-row"><span>Native login</span><code><a id="hws-ml-bypass-url" href="<?php echo esc_url( $state['emergency_bypass'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $state['emergency_bypass'] ); ?></a></code></div><div class="hws-ml-emergency-row"><span>Repair rewrites</span><code><a id="hws-ml-repair-url" href="<?php echo esc_url( $state['emergency_repair'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $state['emergency_repair'] ); ?></a></code></div></div>
        <p class="hpc-small"><code>define('HWS_DISABLE_LOGIN_MASKING', true);</code> disables login masking completely from <code>wp-config.php</code>.</p>
        <?php $status_body = (string) ob_get_clean(); echo CoreUi::collapsible( [ 'title' => 'Status & Access', 'body_html' => $status_body, 'meta_html' => CoreUi::pill( $state['enabled'] ? 'Active' : 'Disabled', $state['enabled'] ? 'success' : 'danger' ), 'open' => true, 'persist_key' => 'hws-masked-login-status', 'query_state' => false ] ); ?>

        <?php ob_start(); ?>
        <div class="hpc-actions"><button type="button" class="hpc-button danger" data-ml-action="disable">Temporarily disable</button><button type="button" class="hpc-button" data-ml-action="enable">Re-enable masking</button><button type="button" class="hpc-button secondary" data-ml-action="flush">Flush permalinks</button><button type="button" class="hpc-button secondary" data-ml-action="status">Check status</button></div><div id="hws-ml-action-status" class="hws-ml-action-status" aria-live="polite"></div>
        <?php $actions_body = (string) ob_get_clean(); echo CoreUi::collapsible( [ 'title' => 'Quick Actions', 'body_html' => $actions_body, 'open' => true, 'persist_key' => 'hws-masked-login-actions', 'query_state' => false ] ); ?>

        <?php ob_start(); ?>
        <div class="hws-ml-section-actions"><p class="hpc-small">Authenticated secret URLs return JSON by default. Add <code>&format=html</code> or <code>&format=text</code> for another format.</p><?php echo CoreUi::toggle( 'hws-ml-urls-toggle', (bool) $state['urls_enabled'], 'Remote URLs enabled' ); ?></div>
        <div id="hws-ml-public-url-list"><?php foreach ( $state['url_rows'] as $row ) : ?><div class="hws-ml-url-row"><strong><?php echo esc_html( $row['label'] ); ?></strong><code><?php echo esc_html( $row['url'] ); ?></code><a class="hpc-external" href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer">Open</a></div><?php endforeach; ?></div>
        <?php $urls_body = (string) ob_get_clean(); echo CoreUi::collapsible( [ 'title' => 'Remote Control URLs', 'body_html' => $urls_body, 'meta_html' => CoreUi::pill( $state['urls_enabled'] ? 'Enabled' : 'Disabled', $state['urls_enabled'] ? 'success' : 'warning' ), 'open' => false, 'persist_key' => 'hws-masked-login-urls', 'query_state' => false ] ); ?>

        <?php ob_start(); ?>
        <div class="hws-ml-section-actions"><p class="hpc-small">Blocked access, recovery actions, and remote URL use appear here.</p><button type="button" class="hpc-button danger" id="hws-ml-clear-log">Clear log</button></div>
        <div id="hws-ml-log-body"><?php if ( empty( $state['log_entries'] ) ) : ?><p class="hpc-small">No activity logged yet.</p><?php else : ?><div class="hws-ml-log"><?php foreach ( $state['log_entries'] as $entry ) : $type_class = 'hws-ml-log-type-' . ( $entry['type'] ?? 'info' ); $time = isset( $entry['time'] ) ? date( 'M j H:i:s', strtotime( $entry['time'] ) ) : '?'; $ip = $entry['ip'] ?? ''; $ua = isset( $entry['ua'] ) ? ' | UA: ' . esc_html( $entry['ua'] ) : ''; ?><div class="hws-ml-log-entry"><span class="hws-ml-log-time"><?php echo esc_html( $time ); ?></span><span class="<?php echo esc_attr( $type_class ); ?>">[<?php echo esc_html( strtoupper( $entry['type'] ?? 'INFO' ) ); ?>]</span> <?php echo esc_html( $entry['message'] ?? '' ); ?><?php if ( $ip ) : ?> <span class="hws-ml-log-ip">(<?php echo esc_html( $ip ); ?><?php echo $ua; ?>)</span><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div>
        <?php $log_body = (string) ob_get_clean(); echo CoreUi::collapsible( [ 'title' => 'Activity Log', 'body_html' => $log_body, 'meta_html' => '<span class="hpc-pill dark"><span id="hws-ml-log-count">' . count( $state['log_entries'] ) . '</span> entries</span>', 'open' => false, 'persist_key' => 'hws-masked-login-log', 'query_state' => false ] ); ?>
    </div>

    <!-- ──────── Inline JS ──────── -->
    <script>
    jQuery(document).ready(function($) {
        var $status = $('#hws-ml-action-status');

        $('#hws-ml-setting-slug').on('input', function() {
            var slug = String($(this).val() || '')
                .toLowerCase()
                .replace(/[^a-z0-9-]+/g, '-')
                .replace(/^-+|-+$/g, '');

            $('#hws-ml-slug-preview').text(slug || 'hexa-admin');
        });

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function renderLoginMaskLog(entries) {
            if (!entries || !entries.length) {
                return '<p style="color:#646970;font-style:italic;">No activity logged yet. Events like blocked access attempts, emergency actions, and public URL usage will appear here.</p>';
            }

            var html = '<div class="hws-ml-log">';

            entries.forEach(function(entry) {
                var time = entry.time ? new Date(entry.time).toLocaleString([], {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }) : '?';
                var type = (entry.type || 'info').toLowerCase();
                var suffix = '';

                if (entry.ip) {
                    suffix = ' <span class="hws-ml-log-ip">(' + escapeHtml(entry.ip);
                    if (entry.ua) {
                        suffix += ' | UA: ' + escapeHtml(entry.ua);
                    }
                    suffix += ')</span>';
                }

                html += '<div class="hws-ml-log-entry">';
                html += '<span class="hws-ml-log-time">' + escapeHtml(time) + '</span>';
                html += '<span class="hws-ml-log-type-' + escapeHtml(type) + '">[' + escapeHtml(type.toUpperCase()) + ']</span> ';
                html += escapeHtml(entry.message || '') + suffix;
                html += '</div>';
            });

            html += '</div>';

            return html;
        }

        function renderLoginMaskUrls(urlRows) {
            if (!urlRows || !urlRows.length) {
                return '<p class="hpc-small">No remote URLs available.</p>';
            }

            return urlRows.map(function(row) {
                return '<div class="hws-ml-url-row">' +
                    '<strong>' + escapeHtml(row.label) + '</strong>' +
                    '<code>' + escapeHtml(row.url) + '</code>' +
                    '<a class="hpc-external" href="' + escapeHtml(row.url) + '" target="_blank" rel="noopener noreferrer">Open</a>' +
                    '</div>';
            }).join('');
        }

        function message(tone, text) {
            return '<span class="hws-ml-message ' + escapeHtml(tone) + '">' + escapeHtml(text) + '</span>';
        }

        function applyLoginMaskState(state) {
            $('#hws-ml-status-badge')
                .toggleClass('success', !!state.enabled)
                .toggleClass('danger', !state.enabled)
                .text(state.enabled ? 'Active' : 'Disabled');

            $('#hws-ml-login-url').html('<a href="' + escapeHtml(state.login_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(state.login_url) + '</a>');
            $('#hws-ml-slug').html('<code>' + escapeHtml(state.slug) + '</code>');
            $('#hws-ml-hide-admin').toggleClass('is-on', !!state.hide_wp_admin).text(state.hide_wp_admin ? 'Enabled; guests receive a 404' : 'Disabled');
            $('#hws-ml-well-known').html(state.well_known
                ? '<a href="' + escapeHtml(state.json_url) + '" target="_blank" rel="noopener noreferrer">Enabled; view JSON</a>'
                : 'Disabled').toggleClass('is-on', !!state.well_known);
            $('#hws-ml-toolkit').toggleClass('is-on', !!state.compat_wptoolkit).text(state.compat_wptoolkit ? 'Enabled' : 'Disabled');
            $('#hws-ml-allowlist').html(state.allowlist_ips ? '<code>' + escapeHtml(state.allowlist_ips) + '</code>' : '<em>None set</em>');
            $('#hws-ml-bypass-url').attr('href', state.emergency_bypass).text(state.emergency_bypass);
            $('#hws-ml-repair-url').attr('href', state.emergency_repair).text(state.emergency_repair);
            $('#hws-ml-urls-toggle').prop('checked', !!state.urls_enabled);
            $('#hws-ml-public-url-list').html(renderLoginMaskUrls(state.url_rows));
            $('#hws-ml-log-count').text((state.log_entries || []).length);
            $('#hws-ml-log-body').html(renderLoginMaskLog(state.log_entries || []));
        }

        function refreshLoginMaskState() {
            return $.post(ajaxurl, {
                action: 'hws_get_login_mask_state',
                nonce: hwsNonce
            }).done(function(response) {
                if (response && response.success && response.data) {
                    applyLoginMaskState(response.data);
                }
            });
        }

        // Quick action buttons
        $(document).on('click', '[data-ml-action]', function() {
            var $btn = $(this);
            var action = $btn.data('ml-action');
            var origText = $btn.text();

            $btn.prop('disabled', true).text('Working...');
            $status.html(message('pending', 'Processing...'));

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
                        $status.html(message('success', msg));
                        refreshLoginMaskState();
                    } else {
                        $status.html(message('error', response.data || 'Action failed.'));
                    }
                },
                error: function(xhr, st, err) {
                    $btn.prop('disabled', false).text(origText);
                    $status.html(message('error', 'AJAX error: ' + err));
                }
            });
        });

        // Toggle public URLs
        $('#hws-ml-urls-toggle').on('change', function() {
            var enabled = $(this).prop('checked') ? 1 : 0;
            $.post(ajaxurl, {
                action: 'hws_toggle_login_urls',
                enabled: enabled,
                nonce: hwsNonce
            }).done(function(response) {
                if (response && response.success) {
                    $status.html(message('success', 'Remote URLs ' + (enabled ? 'enabled.' : 'disabled.')));
                    refreshLoginMaskState();
                } else {
                    $status.html(message('error', 'Failed to update remote URLs.'));
                    $('#hws-ml-urls-toggle').prop('checked', !enabled);
                }
            }).fail(function() {
                $status.html(message('error', 'AJAX error.'));
                $('#hws-ml-urls-toggle').prop('checked', !enabled);
            });
        });

        // Clear log
        $('#hws-ml-clear-log').on('click', function() {
            if (!confirm('Clear all login activity log entries?')) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'hws_clear_login_log',
                nonce: hwsNonce
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.html(message('success', response.data.message || 'Log cleared.'));
                    refreshLoginMaskState();
                } else {
                    $status.html(message('error', response.data || 'Failed to clear log.'));
                }
            });
        });
    });
    </script>

    <?php
}
