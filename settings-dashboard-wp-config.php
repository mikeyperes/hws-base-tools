<?php namespace hws_base_tools;



function hws_ct_display_settings_wp_config()
{?>

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
<?php
                // Get the sizes of debug.log and error_log
                $debug_log_size = file_exists(WP_CONTENT_DIR . '/debug.log') ? size_format(filesize(WP_CONTENT_DIR . '/debug.log')) : 'Not found';
                $error_log_size = file_exists(ABSPATH . 'error_log') ? size_format(filesize(ABSPATH . 'error_log')) : 'Not found';

           // Usage examples:
$wp_debug = check_wp_config_constant_status('WP_DEBUG');
$wp_debug_display = check_wp_config_constant_status('WP_DEBUG_DISPLAY');
$wp_debug_log = check_wp_config_constant_status('WP_DEBUG_LOG');

            
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
                

                



<!-- Debug Log -->
<p>Debug Log Size: <?php echo $debug_log_size; ?></p>
<div>
    <button class="button" id="toggle-debug-log">View Last 100 Lines of debug.log</button>
    <button class="button" id="copy-debug-log">Copy to Clipboard</button>
  
    <a href="<?php echo WP_CONTENT_URL . '/debug.log'; ?>" target="_blank" class="button">View debug.log</a>
    <button class="button" id="delete-debug-log">Delete</button>
    <pre id="debug-log-content" style="display:none; background-color:#222;color:#ddd; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;"><?php
        // Check if debug.log exists
        $log_file_path = WP_CONTENT_DIR . '/debug.log';

        if (file_exists($log_file_path)) {
            // Use file_get_contents to read the file's last 100 lines
            $log_content = file($log_file_path);
            $last_lines = array_slice($log_content, -200); // Get the last 100 lines
            echo htmlspecialchars(implode("", $last_lines)); // Output the lines
        } else {
            echo "debug.log not found.";
        }
    ?></pre>
</div>

<!-- Error Log -->
<p>Error Log Size: <?php echo $error_log_size; ?></p>
<div>
    <button class="button" id="toggle-error-log">View Last 100 Lines of error_log</button>
    <button class="button" id="copy-error-log">Copy to Clipboard</button>

    <a href="<?php echo site_url('/error_log'); ?>" target="_blank" class="button">View error_log</a>
    <button class="button" id="delete-error-log">Delete</button>
    <pre id="error-log-content" style="display:none; background-color:#222;color:#ddd; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;"><?php
        if (file_exists(ABSPATH . 'error_log')) {
            echo htmlspecialchars(shell_exec("tail -n 300 " . ABSPATH . "error_log"));
        } else {
            echo "error_log not found.";
        }
    ?></pre>
</div>




     



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


<?php }?>