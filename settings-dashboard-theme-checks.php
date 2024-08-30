<?php namespace hws_base_tools;
function hws_ct_display_settings_theme_checks()
{    ?>
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
                    $hello_elementor_auto_update = is_theme_auto_update_enabled('hello-elementor'); // Use correct theme slug
                    display_check_status($hello_elementor_auto_update, 'Auto-Updates Enabled');
                    ?>
                </div>
            </div>

            <!-- List All Themes -->
            <div style="margin-bottom: 15px;">
                <strong>Installed Themes:</strong>
                <div style="margin-left: 15px;">
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
                        $auto_update_enabled = wp_is_auto_update_enabled_for_type('theme', $theme_slug);

                        // Adjust the status display to include auto-update information
                        $auto_update_status = $auto_update_enabled ? 'Auto-Updates Enabled' : 'Auto-Updates Disabled';
                        $auto_update_style = $auto_update_enabled ? 'color: green;' : 'color: red;';

                        echo "<div style='$focus_style'>{$theme_data->get('Name')} - {$theme_data->get('Version')} - $status - <span style='$auto_update_style'>$auto_update_status</span></div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Warning if More Than 2 Themes Installed -->
            <?php if ($theme_count > 2): ?>
                <div style="color: red;">
                    <strong>Warning:</strong> There are more than 2 themes installed on the site.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

}
?>
