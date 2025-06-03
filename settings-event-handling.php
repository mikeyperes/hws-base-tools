<?php namespace hws_base_tools;

// Hook to load custom JavaScript in wp-admin head
add_action('admin_head',  __NAMESPACE__ . '\\activate_listeners');
add_action('wp_ajax_'.__NAMESPACE__.'_execute_function',  __NAMESPACE__ . '\\handle_execute_function_ajax');
add_action('wp_ajax_nopriv_'.__NAMESPACE__.'_execute_function',  __NAMESPACE__ . '\\handle_execute_function_ajax');  // For non-logged in users (optional)
add_action('wp_ajax_'.__NAMESPACE__.'_modify_wp_config_constants',  __NAMESPACE__ . '\\modify_wp_config_constants_handler');
add_action('wp_ajax_'.__NAMESPACE__.'_toggle_snippet',  __NAMESPACE__ . '\\toggle_snippet');


function activate_listeners()
{?>




<script>
jQuery(document).ready(function($) {
    // 1) Bind to all <button class="execute-function"> inside #hws-base-tools
    $('#hws-base-tools .execute-function').on('click', function(e) {
        e.preventDefault();

        var $btn       = $(this);
        var methodName = $btn.data('method');   // e.g. "toggle_wordpress_comments_new"
        var state      = $btn.data('state');    // e.g. "enable" or "disable"
        var reportDiv  = $('#hws-base-tools .comments-report');

        if ( methodName === 'toggle_wordpress_comments_new' ) {
            // 2) First, call the generic AJAX handler to get total_posts & batch_size
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action:  'hws_base_tools_execute_function',
                    method:  methodName,
                    state:   state
                },
                success: function(response) {
                    if ( ! response.success ) {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        return;
                    }

                    var data       = response.data;
                    var totalPosts = parseInt(data.total_posts, 10)  || 0;
                    var batchSize  = parseInt(data.batch_size, 10)   || 20;
                    var message    = data.message || '';

                    // Show initial message
                    reportDiv.text( message );

                    // Kick off the first batch (offset=0)
                    processCommentsBatch(0, totalPosts, batchSize, state, reportDiv);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Initial AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
                }
            });

        } else {
            // 3) Fallback: any other methodName just calls the generic handler unchanged
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action:   'hws_base_tools_execute_function',
                    method:   methodName,
                    state:    state,
                    variable: $btn.data('variable') || ''
                },
                success: function(response) {
                    if ( response.success ) {
                        alert(methodName + ' executed successfully:\n' + JSON.stringify(response.data,null,2));
                        // Optionally reload or update DOM
                    } else {
                        alert('Error for ' + methodName + ':\n' + (response.data || 'No message'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('AJAX request failed: ' + textStatus + ', ' + errorThrown);
                }
            });
        }
    });

    /**
     * Recursively process comments in batches.
     *
     * @param {number} offset      The offset of posts already handled
     * @param {number} totalPosts  Total published posts
     * @param {number} batchSize   How many posts per AJAX call
     * @param {string} state       'enable' or 'disable'
     * @param {jQuery} reportDiv   The DIV where we show progress
     */
    function processCommentsBatch(offset, totalPosts, batchSize, state, reportDiv) {
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
            action:     'hws_base_tools_toggle_wordpress_comments_batch',
            state:      state,
            offset:     offset,
            batch_size: batchSize
        },
        success: function(response) {
            if (!response.success) {
                reportDiv.html(
                    '<span style="color:red;">Error: ' +
                    (response.data && response.data.message ? response.data.message : 'Unknown error') +
                    '</span>'
                );
                return;
            }
            var data       = response.data;
            var processed  = data.processed;
            var nextOffset = data.next_offset;
            var total      = data.total_posts;

            reportDiv.text('Processed ' + nextOffset + ' of ' + total + ' posts…');

            if (nextOffset < total && processed > 0) {
                setTimeout(function() {
                    processCommentsBatch(nextOffset, total, batchSize, state, reportDiv);
                }, 200);
            } else {
                reportDiv.html(
                    '<span style="color:green;">All ' + total +
                    ' posts have had comments ' + (state === 'enable' ? 'enabled' : 'disabled') +
                    '.</span>'
                );
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // Log raw info to console
            console.error('Batch AJAX Error:', {
                httpStatus:   jqXHR.status + ' ' + jqXHR.statusText,
                textStatus:   textStatus,
                errorThrown:  errorThrown,
                responseText: jqXHR.responseText,
                responseJSON: jqXHR.responseJSON
            });

            // Build a more helpful message for "0" or other errors
            var responseText = (jqXHR.responseText || '').trim();
            var explanation  = '';

            if (responseText === '0') {
                explanation = 
                  '<br><strong>Note:</strong> WordPress responded with "0". ' +
                  'That usually means one of two things:<br>' +
                  '• The AJAX “action” (hws_base_tools_toggle_wordpress_comments_batch) was not registered.<br>' +
                  '• You are not logged in or lack the required capability (manage_options).<br>';
            }

            var messageHtml = 
                '<span style="color:red;">' +
                '<strong>AJAX Error</strong><br>' +
                'HTTP Status: ' + jqXHR.status + ' ' + jqXHR.statusText + '<br>' +
                'textStatus: ' + textStatus + '<br>' +
                'errorThrown: ' + errorThrown + '<br>' +
                'Response Text:<br>' +
                '<code style="white-space: pre-wrap; display:block; max-height:200px; overflow-y:auto; ' +
                'background:#f9f9f9; padding:8px; border:1px solid #ddd;">' +
                ( responseText
                    ? responseText.replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    : '(empty response)' ) +
                '</code>' +
                explanation +
                '</span>';

            reportDiv.html(messageHtml);
        }
    });
}








});
</script>



































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
                action: '<?php echo __NAMESPACE__; ?>_toggle_auto_delete',
                
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
             action: '<?php echo __NAMESPACE__; ?>_toggle_auto_delete',
                
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


    </script>


<script type="text/javascript">
    console.log("hws_base_tools: Listeners activated");



      // 1) Create (or reuse) the global namespace object
  window.hws_base_tools = window.hws_base_tools || {};

// 2) Move toggleSnippet() into our namespace
window.hws_base_tools.toggleSnippet = function(snippetId) {
  var isChecked = jQuery('#' + snippetId).prop('checked');
  alert("snippet ID: " + snippetId);

  // Make the AJAX call under our namespace
  jQuery.ajax({
    url: ajaxurl,
    type: 'post',
    data: {
      action: '<?php echo __NAMESPACE__; ?>_toggle_snippet',
      snippet_id: snippetId,
      enable: isChecked
    },
    success: function(response) {
      if (response.success) {
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
};






/*
    function toggleSnippet(snippetId) {
        var isChecked = jQuery('#' + snippetId).prop('checked');
alert("snippet ID: "+snippetId+"::: is checked"+isChecked);
        // Make an AJAX call to toggle the snippet
        jQuery.ajax({
            url: ajaxurl,  // Ensure ajaxurl is set correctly
            type: 'post',
            data: {
                
                action: '<?php echo __NAMESPACE__; ?>_toggle_snippet',

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
*/
// assets/js/hws-base-tools-listeners.js
;(function($){
  'use strict';

  // Define a unique namespace for our plugin listeners
  var ns = 'hws_base_tools';
  window[ns] = window[ns] || {};

  window[ns].toggleSnippet = function(snippetId) {
    var isChecked = $('#'+snippetId).prop('checked');
    $.ajax({
      url: ajaxurl,
      type: 'post',
      data: {
        action: ns + '_toggle_snippet',
        snippet_id: snippetId,
        enable: isChecked
      },
      success: function(response) {
        if (response.success) {
          alert(response.data);
        } else {
          alert('Error: ' + response.data);
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
        alert('An AJAX error occurred: ' + textStatus + ' - ' + errorThrown);
      }
    });  
  };

})(jQuery);















  
    jQuery(document).ready(function($) {


          // Handle "Toggle Auto Updates" button click
    // Handle "Toggle Auto Updates" button click
    $('#hws-base-tools .modify-snippet-via-button').on('click', function() {
        var snippetId = $(this).data('snippet-id');
        var action = $(this).data('action');
        
     
        // Now you can directly use snippetId without conditional checks
        alert(action + " | Snippet ID: " + snippetId);
  

        // Do nothing if snippetId is not set (invalid constant)
        if (snippetId === null) {
            return;
        }

        // Toggle the checkbox state based on the action (enable or disable)
        var checkbox = $('#' + snippetId);
        var isChecked = (action === 'enable');
        checkbox.prop('checked', isChecked);

        // Trigger the toggleSnippet function to update the setting
        window.hws_base_tools.toggleSnippet(snippetId);
    
    });
});A















/*

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
  url: ajaxurl,
  type: 'POST',
  dataType: 'json',        // force jQuery to parse JSON
  data:   dataToSend
})
.done(function(response, textStatus, jqXHR) {
  if ( response.success ) {
    alert(methodName + ' executed successfully: ' + response.data);
  } else {
    // server returned success=false
    console.error('AJAX Logical Error:', response);
    alert('Error for ' + methodName + ':\n' + (response.data || 'No message'));
  }
})
.fail(function(jqXHR, textStatus, errorThrown) {
  // collect every bit of info
  var info = {
    method:        methodName,
    setting:       dataToSend.setting,
    state:         dataToSend.state,
    httpStatus:    jqXHR.status + ' ' + jqXHR.statusText,
    textStatus:    textStatus,
    errorThrown:   errorThrown,
    responseText:  jqXHR.responseText,
    responseJSON:  jqXHR.responseJSON
  };

  console.error('AJAX Request Failed:', info);

  // craft a user-facing message
  var message = 
    'AJAX Error (' + methodName + ')\n' +
    'HTTP: ' + info.httpStatus + '\n' +
    'Status: ' + info.textStatus + '\n' +
    'Error: ' + info.errorThrown + '\n\n' +
    'Response:\n' + jqXHR.responseText;

  alert(message);
})
.always(function() {
  // optional cleanup or spinner-stop here
});






        } else {
            alert('No method or setting provided.');
        }
    });
});
*/







$ = jQuery;
$(document).ready(function($) {

  // assets/js/hws-base-tools-listeners.js
  ;(function($){
    'use strict';

    var ns = 'hws_base_tools';
    window[ns] = window[ns] || {};

    /**
     * Execute a PHP function via AJAX, namespaced to avoid conflicts.
     * If a button has data-type="ini", we send an object { type:"ini", value: ... }.
     * Otherwise, we send the raw value for a DEFINE.
     *
     * @param {jQuery} $btn  The <button> that was clicked
     */
    window[ns].executeFunction = function($btn) {
      // 1) Read all possible data-attributes from the button
      var methodName = $btn.data('method');            // e.g. "modify_wp_config_constants"
      var setting    = $btn.data('setting')  || '';    // existing behavior
      var state      = $btn.data('state')    || '';    // existing behavior
      var variable   = $btn.data('variable') || '';    // existing behavior

      // NEW for "ini" support:
      var type      = $btn.data('type')      || '';    // either "ini" or ""
      var constant  = $btn.data('constant')  || '';    // e.g. "display_errors" or "WP_MEMORY_LIMIT"
      var value     = $btn.data('value')     || '';    // e.g. "Off" or "4000M"
      var target    = $btn.data('target')    || '';    // (if you still need data-target)

      // 2) Make sure methodName is provided
      if ( ! methodName ) {
        alert('No data-method provided on this button.');
        return;
      }

      // 3) Build the payload object we will send in "constants"
      //    We always send a "constants" key (so PHP sees $_POST['constants'])
      var constantsPayload = {};

      if ( type === 'ini' && constant ) {
        // If data-type="ini", create nested object:
        //    constantsPayload[ constant ] = { type:"ini", value: "Off" };
        constantsPayload[ constant ] = {
          type:  'ini',
          value: value
        };
      } else if ( constant ) {
        // No "ini" type: just send a simple string, which triggers define(...)
        //    constantsPayload[ constant ] = "4000M"  (or any raw value)
        constantsPayload[ constant ] = value;
      }

      // 4) If no "constant" is provided, fall back to old behavior (method/setting/state/variable)
      var postData;
      if ( constant ) {
        // We are updating wp-config via modify_wp_config_constants
        postData = {
          action:    'modify_wp_config_constants',
          constants: constantsPayload
        };
      } else {
        // No "constant" key: use the legacy execute_function path
        postData = {
          action:   ns + '_execute_function',
          method:   methodName,
          setting:  setting,
          state:    state,
          variable: variable
        };
      }

      // 5) Send the AJAX request
      var originalText = $btn.text();
      $btn.prop('disabled', true).text('Working…');

      $.ajax({
        url: ajaxurl,
        type: 'post',
        dataType: 'json',
        data: postData
      })
      .done(function(response, textStatus, jqXHR) {
        if ( response.success ) {
          // If we went the wp-config route, response.data may contain "inserted_lines"
          // or a simple message string. We just alert whatever response.data is.
          alert(methodName + ' executed successfully:\n' + JSON.stringify(response.data, null, 2));
        } else {
          console.error('AJAX Logical Error:', response);
          alert('Error for ' + methodName + ':\n' + (response.data || 'No message'));
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown) {
        var info = {
          method:        methodName,
          setting:       setting,
          state:         state,
          constant:      constant,
          value:         value,
          type:          type,
          httpStatus:    jqXHR.status + ' ' + jqXHR.statusText,
          textStatus:    textStatus,
          errorThrown:   errorThrown,
          responseText:  jqXHR.responseText,
          responseJSON:  jqXHR.responseJSON
        };
        console.error('AJAX Request Failed:', info);

        var message =
          'AJAX Error (' + methodName + ')\n' +
          'HTTP: ' + info.httpStatus + '\n' +
          'Status: ' + info.textStatus + '\n' +
          'Error: ' + info.errorThrown + '\n\n' +
          'Response:\n' + jqXHR.responseText;
        alert(message);
      })
      .always(function() {
        $btn.prop('disabled', false).text(originalText);
      });
    };

    // 6) Bind click handler to any <button class="execute-function"> inside #hws-base-tools
    $(document).ready(function() {
      $('#hws-base-tools .execute-function').on('click', function(e) {
        e.preventDefault();
        window[ns].executeFunction( $(this) );
      });
    });

  })(jQuery);

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
</script>



<script>


jQuery(document).ready(function($) {
    $('#hws-base-tools .modify-wp-config').on('click', function(e) {

        e.preventDefault();

        var constant = $(this).data('constant');
        var value    = $(this).data('value');
        var type     = $(this).data('type') || '';
        var payload  = {};

        if (type === 'ini' && constant) {
            payload[constant] = { type: 'ini', value: value };
        } else if (constant) {
            payload[constant] = value;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: '<?php echo __NAMESPACE__; ?>_modify_wp_config_constants',
                constants: payload
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



})(jQuery);
</script>


</script>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#hws-base-tools #debug-toggle, #hws-base-tools #debug-display-toggle, #hws-base-tools #debug-log-toggle').on('change', function() {
    var setting = $(this).attr('id').replace('-toggle', '').replace(/-/, '_').toUpperCase();
    var value = $(this).is(':checked'); // This now keeps the value as a boolean
    updateDebugSetting('WP_' + setting, value);
});
    function updateDebugSetting(setting, value) {
    alert('Sending request to update ' + setting + ' to ' + (value ? 'true' : 'false'));
    $.post(ajaxurl, {
        action: '<?php echo __NAMESPACE__; ?>_tmodify_wp_config_constants',
              
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
                    action: '<?php echo __NAMESPACE__; ?>_force_update_check',
            

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
                    action: '<?php echo __NAMESPACE__; ?>_force_update_check',
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
                    action: '<?php echo __NAMESPACE__; ?>_enable_plugin_auto_updates',
            
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

                    action: '<?php echo __NAMESPACE__; ?>_modify_wp_config_constants',
            
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

 
if (!function_exists(__NAMESPACE__.'\toggle_snippet')) {
    function toggle_snippet() {
        $settings_snippets = hws_ct_get_settings_snippets();

        // Retrieve the snippet ID and the enable/disable state from the AJAX request
        $snippet_id = sanitize_text_field($_POST['snippet_id']);
        $enable = filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN);

        write_log("Toggle snippet called with ID: {$snippet_id}, enable: " . ($enable ? 'true' : 'false'));

        // Find the corresponding snippet and function
        foreach ($settings_snippets as $snippet) {
            if ($snippet['id'] === $snippet_id) {
                // Get the current value from the database
                $current_value = get_option($snippet_id);
                write_log("Current value of '{$snippet_id}': " . var_export($current_value, true));

                // Ensure both current and new values are booleans for accurate comparison
                $current_value_bool = filter_var($current_value, FILTER_VALIDATE_BOOLEAN);

                // Only update if the value has actually changed
                if ($current_value_bool !== $enable) {
                    write_log("Attempting to update '{$snippet_id}' to " . ($enable ? 'true' : 'false'));

                    // Attempt the update
                    $updated = update_option($snippet_id, $enable);

                    // Log the result of the update attempt
                    if ($updated) {
                        write_log("Option '{$snippet_id}' updated successfully.");
                        wp_send_json_success("Option '{$snippet_id}' updated successfully.");
                    } else {
                        global $wpdb;
                        $db_error = $wpdb->last_error;
                        write_log("Failed to update option '{$snippet_id}'. Database error: {$db_error}");
                        wp_send_json_error("Failed to update option '{$snippet_id}'. Database error: {$db_error}");
                    }
                } else {
                    write_log("No update required for '{$snippet_id}'. Current value is the same as the new value.");
                    wp_send_json_error("No update required for '{$snippet_id}'. Current value is the same.");
                }

                exit; // Stop further processing once the correct snippet is found
            }
        }

        write_log("Invalid snippet ID: {$snippet_id}");
        wp_send_json_error("Invalid snippet ID: {$snippet_id}");

        wp_die(); // Ensure proper termination of the script
    }
} else write_log("Warning: hws_base_tools/toggle_snippet function is already declared", true);







/**
 * Register our AJAX handler.
 */
add_action( 'wp_ajax_execute_function', __NAMESPACE__ . '\\execute_function' );
/**
 * Handle the `execute_function` AJAX call with debug logging.
 */
function execute_function() {
    write_log( 'execute_function() called' );

    // Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        write_log( 'Permission denied for user ' . get_current_user_id() );
        wp_send_json_error( 'Insufficient permissions.' );
    }

    // Read & sanitize inputs
    $method   = isset( $_POST['method'] )   ? sanitize_text_field( wp_unslash($_POST['method']) )   : '';
    $setting  = isset( $_POST['setting'] )  ? sanitize_text_field( wp_unslash($_POST['setting']) )  : '';
    $state    = isset( $_POST['state'] )    ? sanitize_text_field( wp_unslash($_POST['state']) )    : '';
    $variable = isset( $_POST['variable'] ) ? sanitize_text_field( wp_unslash($_POST['variable']) ) : '';

    write_log( compact('method','setting','state','variable') );

    // Build the full function name
    $func = __NAMESPACE__ . '\\' . $method;
    if ( ! function_exists( $func ) ) {
        write_log( "Function not found: $func" ,true);
        wp_send_json_error( "Function “{$method}” not found." );
    }

    // Invoke
    try {
        $result = call_user_func( $func, $state, $variable );
        write_log( [ 'result' => $result ]  ,true);
        wp_send_json_success( $result );
    }
    catch ( \Throwable $e ) {
        write_log( 'Exception: ' . $e->getMessage() );
        wp_send_json_error( $e->getMessage() );
    }
}
?>