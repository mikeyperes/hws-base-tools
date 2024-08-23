<?php
// Inline jQuery for handling actions
add_action('admin_footer', 'hws_ct_inline_admin_script');
function hws_ct_inline_admin_script() {
    ?>
   <script type="text/javascript">
   jQuery(document).ready(function($) {

/*
    // Handle delete log files
    $('#delete-debug-log, #delete-error-log').on('click', function(e) {
        e.preventDefault();
        var logFile = $(this).attr('id').replace('delete-', '');
        deleteLogFile(logFile);
    });*/

    // Toggle WP-Config settings
    /*
    $('#debug-toggle, #debug-display-toggle, #debug-log-toggle').on('change', function() {
        var setting = $(this).attr('id').replace('-toggle', '').replace(/-/, '_').toUpperCase();
        var value = $(this).is(':checked') ? 'true' : 'false';
        updateDebugSetting('WP_' + setting, value);
    });
    // Function to handle AJAX request to update WP_DEBUG settings
    function updateDebugSetting(setting, value) {
        alert('Sending request to update ' + setting + ' to ' + value);
        $.post(ajaxurl, {
            action: 'hws_ct_update_debug',
            setting: setting,
            value: value
        }, function(response) {
            if (response.success) {
                alert(setting + ' set to ' + value);
            } else {
                alert('Failed to update ' + setting + ': ' + response.data);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    }*/
/*
    // Function to handle AJAX request to delete log files
    function deleteLogFile(logFile) {
        alert('Sending request to delete ' + logFile);
        $.post(ajaxurl, {
            action: 'hws_ct_delete_log_file',
            log_file: logFile
        }, function(response) {
            if (response.success) {
                alert(logFile + ' has been deleted.');
            } else {
                alert('Failed to delete ' + logFile + ': ' + response.data);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    }*/

    // Handle the auto-delete toggle
    $('#auto-delete-toggle').on('change', function() {
        var isEnabled = $(this).is(':checked') ? 'enabled' : 'disabled';
        alert('Toggling auto delete to ' + isEnabled);
        $.post(ajaxurl, {
            action: 'hws_ct_toggle_auto_delete',
            status: isEnabled
        }, function(response) {
            if (response.success) {
                alert('Auto delete is now ' + isEnabled + '. Cron Status: ' + (response.data.cron_enabled ? 'Enabled' : 'Disabled') + '. Last cron run: ' + response.data.last_run);
            } else {
                alert('Failed to update auto delete setting.');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    });
});
</script>
    <?php
}
/*
add_action('wp_ajax_hws_ct_update_debug', 'hws_ct_update_debug');
function hws_ct_update_debug() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user');
    }

    $setting = sanitize_text_field($_POST['setting']);
    $value = ($_POST['value'] === 'true') ? 'true' : 'false';

    $config_path = ABSPATH . 'wp-config.php';

    if (file_exists($config_path) && is_writable($config_path)) {
        $config_contents = file_get_contents($config_path);

        // Remove any previous correct definitions to prevent duplicates
        $config_contents = preg_replace($correct_pattern, '', $config_contents);

        // Find the position to insert the new definition after 'WP_DEBUG_DISPLAY'
        $insert_position = strpos($config_contents, "define('WP_DEBUG_DISPLAY',");
        if ($insert_position !== false) {
            $insert_position += strlen("define('WP_DEBUG_DISPLAY', true);\n");
            $config_contents = substr_replace($config_contents, "define('" . $setting . "', " . $value . ");\n", $insert_position, 0);
        } else {
            // Fallback in case 'WP_DEBUG_DISPLAY' is not found, insert after 'WP_DEBUG'
            $insert_position = strpos($config_contents, "define('WP_DEBUG',");
            if ($insert_position !== false) {
                $insert_position += strlen("define('WP_DEBUG', true);\n");
                $config_contents = substr_replace($config_contents, "define('" . $setting . "', " . $value . ");\n", $insert_position, 0);
            }
        }

        // Save the updated wp-config.php file
        if (file_put_contents($config_path, $config_contents)) {
            wp_send_json_success($setting . ' updated to ' . $value);
        } else {
            wp_send_json_error('Failed to update wp-config.php');
        }
    } else {
        wp_send_json_error('wp-config.php is not writable');
    }
}*/