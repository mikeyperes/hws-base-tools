<?php namespace hws_base_tools;

// Hook to load custom JavaScript in wp-admin head
add_action('admin_head', 'hws_base_tools\active_listeners');

function active_listeners()
{?><script type="text/javascript">
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
    $('.modify-snippet-via-button').on('click', function() {
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
    $('.execute-function').on('click', function() {
        var methodName = $(this).data('method');  // Get the method name
        var state = $(this).data('state');  // Get the state
        var setting = $(this).data('setting');  // Get the setting name

        // Ensure methodName and setting are available
        if (methodName) {
         //   console.log('State passed:', state);  // Log the state for debugging
        //    console.log('Setting passed:', setting);  // Log the setting for debugging

            // Make the AJAX call to execute the function
            var dataToSend = {
                action: 'execute_function',  // The action to hook into on the server-side
                method: methodName,          // Pass the method name
                setting: setting,            // Pass the setting name
                state: state                 // Pass the state
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
</script><?php }

    
add_action('wp_ajax_execute_function', 'hws_base_tools\handle_execute_function_ajax');
add_action('wp_ajax_nopriv_execute_function', 'hws_base_tools\handle_execute_function_ajax');  // For non-logged in users (optional)
function handle_execute_function_ajax() {
    write_log("entered handle_execute_function_ajax", true);

    // Verify if the method parameter is passed and is not empty
    if (isset($_POST['method']) && !empty($_POST['method'])) {
        $method_name = sanitize_text_field($_POST['method']);
        write_log("Method name passed: " . $method_name, true);

        // Determine the correct namespace
        $namespace = 'hws_base_tools';
        $fully_qualified_function_name = $namespace . '\\' . $method_name;

  
        // Get the state if passed
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : null;
      //  $setting = isset($_POST['setting']) ? sanitize_text_field($_POST['setting']) : null;
/*
        if ($state !== null) {
            write_log("State passed: " . $state . " Setting passed: " . $setting, false);  // Log the state and setting to confirm
        } else {
            write_log("No state or setting provided in the request, exiting.", false);
            wp_send_json_error('No state or setting provided. state:'.state.":: method".method_name );
            wp_die();
        }*/

       

        // Check if the function exists with the namespace
        if (function_exists($fully_qualified_function_name)) {
            // Execute the function with both the setting and state
            $response = call_user_func($fully_qualified_function_name, $state);
            write_log("RESPONSE: " . $response, true);
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
?>