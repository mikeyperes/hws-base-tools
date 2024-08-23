<? // Function to delete log files
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
add_action('wp_ajax_delete_debug_log', 'delete_debug_log');
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
add_action('wp_ajax_delete_error_log', 'delete_error_log');
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

    $cron_enabled = wp_next_scheduled($cron_hook) !== false;
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
        });
    </script>
    <?php
}

function hws_ct_render_auto_delete_toggle() {
    ?>
    <div class="wrap">
        
            
                            <?php
                // Get the sizes of debug.log and error_log
                $debug_log_size = file_exists(WP_CONTENT_DIR . '/debug.log') ? size_format(filesize(WP_CONTENT_DIR . '/debug.log')) : 'Not found';
                $error_log_size = file_exists(ABSPATH . 'error_log') ? size_format(filesize(ABSPATH . 'error_log')) : 'Not found';

           // Usage examples:
$wp_debug = get_constant_value_from_wp_config('WP_DEBUG');
$wp_debug_display = get_constant_value_from_wp_config('WP_DEBUG_DISPLAY');
$wp_debug_log = get_constant_value_from_wp_config('WP_DEBUG_LOG');

            
                // Define an array of constants to exclude (those already displayed)
                $exclude_constants = [
                    'WP_DEBUG',
                    'WP_DEBUG_DISPLAY',
                    'WP_DEBUG_LOG',
                    'DB_PASSWORD',  // Hide sensitive information
                    'DB_USER',
                    'DB_HOST',
                    'DB_NAME'
                ];
                ?>

                <div>
                    <label for="debug-toggle">Enable WP_DEBUG (Current: <?php echo $wp_debug; ?>)</label>
                    <input type="checkbox" id="debug-toggle" <?php echo $wp_debug === 'true' ? 'checked' : ''; ?>>
                </div>

                <div>
                    <label for="debug-display-toggle">Enable WP_DEBUG_DISPLAY (Current: <?php echo $wp_debug_display; ?>)</label>
                    <input type="checkbox" id="debug-display-toggle" <?php echo $wp_debug_display === 'true' ? 'checked' : ''; ?>>
                </div>

                <div>
                    <label for="debug-log-toggle">Enable WP_DEBUG_LOG (Current: <?php echo $wp_debug_log; ?>)</label>
                    <input type="checkbox" id="debug-log-toggle" <?php echo $wp_debug_log === 'true' ? 'checked' : ''; ?>>
                </div>
                
                
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#debug-toggle, #debug-display-toggle, #debug-log-toggle').on('change', function() {
        var setting = $(this).attr('id').replace('-toggle', '').replace(/-/, '_').toUpperCase();
        var value = $(this).is(':checked') ? 'true' : 'false';
        updateDebugSetting('WP_' + setting, value);
    });

    function updateDebugSetting(setting, value) {
        alert('Sending request to update ' + setting + ' to ' + value);
        $.post(ajaxurl, {
            action: 'modify_wp_config_constants',
            constants: {
                [setting]: value
            }
        },
        function(response) {
            console.log('Raw AJAX Response:', response);

            try {
                var jsonResponse = JSON.parse(response);
                var message = jsonResponse.data ? jsonResponse.data.message : 'No message received';

                if (jsonResponse.success) {
                    alert(setting + ' set to ' + value);
                    location.reload();
                } else {
                    alert('Failed to update ' + setting + ': ' + jsonResponse.data);
                }
            } catch (e) {
                console.error('Response is not valid JSON:', response);
                alert('Unexpected error: ' + response);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            console.error('AJAX Request Failed:', jqXHR, textStatus, errorThrown);
        });
    }
});
</script>

                



<!-- Debug Log -->
<p>Debug Log Size: <?php echo $debug_log_size; ?></p>
<div>
    <button class="button" id="toggle-debug-log">View Last 100 Lines of debug.log</button>
    <pre id="debug-log-content" style="display:none; background-color:#f1f1f1; padding:10px; border:1px solid #ccc; max-height:200px; overflow:auto;"><?php
        if (file_exists(WP_CONTENT_DIR . '/debug.log')) {
            echo htmlspecialchars(shell_exec("tail -n 100 " . WP_CONTENT_DIR . "/debug.log"));
        } else {
            echo "debug.log not found.";
        }
    ?></pre>
    <a href="<?php echo WP_CONTENT_URL . '/debug.log'; ?>" target="_blank" class="button">View debug.log</a>
    <button class="button" id="delete-debug-log">Delete</button>
</div>

<!-- Error Log -->
<p>Error Log Size: <?php echo $error_log_size; ?></p>
<div>
    <button class="button" id="toggle-error-log">View Last 100 Lines of error_log</button>
    <pre id="error-log-content" style="display:none; background-color:#f1f1f1; padding:10px; border:1px solid #ccc; max-height:200px; overflow:auto;"><?php
        if (file_exists(ABSPATH . 'error_log')) {
            echo htmlspecialchars(shell_exec("tail -n 100 " . ABSPATH . "error_log"));
        } else {
            echo "error_log not found.";
        }
    ?></pre>
    <a href="<?php echo site_url('/error_log'); ?>" target="_blank" class="button">View error_log</a>
    <button class="button" id="delete-error-log">Delete</button>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#toggle-debug-log').on('click', function() {
        $('#debug-log-content').toggle();
        $(this).text($(this).text() === 'View Last 100 Lines of debug.log' ? 'Hide Last 100 Lines of debug.log' : 'View Last 100 Lines of debug.log');
    });

    $('#toggle-error-log').on('click', function() {
        $('#error-log-content').toggle();
        $(this).text($(this).text() === 'View Last 100 Lines of error_log' ? 'Hide Last 100 Lines of error_log' : 'View Last 100 Lines of error_log');
    });
});
</script>


     



        <h3>Auto Delete Log Files</h3>
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
        $cron_enabled = wp_next_scheduled($cron_hook) !== false;
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