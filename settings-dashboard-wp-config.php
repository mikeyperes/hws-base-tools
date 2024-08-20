<? 




// Inline jQuery for handling actions
add_action('admin_footer', 'hws_ct_inline_admin_script');
function hws_ct_inline_admin_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle WP-Config settings
        $('#debug-toggle, #debug-display-toggle, #debug-log-toggle').on('change', function() {
            var setting = $(this).attr('id').replace('-toggle', '').toUpperCase();
            var value = $(this).is(':checked') ? 'true' : 'false';
            updateDebugSetting('WP_' + setting, value);
        });

        // Handle delete log files
        $('#delete-debug-log, #delete-error-log').on('click', function(e) {
            e.preventDefault();
            var logFile = $(this).attr('id').replace('delete-', '');
            deleteLogFile(logFile);
        });

        // Function to handle AJAX request to update WP_DEBUG settings
        function updateDebugSetting(setting, value) {
            $.post(ajaxurl, {
                action: 'hws_ct_update_debug',
                setting: setting,
                value: value
            }, function(response) {
                if (response.success) {
                    console.log(setting + ' set to ' + value);
                } else {
                    console.log('Failed to update ' + setting);
                }
            });
        }

        // Function to handle AJAX request to delete log files
        function deleteLogFile(logFile) {
            $.post(ajaxurl, {
                action: 'hws_ct_delete_log_file',
                log_file: logFile
            }, function(response) {
                if (response.success) {
                    alert(logFile + ' has been deleted.');
                } else {
                    alert('Failed to delete ' + logFile);
                }
            });
        }




        // Panel toggle logic
        $('.components-panel__body-title').on('click', function() {
            var panelBody = $(this).closest('.components-panel__body');
            panelBody.toggleClass('is-opened');
        });
    });
    
    
    
    
    jQuery(document).ready(function($) {
        // Handle the auto-delete toggle
        $('#auto-delete-toggle').on('change', function() {
            var isEnabled = $(this).is(':checked') ? 'enabled' : 'disabled';
            
            $.post(ajaxurl, {
                action: 'hws_ct_toggle_auto_delete',
                status: isEnabled
            }, function(response) {
                if (response.success) {
                    alert('Auto delete is now ' + isEnabled + '. Cron Status: ' + (response.data.cron_enabled ? 'Enabled' : 'Disabled') + '. Last cron run: ' + response.data.last_run);
                } else {
                    alert('Failed to update auto delete setting.');
                }
            });
        });
    });
</script>
    <?php
}
                
                
                
                
                
                
                
// AJAX handler to delete log files
add_action('wp_ajax_hws_ct_delete_log_file', 'hws_ct_delete_log_file');
function hws_ct_delete_log_file() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }

    $log_file = sanitize_text_field($_POST['log_file']);
    $log_path = ($log_file === 'debug.log') ? WP_CONTENT_DIR . '/' . $log_file : ABSPATH . $log_file;

    if (file_exists($log_path) && unlink($log_path)) {
        wp_send_json_success("{$log_file} has been deleted.");
    } else {
        wp_send_json_error("Failed to delete {$log_file}.");
    }
}

