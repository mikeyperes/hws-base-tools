<?php namespace hws_base_tools;

// Register AJAX handler for plugin download
add_action( 'wp_ajax_hws_download_plugin_zip', __NAMESPACE__ . '\\ajax_download_plugin_zip' );

/**
 * AJAX handler to download plugin, rename folder, and provide clean zip
 */
function ajax_download_plugin_zip() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    
    $github_repo = 'mikeyperes/hws-base-tools';
    $github_branch = 'main';
    $correct_folder_name = 'hws-base-tools';
    
    // Download URL from GitHub
    $download_url = 'https://github.com/' . $github_repo . '/archive/refs/heads/' . $github_branch . '.zip';
    
    // Create temp directory
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/hws-temp-' . time();
    $temp_zip = $temp_dir . '/github-download.zip';
    $final_zip = $upload_dir['basedir'] . '/' . $correct_folder_name . '.zip';
    
    // Create temp directory
    if ( ! wp_mkdir_p( $temp_dir ) ) {
        wp_send_json_error( 'Could not create temp directory' );
        return;
    }
    
    // Download the file from GitHub
    $response = wp_remote_get( $download_url, [
        'timeout'  => 60,
        'stream'   => true,
        'filename' => $temp_zip,
    ]);
    
    if ( is_wp_error( $response ) ) {
        // Cleanup
        @unlink( $temp_zip );
        @rmdir( $temp_dir );
        wp_send_json_error( 'Failed to download from GitHub: ' . $response->get_error_message() );
        return;
    }
    
    // Check if download was successful
    if ( ! file_exists( $temp_zip ) ) {
        @rmdir( $temp_dir );
        wp_send_json_error( 'Download failed - file not created' );
        return;
    }
    
    // Extract the zip
    $extract_dir = $temp_dir . '/extracted';
    wp_mkdir_p( $extract_dir );
    
    $zip = new \ZipArchive();
    if ( $zip->open( $temp_zip ) !== true ) {
        // Cleanup
        @unlink( $temp_zip );
        @rmdir( $temp_dir );
        wp_send_json_error( 'Failed to open downloaded zip file' );
        return;
    }
    
    $zip->extractTo( $extract_dir );
    $zip->close();
    
    // Find the extracted folder (GitHub names it repo-branch)
    $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        // Cleanup
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in extracted zip' );
        return;
    }
    
    $wrong_folder = $extracted_folders[0];
    $correct_folder = $extract_dir . '/' . $correct_folder_name;
    
    // Rename folder from hws-base-tools-main to hws-base-tools
    if ( basename( $wrong_folder ) !== $correct_folder_name ) {
        rename( $wrong_folder, $correct_folder );
    } else {
        $correct_folder = $wrong_folder;
    }
    
    // Create new zip with correct folder name
    $new_zip = new \ZipArchive();
    if ( $new_zip->open( $final_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to create new zip file' );
        return;
    }
    
    // Add all files from the correctly named folder
    hws_add_folder_to_zip( $new_zip, $correct_folder, $correct_folder_name );
    $new_zip->close();
    
    // Cleanup temp directory
    hws_delete_directory( $temp_dir );
    
    // Return download URL
    $final_url = $upload_dir['baseurl'] . '/' . $correct_folder_name . '.zip';
    
    wp_send_json_success( [
        'message' => 'Plugin zip created successfully',
        'url'     => $final_url,
    ]);
}

/**
 * Recursively add folder contents to zip
 */
function hws_add_folder_to_zip( $zip, $folder, $base_path ) {
    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( $folder ),
        \RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ( $files as $file ) {
        if ( ! $file->isDir() ) {
            $file_path = $file->getRealPath();
            $relative_path = $base_path . '/' . substr( $file_path, strlen( $folder ) + 1 );
            $zip->addFile( $file_path, $relative_path );
        }
    }
}

/**
 * Recursively delete directory
 */
function hws_delete_directory( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    
    $files = array_diff( scandir( $dir ), [ '.', '..' ] );
    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        is_dir( $path ) ? hws_delete_directory( $path ) : unlink( $path );
    }
    
    rmdir( $dir );
}

/**
 * Get the latest version from GitHub repository
 * 
 * @param string $repo GitHub repo in format 'owner/repo'
 * @param string $branch Branch name (default: main)
 * @return string|false Version string or false on failure
 */
function hws_get_github_version( $repo, $branch = 'main' ) {
    $transient_key = 'hws_github_ver_' . md5( $repo . $branch );
    $cached = get_site_transient( $transient_key );
    
    if ( $cached !== false ) {
        return $cached;
    }
    
    $url = 'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/initialization.php';
    
    $response = wp_remote_get( $url, [
        'timeout'   => 10,
        'sslverify' => true,
    ] );
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return false;
    }
    
    $body = wp_remote_retrieve_body( $response );
    if ( preg_match( '/^[\s\*]*Version:\s*(.+)$/mi', $body, $matches ) ) {
        $version = trim( $matches[1] );
        set_site_transient( $transient_key, $version, 6 * HOUR_IN_SECONDS );
        return $version;
    }
    
    return false;
}
 
function hws_ct_get_plugin_data() {
    // Determine the main plugin file
    $plugin_file = __FILE__; // This should point to the current file

    // Get the directory name
    $plugin_dir = dirname($plugin_file);

    // Define the main plugin file explicitly
    $main_plugin_file = $plugin_dir . '/initialization.php'; // Update this to the correct main file

    // Ensure the file exists, is a regular file, and is readable
    if (!file_exists($main_plugin_file) || !is_file($main_plugin_file) || !is_readable($main_plugin_file)) {
        write_log("Main plugin file does not exist, is a directory, or is not readable: $main_plugin_file", true);
        return [
            'Name' => 'Not Available',
            'Version' => 'Not Available',
            'PluginURI' => 'Not Available',
            'Author' => 'Not Available',
            'AuthorURI' => 'Not Available',
        ];
    }

    // Fetch plugin data using WordPress' built-in function
    $plugin_data = get_plugin_data($main_plugin_file);

    // If any field is empty, set it to 'Not Available'
    foreach ($plugin_data as $key => $value) {
        if (empty($value)) {
            $plugin_data[$key] = 'Not Available';
        }
    }

    return $plugin_data;


    
}

function hws_ct_display_plugin_info() {
    // Fetch the plugin data
    $plugin_data = hws_ct_get_plugin_data();

    // Get version info - use helper function for simplicity
    $github_repo = 'mikeyperes/hws-base-tools';
    $github_branch = 'main';
    $new_version = hws_get_github_version( $github_repo, $github_branch ) ?: 'Not Available';
    $download_url = 'https://github.com/' . $github_repo . '/archive/' . $github_branch . '.zip';

    // Extract the URL and name from the author HTML
    preg_match('/href=["\']([^"\']+)["\']/', $plugin_data['Author'], $matches);
    $author_url = $matches[1] ?? '#';
    $author_name = strip_tags($plugin_data['Author']);
 
    // Display the plugin information
    ?> 
    <!-- Plugin Info Panel -->
    <div class="panel">
        <h2 class="panel-title">HWS - Base Tools Plugin Info</h2>
        <div class="panel-content">
            <div style="margin-bottom: 15px;">
                <strong>Plugin Name:</strong> <?php echo esc_html($plugin_data['Name']); ?>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Plugin Slug:</strong> <?php echo esc_html(dirname(plugin_basename(__FILE__))); ?>
            </div>
            <div style="margin-bottom: 15px;">
    <strong style="color: <?php echo ($plugin_data['Version'] !== $new_version) ? 'red' : 'inherit'; ?>;">Current Version:</strong> 
    <span style="color: <?php echo ($plugin_data['Version'] !== $new_version) ? 'red' : 'inherit'; ?>;">
        <?php echo esc_html($plugin_data['Version']); ?>
    </span>
</div>
<div style="margin-bottom: 15px;">
    <strong>Latest Version:</strong> <?php echo esc_html($new_version); ?>
    <br /><small>If the latest version number does not reflect the version number on GitHub, please wait until the Git API reflects the correct version.</small>    <?php
    // Generate the dynamic URL for update check
    $update_check_url = admin_url('update-core.php?force-check=1');
    // Generate the dynamic URL for plugins page
    $plugins_page_url = admin_url('plugins.php');
  
    // Output the buttons with the dynamic URLs
    echo '<br /><small><i><a href="' . esc_url($update_check_url) . '" target="_blank">Force WordPress to Perform an Update Check</a></i></small>';
    echo '<br /><small><i><a href="' . esc_url($plugins_page_url) . '" target="_blank">View Plugins Page</a></i></small>';
    ?>
</div>

            
            <div style="margin-bottom: 15px;">
                <strong>Download URL:</strong> <a href="<?php echo esc_url($download_url); ?>" target="_blank"><?php echo esc_html($download_url); ?></a>
            </div>
            
            <!-- Download Plugin Button -->
            <div style="margin-bottom: 15px; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                <strong>📦 Download Plugin (Properly Named):</strong>
                <p style="font-size: 12px; color: #666; margin: 5px 0 10px;">
                    Downloads from GitHub, fixes folder name (removes -main), and provides clean zip ready for WordPress.
                </p>
                <button type="button" id="hws-download-plugin-zip" class="button button-primary">
                    ⬇️ Download hws-base-tools.zip
                </button>
                <span id="hws-download-status" style="margin-left: 10px;"></span>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#hws-download-plugin-zip').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#hws-download-status');
                    
                    $btn.prop('disabled', true).text('⏳ Downloading & Processing...');
                    $status.text('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hws_download_plugin_zip'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.html('<a href="' + response.data.url + '" target="_blank" style="color: green;">✅ Click to Download</a>');
                                // Auto-trigger download
                                window.location.href = response.data.url;
                            } else {
                                $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                            }
                            $btn.prop('disabled', false).text('⬇️ Download hws-base-tools.zip');
                        },
                        error: function() {
                            $status.html('<span style="color: red;">❌ AJAX Error</span>');
                            $btn.prop('disabled', false).text('⬇️ Download hws-base-tools.zip');
                        }
                    });
                });
            });
            </script>
            
            <div style="margin-bottom: 15px;">
                <strong>Plugin URI:</strong> <a href="<?php echo esc_url($plugin_data['PluginURI']); ?>" target="_blank"><?php echo esc_html($plugin_data['PluginURI']); ?></a>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Author:</strong> 
                <a href="<?php echo esc_url($author_url); ?>" target="_blank"><?php echo esc_html($author_name) . ' - ' . esc_html(parse_url($author_url, PHP_URL_HOST)); ?></a>
            </div>
        </div>
    </div>
    <?php
}