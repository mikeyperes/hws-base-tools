<?php

// Function to check if a cron job is scheduled
function get_cron_status($cron_hook) {
    return wp_next_scheduled($cron_hook) !== false;
}

// Schedule the cron event if it is not already scheduled
if (!wp_next_scheduled('check_wp_log_size_daily')) {
    wp_schedule_event(time(), 'daily', 'check_wp_log_size_daily');
    error_log('[check_wp_log_size] Scheduled cron event: check_wp_log_size_daily');
}

// Hook the function to the scheduled event
add_action('check_wp_log_size_daily', 'check_wp_log_size');

function check_wp_log_size() {
    $log_file_paths = [
        WP_CONTENT_DIR . '/debug.log',
        ABSPATH . 'public_html/error_log'
    ];

    foreach ($log_file_paths as $log_file_path) {
        if (file_exists($log_file_path)) {
            $file_size = filesize($log_file_path);

            if ($file_size > 50 * 1024 * 1024) { // 50 MB
                unlink($log_file_path);
                error_log('[check_wp_log_size] Deleted log file: ' . $log_file_path);
            }
        }
    }

    // Update the last run time
    update_option('hws_last_cron_run', current_time('mysql'));
}

// Unschedule the event upon theme deactivation
function remove_check_wp_log_size_cron() {
    $timestamp = wp_next_scheduled('check_wp_log_size_daily');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'check_wp_log_size_daily');
        error_log('[check_wp_log_size] Unscheduled cron event: check_wp_log_size_daily');
    }
}
add_action('switch_theme', 'remove_check_wp_log_size_cron');

// Handle the AJAX request to toggle auto-delete
add_action('wp_ajax_hws_ct_toggle_auto_delete', 'hws_ct_toggle_auto_delete');
function hws_ct_toggle_auto_delete() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user');
    }

    $status = sanitize_text_field($_POST['status']);
    $cron_hook = 'check_wp_log_size_daily';

    if ($status === 'enabled') {
        if (!wp_next_scheduled($cron_hook)) {
            wp_schedule_event(time(), 'daily', $cron_hook);
        }
        update_option('hws_auto_delete_enabled', 'enabled');
    } else {
        $timestamp = wp_next_scheduled($cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $cron_hook);
        }
        update_option('hws_auto_delete_enabled', 'disabled');
    }

    $cron_enabled = get_cron_status($cron_hook);
    $last_run = get_option('hws_last_cron_run', 'Never');

    wp_send_json_success([
        'cron_enabled' => $cron_enabled,
        'last_run' => $last_run
    ]);
}

// Inject the inline script to handle the toggle
add_action('admin_print_footer_scripts', 'hws_ct_inline_auto_delete_script');
function hws_ct_inline_auto_delete_script() {
    $is_enabled = get_option('hws_auto_delete_enabled', 'disabled') === 'enabled';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#auto-delete-toggle').prop('checked', <?php echo $is_enabled ? 'true' : 'false'; ?>);

            $('#auto-delete-toggle').on('change', function() {
                var isEnabled = $(this).is(':checked') ? 'enabled' : 'disabled';
                
                $.post(ajaxurl, {
                    action: 'hws_ct_toggle_auto_delete',
                    status: isEnabled
                }, function(response) {
                    if (response.success) {
                        alert('Auto delete is now ' + isEnabled + '. Last cron run: ' + response.data.last_run);
                    } else {
                        alert('Failed to update auto delete setting.');
                    }
                });
            });
        });
    </script>
    <?php
}

function hws_ct_render_auto_delete_toggle() {
    ?>
    <div class="wrap">
        <h1>Auto Delete Log Files</h1>
        <form method="post" action="options.php">
            <?php settings_fields('hws_ct_auto_delete_group'); ?>
            <?php do_settings_sections('hws_ct_auto_delete_group'); ?>
            <label for="auto-delete-toggle">Enable Auto Delete:</label>
            <input type="checkbox" id="auto-delete-toggle" name="hws_auto_delete_enabled" value="enabled">
        </form>
        <hr>
        <h2>Cron Job Information</h2>
        <?php
        $cron_hook = 'check_wp_log_size_daily';
        $cron_enabled = get_cron_status($cron_hook);
        $next_run = wp_next_scheduled($cron_hook);
        $last_run = get_option('hws_last_cron_run', 'Never');
        $is_auto_delete_enabled = get_option('hws_auto_delete_enabled', 'disabled') === 'enabled';

        echo "<p><strong>Cron Job Hook:</strong> $cron_hook</p>";

        if ($is_auto_delete_enabled) {
            echo "<p style='color: green;'>&#x2705; Auto Delete is enabled.</p>";
        } else {
            echo "<p style='color: red;'>&#x274C; Auto Delete is disabled.</p>";
        }

        if ($cron_enabled) {
            echo "<p style='color: green;'>&#x2705; Cron job is scheduled.</p>";
            echo "<p><strong>Cron Job ID:</strong> " . $cron_hook . "</p>";
            echo "<p>Next scheduled run: " . date('Y-m-d H:i:s', $next_run) . "</p>";
            echo "<p><strong>Interval:</strong> Daily</p>";
        } else {
            echo "<p style='color: red;'>&#x274C; Cron job is not scheduled.</p>";
        }
        echo "<p>Last run: " . $last_run . "</p>";
        ?>
    </div>
    <?php
}

?>