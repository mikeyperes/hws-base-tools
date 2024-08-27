<?php
// Inline jQuery for handling actions
add_action('admin_footer', 'hws_ct_inline_admin_script');
function hws_ct_inline_admin_script() {
    ?>
 
    <?php
}

function hws_ct_display_settings_wp_config()
{?>




        <!-- Debug Information Panel -->
        <div class="panel panel-setting-debug-information">
            <h2 class="panel-title">WP Config and Debug Settings</h2>
            <div class="panel-content">
                
                
            

        <!-- Expandable section for WP-Config Constants -->
        <div class="wp-config-expandable">
            <span class="wp-config-toggle">Show More WP-Config Constants</span>
            <div class="wp-config-content">
                <ul>
                    <?php
                    // Get all defined constants
                    $defined_constants = get_wp_config_defined_constants();

                    foreach ($defined_constants as $constant => $value) {
                        echo '<li>' . $constant . ': ' . ($value ? 'true' : 'false') . '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>

        <!-- Expandable section for viewing wp-config.php content -->
        <div class="wp-config-expandable">
            <span class="wp-config-toggle">View wp-config.php</span>
            <div class="wp-config-content">
                <pre style="white-space: pre-wrap; word-wrap: break-word;">
                    <?php
                    // Path to the wp-config.php file
                    $wp_config_path = ABSPATH . 'wp-config.php';

                    // Exclude sensitive constants
                    $exclude_constants = [
                        'DB_NAME',
                        'DB_USER',
                        'DB_PASSWORD',
                        'DB_HOST',
                        'DB_CHARSET',
                        'DB_COLLATE',
                        'AUTH_KEY',
                        'SECURE_AUTH_KEY',
                        'LOGGED_IN_KEY',
                        'NONCE_KEY',
                        'AUTH_SALT',
                        'SECURE_AUTH_SALT',
                        'LOGGED_IN_SALT',
                        'NONCE_SALT',
                    ];

                    if (file_exists($wp_config_path)) {
                        $config_content = file($wp_config_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                        foreach ($config_content as $line) {
                            $line_trimmed = trim($line);

                            // Skip lines that contain excluded constants
                            $skip = false;
                            foreach ($exclude_constants as $constant) {
                                if (strpos($line_trimmed, $constant) !== false) {
                                    $skip = true;
                                    break;
                                }
                            }

                            if (!$skip) {
                                echo htmlspecialchars($line) . "\n";
                            }
                        }
                    } else {
                        echo 'wp-config.php file not found.';
                    }
                    ?>
                </pre>
            </div>
        </div>

<!-- START CRON SECTION -->

        
            
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
    <label for="debug-toggle" style="color: <?php echo $wp_debug === 'true' ? 'red' : 'inherit'; ?>;">
        Enable WP_DEBUG (Current: <?php echo $wp_debug; ?>)
    </label>
    <input type="checkbox" id="debug-toggle" <?php echo $wp_debug === 'true' ? 'checked' : ''; ?>>
</div>

<div>
    <label for="debug-display-toggle" style="color: <?php echo $wp_debug_display === 'true' ? 'red' : 'inherit'; ?>;">
        Enable WP_DEBUG_DISPLAY (Current: <?php echo $wp_debug_display; ?>)
    </label>
    <input type="checkbox" id="debug-display-toggle" <?php echo $wp_debug_display === 'true' ? 'checked' : ''; ?>>
</div>

<div>
    <label for="debug-log-toggle" style="color: <?php echo $wp_debug_log === 'true' ? 'red' : 'inherit'; ?>;">
        Enable WP_DEBUG_LOG (Current: <?php echo $wp_debug_log; ?>)
    </label>
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
  
    <a href="<?php echo WP_CONTENT_URL . '/debug.log'; ?>" target="_blank" class="button">View debug.log</a>
    <button class="button" id="delete-debug-log">Delete</button>
    <pre id="debug-log-content" style="display:none; background-color:#222;color:#ddd; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;"><?php
        if (file_exists(WP_CONTENT_DIR . '/debug.log')) {
            echo htmlspecialchars(shell_exec("tail -n 200 " . WP_CONTENT_DIR . "/debug.log"));
        } else {
            echo "debug.log not found.";
        }
    ?></pre>
</div>

<!-- Error Log -->
<p>Error Log Size: <?php echo $error_log_size; ?></p>
<div>
    <button class="button" id="toggle-error-log">View Last 100 Lines of error_log</button>

    <a href="<?php echo site_url('/error_log'); ?>" target="_blank" class="button">View error_log</a>
    <button class="button" id="delete-error-log">Delete</button>
    <pre id="error-log-content" style="display:none; background-color:#222;color:#ddd; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;"><?php
        if (file_exists(ABSPATH . 'error_log')) {
            echo htmlspecialchars(shell_exec("tail -n 200 " . ABSPATH . "error_log"));
        } else {
            echo "error_log not found.";
        }
    ?></pre>
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

        <!-- END CRON SECTION -->
        










            </div>
        </div>
    </div>





<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle WP-Config Constants section
        $('.wp-config-toggle').on('click', function() {
            $(this).next('.wp-config-content').slideToggle();
        });

        // Delete log files
        function deleteLogFile(logType) {
            const action = logType === 'debug' ? 'delete_debug_log' : 'delete_error_log';
            $.post(ajaxurl, { action: action }, function(response) {
                if (response.success) {
                    console.log(logType + '.log deleted successfully');
                    location.reload();
                } else {
                    console.error('Failed to delete ' + logType + '.log:', response.data);
                    alert('Error: ' + response.data);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            });
        }

        // Bind delete actions to buttons
        $('#delete-debug-log').on('click', function(e) {
            e.preventDefault();
            deleteLogFile('debug');
        });

        $('#delete-error-log').on('click', function(e) {
            e.preventDefault();
            deleteLogFile('error');
        });

        // Handle the auto-delete toggle
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
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            });
        });
    });
</script>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
 
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
<? }?>