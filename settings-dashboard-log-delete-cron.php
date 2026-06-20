<?php namespace hws_base_tools;

/**
 * Log File Cleaner with Cron Management
 * 
 * Features:
 * - Auto-enabled by default
 * - Configurable interval (1-30 days)
 * - Configurable size limit (1-500 MB)
 * - Detailed cron job status reporting
 * - Activity logging with reports
 * 
 * @since 8.9.5.3
 * @updated 8.9.5.6 - Added comprehensive cron status display
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configuration class for Log Cleaner
 */
class Log_Cleaner_Config {
    // Option keys
    const OPT_ENABLED      = 'hws_log_cleaner_enabled';
    const OPT_INTERVAL     = 'hws_log_cleaner_interval';
    const OPT_SIZE_LIMIT   = 'hws_log_cleaner_size_limit';
    const OPT_LAST_RUN     = 'hws_log_cleaner_last_run';
    const OPT_LAST_REPORT  = 'hws_log_cleaner_last_report';
    const OPT_FIRST_RUN    = 'hws_log_cleaner_first_run_complete';
    
    // Cron hook name
    const CRON_HOOK        = 'hws_log_cleaner_cron';
    
    // Defaults
    const DEFAULT_ENABLED    = true;
    const DEFAULT_INTERVAL   = 5;
    const DEFAULT_SIZE_LIMIT = 10;
    
    /**
     * Get all settings with defaults
     */
    public static function get_settings() {
        return [
            'enabled'     => get_option( self::OPT_ENABLED, self::DEFAULT_ENABLED ),
            'interval'    => (int) get_option( self::OPT_INTERVAL, self::DEFAULT_INTERVAL ),
            'size_limit'  => (int) get_option( self::OPT_SIZE_LIMIT, self::DEFAULT_SIZE_LIMIT ),
            'last_run'    => get_option( self::OPT_LAST_RUN, 'Never' ),
            'last_report' => get_option( self::OPT_LAST_REPORT, [] ),
        ];
    }
    
    /**
     * Get log file paths
     */
    public static function get_log_paths() {
        return [
            'debug_log' => WP_CONTENT_DIR . '/debug.log',
            'error_log' => ABSPATH . 'error_log',
            'admin_log' => ABSPATH . 'wp-admin/error_log',
        ];
    }
}


/**
 * Initialize log cleaner on plugin load
 */
function hws_log_cleaner_init() {
    // Register AJAX handlers
    add_action( 'wp_ajax_hws_base_tools_toggle_auto_delete', __NAMESPACE__ . '\\hws_log_cleaner_ajax_toggle' );
    add_action( 'wp_ajax_hws_base_tools_update_log_settings', __NAMESPACE__ . '\\hws_log_cleaner_ajax_update_settings' );
    add_action( 'wp_ajax_delete_debug_log', __NAMESPACE__ . '\\hws_log_cleaner_ajax_delete_debug' );
    add_action( 'wp_ajax_delete_error_log', __NAMESPACE__ . '\\hws_log_cleaner_ajax_delete_error' );
    add_action( 'wp_ajax_hws_base_tools_run_log_cleaner', __NAMESPACE__ . '\\hws_log_cleaner_ajax_run_now' );
    add_action( 'wp_ajax_hws_get_log_cleaner_state', __NAMESPACE__ . '\\hws_log_cleaner_ajax_get_state' );
    
    // Register cron hook
    add_action( Log_Cleaner_Config::CRON_HOOK, __NAMESPACE__ . '\\hws_log_cleaner_run' );
    
    // First run setup
    if ( ! get_option( Log_Cleaner_Config::OPT_FIRST_RUN ) ) {
        hws_log_cleaner_first_run();
    }
}
add_action( 'init', __NAMESPACE__ . '\\hws_log_cleaner_init' );

/**
 * AJAX: Return fresh dashboard state for the log cleaner tab.
 */
function hws_log_cleaner_ajax_get_state() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    hws_require_ajax_nonce_or_error();

    wp_send_json_success( hws_log_cleaner_get_status() );
}


/**
 * First-run setup - auto-enable with defaults
 */
function hws_log_cleaner_first_run() {
    update_option( Log_Cleaner_Config::OPT_ENABLED, Log_Cleaner_Config::DEFAULT_ENABLED );
    update_option( Log_Cleaner_Config::OPT_INTERVAL, Log_Cleaner_Config::DEFAULT_INTERVAL );
    update_option( Log_Cleaner_Config::OPT_SIZE_LIMIT, Log_Cleaner_Config::DEFAULT_SIZE_LIMIT );
    update_option( Log_Cleaner_Config::OPT_FIRST_RUN, true );
    
    // Schedule if enabled by default
    if ( Log_Cleaner_Config::DEFAULT_ENABLED ) {
        hws_log_cleaner_schedule();
    }
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[HWS Log Cleaner] First run setup complete - auto-enabled' );
    }
}


/**
 * Schedule the cron job
 */
function hws_log_cleaner_schedule() {
    $interval_days = (int) get_option( Log_Cleaner_Config::OPT_INTERVAL, Log_Cleaner_Config::DEFAULT_INTERVAL );
    $interval_seconds = $interval_days * DAY_IN_SECONDS;

    $scheduled = \Hexa\PluginCore\WpCronTasks\WpCronTask::schedule_interval(
        Log_Cleaner_Config::CRON_HOOK,
        'hws_log_cleaner_interval',
        $interval_seconds,
        sprintf( 'Every %d days (HWS Log Cleaner)', $interval_days )
    );

    if ( $scheduled && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "[HWS Log Cleaner] Cron scheduled: every {$interval_days} days" );
    }
}


/**
 * Unschedule the cron job
 */
function hws_log_cleaner_unschedule() {
    \Hexa\PluginCore\WpCronTasks\WpCronTask::unschedule_hook( Log_Cleaner_Config::CRON_HOOK );
}


/**
 * Run the log cleaner
 */
function hws_log_cleaner_run() {
    $settings  = Log_Cleaner_Config::get_settings();
    $log_paths = Log_Cleaner_Config::get_log_paths();
    $size_limit_bytes = $settings['size_limit'] * 1024 * 1024;
    
    $report = [
        'run_time'      => current_time( 'mysql' ),
        'size_limit'    => $settings['size_limit'] . ' MB',
        'files_checked' => [],
        'files_deleted' => [],
        'errors'        => [],
    ];
    
    foreach ( $log_paths as $key => $path ) {
        $file_info = [
            'path'    => $path,
            'exists'  => file_exists( $path ),
            'size'    => 0,
            'deleted' => false,
        ];
        
        if ( $file_info['exists'] ) {
            $file_info['size'] = filesize( $path );
            
            // Check if exceeds limit
            if ( $file_info['size'] > $size_limit_bytes ) {
                if ( hws_log_cleaner_delete_file( $path ) ) {
                    $file_info['deleted'] = true;
                    $report['files_deleted'][] = [
                        'file' => $key,
                        'size' => size_format( $file_info['size'] ),
                    ];
                } else {
                    $report['errors'][] = "Failed to delete {$key}";
                }
            }
        }
        
        $report['files_checked'][$key] = $file_info;
    }
    
    // Save report
    update_option( Log_Cleaner_Config::OPT_LAST_RUN, current_time( 'mysql' ) );
    update_option( Log_Cleaner_Config::OPT_LAST_REPORT, $report );
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[HWS Log Cleaner] Run complete. Deleted: ' . count( $report['files_deleted'] ) . ' files' );
    }
    
    return $report;
}


/**
 * Safely delete a log file
 */
function hws_log_cleaner_delete_file( $path ) {
    if ( ! file_exists( $path ) ) {
        return true;
    }
    
    if ( ! is_writable( $path ) ) {
        return false;
    }
    
    return @unlink( $path );
}


/**
 * AJAX: Toggle auto-delete on/off
 */
function hws_log_cleaner_ajax_toggle() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        hws_require_ajax_nonce_or_error();
        
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $enabled = ( $status === 'enabled' );
        
        update_option( Log_Cleaner_Config::OPT_ENABLED, $enabled );
        
        if ( $enabled ) {
            hws_log_cleaner_schedule();
            wp_send_json_success( [ 'message' => 'Auto-delete enabled and scheduled' ] );
        } else {
            hws_log_cleaner_unschedule();
            wp_send_json_success( [ 'message' => 'Auto-delete disabled' ] );
        }
    } catch ( \Exception $e ) {
        wp_send_json_error( 'Error: ' . $e->getMessage() );
    }
}


/**
 * AJAX: Update settings
 */
function hws_log_cleaner_ajax_update_settings() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            return;
        }

        hws_require_ajax_nonce_or_error();
        
        $interval   = isset( $_POST['interval'] ) ? absint( $_POST['interval'] ) : Log_Cleaner_Config::DEFAULT_INTERVAL;
        $size_limit = isset( $_POST['size_limit'] ) ? absint( $_POST['size_limit'] ) : Log_Cleaner_Config::DEFAULT_SIZE_LIMIT;
        
        // Validate ranges
        $interval   = max( 1, min( 30, $interval ) );
        $size_limit = max( 1, min( 500, $size_limit ) );
        
        update_option( Log_Cleaner_Config::OPT_INTERVAL, $interval );
        update_option( Log_Cleaner_Config::OPT_SIZE_LIMIT, $size_limit );
        
        // Reschedule if enabled
        if ( get_option( Log_Cleaner_Config::OPT_ENABLED ) ) {
            hws_log_cleaner_schedule();
        }
        
        wp_send_json_success( [
            'interval'   => $interval,
            'size_limit' => $size_limit,
            'message'    => 'Settings updated',
        ] );
    } catch ( \Exception $e ) {
        wp_send_json_error( [ 'message' => 'Error: ' . $e->getMessage() ] );
    }
}


/**
 * AJAX: Delete debug.log manually
 */
function hws_log_cleaner_ajax_delete_debug() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        hws_require_ajax_nonce_or_error();
        
        $log_paths = Log_Cleaner_Config::get_log_paths();
        $path = $log_paths['debug_log'];
        
        if ( ! file_exists( $path ) ) {
            wp_send_json_error( 'debug.log does not exist' );
            return;
        }
        
        $size = size_format( filesize( $path ) );
        
        if ( hws_log_cleaner_delete_file( $path ) ) {
            wp_send_json_success( "debug.log deleted (was $size)" );
        } else {
            wp_send_json_error( 'Failed to delete debug.log' );
        }
    } catch ( \Exception $e ) {
        wp_send_json_error( 'Error: ' . $e->getMessage() );
    }
}


/**
 * AJAX: Delete error_log manually
 */
function hws_log_cleaner_ajax_delete_error() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        hws_require_ajax_nonce_or_error();
        
        $log_paths = Log_Cleaner_Config::get_log_paths();
        $path = $log_paths['error_log'];
        
        if ( ! file_exists( $path ) ) {
            wp_send_json_error( 'error_log does not exist' );
            return;
        }
        
        $size = size_format( filesize( $path ) );
        
        if ( hws_log_cleaner_delete_file( $path ) ) {
            wp_send_json_success( "error_log deleted (was $size)" );
        } else {
            wp_send_json_error( 'Failed to delete error_log' );
        }
    } catch ( \Exception $e ) {
        wp_send_json_error( 'Error: ' . $e->getMessage() );
    }
}


/**
 * AJAX: Run the cleaner immediately
 */
function hws_log_cleaner_ajax_run_now() {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        hws_require_ajax_nonce_or_error();
        
        $report = hws_log_cleaner_run();
        
        wp_send_json_success( [
            'report'  => $report,
            'message' => sprintf( 
                'Checked %d files, deleted %d files', 
                count( $report['files_checked'] ), 
                count( $report['files_deleted'] ) 
            ),
        ] );
    } catch ( \Exception $e ) {
        wp_send_json_error( 'Error: ' . $e->getMessage() );
    }
}


/**
 * Cleanup on plugin deactivation
 */
function hws_log_cleaner_deactivate() {
    hws_log_cleaner_unschedule();
}
$hws_log_cleaner_main_file = defined( 'HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE' )
    ? HWS_BASE_TOOLS_CANONICAL_PLUGIN_FILE
    : dirname( __FILE__ ) . '/hws-base-tools.php';
register_deactivation_hook( $hws_log_cleaner_main_file, __NAMESPACE__ . '\\hws_log_cleaner_deactivate' );


/**
 * Get comprehensive cron job status
 * 
 * @return array Detailed cron status information
 */
function hws_log_cleaner_get_cron_status() {
    return \Hexa\PluginCore\WpCronTasks\WpCronTask::status(
        Log_Cleaner_Config::CRON_HOOK,
        [
            'callback'     => __NAMESPACE__ . '\\hws_log_cleaner_run',
            'schedule_key' => 'hws_log_cleaner_interval',
        ]
    );
}


/**
 * Get full status info for display in dashboard
 */
function hws_log_cleaner_get_status() {
    $settings   = Log_Cleaner_Config::get_settings();
    $log_paths  = Log_Cleaner_Config::get_log_paths();
    $cron_status = hws_log_cleaner_get_cron_status();
    
    $status = [
        'enabled'     => $settings['enabled'],
        'interval'    => $settings['interval'],
        'size_limit'  => $settings['size_limit'],
        'last_run'    => $settings['last_run'],
        'last_report' => $settings['last_report'],
        'cron'        => $cron_status,
        'files'       => [],
    ];
    
    // Get current file info
    foreach ( $log_paths as $key => $path ) {
        $status['files'][$key] = [
            'path'       => $path,
            'exists'     => file_exists( $path ),
            'size'       => file_exists( $path ) ? size_format( filesize( $path ) ) : 'N/A',
            'size_bytes' => file_exists( $path ) ? filesize( $path ) : 0,
            'writable'   => file_exists( $path ) ? is_writable( $path ) : is_writable( dirname( $path ) ),
        ];
    }
    
    return $status;
}


/**
 * Display the Log Cleaner Dashboard UI
 */
function display_settings_log_cleaner() {
    $status = hws_log_cleaner_get_status();
    $size_limit_bytes = $status['size_limit'] * 1024 * 1024;
    $cron = $status['cron'];
    ?>
    <style>
        .hws-log-cleaner-dashboard {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .hws-log-cleaner-dashboard h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .hws-log-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .hws-log-stat-card {
            background: #f6f7f7;
            border-radius: 4px;
            padding: 12px;
            text-align: center;
            border-left: 4px solid #c3c4c7;
        }
        .hws-log-stat-card.status-enabled { border-left-color: #00a32a; }
        .hws-log-stat-card.status-disabled { border-left-color: #d63638; }
        .hws-log-stat-card.status-warning { border-left-color: #dba617; }
        .hws-log-stat-card.status-ok { border-left-color: #00a32a; }
        .hws-log-stat-card .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
        }
        .hws-log-stat-card .stat-label {
            font-size: 10px;
            color: #646970;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .hws-cron-status-box {
            background: #f0f6fc;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .hws-cron-status-box.cron-healthy {
            background: #edfaef;
            border-color: #00a32a;
        }
        .hws-cron-status-box.cron-unhealthy {
            background: #fcf0f1;
            border-color: #d63638;
        }
        .hws-cron-status-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .hws-cron-detail {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 13px;
        }
        .hws-cron-detail:last-child {
            border-bottom: none;
        }
        .hws-cron-detail .label {
            color: #646970;
        }
        .hws-cron-detail .value {
            font-weight: 500;
            font-family: monospace;
        }
        .hws-cron-detail .value.ok { color: #00a32a; }
        .hws-cron-detail .value.error { color: #d63638; }
        .hws-cron-detail .value.warning { color: #dba617; }
        .hws-log-settings-form {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .hws-log-settings-form label {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }
        .hws-log-settings-form input[type="number"] {
            width: 70px;
        }
        .hws-log-files-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .hws-log-files-table th,
        .hws-log-files-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .hws-log-files-table th {
            background: #f6f7f7;
            font-weight: 600;
        }
        .hws-log-progress-log {
            width: 100%;
            height: 120px;
            font-family: monospace;
            font-size: 12px;
            background: #1d2327;
            color: #50c878;
            padding: 10px;
            border-radius: 4px;
            resize: none;
            border: none;
            margin-top: 10px;
        }
        .hws-file-size-warning { color: #d63638; font-weight: 600; }
        .hws-file-size-ok { color: #00a32a; }
    </style>

    <div class="hws-log-cleaner-dashboard panel">
        <h2 class="panel-title">🧹 Log File Cleaner</h2>
        
        <!-- Status Cards -->
        <div class="hws-log-stats-grid">
            <div class="hws-log-stat-card <?php echo $status['enabled'] ? 'status-enabled' : 'status-disabled'; ?>" data-log-card="enabled">
                <div class="stat-value" data-log-stat="enabled"><?php echo $status['enabled'] ? '✅ ON' : '❌ OFF'; ?></div>
                <div class="stat-label">Auto-Clean</div>
            </div>
            <div class="hws-log-stat-card">
                <div class="stat-value" data-log-stat="interval"><?php echo $status['interval']; ?> days</div>
                <div class="stat-label">Interval</div>
            </div>
            <div class="hws-log-stat-card">
                <div class="stat-value" data-log-stat="size_limit"><?php echo $status['size_limit']; ?> MB</div>
                <div class="stat-label">Size Limit</div>
            </div>
            <div class="hws-log-stat-card <?php echo $cron['is_scheduled'] ? 'status-ok' : 'status-warning'; ?>" data-log-card="scheduled">
                <div class="stat-value" data-log-stat="scheduled"><?php echo $cron['is_scheduled'] ? '✅' : '⚠️'; ?></div>
                <div class="stat-label">Cron Scheduled</div>
            </div>
            <div class="hws-log-stat-card">
                <div class="stat-value" data-log-stat="last_run" style="font-size: 14px;"><?php echo $status['last_run'] !== 'Never' ? date( 'M j', strtotime( $status['last_run'] ) ) : 'Never'; ?></div>
                <div class="stat-label">Last Run</div>
            </div>
        </div>

        <!-- Cron Job Status Panel -->
        <div id="hws-log-cron-box" class="hws-cron-status-box <?php echo $cron['cron_healthy'] ? 'cron-healthy' : 'cron-unhealthy'; ?>">
            <h4>⏰ WordPress Cron Status</h4>
            
            <div class="hws-cron-detail">
                <span class="label">Hook Name:</span>
                <span id="hws-log-cron-hook" class="value"><?php echo esc_html( $cron['hook_name'] ); ?></span>
            </div>
            
            <div class="hws-cron-detail">
                <span class="label">Scheduled:</span>
                <span id="hws-log-cron-scheduled" class="value <?php echo $cron['is_scheduled'] ? 'ok' : 'error'; ?>">
                    <?php echo $cron['is_scheduled'] ? 'Yes ✅' : 'No ❌'; ?>
                </span>
            </div>
            
            <div class="hws-cron-detail">
                <span class="label">Next Run:</span>
                <span id="hws-log-cron-next" class="value <?php echo $cron['is_scheduled'] ? 'ok' : 'warning'; ?>">
                    <?php 
                    if ( $cron['next_run_datetime'] ) {
                        echo esc_html( $cron['next_run_datetime'] );
                        echo ' <small style="color: #666;">(' . esc_html( $cron['next_run_relative'] ) . ')</small>';
                    } else {
                        echo 'Not scheduled';
                    }
                    ?>
                </span>
            </div>
            
            <div class="hws-cron-detail">
                <span class="label">Callback Registered:</span>
                <span id="hws-log-cron-callback" class="value <?php echo $cron['callback_registered'] ? 'ok' : 'error'; ?>">
                    <?php echo $cron['callback_registered'] ? 'Yes ✅' : 'No ❌'; ?>
                </span>
            </div>
            
            <div class="hws-cron-detail">
                <span class="label">WP Cron Status:</span>
                <span id="hws-log-cron-wp" class="value <?php echo $cron['wp_cron_disabled'] ? 'warning' : 'ok'; ?>">
                    <?php 
                    if ( $cron['wp_cron_disabled'] ) {
                        echo 'Disabled ⚠️ <small>(DISABLE_WP_CRON is true)</small>';
                    } elseif ( $cron['alternate_cron'] ) {
                        echo 'Alternate Mode 🔄';
                    } else {
                        echo 'Normal ✅';
                    }
                    ?>
                </span>
            </div>
            
            <?php if ( $cron['event_count'] > 0 ) : ?>
            <div id="hws-log-cron-events-row" class="hws-cron-detail">
                <span class="label">Scheduled Events:</span>
                <span id="hws-log-cron-events" class="value"><?php echo $cron['event_count']; ?> event(s)</span>
            </div>
            <?php else : ?>
            <div id="hws-log-cron-events-row" class="hws-cron-detail" style="display:none;">
                <span class="label">Scheduled Events:</span>
                <span id="hws-log-cron-events" class="value"></span>
            </div>
            <?php endif; ?>
            
            <?php if ( ! $cron['cron_healthy'] ) : ?>
            <p id="hws-log-cron-issue" style="margin: 10px 0 0; padding: 8px; background: rgba(214,54,56,0.1); border-radius: 3px; font-size: 12px;">
                <strong>⚠️ Issue Detected:</strong>
                <?php 
                if ( $cron['wp_cron_disabled'] ) {
                    echo 'WP-Cron is disabled. Consider setting up a real server cron job or enable WP-Cron.';
                } elseif ( ! $cron['is_scheduled'] ) {
                    echo 'The cron job is not scheduled. Try toggling the auto-clean setting off and on again.';
                } elseif ( ! $cron['callback_registered'] ) {
                    echo 'The callback function is not registered. Try deactivating and reactivating the plugin.';
                }
                ?>
            </p>
            <?php else : ?>
            <p id="hws-log-cron-issue" style="display:none;margin: 10px 0 0; padding: 8px; background: rgba(214,54,56,0.1); border-radius: 3px; font-size: 12px;"></p>
            <?php endif; ?>
        </div>

        <!-- Current Log Files -->
        <h4>📁 Current Log Files</h4>
        <table class="hws-log-files-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Path</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $status['files'] as $key => $file ) : 
                    $exceeds_limit = $file['size_bytes'] > $size_limit_bytes;
                ?>
                <tr data-log-row="<?php echo esc_attr( $key ); ?>">
                    <td><strong><?php echo $key === 'debug_log' ? 'debug.log' : ( $key === 'admin_log' ? 'wp-admin/error_log' : 'error_log' ); ?></strong></td>
                    <td><code style="font-size: 11px;"><?php echo esc_html( $file['path'] ); ?></code></td>
                    <td data-log-file-status="<?php echo esc_attr( $key ); ?>">
                        <?php echo $file['exists'] ? '✅ Exists' : '❌ Not found'; ?>
                        <?php if ( $file['exists'] && ! $file['writable'] ) echo '<br><small style="color: #d63638;">⚠️ Not writable</small>'; ?>
                    </td>
                    <td data-log-file-size="<?php echo esc_attr( $key ); ?>" class="<?php echo $exceeds_limit ? 'hws-file-size-warning' : 'hws-file-size-ok'; ?>">
                        <?php echo $file['size']; ?>
                        <?php if ( $exceeds_limit ) echo ' ⚠️'; ?>
                    </td>
                    <td data-log-file-action="<?php echo esc_attr( $key ); ?>">
                        <?php if ( $file['exists'] ) : ?>
                        <button type="button" class="button button-small hws-delete-log-btn" 
                                data-log="<?php echo $key; ?>" data-size="<?php echo $file['size']; ?>">
                            🗑️ Delete
                        </button>
                        <?php else : ?>
                        <span style="color: #999;">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Settings Form -->
        <div class="hws-log-settings-form">
            <h4 style="margin-top: 0;">⚙️ Settings</h4>
            <form id="hws-log-cleaner-settings">
                <label>
                    <input type="checkbox" id="hws-log-enabled" <?php checked( $status['enabled'] ); ?>>
                    Enable Auto-Clean
                </label>
                <label>
                    Every 
                    <input type="number" id="hws-log-interval" min="1" max="30" value="<?php echo $status['interval']; ?>">
                    days
                </label>
                <label>
                    Delete when &gt; 
                    <input type="number" id="hws-log-size-limit" min="1" max="500" value="<?php echo $status['size_limit']; ?>">
                    MB
                </label>
                <br style="margin-bottom: 10px;">
                <button type="button" class="button button-primary" id="hws-save-log-settings">
                    💾 Save Settings
                </button>
                <button type="button" class="button" id="hws-run-log-cleaner-now">
                    ▶️ Run Now
                </button>
            </form>
        </div>

        <!-- Progress Log -->
        <h4>📋 Activity Log</h4>
        <textarea id="hws-log-cleaner-output" class="hws-log-progress-log" readonly placeholder="Activity log will appear here..."><?php 
            // Show last report if available
            if ( ! empty( $status['last_report'] ) && is_array( $status['last_report'] ) ) {
                $report = $status['last_report'];
                echo "═══ Last Run Report ═══\n";
                echo "Time: " . ( $report['run_time'] ?? 'Unknown' ) . "\n";
                echo "Size limit: " . ( $report['size_limit'] ?? 'Unknown' ) . "\n";
                if ( ! empty( $report['files_deleted'] ) ) {
                    echo "Deleted:\n";
                    foreach ( $report['files_deleted'] as $deleted ) {
                        echo "  ✓ " . $deleted['file'] . " (" . $deleted['size'] . ")\n";
                    }
                } else {
                    echo "No files deleted (all under limit)\n";
                }
                if ( ! empty( $report['errors'] ) ) {
                    echo "Errors:\n";
                    foreach ( $report['errors'] as $error ) {
                        echo "  ⚠️ " . $error . "\n";
                    }
                }
            } else {
                echo "No activity yet. Enable auto-clean or click 'Run Now'.\n";
            }
        ?></textarea>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var $output = $('#hws-log-cleaner-output');
        var logCleanerNonce = window.hwsNonce || '<?php echo esc_js( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>';

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function formatLogCleanerLastReport(report) {
            if (!report || typeof report !== 'object' || !Object.keys(report).length) {
                return 'No activity yet. Enable auto-clean or click \'Run Now\'.\n';
            }

            var lines = [];
            lines.push('═══ Last Run Report ═══');
            lines.push('Time: ' + (report.run_time || 'Unknown'));
            lines.push('Size limit: ' + (report.size_limit || 'Unknown'));

            if (report.files_deleted && report.files_deleted.length) {
                lines.push('Deleted:');
                report.files_deleted.forEach(function(file) {
                    lines.push('  ✓ ' + file.file + ' (' + file.size + ')');
                });
            } else {
                lines.push('No files deleted (all under limit)');
            }

            if (report.errors && report.errors.length) {
                lines.push('Errors:');
                report.errors.forEach(function(error) {
                    lines.push('  ⚠️ ' + error);
                });
            }

            return lines.join('\n') + '\n';
        }

        function formatLogCleanerIssue(cron) {
            if (cron.wp_cron_disabled) {
                return 'WP-Cron is disabled. Consider setting up a real server cron job or enable WP-Cron.';
            }
            if (!cron.is_scheduled) {
                return 'The cron job is not scheduled. Try toggling the auto-clean setting off and on again.';
            }
            if (!cron.callback_registered) {
                return 'The callback function is not registered. Try deactivating and reactivating the plugin.';
            }
            return '';
        }

        function getLogCleanerFileLabel(key) {
            if (key === 'debug_log') {
                return 'debug.log';
            }
            if (key === 'admin_log') {
                return 'wp-admin/error_log';
            }
            return 'error_log';
        }

        function applyLogCleanerState(state, updateOutput) {
            var cron = state.cron || {};
            var sizeLimitBytes = parseInt(state.size_limit, 10) * 1024 * 1024;

            $('[data-log-stat="enabled"]').text(state.enabled ? '✅ ON' : '❌ OFF');
            $('[data-log-stat="interval"]').text(state.interval + ' days');
            $('[data-log-stat="size_limit"]').text(state.size_limit + ' MB');
            $('[data-log-stat="scheduled"]').text(cron.is_scheduled ? '✅' : '⚠️');
            $('[data-log-stat="last_run"]').text(state.last_run && state.last_run !== 'Never'
                ? new Date(state.last_run.replace(' ', 'T')).toLocaleString([], { month: 'short', day: 'numeric' })
                : 'Never');

            $('[data-log-card="enabled"]')
                .toggleClass('status-enabled', !!state.enabled)
                .toggleClass('status-disabled', !state.enabled);
            $('[data-log-card="scheduled"]')
                .toggleClass('status-ok', !!cron.is_scheduled)
                .toggleClass('status-warning', !cron.is_scheduled);

            $('#hws-log-enabled').prop('checked', !!state.enabled);
            $('#hws-log-interval').val(state.interval);
            $('#hws-log-size-limit').val(state.size_limit);

            $('#hws-log-cron-box')
                .toggleClass('cron-healthy', !!cron.cron_healthy)
                .toggleClass('cron-unhealthy', !cron.cron_healthy);
            $('#hws-log-cron-hook').text(cron.hook_name || '');
            $('#hws-log-cron-scheduled')
                .removeClass('ok error warning')
                .addClass(cron.is_scheduled ? 'ok' : 'error')
                .text(cron.is_scheduled ? 'Yes ✅' : 'No ❌');
            $('#hws-log-cron-next')
                .removeClass('ok error warning')
                .addClass(cron.is_scheduled ? 'ok' : 'warning')
                .html(cron.next_run_datetime
                    ? escapeHtml(cron.next_run_datetime) + ' <small style="color: #666;">(' + escapeHtml(cron.next_run_relative || '') + ')</small>'
                    : 'Not scheduled');
            $('#hws-log-cron-callback')
                .removeClass('ok error warning')
                .addClass(cron.callback_registered ? 'ok' : 'error')
                .text(cron.callback_registered ? 'Yes ✅' : 'No ❌');
            $('#hws-log-cron-wp')
                .removeClass('ok error warning')
                .addClass(cron.wp_cron_disabled ? 'warning' : 'ok')
                .html(cron.wp_cron_disabled
                    ? 'Disabled ⚠️ <small>(DISABLE_WP_CRON is true)</small>'
                    : (cron.alternate_cron ? 'Alternate Mode 🔄' : 'Normal ✅'));

            $('#hws-log-cron-events-row').toggle(!!cron.event_count);
            $('#hws-log-cron-events').text((cron.event_count || 0) + ' event(s)');

            var issue = formatLogCleanerIssue(cron);
            $('#hws-log-cron-issue').toggle(!!issue).html(issue ? '<strong>⚠️ Issue Detected:</strong> ' + escapeHtml(issue) : '');

            $.each(state.files || {}, function(key, file) {
                var exceedsLimit = (file.size_bytes || 0) > sizeLimitBytes;
                var $statusCell = $('[data-log-file-status="' + key + '"]');
                var $sizeCell = $('[data-log-file-size="' + key + '"]');
                var $actionCell = $('[data-log-file-action="' + key + '"]');

                $statusCell.html(file.exists
                    ? '✅ Exists' + (!file.writable ? '<br><small style="color: #d63638;">⚠️ Not writable</small>' : '')
                    : '❌ Not found');

                $sizeCell
                    .toggleClass('hws-file-size-warning', exceedsLimit)
                    .toggleClass('hws-file-size-ok', !exceedsLimit)
                    .html(escapeHtml(file.size) + (exceedsLimit ? ' ⚠️' : ''));

                if (file.exists) {
                    $actionCell.html(
                        '<button type="button" class="button button-small hws-delete-log-btn" data-log="' + escapeHtml(key) + '" data-size="' + escapeHtml(file.size) + '">' +
                        '🗑️ Delete' +
                        '</button>'
                    );
                } else {
                    $actionCell.html('<span style="color: #999;">N/A</span>');
                }
            });

            if (updateOutput) {
                $output.val(formatLogCleanerLastReport(state.last_report));
            }
        }

        function refreshLogCleanerState(updateOutput) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_get_log_cleaner_state',
                    nonce: logCleanerNonce
                }
            }).done(function(response) {
                if (response && response.success && response.data) {
                    applyLogCleanerState(response.data, updateOutput);
                }
            });
        }
        
        function appendLog(message) {
            var timestamp = new Date().toLocaleTimeString();
            $output.val($output.val() + '\n[' + timestamp + '] ' + message);
            $output.scrollTop($output[0].scrollHeight);
        }
        
        // Toggle auto-clean
        $('#hws-log-enabled').on('change', function() {
            var enabled = $(this).is(':checked');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_toggle_auto_delete',
                    status: enabled ? 'enabled' : 'disabled',
                    nonce: logCleanerNonce
                },
                success: function(response) {
                    if (response.success) {
                        appendLog('Auto-clean ' + (enabled ? 'enabled ✅' : 'disabled ❌'));
                        refreshLogCleanerState(false);
                    } else {
                        appendLog('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    appendLog('AJAX error - please try again');
                }
            });
        });
        
        // Save settings
        $('#hws-save-log-settings').on('click', function() {
            var $btn = $(this);
            var interval = $('#hws-log-interval').val();
            var sizeLimit = $('#hws-log-size-limit').val();
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_update_log_settings',
                    interval: interval,
                    size_limit: sizeLimit,
                    nonce: logCleanerNonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    if (response.success) {
                        appendLog('Settings saved: every ' + interval + ' days, ' + sizeLimit + ' MB limit');
                        refreshLogCleanerState(false);
                    } else {
                        appendLog('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    appendLog('AJAX error - please try again');
                }
            });
        });
        
        // Run now
        $('#hws-run-log-cleaner-now').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Running...');
            appendLog('Starting log cleaner...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_base_tools_run_log_cleaner',
                    nonce: logCleanerNonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('▶️ Run Now');
                    if (response.success) {
                        appendLog('✅ ' + response.data.message);
                        if (response.data.report && response.data.report.files_deleted) {
                            response.data.report.files_deleted.forEach(function(f) {
                                appendLog('  Deleted: ' + f.file + ' (' + f.size + ')');
                            });
                        }
                        refreshLogCleanerState(true);
                    } else {
                        appendLog('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('▶️ Run Now');
                    appendLog('AJAX error - please try again');
                }
            });
        });
        
        // Delete individual log files
        $('.hws-delete-log-btn').on('click', function() {
            var $btn = $(this);
            var logType = $btn.data('log');
            var size = $btn.data('size');
            var action = logType === 'debug_log' ? 'delete_debug_log' : 'delete_error_log';
            
            if (!confirm('Delete ' + (logType === 'debug_log' ? 'debug.log' : 'error_log') + ' (' + size + ')?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Deleting...');
            appendLog('Deleting ' + logType + '...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: logCleanerNonce
                },
                success: function(response) {
                    if (response.success) {
                        appendLog('✅ ' + response.data);
                        refreshLogCleanerState(true);
                    } else {
                        $btn.prop('disabled', false).text('🗑️ Delete');
                        appendLog('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('🗑️ Delete');
                    appendLog('AJAX error - please try again');
                }
            });
        });
    });
    </script>
    <?php
}
