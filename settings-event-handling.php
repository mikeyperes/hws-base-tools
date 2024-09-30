<?php namespace hws_base_tools;

// Hook to load custom JavaScript in wp-admin head
add_action('admin_head', 'hws_base_tools\activate_listeners');

function activate_listeners()
{?>

<script type="text/javascript">
jQuery(document).ready(function($) {
        // Toggle WP-Config Constants section
      /*  $('.wp-config-toggle').on('click', function() {
            $(this).next('.wp-config-content').slideToggle();
        });*/

        // Delete log files
        function deleteLogFile(logType) {
            const action = logType === 'debug' ? 'delete_debug_log' : 'delete_error_log';
            $.post(ajaxurl, { action: action }, function(response) {

                if (response.success) {
                    console.log(logType + '.log deleted successfully');
                    location.reload();
                } else {
                    console.error('Failed to delete ' + logType + '.log:', response.data);
              //      alert('Error: ' + response.data);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            }); 
        }   
 
        // Bind delete actions to buttons
        $('#hws-base-tools #delete-debug-log').on('click', function(e) {
            e.preventDefault();
            deleteLogFile('debug');
        });

        $('#hws-base-tools #delete-error-log').on('click', function(e) {
            e.preventDefault();
            deleteLogFile('error');
        });
 
        // Handle the auto-delete toggle
        $('#hws-base-tools #auto-delete-toggle').on('change', function() {
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
     $('#hws-base-tools #auto-delete-toggle').on('change', function() {
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

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Copy debug log to clipboard
        $('#hws-base-tools #copy-debug-log').on('click', function() {
            var text = $('#debug-log-content').text();
            navigator.clipboard.writeText(text).then(function() {
                alert('Debug log copied to clipboard.');
            }, function(err) {
                alert('Failed to copy debug log: ' + err);
            });
        });

        // Copy error log to clipboard
        $('#hws-base-tools #copy-error-log').on('click', function() {
            var text = $('#error-log-content').text();
            navigator.clipboard.writeText(text).then(function() {
                alert('Error log copied to clipboard.');
            }, function(err) {
                alert('Failed to copy error log: ' + err);
            });
        });

    });

/*
        // Toggle WP-Config Constants section
        $('.wp-config-toggle').on('click', function() {
            $(this).next('.wp-config-content').slideToggle();
        });
*/
        // Delete log files
    /*    function deleteLogFile(logType) {
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



        */



    
    </script>


<script type="text/javascript">
    console.log("hws_base_tools: Listeners activated");
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
  
    jQuery(document).ready(function($) {


          // Handle "Toggle Auto Updates" button click
    // Handle "Toggle Auto Updates" button click
    $('#hws-base-tools .modify-snippet-via-button').on('click', function() {
        var snippetId = $(this).data('snippet-id');
        var action = $(this).data('action');
        
     
        // Now you can directly use snippetId without conditional checks
        alert("Action: " + action + " | Snippet ID: " + snippetId);
  

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


$ = jQuery;
$(document).ready(function($) {
    // Handle click event and AJAX request all in one function
    $('#hws-base-tools .execute-function').on('click', function() {
        var methodName = $(this).data('method');  // Get the method name
        var state = $(this).data('state');  // Get the state
        var setting = $(this).data('setting');  // Get the setting name
        var variable = $(this).data('variable');  // Get the setting name

        // Ensure methodName and setting are available
        if (methodName) {
         //   console.log('State passed:', state);  // Log the state for debugging
        //    console.log('Setting passed:', setting);  // Log the setting for debugging

            // Make the AJAX call to execute the function
            var dataToSend = {
                action: 'execute_function',  // The action to hook into on the server-side
                method: methodName,          // Pass the method name
                setting: setting,            // Pass the setting name
                state: state,                // Pass the state
                variable: variable                 // Pass the variable
            };

            jQuery.ajax({
                url: ajaxurl,  // WordPress provides this for AJAX calls in the admin area
                type: 'post',
                data: dataToSend,
                success: function(response) {
                    if (response.success) {
                        alert(methodName+' executed successfully: ' + response.data);
                    } else {
                        alert('Error for '+methodName+': ' + response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('An AJAX error occurred: ' + textStatus + ' - ' + errorThrown);
                }
            });
        } else {
            alert('No method or setting provided.');
        }
    });
});
</script>


<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#hws-base-tools #toggle-debug-log').on('click', function() {
        $('#debug-log-content').toggle();
        $(this).text($(this).text() === 'View Last 200 Lines of debug.log' ? 'Hide Last 200 Lines of debug.log' : 'View Last 100 Lines of debug.log');
    });

    $('#hws-base-tools #toggle-error-log').on('click', function() {
        $('#error-log-content').toggle();
        $(this).text($(this).text() === 'View Last 200 Lines of error_log' ? 'Hide Last 200 Lines of error_log' : 'View Last 100 Lines of error_log');
    });
});
</script><script>
jQuery(document).ready(function($) {
    $('#hws-base-tools .modify-wp-config').on('click', function(e) {
        e.preventDefault();
        const constant = $(this).data('constant');
        const value = $(this).data('value');
        const target = $(this).data('target');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hws_modify_wp_config_constants',
                constants: { [constant]: value }
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Configuration updated successfully.');
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to update configuration.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Request Failed:', jqXHR, textStatus, errorThrown);
                alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
            }
        });
    });
});
</script>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#hws-base-tools #debug-toggle, #hws-base-tools #debug-display-toggle, #hws-base-tools #debug-log-toggle').on('change', function() {
    var setting = $(this).attr('id').replace('-toggle', '').replace(/-/, '_').toUpperCase();
    var value = $(this).is(':checked'); // This now keeps the value as a boolean
    updateDebugSetting('WP_' + setting, value);
    alert("hihihi");
});
    function updateDebugSetting(setting, value) {
    alert('Sending request to update ' + setting + ' to ' + (value ? 'true' : 'false'));
    $.post(ajaxurl, {
        action: 'modify_wp_config_constants',
        constants: {
            [setting]: value // Send the boolean directly
        }
    },
    
    function(response) {
        console.log('Raw AJAX Response:', response);

        try {
            var jsonResponse = JSON.parse(response);
            var message = jsonResponse.data ? jsonResponse.data.message : 'No message received';

            if (jsonResponse.success) {
                alert(setting + ' set to ' + (value ? 'true' : 'false'));
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
<script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#php-ini-toggle').on('click', function() {
                $('#php-ini-details').slideToggle();
            });
        });
    </script>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#hws-base-tools #force-update-check').on('click', function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Checking...');

            $.post(
                '<?= admin_url('admin-ajax.php') ?>', 
                {
                    action: 'hws_ct_force_update_check'
                }, 
                function(response) {
                    var data = JSON.parse(response);
                    $('#last-checked').text(data.last_checked);
                    $('#plugins-with-updates').text(data.plugins_with_updates);

                    // Update the plugins list
                    var $pluginsList = $('#plugins-list');
                    $pluginsList.empty();
                    if (data.plugins_with_updates > 0) {
                        $.each(data.plugins_list, function(index, pluginName) {
                            $pluginsList.append('<li>' + pluginName + '</li>');
                        });
                    }

                    $button.prop('disabled', false).text('Force WordPress to Check for Plugin Updates');
                }
            ).fail(function() {
                $button.prop('disabled', false).text('Failed to Check');
            });
        });
    });
    </script>
        <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#hws-base-tools #force-update-check').on('click', function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Checking...');

            $.post(
                '<?php echo admin_url('admin-ajax.php'); ?>', 
                {
                    action: 'hws_ct_force_update_check'
                }, 
                function(response) {
                    var data = JSON.parse(response);
                    $('#last-checked').text(data.last_checked);
                    $('#plugins-with-updates').text(data.plugins_with_updates);

                    // Update the plugins list
                    var $pluginsList = $('#plugins-list');
                    $pluginsList.empty();
                    if (data.plugins_with_updates > 0) {
                        $.each(data.plugins_list, function(index, pluginName) {
                            $pluginsList.append('<li>' + pluginName + '</li>');
                        });
                    }

                    $button.prop('disabled', false).text('Force WordPress to Check for Plugin Updates');
                }
            ).fail(function() {
                $button.prop('disabled', false).text('Failed to Check');
            });
        });
    });
    </script>
       <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Event handler for enabling auto-updates for all plugins
            $('#hws-base-tools #enable-plugin-auto-updates').on('click', function(e) {
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
            $('#hws-base-tools #enable-auto-updates').on('click', function(e) {
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
<?php }

    
add_action('wp_ajax_execute_function', 'hws_base_tools\handle_execute_function_ajax');
add_action('wp_ajax_nopriv_execute_function', 'hws_base_tools\handle_execute_function_ajax');  // For non-logged in users (optional)
function handle_execute_function_ajax() {
    write_log("entered handle_execute_function_ajax", true);


    // Verify if the method parameter is passed and is not empty
    if (isset($_POST['method']) && !empty($_POST['method'])) {
        $method_name = sanitize_text_field($_POST['method']);
        write_log("Method name passed: " . $method_name, true);
        $variable = "";
        if(isset($_POST['variable']))
        $variable = $_POST['variable'];
        // Determine the correct namespace
        $namespace = 'hws_base_tools';
        $fully_qualified_function_name = $namespace . '\\' . $method_name;

        // Get the state if passed
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : null;

        // Check if the function exists with the namespace
        if (function_exists($fully_qualified_function_name)) {
            // Execute the function with both the setting and state

            if($method_name == "toggle_php_ini_value")
            $response = call_user_func($fully_qualified_function_name,$variable, $state);
            else
            $response = call_user_func($fully_qualified_function_name, $state);
        
            // Send a success response with the result of the function execution
            wp_send_json_success($response);
        } else {
            write_log("The function does not exist: " . $fully_qualified_function_name, true);
            wp_send_json_error('The function does not exist.');
        }
    } else {
        wp_send_json_error('No method name provided.');
    }

    wp_die();  // This is required to properly terminate the script when doing AJAX in WordPress
}



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

add_action('wp_ajax_hws_modify_wp_config_constants', 'hws_base_tools\modify_wp_config_constants_handler');


?>