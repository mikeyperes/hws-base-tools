<?php namespace hws_base_tools;

function hws_ct_display_plugin_info() {
    // Get the current plugin's file path
    $plugin_file = plugin_basename(__FILE__);
    $file_path = WP_PLUGIN_DIR . '/' . $plugin_file;

    // Extract the plugin slug
    $slug = dirname($plugin_file);

    // Log the paths and slug for debugging
    write_log('Attempting to read file: ' . $file_path, true);
    write_log('Plugin File: ' . $plugin_file, true); // Logs the plugin file path
    write_log('Plugin Slug: ' . $slug, true);        // Logs the plugin slug

    // Fetch current plugin data
    $plugin_data = get_plugin_data($file_path);

    // Initialize the GitHub Updater to get the latest version and download URL
    global $config;
    $config['slug'] = $slug; // Update the slug in the config array
    $updater = new \hws_base_tools\WP_GitHub_Updater($config);
    $new_version = $updater->get_new_version();
    $download_url = $updater->config['zip_url'];

    ?>
    <!-- Plugin Info Panel -->
    <div class="panel">
        <h2 class="panel-title">HWS - Base Tools Plugin Info</h2>
        <div class="panel-content">
            <div style="margin-bottom: 15px;">
                <strong>Plugin Name:</strong> <?php echo esc_html($plugin_data['Name']); ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Plugin Slug:</strong> <?php echo esc_html($slug); ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Current Version:</strong> <?php echo esc_html($plugin_data['Version']); ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Latest (Repository) Version:</strong> <?php echo esc_html($new_version); ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Download URL:</strong> <a href="<?php echo esc_url($download_url); ?>" target="_blank"><?php echo esc_url($download_url); ?></a>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Plugin URI:</strong> <a href="<?php echo esc_url($plugin_data['PluginURI']); ?>" target="_blank"><?php echo esc_html($plugin_data['PluginURI']); ?></a>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Author:</strong> <a href="<?php echo esc_url($plugin_data['AuthorURI']); ?>" target="_blank"><?php echo esc_html($plugin_data['Author']); ?></a>
            </div>
        </div>
    </div>
    <?php
}