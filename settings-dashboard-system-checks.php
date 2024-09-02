<?php namespace hws_base_tools;

use function hws_base_tools\modify_wp_config_constants;
use function hws_base_tools\modify_wp_config_constants_handler;
use function hws_base_tools\hws_ct_highlight_based_on_criteria;



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

    $system_checks = [
        'WordPress Admin Email' => [
            'id' => 'wp-main-email',
            'value' => hws_ct_highlight_based_on_criteria(check_wordpress_main_email()) . 
            ' <a target="_blank" href="' . esc_url(admin_url('options-general.php')) . '">modify</a>'
        ],
        'Imagick Library Available' => [
            'id' => 'imagick-available',
            'value' => hws_ct_highlight_based_on_criteria(check_imagick_available())
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
            'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_AUTO_UPDATE_CORE', check_wp_config_constant_status('WP_AUTO_UPDATE_CORE'), ['listed_values' => ['false', false]]))

        ],
        'Cloudflare Active' => [
            'id' => 'cloudflare-active',
            'value' => hws_ct_highlight_based_on_criteria(check_cloudflare_active())
        ],   
        'PHP Type' => [
            'id' => 'php-type',
            'value' => hws_ct_highlight_based_on_criteria(check_php_type())
        ],
        'PHP Handler' => [
            'id' => 'php-handler',
            'value' => hws_ct_highlight_based_on_criteria(check_php_handler())
        ],
        'Plugin Auto Updates' => [
            'id' => 'plugin-auto-updates',
            'value' => hws_ct_highlight_based_on_criteria(
                [
                    'status' => has_filter('auto_update_plugin', '__return_true'),
                    'raw_value' => has_filter('auto_update_plugin', '__return_true') ? 'Enabled' : 'Disabled'
                ]
            ),
        ],
                    // Add for theme auto-updates
        'Theme Auto Updates' => [
            'id' => 'theme-auto-updates',
            'value' => hws_ct_highlight_based_on_criteria(
                [
                    'status' => has_filter('auto_update_theme', '__return_true'),
                    'raw_value' => has_filter('auto_update_theme', '__return_true') ? 'Enabled' : 'Disabled'
                ]  ),
        ],
        'Database Table Prefix' => [
            'id' => 'database-table-prefix',
            'value' => hws_ct_highlight_based_on_criteria(get_database_table_prefix())
            ],
            'MyISAM Tables' => [
    'id' => 'myisam-tables',
'value' => hws_ct_highlight_based_on_criteria(check_myisam_tables()) . ' - <a target=_blank href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '">View More</a>'

],
/*
'Additional WordPress Installs Detected' => [
    'id' => 'additional-wp-installs',
    'raw_value' => hws_ct_highlight_based_on_criteria(detect_additional_wp_installs())
],*/

'WordFence Notification Email' => [
    'id' => 'wf-email',
    'value' => hws_ct_highlight_based_on_criteria(check_wordfence_notification_email())
],
'LSCWP_OBJECT_CACHE State' => [
    'id' => 'wp-ls-cache',
        'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('LSCWP_OBJECT_CACHE', check_wp_config_constant_status('LSCWP_OBJECT_CACHE'), ['listed_values' => ['false', false]]))

],
'WP_DEBUG State' => [
    'id' => 'wp-debug',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG', check_wp_config_constant_status('WP_DEBUG'), ['listed_values' => ['true', true]]))

],
'WP_DEBUG_DISPLAY State' => [
    'id' => 'wp-debug-display',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG_DISPLAY', check_wp_config_constant_status('WP_DEBUG_DISPLAY'), ['listed_values' => ['true', true]]))
],
'WP_DEBUG_LOG State' => [
    'id' => 'wp-debug-log',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG_LOG', check_wp_config_constant_status('WP_DEBUG_LOG'), ['listed_values' => ['true', true]]))
],
'Debug Log Size' => [
    'id' => 'debug-log',
    'value' => hws_ct_highlight_based_on_criteria(check_log_file_sizes()['debug_log'])
],

'Error Log Size' => [
    'id' => 'error-log',
    'value' => hws_ct_highlight_based_on_criteria(check_log_file_sizes()['error_log'])
],
            'SMTP Authentication Enabled' => [
                'id' => 'smtp-auth',
                'value' => hws_ct_highlight_based_on_criteria(check_smtp_auth_status_and_mailer()).' <br /><a href="' . esc_url(admin_url('admin.php?page=wp-mail-smtp')) . '" target="_blank" class="button">Edit</a>'
            ],
          /* 'SMTP Mailer Service' => [
                'id' => 'smtp-mailer',
                'value' => hws_ct_highlight_based_on_criteria([
                    'status' => !empty(check_smtp_auth_status_and_mailer()['mailer']),
                    'value' => check_smtp_auth_status_and_mailer()['mailer']
                ])
            ],
 'SMTP Authenticated Domain' => [
    'id' => 'smtp-domain',
    'value' => hws_ct_highlight_based_on_criteria([
        'status' => !empty(check_smtp_auth_status_and_mailer()['details']),
        'details' => check_smtp_auth_status_and_mailer()['details']
    ]) . (check_smtp_auth_status_and_mailer()['details'] ?  : '')
],*/

'REDIS Active' => [
    'id' => 'redis-active',
    'value' => hws_ct_highlight_based_on_criteria(check_redis_active())
],
'Caching Source' => [
                'id' => 'caching-source',
                'value' => hws_ct_highlight_based_on_criteria(check_caching_source())
            ],
            'PHP Version' => [
                'id' => 'php-version',
                'value' => hws_ct_highlight_based_on_criteria(check_php_version())
               
                
            ],
'WordPress RAM' => [
    'id' => 'wp-ram',
    'value' => hws_ct_highlight_based_on_criteria(
        check_wordpress_memory_limit() ),
],

'WP_CACHE State' => [
    'id' => 'wp-cache',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_CACHE', check_wp_config_constant_status('WP_CACHE'), ['listed_values' => ['false', false]]))
    ],

            'Server RAM' => [
                'id' => 'server-ram',
                'value' => hws_ct_highlight_based_on_criteria(check_server_memory_limit())
            ],
            'Number of Processors' => [
                'id' => 'num-processors',
                'value' => hws_ct_highlight_based_on_criteria(check_server_specs()),
            ]
    ];
    return $system_checks;

}

/*,
            
         
            // Add for plugin auto-updates
    


   
  

           
        
       
         

          
            */

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

                <p id="<?= $setting['id'] ?>"><strong><?= $label ?>:</strong> <?php echo nl2br($setting['value']); ?></p>
                
                <?php if ($setting['id'] === 'wp-ram'): ?>
                    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
                        <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="4000M" data-target="wp-ram">Add Memory Limit</button>
                    <?php endif; ?>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="512M" data-target="wp-ram">Remove WP_MEMORY_LIMIT</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-cache'):

    $wp_cache_status = check_wp_config_constant_status('WP_CACHE'); 
 if ($wp_cache_status): ?>
        <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="false" data-target="wp-cache">Disable WP_CACHE</button>
    <?php else: ?>
        <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="true" data-target="wp-cache">Enable WP_CACHE</button>
    <?php endif; ?>
<?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()): ?>
                    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="true" data-target="wp-auto-updates">Enable Auto Updates</button>
                <?php endif; ?>
                
            
                <?php if ($setting['id'] === 'plugin-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-constant="auto_update_plugin" data-action="enable">Enable Plugin Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-constant="auto_update_plugin" data-action="disable">Disable Plugin Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>

<?php if ($setting['id'] === 'theme-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-constant="auto_update_theme" data-action="enable">Enable Theme Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-constant="auto_update_theme" data-action="disable">Disable Theme Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>
                

            <?php endforeach;




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