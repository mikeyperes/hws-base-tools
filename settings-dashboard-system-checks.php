<?php namespace hws_base_tools;

use function hws_base_tools\modify_wp_config_constants;
use function hws_base_tools\modify_wp_config_constants_handler;



function modify_wp_config_constants_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $constants = isset($_POST['constants']) ? $_POST['constants'] : [];
    if (empty($constants)) {
        wp_send_json_error(['message' => 'No constants provided']);
    }

    $result = modify_wp_config_constants($constants);

    if ($result['status']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

add_action('wp_ajax_modify_wp_config_constants', 'hws_base_tools\modify_wp_config_constants_handler');



function hws_ct_get_settings_system_checks()
{

        // Gather system checks settings
        $system_checks = [
            'Database Table Prefix' => [
    'id' => 'database-table-prefix',
    'value' => hws_ct_highlight_if_essential_setting_failed(get_database_table_prefix())
],
            'WordPress Admin Email' => [
                'id' => 'wp-main-email',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wordpress_main_email()) . 
                ' <a target=_blank href="' . esc_url(admin_url('options-general.php')) . '">modify</a>'
  

            ],
            'Imagick Library Available' => [
                'id' => 'imagick-available',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => check_imagick_available(),
                    'details' => check_imagick_available() ? 'Available' : 'Not Available'
                ]),
            ],
            /*
            'CloudLinux Configurations Enabled' => [
                'id' => 'cloudlinux-config',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => check_cloudlinux_config(),
                    'details' => check_cloudlinux_config() ? 'Enabled' : 'Not Enabled'
                ]),
            ],*/
            'WordPress Auto Updates Enabled' => [
                'id' => 'wp-auto-updates',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => get_constant_value_from_wp_config('WP_AUTO_UPDATE_CORE') === 'true',
                    'details' => get_constant_value_from_wp_config('WP_AUTO_UPDATE_CORE') === 'true' ? 'Enabled' : 'Disabled'
                ]),
            ],
            'Cloudflare Active' => [
                'id' => 'cloudflare-active',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_cloudflare_active())
            ],
            'PHP Type' => [
                'id' => 'php-type',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_php_type())
            ],
            'PHP Handler' => [
                'id' => 'php-handler',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_php_handler())
            ],
            // Add for plugin auto-updates
            'Plugin Auto Updates' => [
                'id' => 'plugin-auto-updates',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => has_filter('auto_update_plugin', '__return_true'),
                    'details' => has_filter('auto_update_plugin', '__return_true') ? 'Enabled' : 'Disabled'
                ]),
            ],
            // Add for theme auto-updates
'Theme Auto Updates' => [
    'id' => 'theme-auto-updates',
    'value' => hws_ct_highlight_if_essential_setting_failed([
        'status' => has_filter('auto_update_theme', '__return_true'),
        'details' => has_filter('auto_update_theme', '__return_true') ? 'Enabled' : 'Disabled'
    ]),
],
  
            'MyISAM Tables' => [
    'id' => 'myisam-tables',
    'value' => hws_ct_highlight_if_essential_setting_failed(check_myisam_tables())
],
            'WordFence Notification Email' => [
                'id' => 'wf-email',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wordfence_notification_email())
            ],
'WP_CACHE State' => [
    'id' => 'wp-cache',
    'value' => hws_ct_highlight_if_essential_setting_failed(check_wp_config_constant_status("WP_CACHE")),
],


            'LSCWP_OBJECT_CACHE State' => [
                'id' => 'wp-ls-cache',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wp_config_constant_status("LSCWP_OBJECT_CACHE"))
            ],
            'WP_DEBUG State' => [
                'id' => 'wp-debug',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wp_config_constant_status("WP_DEBUG"))
            ],
            'WP_DEBUG_DISPLAY State' => [
                'id' => 'wp-debug-display',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wp_config_constant_status("WP_DEBUG_DISPLAY"))
            ],
            'WP_DEBUG_LOG State' => [
                'id' => 'wp-debug-log',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_wp_config_constant_status("WP_DEBUG_LOG"))
            ],
      
         

      'Debug Log Size' => [
    'id' => 'debug-log',
    'value' => check_log_file_sizes()['debug_log']['details'] === 'Not Found' 
              ? 'Not Found' 
              : (check_log_file_sizes()['debug_log']['status'] 
                 ? hws_ct_highlight_if_essential_setting_failed(check_log_file_sizes()['debug_log']) 
                 : check_log_file_sizes()['debug_log']['details'])
],
'Error Log Size' => [
    'id' => 'error-log',
    'value' => check_log_file_sizes()['error_log']['details'] === 'Not Found' 
              ? 'Not Found' 
              : (check_log_file_sizes()['error_log']['status'] 
                 ? hws_ct_highlight_if_essential_setting_failed(check_log_file_sizes()['error_log']) 
                 : check_log_file_sizes()['error_log']['details'])
],
            'SMTP Authentication Enabled' => [
                'id' => 'smtp-auth',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => check_smtp_auth_status_and_mailer()['status'],
                    'details' => check_smtp_auth_status_and_mailer()['status'] ? 'Yes' : 'No'
                ])
            ],
            'SMTP Mailer Service' => [
                'id' => 'smtp-mailer',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => !empty(check_smtp_auth_status_and_mailer()['mailer']),
                    'details' => check_smtp_auth_status_and_mailer()['mailer']
                ])
            ],
            'SMTP Authenticated Domain' => [
                'id' => 'smtp-domain',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => !empty(check_smtp_auth_status_and_mailer()['details']),
                    'details' => check_smtp_auth_status_and_mailer()['details']
                ])
            ],
            'REDIS Active' => [
                'id' => 'redis-active',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_redis_active())
            ],
            'Caching Source' => [
                'id' => 'caching-source',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_caching_source())
            ],
            'PHP Version' => [
                'id' => 'php-version',
                'value' => phpversion()
            ],
   'WordPress RAM' => [
    'id' => 'wp-ram',
    'value' => hws_ct_highlight_if_essential_setting_failed(check_wordpress_memory_limit()),
],
            'Server RAM' => [
                'id' => 'server-ram',
                'value' => hws_ct_highlight_if_essential_setting_failed(check_server_memory_limit())
            ],
            'Number of Processors' => [
                'id' => 'num-processors',
                'value' => hws_ct_highlight_if_essential_setting_failed([
                    'status' => check_server_specs()['num_processors'] !== 'Unknown',
                    'details' => check_server_specs()['num_processors'] . ' processors'
                ]),
            ]
        ];
        return $system_checks;

}



function hws_ct_display_settings_system_checks()
{

    ?>
    <!-- System Checks Panel -->
    <div class="panel">
     <h2 class="panel-title">System Checks</h2>
     <small><a href="<?= admin_url('site-health.php') ?>" target="_blank">View WordPress Site Health</a></small>
        <div class="panel-content">
            <?php
            $system_checks = hws_ct_get_settings_system_checks();
            
             foreach ($system_checks as $label => $setting): ?>
                <p id="<?= $setting['id'] ?>"><strong><?= $label ?>:</strong> <?= nl2br($setting['value']) ?></p>
                
                <?php if ($setting['id'] === 'wp-ram'): ?>
                    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
                        <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="4000M" data-target="wp-ram">Add Memory Limit</button>
                    <?php endif; ?>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="__unset__" data-target="wp-ram">Remove WP_MEMORY_LIMIT</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-cache'): ?>
                    <?php if (check_wp_config_constant_status('WP_CACHE')['status']): ?>
                        <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="false" data-target="wp-cache">Disable WP_CACHE</button>
                    <?php else: ?>
                        <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="true" data-target="wp-cache">Enable WP_CACHE</button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()): ?>
                    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="true" data-target="wp-auto-updates">Enable Auto Updates</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'plugin-auto-updates' && strpos($setting['value'], 'color: red') !== false): ?>
                    <button class="button modify-snippet-via-button" data-constant="auto_update_plugin" data-value="__return_true" data-target="plugin-auto-updates">Enable Plugin Auto Updates</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'theme-auto-updates' && strpos($setting['value'], 'color: red') !== false): ?>
                    <button class="button modify-snippet-via-button" data-constant="auto_update_theme" data-value="__return_true" data-target="theme-auto-updates">Enable Theme Auto Updates</button>
                <?php endif; ?>
            <?php endforeach;

/*

            foreach ($system_checks as $label => $setting) {
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

                // Add the "Enable Theme Auto Updates" button if theme auto-updates are not enabled
if ($setting['id'] === 'theme-auto-updates' && strpos($setting['value'], 'color: red') !== false) {
    echo "<button id='enable-theme-auto-updates' class='button'>Enable Theme Auto Updates</button>";
}

            }*/
            ?>
        </div>
    </div>

    <script>

jQuery(document).ready(function($) {
    $('.modify-wp-config').on('click', function(e) {
        e.preventDefault();

        const constant = $(this).data('constant');
        const value = $(this).data('value');
        const target = $(this).data('target');

        $.post(ajaxurl, {
            action: 'modify_wp_config_constants',
            constants: {
                [constant]: value
            }
        }, function(response) {
            if (response.success) {
                alert(response.data.message || 'Configuration updated successfully.');
                location.reload();
            } else {
                alert(response.data.message || 'Failed to update configuration.');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Request Failed:', jqXHR, textStatus, errorThrown);
            alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
        });
    });
});




</script>
<? 

//add_action('wp_ajax_hws_ct_update_wp_config', 'hws_ct_update_wp_config');


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
    <?php
    

}?>