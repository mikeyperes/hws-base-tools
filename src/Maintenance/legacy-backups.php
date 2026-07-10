<?php namespace hws_base_tools;

/**
 * Backup Detection & Auto-Cleanup System
 *
 * Features:
 * - Detects backups from popular backup plugins
 * - Individual and mass delete capability
 * - Auto-cleanup cron (default: 5 days, auto-enabled)
 * - Cron status reporting
 *
 * @since 8.9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Backup Cleaner Configuration
 */
class Backup_Cleaner_Config {
    // Option keys
    const OPT_ENABLED      = 'hws_backup_cleaner_enabled';
    const OPT_DAYS         = 'hws_backup_cleaner_days';
    const OPT_LAST_RUN     = 'hws_backup_cleaner_last_run';
    const OPT_LAST_REPORT  = 'hws_backup_cleaner_last_report';
    const OPT_FIRST_RUN    = 'hws_backup_cleaner_first_run';

    // Cron hook
    const CRON_HOOK        = 'hws_backup_cleaner_cron';

    // Defaults
    const DEFAULT_ENABLED  = true;
    const DEFAULT_DAYS     = 5;

    /**
     * Get settings
     */
    public static function get_settings() {
        return [
            'enabled'     => get_option( self::OPT_ENABLED, self::DEFAULT_ENABLED ),
            'days'        => (int) get_option( self::OPT_DAYS, self::DEFAULT_DAYS ),
            'last_run'    => get_option( self::OPT_LAST_RUN, 'Never' ),
            'last_report' => get_option( self::OPT_LAST_REPORT, [] ),
        ];
    }
}


/**
 * Known backup plugin locations
 */
function hws_get_backup_locations() {
    return [
        'all-in-one-wp-migration' => [
            'name' => 'All-in-One WP Migration',
            'path' => WP_CONTENT_DIR . '/ai1wm-backups/',
            'extensions' => [ 'wpress' ],
        ],
        'updraftplus' => [
            'name' => 'UpdraftPlus',
            'path' => WP_CONTENT_DIR . '/updraft/',
            'extensions' => [ 'zip', 'gz', 'sql' ],
        ],
        'backwpup' => [
            'name' => 'BackWPup',
            'path' => WP_CONTENT_DIR . '/uploads/backwpup*/',
            'extensions' => [ 'zip', 'tar', 'gz' ],
        ],
        'duplicator' => [
            'name' => 'Duplicator',
            'path' => WP_CONTENT_DIR . '/backups-dup-lite/',
            'extensions' => [ 'zip', 'daf' ],
        ],
        'duplicator-pro' => [
            'name' => 'Duplicator Pro',
            'path' => WP_CONTENT_DIR . '/backups-dup-pro/',
            'extensions' => [ 'zip', 'daf' ],
        ],
        'wpvivid' => [
            'name' => 'WPVivid',
            'path' => WP_CONTENT_DIR . '/wpvivid/',
            'extensions' => [ 'zip' ],
        ],
        'backup-migration' => [
            'name' => 'Backup Migration',
            'path' => WP_CONTENT_DIR . '/backup-migration*/',
            'extensions' => [ 'zip' ],
        ],
    ];
}


/**
 * Scan for backups
 */
function hws_scan_backups() {
    $locations = hws_get_backup_locations();
    $found = [];

    foreach ( $locations as $plugin_key => $config ) {
        $path = $config['path'];
        $plugin_name = $config['name'];
        $extensions = $config['extensions'];

        // Handle wildcard paths
        if ( strpos( $path, '*' ) !== false ) {
            $dirs = glob( $path, GLOB_ONLYDIR );
            if ( ! $dirs ) continue;
        } else {
            $dirs = [ $path ];
        }

        foreach ( $dirs as $dir ) {
            if ( ! is_dir( $dir ) ) continue;

            $files = @scandir( $dir );
            if ( ! $files ) continue;

            foreach ( $files as $file ) {
                if ( $file === '.' || $file === '..' ) continue;

                $file_path = $dir . '/' . $file;
                if ( ! is_file( $file_path ) ) continue;

                // Check extension
                $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, $extensions, true ) ) continue;

                $found[] = [
                    'plugin'    => $plugin_key,
                    'plugin_name' => $plugin_name,
                    'file'      => $file,
                    'path'      => $file_path,
                    'size'      => filesize( $file_path ),
                    'size_human'=> size_format( filesize( $file_path ) ),
                    'modified'  => filemtime( $file_path ),
                    'age_days'  => floor( ( time() - filemtime( $file_path ) ) / DAY_IN_SECONDS ),
                ];
            }
        }
    }

    // Sort by modified date (newest first)
    usort( $found, function( $a, $b ) {
        return $b['modified'] - $a['modified'];
    } );

    return $found;
}


/**
 * Delete a backup file
 */
function hws_delete_backup_file( $path ) {
    if ( ! file_exists( $path ) ) {
        return true;
    }

    if ( ! is_writable( $path ) ) {
        return false;
    }

    return @unlink( $path );
}


/**
 * Initialize backup cleaner
 */
function hws_backup_cleaner_init() {
    // Register AJAX handlers
    add_action( 'wp_ajax_hws_delete_backups', __NAMESPACE__ . '\\ajax_delete_backups' );
    add_action( 'wp_ajax_hws_backup_cleaner_toggle', __NAMESPACE__ . '\\ajax_backup_cleaner_toggle' );
    add_action( 'wp_ajax_hws_backup_cleaner_update', __NAMESPACE__ . '\\ajax_backup_cleaner_update' );
    add_action( 'wp_ajax_hws_backup_cleaner_run', __NAMESPACE__ . '\\ajax_backup_cleaner_run' );

    // Register cron hook
    add_action( Backup_Cleaner_Config::CRON_HOOK, __NAMESPACE__ . '\\hws_backup_cleaner_run' );

    // First run setup
    if ( ! get_option( Backup_Cleaner_Config::OPT_FIRST_RUN ) ) {
        update_option( Backup_Cleaner_Config::OPT_ENABLED, Backup_Cleaner_Config::DEFAULT_ENABLED );
        update_option( Backup_Cleaner_Config::OPT_DAYS, Backup_Cleaner_Config::DEFAULT_DAYS );
        update_option( Backup_Cleaner_Config::OPT_FIRST_RUN, true );

        if ( Backup_Cleaner_Config::DEFAULT_ENABLED ) {
            hws_backup_cleaner_schedule();
        }
    }
}
add_action( 'init', __NAMESPACE__ . '\\hws_backup_cleaner_init' );


/**
 * Schedule cron
 */
function hws_backup_cleaner_schedule() {
    \Hexa\PluginCore\WpCronTasks\WpCronTask::schedule_existing(
        Backup_Cleaner_Config::CRON_HOOK,
        'daily',
        time() + DAY_IN_SECONDS
    );
}


/**
 * Unschedule cron
 */
function hws_backup_cleaner_unschedule() {
    \Hexa\PluginCore\WpCronTasks\WpCronTask::unschedule_hook( Backup_Cleaner_Config::CRON_HOOK );
}


/**
 * Run backup cleaner
 */
function hws_backup_cleaner_run() {
    $settings = Backup_Cleaner_Config::get_settings();
    $max_age_days = $settings['days'];

    $backups = hws_scan_backups();
    $deleted = [];
    $errors = [];

    foreach ( $backups as $backup ) {
        if ( $backup['age_days'] >= $max_age_days ) {
            if ( hws_delete_backup_file( $backup['path'] ) ) {
                $deleted[] = [
                    'file'   => $backup['file'],
                    'plugin' => $backup['plugin_name'],
                    'size'   => $backup['size_human'],
                    'age'    => $backup['age_days'] . ' days',
                ];
            } else {
                $errors[] = 'Failed to delete: ' . $backup['file'];
            }
        }
    }

    $report = [
        'run_time'     => current_time( 'mysql' ),
        'max_age_days' => $max_age_days,
        'scanned'      => count( $backups ),
        'deleted'      => $deleted,
        'errors'       => $errors,
    ];

    update_option( Backup_Cleaner_Config::OPT_LAST_RUN, current_time( 'mysql' ) );
    update_option( Backup_Cleaner_Config::OPT_LAST_REPORT, $report );

    return $report;
}


/**
 * Get cron status
 */
function hws_backup_cleaner_get_cron_status() {
    return \Hexa\PluginCore\WpCronTasks\WpCronTask::status(
        Backup_Cleaner_Config::CRON_HOOK,
        [
            'callback'     => __NAMESPACE__ . '\\hws_backup_cleaner_run',
            'schedule_key' => 'daily',
        ]
    );
}


/**
 * AJAX: Delete backups
 */
function ajax_delete_backups() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $delete_all = isset( $_POST['delete_all'] ) && $_POST['delete_all'];
    $plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( $_POST['plugin'] ) : '';
    $files = isset( $_POST['files'] ) ? array_map( 'sanitize_text_field', (array) $_POST['files'] ) : [];

    $deleted = 0;
    $errors = [];

    if ( $delete_all && $plugin ) {
        // Delete all backups for a specific plugin
        $backups = hws_scan_backups();
        foreach ( $backups as $backup ) {
            if ( $backup['plugin'] === $plugin ) {
                if ( hws_delete_backup_file( $backup['path'] ) ) {
                    $deleted++;
                } else {
                    $errors[] = $backup['file'];
                }
            }
        }
    } else {
        // Delete specific files
        foreach ( $files as $file ) {
            // Security: ensure file is in a known backup location
            $backups = hws_scan_backups();
            foreach ( $backups as $backup ) {
                if ( $backup['path'] === $file || $backup['file'] === $file ) {
                    if ( hws_delete_backup_file( $backup['path'] ) ) {
                        $deleted++;
                    } else {
                        $errors[] = $backup['file'];
                    }
                    break;
                }
            }
        }
    }

    if ( empty( $errors ) ) {
        wp_send_json_success( [ 'deleted' => $deleted ] );
    } else {
        wp_send_json_error( 'Failed to delete: ' . implode( ', ', $errors ) );
    }
}


/**
 * AJAX: Toggle backup cleaner
 */
function ajax_backup_cleaner_toggle() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1';
    update_option( Backup_Cleaner_Config::OPT_ENABLED, $enabled );

    if ( $enabled ) {
        hws_backup_cleaner_schedule();
    } else {
        hws_backup_cleaner_unschedule();
    }

    wp_send_json_success( [
        'enabled'     => $enabled,
        'settings'    => Backup_Cleaner_Config::get_settings(),
        'cron_status' => hws_backup_cleaner_get_cron_status(),
    ] );
}


/**
 * AJAX: Update backup cleaner settings
 */
function ajax_backup_cleaner_update() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : Backup_Cleaner_Config::DEFAULT_DAYS;
    $days = max( 1, min( 30, $days ) );

    update_option( Backup_Cleaner_Config::OPT_DAYS, $days );

    wp_send_json_success( [
        'days'        => $days,
        'settings'    => Backup_Cleaner_Config::get_settings(),
        'cron_status' => hws_backup_cleaner_get_cron_status(),
    ] );
}


/**
 * AJAX: Run backup cleaner now
 */
function ajax_backup_cleaner_run() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    hws_require_ajax_nonce_or_error();

    $report = hws_backup_cleaner_run();

    wp_send_json_success( array_merge( $report, [
        'settings'    => Backup_Cleaner_Config::get_settings(),
        'cron_status' => hws_backup_cleaner_get_cron_status(),
    ] ) );
}


/**
 * Render Backups Tab
 */
function render_tab_backups() {
    $backups = hws_scan_backups();
    $settings = Backup_Cleaner_Config::get_settings();
    $cron_status = hws_backup_cleaner_get_cron_status();

    // Group by plugin
    $grouped = [];
    $total_size = 0;
    foreach ( $backups as $backup ) {
        $plugin = $backup['plugin'];
        if ( ! isset( $grouped[ $plugin ] ) ) {
            $grouped[ $plugin ] = [
                'name'    => $backup['plugin_name'],
                'files'   => [],
                'total'   => 0,
            ];
        }
        $grouped[ $plugin ]['files'][] = $backup;
        $grouped[ $plugin ]['total'] += $backup['size'];
        $total_size += $backup['size'];
    }
    ?>

    <!-- Summary -->
    <div class="hws-status-grid">
        <div class="hws-status-card <?php echo count( $backups ) > 0 ? 'warn' : 'good'; ?>">
            <div id="hws-backup-count" class="value"><?php echo count( $backups ); ?></div>
            <div class="label">Backup Files</div>
        </div>
        <div class="hws-status-card <?php echo $total_size > 100 * 1024 * 1024 ? 'bad' : ''; ?>">
            <div id="hws-backup-total-size" class="value"><?php echo size_format( $total_size ); ?></div>
            <div class="label">Total Size</div>
        </div>
        <div class="hws-status-card <?php echo $settings['enabled'] ? 'good' : 'warn'; ?>">
            <div id="hws-backup-auto-clean" class="value"><?php echo $settings['enabled'] ? '✅ ON' : '❌ OFF'; ?></div>
            <div class="label">Auto-Clean</div>
        </div>
        <div class="hws-status-card">
            <div id="hws-backup-max-age" class="value"><?php echo $settings['days']; ?> days</div>
            <div class="label">Max Age</div>
        </div>
    </div>

    <!-- Auto-Cleanup Settings -->
    <div class="hws-panel">
        <div class="hws-panel-header">⚙️ Auto-Cleanup Settings</div>
        <div class="hws-panel-body">
            <p>Automatically delete backup files older than the specified number of days.</p>

            <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" id="backup-cleaner-enabled" <?php checked( $settings['enabled'] ); ?>>
                    <strong>Enable Auto-Cleanup</strong>
                </label>
                <label style="display: flex; align-items: center; gap: 10px;">
                    Delete backups older than
                    <input type="number" id="backup-cleaner-days" value="<?php echo $settings['days']; ?>" min="1" max="30" style="width: 60px;">
                    days
                </label>
                <div style="margin-top: 10px;">
                    <button type="button" id="backup-cleaner-save" class="hws-btn">💾 Save Settings</button>
                    <button type="button" id="backup-cleaner-run" class="hws-btn hws-btn-secondary">▶️ Run Now</button>
                </div>
                <div id="hws-backup-cleaner-status" style="margin-top:10px;font-size:13px;color:#646970;"></div>
            </div>

            <!-- Cron Status -->
            <div id="hws-backup-cron-box" style="padding: 15px; background: <?php echo $cron_status['is_scheduled'] ? '#edfaef' : '#fcf0f1'; ?>; border-radius: 6px; margin-top: 15px;">
                <h4 style="margin: 0 0 10px;">⏰ Cron Status</h4>
                <p><strong>Hook:</strong> <?php echo esc_html( $cron_status['hook'] ); ?></p>
                <p id="hws-backup-cron-scheduled"><strong>Scheduled:</strong> <?php echo $cron_status['is_scheduled'] ? '✅ Yes' : '❌ No'; ?></p>
                <?php if ( $cron_status['next_run'] ) : ?>
                    <p id="hws-backup-cron-next"><strong>Next Run:</strong> <?php echo esc_html( $cron_status['next_run'] ); ?> (in <?php echo esc_html( $cron_status['next_run_human'] ); ?>)</p>
                <?php else : ?>
                    <p id="hws-backup-cron-next" style="display:none;"></p>
                <?php endif; ?>
                <p id="hws-backup-last-run"><strong>Last Run:</strong> <?php echo esc_html( $settings['last_run'] ); ?></p>
                <?php if ( $cron_status['wp_cron_disabled'] ) : ?>
                    <p id="hws-backup-cron-warning" style="color: #d63638;">⚠️ WP-Cron is disabled. Consider setting up a server cron job.</p>
                <?php else : ?>
                    <p id="hws-backup-cron-warning" style="color: #d63638; display:none;"></p>
                <?php endif; ?>
            </div>

            <!-- Last Report -->
            <?php if ( ! empty( $settings['last_report'] ) ) :
                $report = $settings['last_report'];
            ?>
            <div id="hws-backup-last-report" style="margin-top: 15px; padding: 15px; background: #f6f7f7; border-radius: 6px;">
                <h4 style="margin: 0 0 10px;">📋 Last Run Report</h4>
                <p><strong>Time:</strong> <?php echo esc_html( $report['run_time'] ?? 'N/A' ); ?></p>
                <p><strong>Files Scanned:</strong> <?php echo esc_html( $report['scanned'] ?? 0 ); ?></p>
                <p><strong>Files Deleted:</strong> <?php echo count( $report['deleted'] ?? [] ); ?></p>
                <?php if ( ! empty( $report['deleted'] ) ) : ?>
                    <ul style="margin: 5px 0 0 20px; font-size: 12px;">
                        <?php foreach ( $report['deleted'] as $del ) : ?>
                            <li><?php echo esc_html( $del['file'] ); ?> (<?php echo esc_html( $del['size'] ); ?>, <?php echo esc_html( $del['age'] ); ?> old)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div id="hws-backup-last-report" style="display:none;margin-top: 15px; padding: 15px; background: #f6f7f7; border-radius: 6px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Backup Files List -->
    <div id="hws-backups-empty-state" class="hws-panel" style="<?php echo empty( $backups ) ? '' : 'display:none;'; ?>">
        <div class="hws-panel-body" style="text-align: center; padding: 40px;">
            <p style="font-size: 48px; margin: 0;">✅</p>
            <p style="font-size: 18px; color: #00a32a;">No backup files found!</p>
            <p style="color: #666;">Your site is clean of old backup files.</p>
        </div>
    </div>

    <div id="hws-backups-list" style="<?php echo empty( $backups ) ? 'display:none;' : ''; ?>">
    <?php if ( ! empty( $backups ) ) : ?>
        <?php foreach ( $grouped as $plugin_key => $plugin_data ) : ?>
            <div class="hws-panel" data-backup-plugin-panel="<?php echo esc_attr( $plugin_key ); ?>" data-backup-plugin-name="<?php echo esc_attr( $plugin_data['name'] ); ?>">
                <div class="hws-panel-header">
                    💾 <?php echo esc_html( $plugin_data['name'] ); ?>
                    <span data-backup-plugin-summary="<?php echo esc_attr( $plugin_key ); ?>" style="float: right; font-weight: normal; font-size: 13px;">
                        <?php echo count( $plugin_data['files'] ); ?> files (<?php echo size_format( $plugin_data['total'] ); ?>)
                    </span>
                </div>
                <div class="hws-panel-body">
                    <table class="hws-plugin-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Size</th>
                                <th>Age</th>
                                <th>Modified</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $plugin_data['files'] as $backup ) : ?>
                                <tr data-backup-file-row="<?php echo esc_attr( $backup['path'] ); ?>" data-backup-plugin="<?php echo esc_attr( $plugin_key ); ?>" data-backup-file-name="<?php echo esc_attr( $backup['file'] ); ?>" data-backup-size-bytes="<?php echo esc_attr( $backup['size'] ); ?>">
                                    <td><code style="font-size: 11px;"><?php echo esc_html( $backup['file'] ); ?></code></td>
                                    <td><?php echo esc_html( $backup['size_human'] ); ?></td>
                                    <td class="<?php echo $backup['age_days'] >= $settings['days'] ? 'status-bad' : ''; ?>">
                                        <?php echo $backup['age_days']; ?> days
                                        <?php if ( $backup['age_days'] >= $settings['days'] ) echo '⚠️'; ?>
                                    </td>
                                    <td><?php echo date( 'Y-m-d H:i', $backup['modified'] ); ?></td>
                                    <td>
                                        <button type="button" class="hws-btn hws-btn-danger hws-delete-backup"
                                                data-file="<?php echo esc_attr( $backup['path'] ); ?>"
                                                data-plugin="<?php echo esc_attr( $plugin_key ); ?>"
                                                style="padding: 4px 8px; font-size: 11px;">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px;">
                        <button type="button" class="hws-btn hws-btn-danger hws-delete-all-backups"
                                data-plugin="<?php echo esc_attr( $plugin_key ); ?>">
                            🗑️ Delete All <?php echo esc_html( $plugin_data['name'] ); ?> Backups
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var backupNonce = window.hwsNonce || '<?php echo esc_js( wp_create_nonce( HWS_AJAX_NONCE ) ); ?>';

        function formatBytes(bytes) {
            var units = ['B', 'KB', 'MB', 'GB', 'TB'];
            var value = Number(bytes) || 0;
            var unitIndex = 0;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value = value / 1024;
                unitIndex++;
            }

            return (unitIndex === 0 ? value : value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)) + ' ' + units[unitIndex];
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function refreshBackupSummary() {
            var totalFiles = $('[data-backup-file-row]').length;
            var totalBytes = 0;

            $('[data-backup-file-row]').each(function() {
                totalBytes += parseInt($(this).data('backup-size-bytes'), 10) || 0;
            });

            $('#hws-backup-count').text(totalFiles);
            $('#hws-backup-total-size').text(formatBytes(totalBytes));
            $('#hws-backups-empty-state').toggle(totalFiles === 0);
            $('#hws-backups-list').toggle(totalFiles > 0);

            $('[data-backup-plugin-panel]').each(function() {
                var $panel = $(this);
                var plugin = $panel.data('backup-plugin-panel');
                var $rows = $panel.find('[data-backup-file-row]');
                var pluginBytes = 0;

                $rows.each(function() {
                    pluginBytes += parseInt($(this).data('backup-size-bytes'), 10) || 0;
                });

                if ($rows.length === 0) {
                    $panel.remove();
                    return;
                }

                $('[data-backup-plugin-summary="' + plugin + '"]').text($rows.length + ' files (' + formatBytes(pluginBytes) + ')');
            });
        }

        function renderBackupReport(report) {
            if (!report || typeof report !== 'object') {
                return '';
            }

            var html = '<h4 style="margin: 0 0 10px;">📋 Last Run Report</h4>';
            html += '<p><strong>Time:</strong> ' + escapeHtml(report.run_time || 'N/A') + '</p>';
            html += '<p><strong>Files Scanned:</strong> ' + escapeHtml(report.scanned || 0) + '</p>';
            html += '<p><strong>Files Deleted:</strong> ' + escapeHtml((report.deleted || []).length) + '</p>';

            if (report.deleted && report.deleted.length) {
                html += '<ul style="margin: 5px 0 0 20px; font-size: 12px;">';
                report.deleted.forEach(function(item) {
                    html += '<li>' + escapeHtml(item.file) + ' (' + escapeHtml(item.size) + ', ' + escapeHtml(item.age) + ' old)</li>';
                });
                html += '</ul>';
            }

            return html;
        }

        function applyBackupState(data) {
            var settings = data.settings || {};
            var cronStatus = data.cron_status || {};

            $('#backup-cleaner-enabled').prop('checked', !!settings.enabled);
            $('#backup-cleaner-days').val(settings.days || <?php echo (int) Backup_Cleaner_Config::DEFAULT_DAYS; ?>);
            $('#hws-backup-auto-clean').text(settings.enabled ? '✅ ON' : '❌ OFF');
            $('#hws-backup-max-age').text((settings.days || <?php echo (int) Backup_Cleaner_Config::DEFAULT_DAYS; ?>) + ' days');
            $('#hws-backup-cron-box').css('background', cronStatus.is_scheduled ? '#edfaef' : '#fcf0f1');
            $('#hws-backup-cron-scheduled').html('<strong>Scheduled:</strong> ' + (cronStatus.is_scheduled ? '✅ Yes' : '❌ No'));
            $('#hws-backup-last-run').html('<strong>Last Run:</strong> ' + escapeHtml(settings.last_run || 'Never'));

            if (cronStatus.next_run) {
                $('#hws-backup-cron-next')
                    .show()
                    .html('<strong>Next Run:</strong> ' + escapeHtml(cronStatus.next_run) + ' (in ' + escapeHtml(cronStatus.next_run_human || '') + ')');
            } else {
                $('#hws-backup-cron-next').hide().text('');
            }

            if (cronStatus.wp_cron_disabled) {
                $('#hws-backup-cron-warning').show().text('⚠️ WP-Cron is disabled. Consider setting up a server cron job.');
            } else {
                $('#hws-backup-cron-warning').hide().text('');
            }

            if (settings.last_report && Object.keys(settings.last_report).length) {
                $('#hws-backup-last-report').show().html(renderBackupReport(settings.last_report));
            } else {
                $('#hws-backup-last-report').hide().html('');
            }
        }

        function removeBackupRowsByPaths(paths) {
            (paths || []).forEach(function(path) {
                $('[data-backup-file-row="' + path.replace(/"/g, '\\"') + '"]').remove();
            });
            refreshBackupSummary();
        }

        function removeBackupRowsByReport(deletedRows) {
            (deletedRows || []).forEach(function(item) {
                $('[data-backup-file-row]').filter(function() {
                    var $row = $(this);
                    var pluginName = $row.closest('[data-backup-plugin-panel]').data('backup-plugin-name');
                    return $row.data('backup-file-name') === item.file && pluginName === item.plugin;
                }).remove();
            });
            refreshBackupSummary();
        }

        window.hwsBackupUi = {
            applyState: applyBackupState,
            removeRowsByPaths: removeBackupRowsByPaths,
            refreshSummary: refreshBackupSummary
        };

        // Toggle backup cleaner
        $('#backup-cleaner-enabled').on('change', function() {
            var enabled = $(this).is(':checked') ? 1 : 0;
            $.post(ajaxurl, {
                action: 'hws_backup_cleaner_toggle',
                enabled: enabled,
                nonce: backupNonce
            }, function(response) {
                if (response && response.success) {
                    applyBackupState(response.data);
                    $('#hws-backup-cleaner-status').html('<span style="color:#00a32a;">✅ Auto-clean ' + (enabled ? 'enabled' : 'disabled') + '</span>');
                } else {
                    $('#hws-backup-cleaner-status').html('<span style="color:#d63638;">❌ Failed to update auto-clean setting</span>');
                }
            });
        });

        // Save settings
        $('#backup-cleaner-save').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.post(ajaxurl, {
                action: 'hws_backup_cleaner_update',
                days: $('#backup-cleaner-days').val(),
                nonce: backupNonce
            }, function(response) {
                $btn.prop('disabled', false).text('💾 Save Settings');
                if (response && response.success) {
                    applyBackupState(response.data);
                    $('#hws-backup-cleaner-status').html('<span style="color:#00a32a;">✅ Settings saved</span>');
                } else {
                    $('#hws-backup-cleaner-status').html('<span style="color:#d63638;">❌ Failed to save settings</span>');
                }
            });
        });

        // Run now
        $('#backup-cleaner-run').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Running...');
            $.post(ajaxurl, {
                action: 'hws_backup_cleaner_run',
                nonce: backupNonce
            }, function(response) {
                $btn.prop('disabled', false).text('▶️ Run Now');
                if (response.success) {
                    applyBackupState(response.data);
                    removeBackupRowsByReport(response.data.deleted || []);
                    $('#hws-backup-cleaner-status').html('<span style="color:#00a32a;">✅ Scanned ' + response.data.scanned + ' files, deleted ' + (response.data.deleted || []).length + '</span>');
                } else {
                    $('#hws-backup-cleaner-status').html('<span style="color:#d63638;">❌ Failed to run backup cleaner</span>');
                }
            });
        });
    });
    </script>
    <?php
}
