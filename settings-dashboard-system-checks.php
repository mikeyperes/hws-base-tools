<?php namespace hws_base_tools;

use function hws_base_tools\modify_wp_config_constants;
use function hws_base_tools\modify_wp_config_constants_handler;
use function hws_base_tools\hws_ct_highlight_based_on_criteria;


function hws_ct_get_settings_system_checks()
{
   
    $system_checks = [
        /*
        'WordPress Comments' => [
    'id' => 'wp-comments',
    'value' => hws_ct_highlight_based_on_criteria(
        perform_comments_system_check() // Calls the function that checks the comment statuses
    ),
],*/



'Check wp-migrate-all Backups' => [
    'id' => 'wp-backups-migrate-all',
    'value' => hws_ct_highlight_based_on_criteria(check_wp_backup_status()) 
],
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

'WordFence Notification Email' => [
    'id' => 'wf-email',
    'value' => hws_ct_highlight_based_on_criteria(check_wordfence_notification_email())
],
'LSCWP_OBJECT_CACHE State' => [
    'id' => 'wp-ls-cache',
        'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('LSCWP_OBJECT_CACHE', check_wp_config_constant_status('LSCWP_OBJECT_CACHE'), ['listed_values' => ['false', false]]))

],
'display_errors State' => [
    'id' => 'display-errors',
    'value' => hws_ct_highlight_based_on_criteria(
            perform_php_ini_check('display_errors',  // Call perform_php_ini_check to get the current status
            [1, "1", "On", "on"],  // ON values passed directly
            [0, "0", "Off", "off"],  // OFF values passed directly
            [true,1, "1", "On", "on"] // mark as fail (red text) if these value
        ))
],

'error_reporting State' => [
    'id' => 'error-reporting',
    'value' => hws_ct_highlight_based_on_criteria(
        hws_ct_package_constant_value_for_checks(
            'error_reporting', 
            check_php_ini_status('error_reporting'), 
            ['listed_values' => [E_ALL]]
        )
    )
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
                'value' => hws_ct_highlight_based_on_criteria(check_smtp_auth_status_and_mailer()).'<a href="' . esc_url(admin_url('admin.php?page=wp-mail-smtp')) . '" target="_blank" class="button">Edit</a>'
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
'value' => hws_ct_highlight_based_on_criteria(check_redis_active()) . ' <a href="' . esc_url(admin_url('admin.php?page=litespeed-cache')) . '" target="_blank">View More</a>'

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
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_CACHE', check_wp_config_constant_status('WP_CACHE'), ['listed_values' => ['false', false,1]]))
    ],

            'Server RAM' => [
                'id' => 'server-ram',
                'value' => hws_ct_highlight_based_on_criteria(check_server_memory_limit())
            ],
            'Number of Processors' => [
                'id' => 'num-processors',
                'value' => hws_ct_highlight_based_on_criteria(check_server_specs()),
            ],
'post_max_size' => [
    'id' => 'php-post-max-size',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('post_max_size', check_php_ini_status('post_max_size'), ['min_value' => 300000]))
],
'upload_max_filesize' => [
    'id' => 'php-upload-max-filesize',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('upload_max_filesize', check_php_ini_status('upload_max_filesize'), ['min_value' => 300000]))
],
    ];
    return $system_checks;
}





function display_settings_system_checks()
{

    ?>

    <style>
        .block{display:block !important}
</style>
        <!-- System Checks Panel -->
    <div class="panel">
     <h2 class="panel-title">System Checks</h2>
     <small><a href="<?php echo admin_url('site-health.php'); ?>" target="_blank">View WordPress Site Health</a></small>
        <div class="panel-content">
            <?php
            $system_checks = hws_ct_get_settings_system_checks();










             foreach ($system_checks as $label => $setting): ?>

                <p id="<?php echo $setting['id']; ?>"><strong><?php echo $label; ?>:</strong> <?php echo nl2br($setting['value']); ?></p>
                
                <?php if ($setting['id'] === 'wp-ram'): ?>
                    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
                        <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="4000M" data-target="wp-ram">Add Memory Limit</button>
                    <?php endif; ?>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="512M" data-target="wp-ram">Remove WP_MEMORY_LIMIT</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-cache'):

$wp_cache_status = check_wp_config_constant_status('WP_CACHE'); 
if ($wp_cache_status === 'true' || $wp_cache_status === true): ?>
    <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="false" data-target="wp-cache">Disable WP_CACHE</button>
<?php else: ?>
    <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="true" data-target="wp-cache">Enable WP_CACHE</button>
<?php endif; ?>

<?php endif; ?>

                <?php if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()): ?>
    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="true" data-target="wp-auto-updates">Enable Auto Updates</button>
<?php elseif ($setting['id'] === 'wp-auto-updates' && check_wp_core_auto_update_status()): ?>
    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="false" data-target="wp-auto-updates">Disable Auto Updates</button>
<?php endif; ?>
                
            
                <?php if ($setting['id'] === 'plugin-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_plugins" data-action="enable">Enable Plugin Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_plugins" data-action="disable">Disable Plugin Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>

<?php if ($setting['id'] === 'wf-email'):
    $wordfence_url = admin_url('admin.php?page=WordfenceOptions'); 
?>
    <a target=_blank href="<?php echo esc_url($wordfence_url); ?>" class="button">Go to Wordfence Options</a>
<?php endif; ?>



<?php if ($setting['id'] === 'theme-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_themes" data-action="enable">Enable Theme Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_themes" data-action="disable">Disable Theme Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>
                

            <?php endforeach; ?>
        </div>
    </div>
<?php }

// Toggle Users Must Be Registered to Comment
if (!function_exists('toggle_users_must_be_registered_to_comment')) {
    function toggle_users_must_be_registered_to_comment($action) {
        if ($action === 'enable') {
            update_option('comment_registration', 1); // Enable user registration requirement
        } else if ($action === 'disable') {
            update_option('comment_registration', 0); // Disable user registration requirement
        }
        return true; // Return true to indicate success
    }
} else write_log("Warning: toggle_users_must_be_registered_to_comment function is already declared", true);