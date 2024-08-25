<?           
if (!function_exists('hws_ct_highlight_if_failed')) {
    function hws_ct_highlight_if_failed($result) {
        return $result['status'] ? $result['details'] : '<span style="color: red;">' . $result['details'] . '</span>';
    }
}

 
// Add settings menu and page
add_action('admin_menu', 'hws_ct_add_verified_profiles_menu');

// Specific usage example for Hexa Core Tools
function hws_ct_add_verified_profiles_menu() {
    add_options_page(
        'Hexa Core Tools',       // Page title
        'Hexa Core Tools',       // Menu title
        'manage_options',        // Capability
        'hws-core-tools',        // Menu slug
        'hws_ct_display_page'    // Callback function
    );
}

function hws_ct_display_page() { ?>

    <style>
        /* Minimalist panel styles */
        .panel {
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            background-color: #fff;
            padding: 15px;
        }

        .panel-title {
            background-color: #f7f7f7;
            padding: 15px;
            border-bottom: 1px solid #ccd0d4;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .panel-content {
            padding: 15px;
        }

        .button {
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 3px;
            text-decoration: none;
            color: #fff;
            background-color: #007cba;
            border: none;
            cursor: pointer;
            display: inline-block;
            margin-right: 10px;
        }

        .button:hover {
            background-color: #005a9c;
        }

        /* WP-Config Constants Expand/Collapse */
        .wp-config-expandable {
            border-top: 1px solid #ccd0d4;
            margin-top: 15px;
            padding-top: 15px;
        }

        .wp-config-toggle {
            cursor: pointer;
            color: #007cba;
            font-size: 16px;
            margin-bottom: 10px;
            display: block;
        }

        .wp-config-content {
            display: none;
            margin-top: 10px;
        }

        .wp-config-content ul {
            list-style: none;
            padding-left: 0;
        }

        .wp-config-content ul li {
            margin-bottom: 5px;
        }
    </style>

    <div class="wrap">
        <h1>Hexa Core Tools - WP-Config Settings</h1>
        
        
        
<!-- Essential Settings Panel -->
<div class="panel">
    <h2 class="panel-title">Essential Settings</h2>
    <div class="panel-content">
        <?php



// Gather essential settings
     $essential_settings = [
             'WordPress Auto Updates Enabled' => [
        'id' => 'wp-auto-updates',
        'value' => hws_ct_highlight_if_failed([
            'status' => get_constant_value_from_wp_config('WP_AUTO_UPDATE_CORE') === 'true',
            'details' => get_constant_value_from_wp_config('WP_AUTO_UPDATE_CORE') === 'true' ? 'Enabled' : 'Disabled'
        ]),],
        'Cloudflare Active' => [
            'id' => 'cloudflare-active',
            'value' => hws_ct_highlight_if_failed(check_cloudflare_active())
        ],
        'PHP Type' => [
            'id' => 'php-type',
            'value' => hws_ct_highlight_if_failed(check_php_type())
        ],
        'PHP Handler' => [
            'id' => 'php-handler',
            'value' => hws_ct_highlight_if_failed(check_php_handler())
        ],
            // Add for plugin auto-updates
    'Plugin Auto Updates' => [
        'id' => 'plugin-auto-updates',
        'value' => hws_ct_highlight_if_failed([
            'status' => has_filter('auto_update_plugin', '__return_true'),
            'details' => has_filter('auto_update_plugin', '__return_true') ? 'Enabled' : 'Disabled'
        ]),
    ],
                    'WordPress Email' => [
                        'id' => 'wp-email', 
                        'value' => hws_ct_highlight_if_failed(check_wordpress_main_email())
                    ],
                    'WordFence Notification Email' => [
                        'id' => 'wf-email', 
                        'value' => hws_ct_highlight_if_failed(check_wordfence_notification_email())
                    ],
                    'WP_Cache Enabled' => [
                        'id' => 'wp-cache', 
                        'value' => hws_ct_highlight_if_failed(check_wp_cache_enabled())
                    ],
                    'Debug Log Size' => [
        'id' => 'debug-log',
        'value' => hws_ct_highlight_if_failed(check_log_file_sizes()['debug_log'])
    ],
    'Error Log Size' => [
        'id' => 'error-log',
        'value' => hws_ct_highlight_if_failed(check_log_file_sizes()['error_log'])
    ],
    'SMTP Authentication Enabled' => [
        'id' => 'smtp-auth', 
        'value' => hws_ct_highlight_if_failed([
            'status' => check_smtp_auth_status_and_mailer()['status'],
            'details' => check_smtp_auth_status_and_mailer()['status'] ? 'Yes' : 'No'
        ])
    ],
    'SMTP Mailer Service' => [
        'id' => 'smtp-mailer', 
        'value' => hws_ct_highlight_if_failed([
            'status' => !empty(check_smtp_auth_status_and_mailer()['mailer']),
            'details' => check_smtp_auth_status_and_mailer()['mailer']
        ])
    ],
    'SMTP Authenticated Domain' => [
        'id' => 'smtp-domain', 
        'value' => hws_ct_highlight_if_failed([
            'status' => !empty(check_smtp_auth_status_and_mailer()['details']),
            'details' => check_smtp_auth_status_and_mailer()['details']
        ])
    ],
                    'REDIS Active' => [
                        'id' => 'redis-active', 
                        'value' => hws_ct_highlight_if_failed(check_redis_active())
                    ],
                    'Caching Source' => [
                        'id' => 'caching-source', 
                        'value' => hws_ct_highlight_if_failed(check_caching_source())
                    ],
                    'PHP Version' => [
                        'id' => 'php-version', 
                        'value' => phpversion()
                    ],
                    'WordPress RAM' => [
                        'id' => 'wp-ram',
                        'value' => hws_ct_highlight_if_failed(check_wordpress_memory_limit())
                    ],
             
                    'Server RAM' => [
                        'id' => 'server-ram',
                        'value' => hws_ct_highlight_if_failed(check_server_memory_limit())
                    ]
                ];

     

    foreach ($essential_settings as $label => $setting) {
        echo "<p id='{$setting['id']}'><strong>$label:</strong> {$setting['value']}</p>";

        // Add the "Fix Issue" button if the WordPress RAM is less than 4GB
        if ($setting['id'] === 'wp-ram' && strpos($setting['value'], 'color: red') !== false) {
            echo "<button class='button fix-ram-issue' data-target='wp-ram'>Fix Issue</button>";
        }
        
        
    // Add the "Enable Auto Updates" button if WP core auto-updates are not enabled
    if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()) {
        echo "<button class='button' id='enable-auto-updates'>Enable Auto Updates</button>";
    }
    
        // Add the "Enable Plugin Auto Updates" button if plugin auto-updates are not enabled
    if ($setting['id'] === 'plugin-auto-updates' && strpos($setting['value'], 'color: red') !== false) {
        echo "<button id='enable-plugin-auto-updates' class='button'>Enable Plugin Auto Updates</button>";
    }
    
    
    }

    // Inline JavaScript for handling the AJAX request
    ?>
    
    
    <script type="text/javascript">
jQuery(document).ready(function($) {
    // Event handler for enabling auto-updates for all plugins
    $('#enable-plugin-auto-updates').on('click', function(e) {
        e.preventDefault();

        $.post(ajaxurl, {
            action: 'enable_plugin_auto_updates'
        }, function(response) {
            if (response.success) {
                alert('Auto updates for all plugins have been enabled.');
                location.reload();
            } else {
                alert('Failed to enable auto updates for plugins: ' + response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    });
});
</script>

    <script type="text/javascript">
jQuery(document).ready(function($) {
    // Event handler for enabling WP Core auto-updates
    $('#enable-auto-updates').on('click', function(e) {
        e.preventDefault();

        $.post(ajaxurl, {
            action: 'modify_wp_config_constants',
            constants: {
                'WP_AUTO_UPDATE_CORE': 'true'
            }
        }, function(response) {
            if (response.success) {
                alert('Auto updates have been enabled.');
                location.reload();
            } else {
                alert('Failed to enable auto updates: ' + response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    });
});
</script>


<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.fix-ram-issue').on('click', function(e) {
        e.preventDefault();

        $.post(ajaxurl, {
            action: 'modify_wp_config_constants',  // This needs to match the action hook in your PHP code
            constants: {
                'WP_MEMORY_LIMIT': '4000M' // Adding the constant to update
            }
        }, function(response) {
            console.log('Raw AJAX Response:', response); // Log the entire response
            console.log('Data Object:', response.data);   // Log the data object to see what's inside

            var message = response.data ? response.data.message : 'No message received';

            if (response.success) {
                alert(message);
                location.reload();
            } else {
                alert(message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            console.error('AJAX Request Failed:', jqXHR, textStatus, errorThrown); // Debugging: Log the failure details
        });
    });
});
</script>
 

    </div>
</div>


        <!-- WordPress Prechecks Panel -->
        <div class="panel">
            <h2 class="panel-title">WordPress Prechecks</h2>
            <div class="panel-content">
                <?php
                // Precheck results
                $prechecks = [
                    
                   // 'SMTP Authentication' => check_wp_mail_smtp_authentication(),
                    'Imagick Library Available' => check_imagick_available(),
                    'Query Monitor Installed' => check_query_monitor_status()['is_installed'],
                    'Query Monitor Active' => check_query_monitor_status()['is_active'],
                    'WP-Sweep Installed' => check_wp_sweep_status()['is_installed'],
                    'WP-Sweep Active' => check_wp_sweep_status()['is_active'],
                    'Site Kit by Google Installed' => check_site_kit_by_google_status()['is_installed'],
                    'Site Kit by Google Active' => check_site_kit_by_google_status()['is_active'],
                    'Number of Processors' => check_server_specs()['num_processors'],
                    'Total RAM' => check_server_specs()['total_ram'],
                    'WordFence Notification Email Set' => check_wordfence_notification_email(),
                    'WordPress Main Email Set' => check_wordpress_main_email(),
                    'CloudLinux Configurations Enabled' => check_cloudlinux_config(),
                ];

                foreach ($prechecks as $label => $result) {
                    display_precheck_result($label, is_array($result) ? $result['status'] : $result, $result['details'] ?? '');
                }
                ?>
            </div>
        </div><!-- WP-Config Settings Panel with Expand/Collapse -->
<div class="panel">
    <h2 class="panel-title">WP-Config Settings</h2>
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
    </div>
</div>


        <!-- Plugin Status Panel -->
        <div class="panel">
            <h2 class="panel-title">Plugin Checks</h2>
            <div class="panel-content">
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
                    echo "<p style='color:" . ($is_installed ? 'green' : 'red') . ";'>" . ($is_installed ? '&#x2705; Installed' : '&#x274C; Not Installed') . "</p>";
                    echo "<p style='color:" . ($is_active ? 'green' : 'red') . ";'>" . ($is_active ? '&#x2705; Active' : '&#x274C; Not Active') . "</p>";
                    echo "<p style='color:" . ($is_auto_update_enabled ? 'green' : 'red') . ";'>" . ($is_auto_update_enabled ? '&#x2705; Auto-update Enabled' : '&#x274C; Auto-update Disabled') . "</p>";
                    echo "<hr>";
                }
                ?>
            </div>
        </div>



<!-- Theme Status Panel -->
<div class="panel">
    <h2 class="panel-title">Theme Checks</h2>
    <div class="panel-content">
        <!-- Active Theme and Auto-Updates Status -->
        <div style="margin-bottom: 15px;">
            <strong>Active Theme:</strong>
            <div style="margin-left: 15px;">
                <?php
                // Check if "Hello Elementor" theme is active
                $hello_elementor_active = is_theme_active('Hello Elementor');
                display_check_status($hello_elementor_active, 'Hello Elementor');

                // Check if auto-updates are enabled for "Hello Elementor"
                $hello_elementor_auto_update = is_theme_auto_update_enabled('hello-elementor'); // Use correct theme slug
                display_check_status($hello_elementor_auto_update, 'Auto-Updates Enabled');
                ?>
            </div>
        </div>

        <!-- List All Themes -->
        <div style="margin-bottom: 15px;">
            <strong>Installed Themes:</strong>
            <div style="margin-left: 15px;">
                <?php
                // Get all themes
                $all_themes = wp_get_themes();
                $active_theme = wp_get_theme();
                $theme_count = count($all_themes);

                // Loop through all themes and display their status
                foreach ($all_themes as $theme_name => $theme_data) {
                    $is_active = ($theme_name === $active_theme->get_stylesheet());
                    $status = $is_active ? 'Active' : 'Inactive';
                    $focus_style = $is_active ? 'font-weight: bold;' : 'color: #555;';
                    echo "<div style='$focus_style'>{$theme_data->get('Name')} - {$theme_data->get('Version')} - $status</div>";
                }
                ?>
            </div>
        </div>

        <!-- Warning if More Than 2 Themes Installed -->
        <?php if ($theme_count > 2): ?>
            <div style="color: red;">
                <strong>Warning:</strong> There are more than 2 themes installed on the site.
            </div>
        <?php endif; ?>
    </div>
</div>


<? hws_ct_display_settings_snippets();?>











        <!-- Debug Information Panel -->
        <div class="panel">
            <h2 class="panel-title">Debug Information</h2>
            <div class="panel-content">
                
                
            
                <?php hws_ct_render_auto_delete_toggle(); ?>
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
    <?php
}
?>