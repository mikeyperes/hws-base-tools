<?php namespace hws_base_tools;

// Function to delete log files
function delete_log_file($log_file) {
    if (file_exists($log_file)) {
        if (unlink($log_file)) {
            return true;
        } else {
            error_log('Failed to delete log file: ' . $log_file);
            return false;
        }
    } 
    return false;
}

// Handle AJAX requests to delete debug.log
add_action('wp_ajax_delete_debug_log', 'hws_base_tools\delete_debug_log');
function delete_debug_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user');
    }

    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (delete_log_file($log_file)) {
        wp_send_json_success('debug.log has been deleted.');
    } else {
        wp_send_json_error('Failed to delete debug.log or file does not exist.');
    }
}

// Handle AJAX requests to delete error_log
add_action('wp_ajax_delete_error_log', 'hws_base_tools\delete_error_log');
function delete_error_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user');
    }

    $log_file = ABSPATH . 'error_log';
    if (delete_log_file($log_file)) {
        wp_send_json_success('error_log has been deleted.');
    } else {
        wp_send_json_error('Failed to delete error_log or file does not exist.');
    }
}

// Handle the cron job for auto-deletion
function check_wp_log_size() {
    $log_file_paths = [
        WP_CONTENT_DIR . '/debug.log',
        ABSPATH . 'error_log'
    ];

    foreach ($log_file_paths as $log_file_path) {
        if (file_exists($log_file_path)) {
            $file_size = filesize($log_file_path);
            if ($file_size > 50 * 1024 * 1024) { // 50 MB
                if (delete_log_file($log_file_path)) {
                    error_log('[check_wp_log_size] Deleted log file: ' . $log_file_path);
                }
            }
        }
    }
    update_option('hws_last_cron_run', current_time('mysql'));
}

// Schedule the cron event if not already scheduled
if (!wp_next_scheduled('check_wp_log_size_daily')) {
    wp_schedule_event(time(), 'daily', 'check_wp_log_size_daily');
    error_log('[check_wp_log_size] Scheduled cron event: check_wp_log_size_daily');
}

add_action('check_wp_log_size_daily', 'check_wp_log_size');

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
add_action('wp_ajax_hws_ct_toggle_auto_delete', 'hws_base_tools\hws_ct_toggle_auto_delete');
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

    $cron_enabled = wp_next_scheduled($cron_hook) !== false;
    $last_run = get_option('hws_last_cron_run', 'Never');

    wp_send_json_success([
        'cron_enabled' => $cron_enabled,
        'last_run' => $last_run
    ]);
}

// Inject the inline script to handle the toggle
add_action('admin_print_footer_scripts', 'hws_base_tools\hws_ct_inline_auto_delete_script');
function hws_ct_inline_auto_delete_script() {
    $is_enabled = get_option('hws_auto_delete_enabled', 'disabled') === 'enabled';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#auto-delete-toggle').prop('checked', <?php echo $is_enabled ? 'true' : 'false'; ?>);
        });
    </script>
    <?php
}