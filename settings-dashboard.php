<? // Add settings menu and page
add_action('admin_menu', 'hws_ct_add_verified_profiles_menu');

// Specific usage example for Verified Profiles
function hws_ct_add_verified_profiles_menu() {
    add_options_page(
        'Hexa Core Tools',       // Page title
        'Hexa Core Tools',       // Menu title
        'manage_options',        // Capability
        'hws-core-tools',        // Menu slug
        'hws_ct_display_page'    // Callback function
    );
}

function hws_ct_display_page() {?>

        <style>
            /* Panel styles */
            .components-panel__body {
                margin-bottom: 1.5em;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .components-panel__body-title {
                background-color: #f7f7f7;
                padding: 0.75em 1em;
                border-bottom: 1px solid #ccd0d4;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .components-panel__arrow {
                transition: transform 0.3s ease;
            }
            .components-panel__body.is-opened .components-panel__arrow {
                transform: rotate(-180deg);
            }
            .components-panel__body-content {
                padding: 1em;
                display: none;
            }
            .components-panel__body.is-opened .components-panel__body-content {
                display: block;
            }
        </style>
        
        
    <div class="wrap">

        <h1>Hexa Core Tools - WP-Config Settings</h1>





<!-- WordPress Prechecks Panel -->
<div class="components-panel__body is-opened" style="margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
    <h2 class="components-panel__body-title" style="background-color: #f7f7f7; padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
        <span>WordPress Prechecks</span>
        <span aria-hidden="true">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="components-panel__arrow" aria-hidden="true" focusable="false" style="transition: transform 0.3s ease;">
                <path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path>
            </svg>
        </span>
    </h2>
    <div class="components-panel__body-content" style="padding: 15px;">
        <div class="components-base-control components-notice is-info">
            <?php
          // Check WP Mail SMTP authentication
            $smtp_check = check_wp_mail_smtp_authentication();
            display_precheck_result('SMTP Authentication', $smtp_check['status'], $smtp_check['details']);


            // Check WP_CACHE
            display_precheck_result('WP_CACHE is enabled', check_wp_cache_enabled());

            // Check memory limits
            $memory_limits = check_memory_limits();
            display_precheck_result(
                'WP_MEMORY_LIMIT is over 512MB',
                $memory_limits['memory_limit_ok'],
                'Current: ' . esc_html($memory_limits['memory_limit'])
            );
            display_precheck_result(
                'WP_MAX_MEMORY_LIMIT is over 512MB',
                $memory_limits['max_memory_limit_ok'],
                'Current: ' . esc_html($memory_limits['max_memory_limit'])
            );
            display_precheck_result(
                'Elementor MEMORY_LIMIT is over 512MB',
                $memory_limits['elementor_memory_limit_ok'],
                'Current: ' . esc_html($memory_limits['elementor_memory_limit'])
            );

            // Check if WP_DEBUG is disabled
            display_precheck_result('WP_DEBUG is disabled', check_wp_debug_disabled());

            // Check if log files are less than 50MB
            $log_file_sizes = check_log_file_sizes();
            display_precheck_result(
                'debug.log is less than 50MB',
                $log_file_sizes['debug_log_size_ok'],
                'Current Size: ' . esc_html($log_file_sizes['debug_log_size'])
            );
            display_precheck_result(
                'error_log is less than 50MB',
                $log_file_sizes['error_log_size_ok'],
                'Current Size: ' . esc_html($log_file_sizes['error_log_size'])
            );

            // Check if server is LiteSpeed
            display_precheck_result('Web server is LiteSpeed', check_server_is_litespeed());

            // Check PHP version
            display_precheck_result('PHP version is >= 8.3', check_php_version(), 'Current Version: ' . PHP_VERSION);

            // Check if PHP SAPI is LiteSpeed
            display_precheck_result('PHP SAPI is LiteSpeed', check_php_sapi_is_litespeed(), 'Current SAPI: ' . php_sapi_name());
            ?>
        </div>
    </div>
</div>







        <!-- WP-Config Settings Panel -->
        <div class="components-panel__body">
            <h2 class="components-panel__body-title">
                <span>WP-Config Settings</span>
                <span aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="components-panel__arrow" aria-hidden="true" focusable="false">
                        <path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path>
                    </svg>
                </span>
                
                
                


                


            </h2>
            
            
<?

    // Get the sizes of debug.log and error.log
    $debug_log_size = file_exists(WP_CONTENT_DIR . '/debug.log') ? size_format(filesize(WP_CONTENT_DIR . '/debug.log')) : 'Not found';
    $error_log_size = file_exists(ABSPATH . 'error.log') ? size_format(filesize(ABSPATH . 'error.log')) : 'Not found';

    // Get current WP constants values from wp-config.php
    $wp_debug = defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'Not defined';
    $wp_debug_display = defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'Not defined';
    $wp_debug_log = defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'Not defined';

    // Get all defined constants
    $all_constants = get_defined_constants(true);

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

           
            <div class="components-panel__body-content">
                
                
                
                <!-- WP-Config Settings content goes here -->

                <div class="components-base-control components-toggle-control">
                    <div class="components-base-control__field">
                        <div class="components-flex components-h-stack">
                            <span class="components-form-toggle">
                                <input class="components-form-toggle__input" id="debug-toggle" type="checkbox" <?php echo $wp_debug === 'true' ? 'checked' : ''; ?>>
                                <span class="components-form-toggle__track"></span>
                                <span class="components-form-toggle__thumb"></span>
                            </span>
                            <label for="debug-toggle" class="components-flex-item components-flex-block components-toggle-control__label">
                                Enable WP_DEBUG (Current: <?php echo $wp_debug; ?>)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="components-base-control components-toggle-control">
                    <div class="components-base-control__field">
                        <div class="components-flex components-h-stack">
                            <span class="components-form-toggle">
                                <input class="components-form-toggle__input" id="debug-display-toggle" type="checkbox" <?php echo $wp_debug_display === 'true' ? 'checked' : ''; ?>>
                                <span class="components-form-toggle__track"></span>
                                <span class="components-form-toggle__thumb"></span>
                            </span>
                            <label for="debug-display-toggle" class="components-flex-item components-flex-block components-toggle-control__label">
                                Enable WP_DEBUG_DISPLAY (Current: <?php echo $wp_debug_display; ?>)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="components-base-control components-toggle-control">
                    <div class="components-base-control__field">
                        <div class="components-flex components-h-stack">
                            <span class="components-form-toggle">
                                <input class="components-form-toggle__input" id="debug-log-toggle" type="checkbox" <?php echo $wp_debug_log === 'true' ? 'checked' : ''; ?>>
                                <span class="components-form-toggle__track"></span>
                                <span class="components-form-toggle__thumb"></span>
                            </span>
                            <label for="debug-log-toggle" class="components-flex-item components-flex-block components-toggle-control__label">
                                Enable WP_DEBUG_LOG (Current: <?php echo $wp_debug_log; ?>)
                            </label>
                            
                            
                        </div>
                    </div>
                </div>
                
                
                
                                                     
<ul>
    <?php
    foreach ($all_constants['user'] as $constant => $value) {
        if (!in_array($constant, $exclude_constants)) {
            echo '<li>' . $constant . ': ' . ($value ? 'true' : 'false') . '</li>';
        }
    }
    ?>
</ul>



                
                
            </div>
        </div>




<!-- Plugin Status Panel -->
<h1>Plugin Status</h1>
<div class="components-panel__body is-opened">
    <h2 class="components-panel__body-title">
        <span>Installed Plugins Status</span>
        <span aria-hidden="true">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="components-panel__arrow" aria-hidden="true" focusable="false">
                <path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path>
            </svg>
        </span>
    </h2>
    <div class="components-panel__body-content">
        <?php
        $plugins = [
            'elementor/elementor.php',
            'elementor-pro/elementor-pro.php',
            'seo-by-rank-math/rank-math.php',
            'seo-by-rank-math-pro/rank-math-pro.php',
            'classic-editor/classic-editor.php',
            'jet-engine/jet-engine.php',
            'media-cleaner/media-cleaner.php',
            'wordfence/wordfence.php',
            'google-site-kit/google-site-kit.php',
            'wp-mail-smtp/wp_mail_smtp.php',
            'wp-user-avatars/wp-user-avatars.php',
            'advanced-custom-fields-pro/acf.php'
        ];

        foreach ($plugins as $plugin) {
            list($is_installed, $is_active, $is_auto_update_enabled) = check_plugin_status($plugin);

            $plugin_name = ucwords(str_replace(['-', '/'], ' ', explode('/', $plugin)[0]));

            echo "<p><strong>$plugin_name:</strong></p>";

            $installed_status = $is_installed ? 'green' : 'red';
            $installed_icon = $is_installed ? '&#x2705;' : '&#x274C;';
            echo "<p style='color: $installed_status;'>$installed_icon Installed</p>";

            $active_status = $is_active ? 'green' : 'red';
            $active_icon = $is_active ? '&#x2705;' : '&#x274C;';
            echo "<p style='color: $active_status;'>$active_icon Active</p>";

            $auto_update_status = $is_auto_update_enabled ? 'green' : 'red';
            $auto_update_icon = $is_auto_update_enabled ? '&#x2705;' : '&#x274C;';
            echo "<p style='color: $auto_update_status;'>$auto_update_icon Auto-update Enabled</p>";

            echo "<hr>";
        }
        ?>
    </div>
</div>



        <!-- Debug Information Panel -->
        <h1>Debug Information</h1>
        <div class="components-panel__body is-opened">
            <h2 class="components-panel__body-title">
                <span>Log Files</span>
                <span aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="components-panel__arrow" aria-hidden="true" focusable="false">
                        <path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path>
                    </svg>
                </span>
            </h2>
            <div class="components-panel__body-content">
                <p>Debug Log Size: <?php echo $debug_log_size; ?></p>
                <div class="components-base-control components-toggle-control">
                    <div class="components-base-control__field">
                        <div class="components-flex components-h-stack">
                            <a href="/wp-content/debug.log" target="_blank" class="components-button">View debug.log</a>
                            <button class="components-button components-notice__dismiss has-icon" id="delete-debug-log" aria-label="Delete debug.log">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <p>Error Log Size: <?php echo $error_log_size; ?></p>
                <div class="components-base-control components-toggle-control">
                    <div class="components-base-control__field">
                        <div class="components-flex components-h-stack">
                            <a href="/error.log" target="_blank" class="components-button">View error.log</a>
                            <button class="components-button components-notice__dismiss has-icon" id="delete-error-log" aria-label="Delete error.log">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>




<? hws_ct_render_auto_delete_toggle();?>



            
            </div>
        </div>
    </div>
        
        <? } ?>
        
        