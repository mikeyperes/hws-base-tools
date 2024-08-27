<? 
add_action('wp_ajax_toggle_snippet', 'toggle_snippet');

    function toggle_snippet() {
        $settings_snippets = hws_ct_get_settings_snippets();
    
      write_log('AJAX Request received: ' . print_r($_POST, true)); // Log the incoming request
    
        $snippet_id = sanitize_text_field($_POST['snippet_id']);
        $enable = filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN);
    
      write_log('Snippet ID: ' . $snippet_id . ', Enable: ' . $enable); // Log parsed values
    
        // Find the corresponding snippet and function
        foreach ($settings_snippets as $snippet) {
            if ($snippet['id'] === $snippet_id) {
                // Update the option in the database
                $updated = update_option($snippet_id, $enable);
    
                if ($updated) {
                    wp_send_json_success('Option updated successfully.');
                } else {
                    write_log('Failed to update option.'); // Log failure
                    wp_send_json_error('Failed to update option.');
                }
    
                break;
            }
        }
    
        write_log('Invalid snippet ID: ' . $snippet_id); // Log invalid snippet ID
        wp_send_json_error('Invalid snippet ID: ' . $snippet_id);
    
        wp_die(); // Ensure proper termination of the script
    }


    function hws_ct_display_settings_snippets() {
        add_action('admin_init', 'acf_form_init');
    
        function acf_form_init() {
            acf_form_head();
        }
    
        // Start output buffering to manage where HTML and PHP content are sent
        ob_start();
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
            jQuery(document).ready(function($) {
                function toggleSnippet(snippetId) {
                    var isChecked = $('#' + snippetId).prop('checked');
    
                    // Make an AJAX call to toggle the snippet
                    $.ajax({
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
    
                // Attach the toggleSnippet function to checkbox click events
                $('input[type="checkbox"]').on('change', function() {
                    toggleSnippet(this.id);
                });
    
                // Handle "Enable Plugin Auto Updates" button click
                $('#enable-plugin-auto-updates').on('click', function() {
                    var snippetId = 'enable_auto_update_plugins';
    
                    // Automatically enable the checkbox for "Enable Automatic Updates for Plugins"
                    $('#' + snippetId).prop('checked', true);
    
                    // Trigger the toggleSnippet function to update the setting
                    toggleSnippet(snippetId);
                });
            });
        </script>
    
        <?php
        // End output buffering and flush the output
        ob_end_flush();
    }
    ?>
    