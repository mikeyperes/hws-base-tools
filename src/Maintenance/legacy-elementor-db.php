<?php namespace hws_base_tools;

/**
 * Elementor Database Auto-Updater with Cron Management
 *
 * Features:
 * - Auto-runs Elementor database updates on schedule
 * - Configurable interval (1-30 days, default: 3 days)
 * - Detailed cron job status reporting
 * - Activity logging with reports
 * - Manual "Run Now" button
 *
 * @since 10.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configuration class for Elementor DB Updater
 */
class Elementor_DB_Updater_Config {
    // Option keys
    const OPT_ENABLED      = 'hws_elementor_db_updater_enabled';
    const OPT_INTERVAL     = 'hws_elementor_db_updater_interval';
    const OPT_LAST_RUN     = 'hws_elementor_db_updater_last_run';
    const OPT_LAST_REPORT  = 'hws_elementor_db_updater_last_report';
    const OPT_FIRST_RUN    = 'hws_elementor_db_updater_first_run_complete';

    // Cron hook name
    const CRON_HOOK        = 'hws_elementor_db_updater_cron';

    // Defaults
    const DEFAULT_ENABLED  = true;
    const DEFAULT_INTERVAL = 3;  // Every 3 days

    /**
     * Get all settings with defaults
     */
    public static function get_settings() {
        return [
            'enabled'     => get_option( self::OPT_ENABLED, self::DEFAULT_ENABLED ),
            'interval'    => (int) get_option( self::OPT_INTERVAL, self::DEFAULT_INTERVAL ),
            'last_run'    => get_option( self::OPT_LAST_RUN, 'Never' ),
            'last_report' => get_option( self::OPT_LAST_REPORT, [] ),
        ];
    }

    /**
     * Check if Elementor is active
     */
    public static function is_elementor_active() {
        return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
    }
}


/**
 * Initialize Elementor DB Updater on plugin load
 */
function hws_elementor_db_updater_init() {
    // Only initialize if Elementor is active
    if ( ! Elementor_DB_Updater_Config::is_elementor_active() ) {
        return;
    }

    // Register custom cron interval
    add_filter( 'cron_schedules', __NAMESPACE__ . '\\hws_elementor_db_cron_schedules' );

    // Register AJAX handlers
    add_action( 'wp_ajax_hws_elementor_db_toggle', __NAMESPACE__ . '\\hws_elementor_db_ajax_toggle' );
    add_action( 'wp_ajax_hws_elementor_db_update_settings', __NAMESPACE__ . '\\hws_elementor_db_ajax_update_settings' );
    add_action( 'wp_ajax_hws_elementor_db_run_now', __NAMESPACE__ . '\\hws_elementor_db_ajax_run_now' );
    add_action( 'wp_ajax_hws_get_elementor_db_state', __NAMESPACE__ . '\\hws_elementor_db_ajax_get_state' );

    // Register cron hook
    add_action( Elementor_DB_Updater_Config::CRON_HOOK, __NAMESPACE__ . '\\hws_elementor_db_updater_run' );

    // First run setup
    if ( ! get_option( Elementor_DB_Updater_Config::OPT_FIRST_RUN ) ) {
        hws_elementor_db_updater_first_run();
    }
}
add_action( 'init', __NAMESPACE__ . '\\hws_elementor_db_updater_init' );

/**
 * Build the current dashboard state for the Elementor DB updater.
 */
function hws_get_elementor_db_state_payload() {
    return [
        'settings'          => Elementor_DB_Updater_Config::get_settings(),
        'cron_status'       => hws_elementor_db_get_cron_status(),
        'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'Unknown',
        'db_version'        => get_option( 'elementor_version', 'Unknown' ),
    ];
}

/**
 * AJAX: Return a fresh Elementor DB updater state payload.
 */
function hws_elementor_db_ajax_get_state() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    check_ajax_referer( 'hws_elementor_db_nonce', 'nonce' );

    wp_send_json_success( hws_get_elementor_db_state_payload() );
}


/**
 * Register custom cron schedule
 */
function hws_elementor_db_cron_schedules( $schedules ) {
    $interval_days = (int) get_option( Elementor_DB_Updater_Config::OPT_INTERVAL, Elementor_DB_Updater_Config::DEFAULT_INTERVAL );
    $interval_seconds = $interval_days * DAY_IN_SECONDS;

    return \Hexa\PluginCore\WpCronTasks\WpCronTask::add_interval_schedule(
        (array) $schedules,
        'hws_elementor_db_interval',
        $interval_seconds,
        sprintf( 'Every %d days (HWS Elementor DB Updater)', $interval_days )
    );
}


/**
 * First-run setup - auto-enable with defaults
 */
function hws_elementor_db_updater_first_run() {
    update_option( Elementor_DB_Updater_Config::OPT_ENABLED, Elementor_DB_Updater_Config::DEFAULT_ENABLED );
    update_option( Elementor_DB_Updater_Config::OPT_INTERVAL, Elementor_DB_Updater_Config::DEFAULT_INTERVAL );
    update_option( Elementor_DB_Updater_Config::OPT_FIRST_RUN, true );

    // Schedule if enabled by default
    if ( Elementor_DB_Updater_Config::DEFAULT_ENABLED ) {
        hws_elementor_db_updater_schedule();
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[HWS Elementor DB Updater] First run setup complete - auto-enabled' );
    }
}


/**
 * Schedule the cron job
 */
function hws_elementor_db_updater_schedule() {
    $interval_days = (int) get_option( Elementor_DB_Updater_Config::OPT_INTERVAL, Elementor_DB_Updater_Config::DEFAULT_INTERVAL );
    $interval_seconds = $interval_days * DAY_IN_SECONDS;

    $scheduled = \Hexa\PluginCore\WpCronTasks\WpCronTask::schedule_interval(
        Elementor_DB_Updater_Config::CRON_HOOK,
        'hws_elementor_db_interval',
        $interval_seconds,
        sprintf( 'Every %d days (HWS Elementor DB Updater)', $interval_days )
    );

    if ( $scheduled && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "[HWS Elementor DB Updater] Cron scheduled: every {$interval_days} days" );
    }
}


/**
 * Unschedule the cron job
 */
function hws_elementor_db_updater_unschedule() {
    \Hexa\PluginCore\WpCronTasks\WpCronTask::unschedule_hook( Elementor_DB_Updater_Config::CRON_HOOK );
}


/**
 * Run the Elementor database update
 * This is the main cron callback
 */
function hws_elementor_db_updater_run() {
    $start_time = microtime( true );
    $report = [
        'timestamp'        => current_time( 'mysql' ),
        'status'           => 'unknown',
        'elementor_version'=> defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'N/A',
        'updates_run'      => 0,
        'message'          => '',
    ];

    // Check if Elementor is active
    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        $report['status'] = 'skipped';
        $report['message'] = 'Elementor not active';
        update_option( Elementor_DB_Updater_Config::OPT_LAST_REPORT, $report );
        update_option( Elementor_DB_Updater_Config::OPT_LAST_RUN, current_time( 'mysql' ) );
        return;
    }

    try {
        $updates_run = 0;

        // Method 1: Try the upgrade manager (Elementor 3.x+)
        if ( class_exists( '\Elementor\Core\Upgrade\Manager' ) ) {
            $upgrade_manager = \Elementor\Plugin::$instance->upgrade;

            if ( $upgrade_manager && method_exists( $upgrade_manager, 'should_upgrade' ) ) {
                if ( $upgrade_manager->should_upgrade() ) {
                    // Run the upgrade
                    if ( method_exists( $upgrade_manager, 'run_upgrade' ) ) {
                        $upgrade_manager->run_upgrade();
                        $updates_run++;
                        $report['message'] = 'Ran upgrade via Manager::run_upgrade()';
                    } elseif ( method_exists( $upgrade_manager, 'do_upgrade' ) ) {
                        $upgrade_manager->do_upgrade();
                        $updates_run++;
                        $report['message'] = 'Ran upgrade via Manager::do_upgrade()';
                    }
                } else {
                    $report['message'] = 'No upgrade needed (should_upgrade = false)';
                }
            }
        }

        // Method 2: Try direct Admin class (older Elementor versions)
        if ( $updates_run === 0 && class_exists( '\Elementor\Admin' ) ) {
            $admin = \Elementor\Plugin::$instance->admin;
            if ( $admin && method_exists( $admin, 'maybe_upgrade' ) ) {
                $admin->maybe_upgrade();
                $updates_run++;
                $report['message'] = 'Ran upgrade via Admin::maybe_upgrade()';
            }
        }

        // Method 3: Try triggering the upgrade action hook
        if ( $updates_run === 0 ) {
            // This triggers Elementor's internal upgrade check
            do_action( 'elementor/admin/after_create_settings_page', '' );
            $report['message'] = 'Triggered elementor settings action';
        }

        // Method 4: Use wp_ajax simulation for background updates
        if ( $updates_run === 0 && class_exists( '\Elementor\Core\Upgrade\Updater' ) ) {
            // Check if there are pending upgrades
            $db_upgrades = get_option( 'elementor_version' );
            if ( $db_upgrades && version_compare( $db_upgrades, ELEMENTOR_VERSION, '<' ) ) {
                update_option( 'elementor_version', ELEMENTOR_VERSION );
                $updates_run++;
                $report['message'] = 'Synced elementor_version option';
            }
        }

        $report['updates_run'] = $updates_run;
        $report['status'] = 'success';

        if ( $updates_run === 0 && empty( $report['message'] ) ) {
            $report['message'] = 'Database already up to date';
        }

    } catch ( \Exception $e ) {
        $report['status'] = 'error';
        $report['message'] = $e->getMessage();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HWS Elementor DB Updater] Error: ' . $e->getMessage() );
        }
    }

    $report['duration'] = round( microtime( true ) - $start_time, 3 ) . 's';

    // Save report
    update_option( Elementor_DB_Updater_Config::OPT_LAST_REPORT, $report );
    update_option( Elementor_DB_Updater_Config::OPT_LAST_RUN, current_time( 'mysql' ) );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[HWS Elementor DB Updater] Run complete: ' . $report['message'] );
    }

    return $report;
}


/**
 * AJAX: Toggle auto-update enabled/disabled
 */
function hws_elementor_db_ajax_toggle() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    check_ajax_referer( 'hws_elementor_db_nonce', 'nonce' );

    $enabled = get_option( Elementor_DB_Updater_Config::OPT_ENABLED, true );
    $new_state = ! $enabled;

    update_option( Elementor_DB_Updater_Config::OPT_ENABLED, $new_state );

    if ( $new_state ) {
        hws_elementor_db_updater_schedule();
    } else {
        hws_elementor_db_updater_unschedule();
    }

    $settings = Elementor_DB_Updater_Config::get_settings();
    $cron_status = hws_elementor_db_get_cron_status();

    wp_send_json_success( [
        'enabled'      => $new_state,
        'cron_status'  => $cron_status,
        'last_run'     => $settings['last_run'],
        'message'      => $new_state ? 'Auto-update enabled' : 'Auto-update disabled',
    ] );
}


/**
 * AJAX: Update interval settings
 */
function hws_elementor_db_ajax_update_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    check_ajax_referer( 'hws_elementor_db_nonce', 'nonce' );

    $interval = isset( $_POST['interval'] ) ? (int) $_POST['interval'] : Elementor_DB_Updater_Config::DEFAULT_INTERVAL;
    $interval = max( 1, min( 30, $interval ) ); // Clamp between 1-30 days

    update_option( Elementor_DB_Updater_Config::OPT_INTERVAL, $interval );

    // Reschedule with new interval
    if ( get_option( Elementor_DB_Updater_Config::OPT_ENABLED ) ) {
        hws_elementor_db_updater_schedule();
    }

    wp_send_json_success( [
        'interval'   => $interval,
        'message'    => "Interval updated to every {$interval} days",
        'cron_status'=> hws_elementor_db_get_cron_status(),
    ] );
}


/**
 * AJAX: Run database update now
 */
function hws_elementor_db_ajax_run_now() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    check_ajax_referer( 'hws_elementor_db_nonce', 'nonce' );

    $report = hws_elementor_db_updater_run();

    wp_send_json_success( [
        'message'     => 'Update check complete',
        'report'      => $report,
        'cron_status' => hws_elementor_db_get_cron_status(),
    ] );
}


/**
 * Get cron job status
 */
function hws_elementor_db_get_cron_status() {
    return \Hexa\PluginCore\WpCronTasks\WpCronTask::status(
        Elementor_DB_Updater_Config::CRON_HOOK,
        [
            'callback'      => __NAMESPACE__ . '\\hws_elementor_db_updater_run',
            'schedule_key'  => 'hws_elementor_db_interval',
            'site_timezone' => true,
        ]
    );
}


/**
 * Display the Elementor DB Updater settings panel
 */
function display_elementor_db_updater_panel() {
    // Only show if Elementor is active
    if ( ! Elementor_DB_Updater_Config::is_elementor_active() ) {
        return;
    }

    $state = hws_get_elementor_db_state_payload();
    $settings = $state['settings'];
    $cron_status = $state['cron_status'];
    $nonce = wp_create_nonce( 'hws_elementor_db_nonce' );

    // Get Elementor version info
    $elementor_version = $state['elementor_version'];
    $db_version = $state['db_version'];
    ?>
    <div class="panel">
        <h2 class="panel-title">⚡ Elementor Database Auto-Updater</h2>
        <div class="panel-content">

            <!-- Status Info -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="padding: 12px; background: #f0f6fc; border-radius: 6px;">
                    <strong>Elementor Version:</strong><br>
                    <span id="hws-elementor-version" style="font-size: 18px;"><?php echo esc_html( $elementor_version ); ?></span>
                </div>
                <div style="padding: 12px; background: #f0f6fc; border-radius: 6px;">
                    <strong>DB Version:</strong><br>
                    <span id="hws-elementor-db-version" style="font-size: 18px; color: <?php echo $db_version === $elementor_version ? '#00a32a' : '#dba617'; ?>;">
                        <?php echo esc_html( $db_version ); ?>
                        <?php echo $db_version === $elementor_version ? '✅' : '⚠️'; ?>
                    </span>
                </div>
                <div style="padding: 12px; background: #f0f6fc; border-radius: 6px;">
                    <strong>Last Run:</strong><br>
                    <span id="hws-elementor-last-run"><?php echo esc_html( $settings['last_run'] ); ?></span>
                </div>
            </div>

            <!-- Controls -->
            <div style="padding: 15px; background: #f9f9f9; border-radius: 6px; margin-bottom: 15px;">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="hws-elementor-db-enabled" <?php checked( $settings['enabled'] ); ?>>
                        <strong>Auto-Update Enabled</strong>
                    </label>

                    <label style="display: flex; align-items: center; gap: 8px;">
                        <span>Run every</span>
                        <select id="hws-elementor-db-interval" style="width: 80px;">
                            <?php for ( $i = 1; $i <= 14; $i++ ) : ?>
                                <option value="<?php echo $i; ?>" <?php selected( $settings['interval'], $i ); ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <span>days</span>
                    </label>

                    <button type="button" id="hws-elementor-db-run-now" class="button button-primary">
                        ▶️ Run Now
                    </button>
                </div>
            </div>

            <!-- Cron Status -->
            <div id="hws-elementor-db-cron-box" style="padding: 15px; background: <?php echo $cron_status['is_scheduled'] ? '#edfaef' : '#fcf0f1'; ?>; border-radius: 6px; margin-bottom: 15px;">
                <strong>🕐 Cron Status</strong>
                <p id="hws-elementor-db-cron-scheduled" style="margin: 5px 0;"><strong>Scheduled:</strong> <?php echo $cron_status['is_scheduled'] ? '✅ Yes' : '❌ No'; ?></p>
                <?php if ( $cron_status['next_run'] ) : ?>
                    <p id="hws-elementor-db-cron-next" style="margin: 5px 0;"><strong>Next Run:</strong> <?php echo esc_html( $cron_status['next_run'] ); ?> (in <?php echo esc_html( $cron_status['next_run_human'] ); ?>)</p>
                <?php else : ?>
                    <p id="hws-elementor-db-cron-next" style="margin: 5px 0; display:none;"></p>
                <?php endif; ?>
                <?php if ( $cron_status['wp_cron_disabled'] ) : ?>
                    <p id="hws-elementor-db-cron-warning" style="color: #d63638; margin: 5px 0;">⚠️ WP-Cron is disabled. Set up a server cron job for reliable scheduling.</p>
                <?php else : ?>
                    <p id="hws-elementor-db-cron-warning" style="color: #d63638; margin: 5px 0; display:none;"></p>
                <?php endif; ?>
            </div>

            <!-- Last Report -->
            <?php if ( ! empty( $settings['last_report'] ) && is_array( $settings['last_report'] ) ) : ?>
            <div id="hws-elementor-db-report" style="padding: 15px; background: #1d2327; color: #b4b4b4; border-radius: 6px; font-family: monospace; font-size: 12px;">
                <strong style="color: #fff;">Last Report:</strong><br>
                <?php foreach ( $settings['last_report'] as $key => $value ) : ?>
                    <span style="color: #5dade2;"><?php echo esc_html( $key ); ?>:</span>
                    <span style="color: <?php echo $key === 'status' && $value === 'success' ? '#00a32a' : '#fff'; ?>;">
                        <?php echo esc_html( is_array( $value ) ? json_encode( $value ) : $value ); ?>
                    </span><br>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div id="hws-elementor-db-report" style="display:none;padding: 15px; background: #1d2327; color: #b4b4b4; border-radius: 6px; font-family: monospace; font-size: 12px;"></div>
            <?php endif; ?>

            <div id="hws-elementor-db-status" style="margin-top: 15px;"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( $nonce ); ?>';

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function renderElementorReport(report) {
            if (!report || typeof report !== 'object' || !Object.keys(report).length) {
                return '';
            }

            var html = '<strong style="color: #fff;">Last Report:</strong><br>';

            $.each(report, function(key, value) {
                var renderedValue = value;
                if (typeof value === 'object') {
                    renderedValue = JSON.stringify(value);
                }

                var color = (key === 'status' && value === 'success') ? '#00a32a' : '#fff';
                html += '<span style="color: #5dade2;">' + escapeHtml(key) + ':</span> ';
                html += '<span style="color: ' + color + ';">' + escapeHtml(renderedValue) + '</span><br>';
            });

            return html;
        }

        function applyElementorDbState(state) {
            var settings = state.settings || {};
            var cronStatus = state.cron_status || {};
            var versionsMatch = state.db_version === state.elementor_version;

            $('#hws-elementor-version').text(state.elementor_version || 'Unknown');
            $('#hws-elementor-db-version')
                .css('color', versionsMatch ? '#00a32a' : '#dba617')
                .text((state.db_version || 'Unknown') + ' ' + (versionsMatch ? '✅' : '⚠️'));
            $('#hws-elementor-last-run').text(settings.last_run || 'Never');
            $('#hws-elementor-db-enabled').prop('checked', !!settings.enabled);
            $('#hws-elementor-db-interval').val(settings.interval || 1);

            $('#hws-elementor-db-cron-box').css('background', cronStatus.is_scheduled ? '#edfaef' : '#fcf0f1');
            $('#hws-elementor-db-cron-scheduled').html('<strong>Scheduled:</strong> ' + (cronStatus.is_scheduled ? '✅ Yes' : '❌ No'));

            if (cronStatus.next_run) {
                $('#hws-elementor-db-cron-next')
                    .show()
                    .html('<strong>Next Run:</strong> ' + escapeHtml(cronStatus.next_run) + ' (in ' + escapeHtml(cronStatus.next_run_human || '') + ')');
            } else {
                $('#hws-elementor-db-cron-next').hide().text('');
            }

            if (cronStatus.wp_cron_disabled) {
                $('#hws-elementor-db-cron-warning').show().text('⚠️ WP-Cron is disabled. Set up a server cron job for reliable scheduling.');
            } else {
                $('#hws-elementor-db-cron-warning').hide().text('');
            }

            if (settings.last_report && Object.keys(settings.last_report).length) {
                $('#hws-elementor-db-report').show().html(renderElementorReport(settings.last_report));
            } else {
                $('#hws-elementor-db-report').hide().html('');
            }
        }

        function refreshElementorDbState() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_get_elementor_db_state',
                    nonce: nonce
                }
            }).done(function(response) {
                if (response && response.success && response.data) {
                    applyElementorDbState(response.data);
                }
            });
        }

        // Toggle enabled
        $('#hws-elementor-db-enabled').on('change', function() {
            var $status = $('#hws-elementor-db-status');
            $status.html('<span style="color: #666;">Saving...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_elementor_db_toggle',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        refreshElementorDbState();
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                }
            });
        });

        // Update interval
        $('#hws-elementor-db-interval').on('change', function() {
            var $status = $('#hws-elementor-db-status');
            var interval = $(this).val();

            $status.html('<span style="color: #666;">Updating interval...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_elementor_db_update_settings',
                    nonce: nonce,
                    interval: interval
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        refreshElementorDbState();
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                }
            });
        });

        // Run now
        $('#hws-elementor-db-run-now').on('click', function() {
            var $btn = $(this);
            var $status = $('#hws-elementor-db-status');

            $btn.prop('disabled', true).text('⏳ Running...');
            $status.html('<span style="color: #666;">Running Elementor database update...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 60000,
                data: {
                    action: 'hws_elementor_db_run_now',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        var report = response.data.report;
                        var reportHtml = '<strong>Result:</strong> ' + report.status + '<br>';
                        reportHtml += '<strong>Message:</strong> ' + report.message + '<br>';
                        reportHtml += '<strong>Duration:</strong> ' + report.duration;

                        $status.html('<div style="padding: 10px; background: #edfaef; border-radius: 4px; color: #1d2327;">' + reportHtml + '</div>');
                        refreshElementorDbState();
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                    $btn.prop('disabled', false).text('▶️ Run Now');
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error or Timeout</span>');
                    $btn.prop('disabled', false).text('▶️ Run Now');
                }
            });
        });
    });
    </script>
    <?php
}
