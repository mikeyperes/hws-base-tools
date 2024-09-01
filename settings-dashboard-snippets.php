<?php namespace hws_base_tools;

use function hws_base_tools\disable_rankmath_sitemap_caching;
use function hws_base_tools\enable_auto_update_plugins;
use function hws_base_tools\enable_auto_update_themes;
use function hws_base_tools\custom_wp_admin_logo;
use function hws_base_tools\disable_litespeed_js_combine;
use function hws_base_tools\hws_ct_snippets_activate_author_social_acfs;
use function hws_base_tools\write_log;
use function hws_base_tools\toggle_snippet;
use function hws_base_tools\hws_ct_get_settings_snippets;

/*
function hws_ct_get_settings_snippets()
{
    $settings_snippets = [

        [
            'id' => 'disable_rankmath_sitemap_caching',
            'name' => 'Disable RankMath Sitemap Caching',
            'description' => 'Disables caching for RankMath sitemaps.',
            'info' => 'This prevents RankMath from caching sitemaps, which can be useful for development or debugging.',
            'function' => 'disable_rankmath_sitemap_caching'
        ],
        [
            'id' => 'enable_auto_update_plugins',
            'name' => 'Enable Automatic Updates for Plugins',
            'description' => 'Enables automatic updates for all plugins.',
            'info' => 'Automatically keeps your plugins up to date.',
            'function' => 'enable_auto_update_plugins'
        ],

     [
    'id' => 'enable_auto_update_themes',
    'name' => 'Enable Automatic Updates for Themes',
    'description' => 'Enables automatic updates for all themes.',
    'info' => 'Automatically keeps your themes up to date.',
    'function' => 'enable_auto_update_themes'
],
        [
            'id' => 'enable_wp_admin_logo',
            'name' => 'Enable WP Admin Logo',
            'description' => 'Enable a custom logo on the WP admin login screen using ACF.',
            'info' => 'This will use the logo from the ACF field "login_logo".',
            'function' => 'custom_wp_admin_logo'
        ],
        [
            'id' => 'disable_litespeed_js_combine',
            'name' => 'Disable JS Combine in LiteSpeed Cache',
            'description' => 'Disables JS combining in LiteSpeed Cache.',
            'info' => 'Prevents LiteSpeed from combining JavaScript files, which can be useful for resolving issues with script loading.',
            'function' => 'disable_litespeed_js_combine'
        ],
        [
            'id' => 'custom_wp_admin_logo',
            'name' => 'Custom WP Admin Logo',
            'description' => 'Adds a custom logo to the WP admin login screen.',
            'info' => 'Allows you to upload a custom logo via ACF and display it on the login page.',
            'function' => 'custom_wp_admin_logo'
        ],
        
    [
        'name' => 'Enable Author Social ACFs',
        'id' => 'hws_ct_snippets_author_social_acfs',
         'function' => 'hws_ct_snippets_activate_author_social_acfs',
        'description' => 'This will enable social media fields in author profiles.',
        'info' => implode('<br>', array_map(function($field) {
            if ($field['type'] === 'group') {
                $sub_fields = implode(', ', array_map(function($sub_field) {
                    return "{$sub_field['name']}";
                }, $field['sub_fields']));
                return "{$field['name']}<br>&emsp;{$sub_fields}";
            } else {
                return "{$field['name']}";
            }
        }, acf_get_fields('group_590d64c31db0a')))
    ],
];
return $settings_snippets; 
}
*/
function toggle_snippet() {
    $settings_snippets = hws_ct_get_settings_snippets();

    write_log('AJAX Request received: ' . print_r($_POST, true)); // Log the incoming request

    $snippet_id = sanitize_text_field($_POST['snippet_id']);
    $enable = filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN);

    write_log('Snippet ID: ' . $snippet_id . ', Enable: ' . ($enable ? 'true' : 'false')); // Log parsed values

    // Find the corresponding snippet and function
    foreach ($settings_snippets as $snippet) {
        if ($snippet['id'] === $snippet_id) {
            // Update the option in the database
            $current_value = get_option($snippet_id);
            write_log('Current value of ' . $snippet_id . ': ' . print_r($current_value, true)); // Log current value

            $updated = update_option($snippet_id, $enable);

            if ($updated) {
                write_log('Option updated successfully for ' . $snippet_id); // Log success
                wp_send_json_success('Option updated successfully.');
            } else {
                write_log('Failed to update option. Current value might be the same.'); // Log failure
                wp_send_json_error('Failed to update option. Current value might be the same.');
            }

            exit; // Stop further processing once the correct snippet is found
        }
    }

    write_log('Invalid snippet ID: ' . $snippet_id); // Log invalid snippet ID
    wp_send_json_error('Invalid snippet ID: ' . $snippet_id);

    wp_die(); // Ensure proper termination of the script
}


    add_action('wp_ajax_toggle_snippet', 'hws_base_tools\toggle_snippet');

    function hws_ct_display_settings_snippets() {
        add_action('admin_init', 'acf_form_init');
    
        function acf_form_init() {
            acf_form_head();
        }
        ?>
    

    <style>
        .panel-settings-snippets {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f7f7f7;
            padding: 10px 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }

        .panel-settings-snippets .panel-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .panel-settings-snippets .panel-content {
            padding: 10px 0;
        }

        .panel-settings-snippets ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .panel-settings-snippets li {
            padding: 1px 0;
            font-size: 12px;
            color: #888;
        }

        .panel-settings-snippets input[type="checkbox"] {
            margin-right: 10px;
        }

        .panel-settings-snippets label {
            font-size: 13px;
            color: #555;
        }

        .panel-settings-snippets small {
            display: block;
            margin-top: 3px;
            color: #777;
            font-size: 12px;
        }

        .snippet-item {
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dcdcdc;
            background-color: #fff;
        }
    </style>
        <!-- Snippets Status Panel -->
        <div class="panel panel-settings-snippets">
            <h2 class="panel-title">Snippets</h2>
            <div class="panel-content">
                <h3>Active Snippets:</h3>
                <div style="margin-left: 15px; color: green;">
                    <?php
                    // Initialize an array to store active snippets
                    $active_snippets = [];
                    $settings_snippets = hws_ct_get_settings_snippets();
    
                    // Iterate through the snippets and check which ones are active
                    foreach ($settings_snippets as $snippet) {
                        $is_enabled = get_option($snippet['id'], false);
                        if ($is_enabled) {
                            $active_snippets[] = $snippet['name']; // Add active snippet names to the array
                        }
                    }
    
                        // Display active snippets or a message if none are found
                if (!empty($active_snippets)) {
                    echo "<ul>";
                    foreach ($active_snippets as $snippet_name) {
                        echo "<li>&#x2705; {$snippet_name}</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>No active snippets found.</p>";
                }
                    ?>
                </div>
    
                <!-- Snippet Actions and Status -->
                <div style="margin-bottom: 15px;">
                    <h3>Available Snippets:</h3>
                    <div style="margin-left: 15px;">
                        <?php
                        // Loop through all snippets and display them with a checkbox
                        foreach ($settings_snippets as $snippet) {
                            $is_enabled = get_option($snippet['id'], false);
    
                            // Determine if the checkbox should be checked
                            $checked = $is_enabled ? 'checked' : '';
    
                            // Display the checkbox and label with the info field included
                            echo "<div style='color: #555; margin-bottom: 10px;'>
                                    <input type='checkbox' id='{$snippet['id']}' onclick='toggleSnippet(\"{$snippet['id']}\")' $checked>
                                    <label for='{$snippet['id']}'>
                                        {$snippet['name']} - <em>{$snippet['description']}</em>
                                        <br>
                                        <small><strong>Details:</strong><br>{$snippet['info']}</small>
                                    </label>
                                  </div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>



     <script type="text/javascript">
    function toggleSnippet(snippetId) {
        var isChecked = jQuery('#' + snippetId).prop('checked');

        // Make an AJAX call to toggle the snippet
        jQuery.ajax({
            url: ajaxurl,  // Ensure ajaxurl is set correctly
            type: 'post',
            data: {
                action: 'toggle_snippet',
                snippet_id: snippetId,
                enable: isChecked
            },
            success: function(response) {
                if(response.success) {
                    alert(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                alert('An AJAX error occurred: ' + textStatus + ' - ' + errorThrown);
            }
        });
    }
<<<<<<< HEAD

=======
 
>>>>>>> 4583631 (fixed GIT issue)
    jQuery(document).ready(function($) {
    // Handle "Toggle Auto Updates" button click
    $('.modify-snippet-via-button').on('click', function() {
        var constant = $(this).data('constant');
        var action = $(this).data('action');
        var snippetId = null;

        // Explicitly check for each constant and set the corresponding snippetId
        if (constant === 'auto_update_plugin') {
            snippetId = 'enable_auto_update_plugins';
        } else if (constant === 'auto_update_theme') {
            snippetId = 'enable_auto_update_themes';
        }

        // Do nothing if snippetId is not set (invalid constant)
        if (snippetId === null) {
            return;
        }

        // Toggle the checkbox state based on the action (enable or disable)
        var checkbox = $('#' + snippetId);
        var isChecked = (action === 'enable');
        checkbox.prop('checked', isChecked);

        // Trigger the toggleSnippet function to update the setting
        toggleSnippet(snippetId);
    });
});

    </script><? } ?>