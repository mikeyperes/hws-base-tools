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




   



function hws_ct_display_settings_snippets(){
    add_action('admin_init', 'acf_form_init');
function acf_form_init() {
    acf_form_head();
}

?>

<!-- Snippets Status Panel --> 
<div class="panel">
    <h2 class="panel-title">Snippets</h2>
    <div class="panel-content">
    <strong>Active Snippets:</strong>
            <div style="margin-left: 15px; color: green;">
           <? // Iterate through the snippets and check which ones are active
           $settings_snippets = hws_ct_get_settings_snippets();
    foreach ($settings_snippets as $snippet) {
        $is_enabled = get_option($snippet['id'], false);
        if ($is_enabled) {
            $active_snippets[] = $snippet['name']; // Add active snippet names to the array
        }
    }

    if (!empty($active_snippets)) {
        echo "<ul>";
        foreach ($active_snippets as $snippet_name) {
            echo "<li>{$snippet_name}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No active snippets found.</p>";
    }

    echo "</div>";
    echo "</div>";
?>
            </div>
        <!-- Snippet Actions and Status -->
        <div style="margin-bottom: 15px;">
            <strong>Available Snippets:</strong>
            <div style="margin-left: 15px;">
                <?php

                $settings_snippets = hws_ct_get_settings_snippets();

                // Loop through all snippets and display them with a checkbox
                foreach ($settings_snippets as $snippet) {
                    // Check if the snippet is enabled in the options
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
                            </label>";

                    // Add the ACF field right below the checkbox for the WP Admin Logo snippet
                    if ($snippet['id'] === 'enable_wp_admin_logo' && function_exists('acf_form')) {
                        // Ensure ACF is initialized before calling acf_form
                        acf_form_head();
                        
                        acf_form(array(
                            'post_id' => 'options',
                            'fields' => array('login_logo'),
                            'form' => false,
                        ));
                    }

                    echo "</div>";
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
                // Log error details to the console for debugging
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

<? }?>