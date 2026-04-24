<?php namespace hws_base_tools;
use function hws_base_tools\is_theme_auto_update_enabled;

// Register AJAX handler for theme deletion
add_action( 'wp_ajax_hws_delete_themes', __NAMESPACE__ . '\\ajax_delete_themes' );

/**
 * AJAX handler to delete selected themes
 */
function ajax_delete_themes() {
    // Check permissions
    if ( ! current_user_can( 'delete_themes' ) ) {
        wp_send_json_error( 'Unauthorized - you do not have permission to delete themes.' );
        return;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], HWS_AJAX_NONCE ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }
    
    // Get themes to delete
    $themes_to_delete = isset( $_POST['themes'] ) ? array_map( 'sanitize_text_field', $_POST['themes'] ) : [];
    
    if ( empty( $themes_to_delete ) ) {
        wp_send_json_error( 'No themes selected.' );
        return;
    }
    
    // Don't allow deleting the active theme
    $active_theme = wp_get_theme()->get_stylesheet();
    $themes_to_delete = array_filter( $themes_to_delete, function( $theme ) use ( $active_theme ) {
        return $theme !== $active_theme;
    });
    
    if ( empty( $themes_to_delete ) ) {
        wp_send_json_error( 'Cannot delete the active theme.' );
        return;
    }
    
    // Load required files
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    
    $deleted = [];
    $failed = [];
    
    foreach ( $themes_to_delete as $theme_slug ) {
        $result = delete_theme( $theme_slug );
        if ( is_wp_error( $result ) ) {
            $failed[] = $theme_slug . ': ' . $result->get_error_message();
        } else {
            $deleted[] = $theme_slug;
        }
    }
    
    if ( ! empty( $deleted ) ) {
        wp_send_json_success( [
            'message' => 'Deleted ' . count( $deleted ) . ' theme(s): ' . implode( ', ', $deleted ),
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    } else {
        wp_send_json_error( 'Failed to delete themes: ' . implode( '; ', $failed ) );
    }
}

function display_settings_theme_checks() { ?>
    <!-- Theme Status Panel -->
    <div class="panel">
        <h2 class="panel-title">Theme Checks</h2>
        <small><a href="<?= admin_url('themes.php') ?>" target="_blank">View all themes</a></small>
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
                    $hello_elementor_auto_update = is_theme_auto_update_enabled('hello-elementor');
                    display_check_status($hello_elementor_auto_update, 'Auto-Updates Enabled');
                    ?>
                </div>
            </div>

            <!-- List All Themes with checkboxes -->
            <div style="margin-bottom: 15px;">
                <strong>Installed Themes:</strong>
                <p style="font-size: 12px; color: #666; margin: 5px 0;">Select inactive themes to delete:</p>
                <div style="margin-left: 15px;" id="hws-theme-list">
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
                    
                        // Check if auto-updates are enabled for the theme
                        $theme_slug = $theme_data->get_stylesheet();
                        $auto_update_enabled = is_theme_auto_update_enabled($theme_slug);
                        $auto_update_status = $auto_update_enabled ? 'Auto-Updates Enabled' : 'Auto-Updates Disabled';
                        $auto_update_style = $auto_update_enabled ? 'color: green;' : 'color: red;';
                    
                        // Check if updates are available for the theme
                        $theme_updates = get_site_transient('update_themes');
                        $updates_available = isset($theme_updates->response[$theme_slug]);
                    
                        // Adjust the updates status display
                        $update_status = $updates_available ? '<span style="color: red;">Updates Available</span>' : '';
                        
                        // Checkbox for non-active themes
                        $checkbox = $is_active ? '' : '<input type="checkbox" class="hws-theme-checkbox" value="' . esc_attr( $theme_slug ) . '" style="margin-right: 8px;">';
                    
                        echo "<div data-theme-row='" . esc_attr( $theme_slug ) . "' style='$focus_style margin-bottom: 5px;'>$checkbox{$theme_data->get('Name')} - {$theme_data->get('Version')} - $status - <span style='$auto_update_style'>$auto_update_status</span> $update_status</div>";
                    }
                    ?>
                </div>
                
                <!-- Delete Selected Themes Button -->
                <?php if ( $theme_count > 1 ) : ?>
                <div style="margin-top: 15px;">
                    <button type="button" id="hws-delete-selected-themes" class="button button-secondary" style="color: #d63638; border-color: #d63638;">
                        🗑️ Delete Selected Themes
                    </button>
                    <button type="button" id="hws-select-all-inactive-themes" class="button button-secondary" style="margin-left: 10px;">
                        Select All Inactive
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Warning if More Than 2 Themes Installed -->
            <?php if ($theme_count > 2): ?>
                <div style="color: red;">
                    <strong>Warning:</strong> There are more than 2 themes installed on the site.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Theme Delete JS -->
    <script>
    jQuery(document).ready(function($) {
        // Select all inactive themes
        $('#hws-select-all-inactive-themes').on('click', function() {
            $('.hws-theme-checkbox').prop('checked', true);
        });
        
        // Delete selected themes
        $('#hws-delete-selected-themes').on('click', function() {
            var selectedThemes = [];
            $('.hws-theme-checkbox:checked').each(function() {
                selectedThemes.push($(this).val());
            });
            
            if (selectedThemes.length === 0) {
                alert('Please select at least one theme to delete.');
                return;
            }
            
            if (!confirm('Are you sure you want to delete ' + selectedThemes.length + ' theme(s)? This cannot be undone.')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_delete_themes',
                    themes: selectedThemes,
                    nonce: typeof hwsNonce !== 'undefined' ? hwsNonce : '<?php echo wp_create_nonce( HWS_AJAX_NONCE ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        (response.data.deleted || []).forEach(function(themeSlug) {
                            $('[data-theme-row="' + themeSlug + '"]').remove();
                        });
                        $btn.prop('disabled', false).text('🗑️ Delete Selected Themes');
                    } else {
                        alert('Error: ' + response.data);
                        $btn.prop('disabled', false).text('🗑️ Delete Selected Themes');
                    }
                },
                error: function() {
                    alert('AJAX request failed.');
                    $btn.prop('disabled', false).text('🗑️ Delete Selected Themes');
                }
            });
        });
    });
    </script>
    <?php
}
?>
