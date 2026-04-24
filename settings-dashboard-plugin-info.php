<?php namespace hws_base_tools;

// Register AJAX handlers
add_action( 'wp_ajax_hws_download_plugin_zip', __NAMESPACE__ . '\\ajax_download_plugin_zip' );
add_action( 'wp_ajax_hws_force_update_check', __NAMESPACE__ . '\\ajax_force_update_check' );
add_action( 'wp_ajax_hws_direct_update_plugin', __NAMESPACE__ . '\\ajax_direct_update_plugin' );
add_action( 'wp_ajax_hws_load_github_versions', __NAMESPACE__ . '\\ajax_load_github_versions' );
add_action( 'wp_ajax_hws_download_specific_version', __NAMESPACE__ . '\\ajax_download_specific_version' );

function hws_plugin_info_require_nonce() {
    hws_require_ajax_nonce_or_error();
}

/**
 * AJAX: Load available versions (commits) from GitHub
 * Fetches actual plugin version from initialization.php at each commit
 */
function ajax_load_github_versions() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    hws_plugin_info_require_nonce();
    
    $github_repo = Config::$github_repo;
    
    // Fetch recent commits from GitHub API
    $commits_url = 'https://api.github.com/repos/' . $github_repo . '/commits?per_page=30';
    $response = wp_remote_get( $commits_url, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version'),
        ],
    ]);
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Failed to fetch versions: ' . $response->get_error_message() );
        return;
    }
    
    $body = wp_remote_retrieve_body( $response );
    $commits = json_decode( $body, true );
    
    if ( ! is_array( $commits ) ) {
        wp_send_json_error( 'Invalid response from GitHub' );
        return;
    }
    
    // Format commits for dropdown
    $versions = [];
    foreach ( $commits as $index => $commit ) {
        if ( ! isset( $commit['sha'], $commit['commit']['message'] ) ) {
            continue;
        }
        
        $sha = $commit['sha'];
        $sha_short = substr( $sha, 0, 7 );
        $message = $commit['commit']['message'];
        $date = isset( $commit['commit']['committer']['date'] ) 
            ? date( 'M j, Y', strtotime( $commit['commit']['committer']['date'] ) )
            : '';
        
        // Fetch actual plugin version from initialization.php at this commit
        // Only fetch for first 10 commits to avoid GitHub rate limits
        $version_label = '';
        if ( $index < 10 ) {
            $version_label = hws_get_version_from_commit( $github_repo, $sha );
        }
        
        // Fallback: try to extract version from commit message
        if ( ! $version_label && preg_match( '/v?(\d+\.\d+(?:\.\d+)?)/i', $message, $matches ) ) {
            $version_label = $matches[1];
        }
        
        // Get first line of commit message
        $message_first_line = strtok( $message, "\n" );
        if ( strlen( $message_first_line ) > 40 ) {
            $message_first_line = substr( $message_first_line, 0, 37 ) . '...';
        }
        
        // Build display name - include version if found
        if ( $version_label ) {
            $display_name = 'v' . $version_label . ' - ' . $message_first_line . ' (' . $date . ')';
        } else {
            $display_name = $sha_short . ' - ' . $message_first_line . ' (' . $date . ')';
        }
        
        $versions[] = [
            'name' => $display_name,
            'sha' => $sha,
            'version' => $version_label,
            'zip_url' => 'https://github.com/' . $github_repo . '/archive/' . $sha . '.zip',
        ];
    }
    
    wp_send_json_success( [
        'versions' => $versions,
        'count' => count( $versions ),
    ]);
}

/**
 * Fetch plugin version from initialization.php at a specific commit
 * 
 * @param string $repo GitHub repo (owner/repo)
 * @param string $sha Commit SHA
 * @return string Version number or empty string
 */
function hws_get_version_from_commit( $repo, $sha ) {
    // Use GitHub raw content URL
    $raw_url = 'https://raw.githubusercontent.com/' . $repo . '/' . $sha . '/initialization.php';
    
    $response = wp_remote_get( $raw_url, [
        'timeout' => 5,
        'headers' => [
            'User-Agent' => 'WordPress/' . get_bloginfo('version'),
        ],
    ]);
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return '';
    }
    
    $content = wp_remote_retrieve_body( $response );
    
    // Try to extract version from plugin header: * Version: X.X.X or Version: X.X.X
    if ( preg_match( '/\*?\s*Version:\s*(\d+\.\d+(?:\.\d+)?)/i', $content, $matches ) ) {
        return $matches[1];
    }
    
    // Try constant style: HWS_VERSION = 'X.X.X'
    if ( preg_match( '/HWS_VERSION[\'"\s,=]+[\'"](\d+\.\d+(?:\.\d+)?)[\'"]/', $content, $matches ) ) {
        return $matches[1];
    }
    
    return '';
}

/**
 * AJAX: Download a specific version from GitHub
 */
function ajax_download_specific_version() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    hws_plugin_info_require_nonce();
    
    $version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
    $sha = isset( $_POST['sha'] ) ? sanitize_text_field( $_POST['sha'] ) : '';
    
    if ( empty( $version ) ) {
        wp_send_json_error( 'No version specified' );
        return;
    }
    
    $github_repo = Config::$github_repo;
    $correct_folder_name = Config::$plugin_folder_name;
    
    // Determine download URL - use SHA if provided, otherwise fall back to version string
    if ( ! empty( $sha ) ) {
        $download_url = 'https://github.com/' . $github_repo . '/archive/' . $sha . '.zip';
        // Extract version number from display name for filename, or use short SHA
        if ( preg_match( '/v?(\d+\.\d+(?:\.\d+)?)/i', $version, $matches ) ) {
            $version_slug = 'v' . $matches[1];
        } else {
            $version_slug = substr( $sha, 0, 7 );
        }
    } else {
        // Fallback for old-style tag-based versions
        $download_url = 'https://github.com/' . $github_repo . '/archive/refs/heads/main.zip';
        $version_slug = 'main';
    }
    
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/hws-temp-' . time();
    $temp_zip = $temp_dir . '/github-download.zip';
    $final_zip = $upload_dir['basedir'] . '/' . $correct_folder_name . '-' . $version_slug . '.zip';
    
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
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to download from GitHub: ' . $response->get_error_message() );
        return;
    }
    
    if ( ! file_exists( $temp_zip ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Download failed - file not created' );
        return;
    }
    
    // Extract the zip
    $extract_dir = $temp_dir . '/extracted';
    wp_mkdir_p( $extract_dir );
    
    $zip = new \ZipArchive();
    if ( $zip->open( $temp_zip ) !== true ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to open downloaded zip file' );
        return;
    }
    
    $zip->extractTo( $extract_dir );
    $zip->close();
    
    // Find the extracted folder (GitHub names it repo-branch or repo-tag)
    $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in extracted zip' );
        return;
    }
    
    $wrong_folder = $extracted_folders[0];
    $correct_folder = $extract_dir . '/' . $correct_folder_name;
    
    // Rename folder to correct name
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
    
    hws_add_folder_to_zip( $new_zip, $correct_folder, $correct_folder_name );
    $new_zip->close();
    
    // Cleanup temp directory
    hws_delete_directory( $temp_dir );
    
    $final_url = $upload_dir['baseurl'] . '/' . $correct_folder_name . '-' . $version_slug . '.zip';
    
    wp_send_json_success( [
        'message' => 'Version ' . $version . ' ready for download',
        'url' => $final_url,
        'filename' => $correct_folder_name . '-' . $version_slug . '.zip',
    ]);
}

/**
 * AJAX: Force WordPress to check for plugin updates immediately
 * Clears all caches and forces a fresh check
 */
function ajax_force_update_check() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    hws_plugin_info_require_nonce();
    
    // Use Config class - never hardcode
    $plugin_basename = Config::get_plugin_basename();
    $github_repo = Config::$github_repo;
    $github_branch = Config::$github_branch;
    
    // Clear our custom transients
    delete_site_transient( 'hws_gu_version_' . md5( $plugin_basename ) );
    delete_site_transient( 'hws_gu_repo_' . md5( $plugin_basename ) );
    delete_site_transient( 'hws_github_ver_' . md5( $github_repo . $github_branch ) );
    
    // Clear WordPress update transients
    delete_site_transient( 'update_plugins' );
    delete_option( '_site_transient_update_plugins' );
    
    // Force WordPress to check for updates
    wp_clean_update_cache();
    wp_update_plugins();
    
    // Get the fresh version
    $new_version = hws_get_github_version_fresh( $github_repo, $github_branch );
    
    wp_send_json_success( [
        'message'     => 'Update check complete',
        'new_version' => $new_version ?: 'Unknown',
    ]);
}

/**
 * Get GitHub version WITHOUT using cache (force fresh)
 */
function hws_get_github_version_fresh( $repo, $branch = 'main' ) {
    $url = 'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/initialization.php';
    
    $response = wp_remote_get( $url, [
        'timeout'   => 15,
        'sslverify' => true,
        'headers'   => [
            'Cache-Control' => 'no-cache',
        ],
    ]);
    
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return false;
    }
    
    $body = wp_remote_retrieve_body( $response );
    if ( preg_match( '/^[\s\*]*Version:\s*(.+)$/mi', $body, $matches ) ) {
        $version = trim( $matches[1] );
        // Update the transient with fresh data (short cache: 30 min)
        set_site_transient( 'hws_github_ver_' . md5( $repo . $branch ), $version, 30 * MINUTE_IN_SECONDS );
        return $version;
    }
    
    return false;
}

/**
 * AJAX: Direct update plugin from GitHub
 * Downloads, extracts, renames folder, and replaces the plugin
 */
function ajax_direct_update_plugin() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized - you need update_plugins capability' );
        return;
    }

    hws_plugin_info_require_nonce();
    
    // Include required files
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    
    WP_Filesystem();
    global $wp_filesystem;
    
    // Use Config class - never hardcode
    $github_repo = Config::$github_repo;
    $github_branch = Config::$github_branch;
    $correct_folder_name = Config::$plugin_folder_name;
    $plugin_file = Config::get_plugin_basename();
    
    // Download URL from GitHub
    $download_url = 'https://github.com/' . $github_repo . '/archive/refs/heads/' . $github_branch . '.zip';
    
    // Create temp directory
    $temp_dir = get_temp_dir() . 'hws-update-' . time();
    $temp_zip = $temp_dir . '/github-download.zip';
    
    if ( ! wp_mkdir_p( $temp_dir ) ) {
        wp_send_json_error( 'Could not create temp directory' );
        return;
    }
    
    // Download the file from GitHub
    $response = wp_remote_get( $download_url, [
        'timeout'  => 120,
        'stream'   => true,
        'filename' => $temp_zip,
    ]);
    
    if ( is_wp_error( $response ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to download from GitHub: ' . $response->get_error_message() );
        return;
    }
    
    if ( ! file_exists( $temp_zip ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Download failed - file not created' );
        return;
    }
    
    // Extract the zip
    $extract_dir = $temp_dir . '/extracted';
    wp_mkdir_p( $extract_dir );
    
    $unzip_result = unzip_file( $temp_zip, $extract_dir );
    if ( is_wp_error( $unzip_result ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to extract zip: ' . $unzip_result->get_error_message() );
        return;
    }
    
    // Find the extracted folder (GitHub names it repo-branch, e.g., hws-base-tools-main)
    $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in extracted zip' );
        return;
    }
    
    $source_folder = $extracted_folders[0]; // This is hws-base-tools-main
    $plugin_dir = WP_PLUGIN_DIR . '/' . $correct_folder_name;
    
    // Deactivate the plugin first
    $was_active = is_plugin_active( $plugin_file );
    if ( $was_active ) {
        deactivate_plugins( $plugin_file, true );
    }
    
    // Remove old plugin folder
    if ( is_dir( $plugin_dir ) ) {
        $wp_filesystem->delete( $plugin_dir, true );
    }
    
    // Move the new folder with correct name
    $move_result = $wp_filesystem->move( $source_folder, $plugin_dir );
    if ( ! $move_result ) {
        // Try copy instead
        $copy_result = copy_dir( $source_folder, $plugin_dir );
        if ( is_wp_error( $copy_result ) ) {
            hws_delete_directory( $temp_dir );
            wp_send_json_error( 'Failed to install plugin: ' . $copy_result->get_error_message() );
            return;
        }
    }
    
    // Cleanup temp directory
    hws_delete_directory( $temp_dir );
    
    // Reactivate the plugin if it was active
    if ( $was_active ) {
        $activate_result = activate_plugin( $plugin_file );
        if ( is_wp_error( $activate_result ) ) {
            wp_send_json_success( [
                'message' => 'Plugin updated but failed to reactivate: ' . $activate_result->get_error_message(),
                'reload'  => true,
            ]);
            return;
        }
    }
    
    // Clear update caches
    delete_site_transient( 'update_plugins' );
    delete_site_transient( 'hws_github_ver_' . md5( $github_repo . $github_branch ) );
    
    // Get new version
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $new_plugin_data = get_plugin_data( $plugin_dir . '/' . Config::$plugin_starter_file );
    
    wp_send_json_success( [
        'message'     => 'Plugin updated successfully to v' . $new_plugin_data['Version'],
        'new_version' => $new_plugin_data['Version'],
        'reload'      => true,
    ]);
}

/**
 * AJAX handler to download plugin, rename folder, and provide clean zip
 */
function ajax_download_plugin_zip() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }

    hws_plugin_info_require_nonce();
    
    // Use Config class - never hardcode
    $github_repo = Config::$github_repo;
    $github_branch = Config::$github_branch;
    $correct_folder_name = Config::$plugin_folder_name;
    
    $download_url = 'https://github.com/' . $github_repo . '/archive/refs/heads/' . $github_branch . '.zip';
    
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/hws-temp-' . time();
    $temp_zip = $temp_dir . '/github-download.zip';
    $final_zip = $upload_dir['basedir'] . '/' . $correct_folder_name . '.zip';
    
    if ( ! wp_mkdir_p( $temp_dir ) ) {
        wp_send_json_error( 'Could not create temp directory' );
        return;
    }
    
    $response = wp_remote_get( $download_url, [
        'timeout'  => 60,
        'stream'   => true,
        'filename' => $temp_zip,
    ]);
    
    if ( is_wp_error( $response ) ) {
        @unlink( $temp_zip );
        @rmdir( $temp_dir );
        wp_send_json_error( 'Failed to download from GitHub: ' . $response->get_error_message() );
        return;
    }
    
    if ( ! file_exists( $temp_zip ) ) {
        @rmdir( $temp_dir );
        wp_send_json_error( 'Download failed - file not created' );
        return;
    }
    
    $extract_dir = $temp_dir . '/extracted';
    wp_mkdir_p( $extract_dir );
    
    $zip = new \ZipArchive();
    if ( $zip->open( $temp_zip ) !== true ) {
        @unlink( $temp_zip );
        @rmdir( $temp_dir );
        wp_send_json_error( 'Failed to open downloaded zip file' );
        return;
    }
    
    $zip->extractTo( $extract_dir );
    $zip->close();
    
    $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'No folder found in extracted zip' );
        return;
    }
    
    $wrong_folder = $extracted_folders[0];
    $correct_folder = $extract_dir . '/' . $correct_folder_name;
    
    if ( basename( $wrong_folder ) !== $correct_folder_name ) {
        rename( $wrong_folder, $correct_folder );
    } else {
        $correct_folder = $wrong_folder;
    }
    
    $new_zip = new \ZipArchive();
    if ( $new_zip->open( $final_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
        hws_delete_directory( $temp_dir );
        wp_send_json_error( 'Failed to create new zip file' );
        return;
    }
    
    hws_add_folder_to_zip( $new_zip, $correct_folder, $correct_folder_name );
    $new_zip->close();
    
    hws_delete_directory( $temp_dir );
    
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
 * Get the latest version from GitHub repository (with caching)
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
        // Reduced cache time: 30 minutes instead of 6 hours
        set_site_transient( $transient_key, $version, 30 * MINUTE_IN_SECONDS );
        return $version;
    }
    
    return false;
}
 
function hws_ct_get_plugin_data() {
    $plugin_file = __FILE__;
    $plugin_dir = dirname($plugin_file);
    $main_plugin_file = $plugin_dir . '/initialization.php';

    if (!file_exists($main_plugin_file) || !is_file($main_plugin_file) || !is_readable($main_plugin_file)) {
        return [
            'Name' => 'Not Available',
            'Version' => 'Not Available',
            'PluginURI' => 'Not Available',
            'Author' => 'Not Available',
            'AuthorURI' => 'Not Available',
        ];
    }

    $plugin_data = get_plugin_data($main_plugin_file);

    foreach ($plugin_data as $key => $value) {
        if (empty($value)) {
            $plugin_data[$key] = 'Not Available';
        }
    }

    return $plugin_data;
}

function hws_ct_display_plugin_info() {
    $plugin_data = hws_ct_get_plugin_data();

    // Use Config class - never hardcode
    $github_repo = Config::$github_repo;
    $github_branch = Config::$github_branch;
    $new_version = hws_get_github_version( $github_repo, $github_branch ) ?: 'Checking...';
    $download_url = 'https://github.com/' . $github_repo . '/archive/' . $github_branch . '.zip';
    
    $update_available = $new_version !== 'Checking...' && version_compare( $new_version, $plugin_data['Version'], '>' );

    preg_match('/href=["\']([^"\']+)["\']/', $plugin_data['Author'], $matches);
    $author_url = $matches[1] ?? '#';
    $author_name = strip_tags($plugin_data['Author']);
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
            
            <!-- Version Info -->
            <div style="margin-bottom: 15px; padding: 15px; background: <?php echo $update_available ? '#fcf0f1' : '#edfaef'; ?>; border: 1px solid <?php echo $update_available ? '#d63638' : '#00a32a'; ?>; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Current Version:</strong> 
                        <span id="hws-current-version" style="font-size: 16px; font-weight: bold;"><?php echo esc_html($plugin_data['Version']); ?></span>
                    </div>
                    <div>
                        <strong>Latest Version:</strong> 
                        <span id="hws-latest-version" style="font-size: 16px; font-weight: bold; color: <?php echo $update_available ? '#d63638' : '#00a32a'; ?>;">
                            <?php echo esc_html($new_version); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ( $update_available ) : ?>
                <p style="margin: 10px 0 0; color: #d63638; font-weight: bold;">
                    ⚠️ Update available! v<?php echo esc_html($plugin_data['Version']); ?> → v<?php echo esc_html($new_version); ?>
                </p>
                <?php else : ?>
                <p style="margin: 10px 0 0; color: #00a32a;">
                    ✅ You are running the latest version
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Update Actions -->
            <div style="margin-bottom: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 6px;">
                <strong>🔄 Update Actions</strong>
                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="hws-force-update-check" class="button button-secondary">
                        🔍 Force Update Check
                    </button>
                    <button type="button" id="hws-direct-update" class="button button-primary" <?php echo $update_available ? '' : 'disabled'; ?>>
                        ⬆️ Update Now from GitHub
                    </button>
                    <a href="<?php echo admin_url('update-core.php?force-check=1'); ?>" class="button button-secondary" target="_blank">
                        📋 WP Update Page
                    </a>
                </div>
                <div id="hws-update-status" style="margin-top: 10px;"></div>
                <p style="font-size: 11px; color: #666; margin: 10px 0 0;">
                    <strong>Force Update Check:</strong> Clears all caches and checks GitHub for new version.<br>
                    <strong>Update Now:</strong> Directly downloads from GitHub and installs (folder name handled correctly).
                </p>
            </div>
            
            <!-- Download Plugin -->
            <div style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <strong>📦 Download Plugin ZIP:</strong>
                <p style="font-size: 12px; color: #666; margin: 5px 0 10px;">
                    Downloads from GitHub with correct folder name (no -main suffix).
                </p>
                <button type="button" id="hws-download-plugin-zip" class="button button-secondary" data-folder="<?php echo esc_attr( Config::$plugin_folder_name ); ?>">
                    ⬇️ Download <?php echo esc_html( Config::$plugin_folder_name ); ?>.zip
                </button>
                <span id="hws-download-status" style="margin-left: 10px;"></span>
            </div>
            
            <!-- Version History -->
            <div style="margin-bottom: 15px; padding: 15px; background: #fff8e5; border: 1px solid #dba617; border-radius: 6px;">
                <strong>📜 Version History (Download Older Versions)</strong>
                <p style="font-size: 12px; color: #666; margin: 5px 0 10px;">
                    Select a version tag from GitHub to download. Useful for rollbacks.
                </p>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select id="hws-version-select" style="min-width: 200px;">
                        <option value="">-- Click "Load Versions" --</option>
                    </select>
                    <button type="button" id="hws-load-versions" class="button button-secondary">
                        🔄 Load Versions
                    </button>
                    <button type="button" id="hws-download-version" class="button button-secondary" disabled>
                        ⬇️ Download Selected Version
                    </button>
                </div>
                <div id="hws-version-status" style="margin-top: 10px;"></div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <strong>GitHub URL:</strong> <a href="https://github.com/<?php echo esc_attr($github_repo); ?>" target="_blank">https://github.com/<?php echo esc_html($github_repo); ?></a>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Author:</strong> 
                <a href="<?php echo esc_url($author_url); ?>" target="_blank"><?php echo esc_html($author_name); ?></a>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Force Update Check
        $('#hws-force-update-check').on('click', function() {
            var $btn = $(this);
            var $status = $('#hws-update-status');
            
            $btn.prop('disabled', true).text('🔄 Checking...');
            $status.html('<span style="color: #666;">Clearing caches and checking GitHub...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_force_update_check',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#hws-latest-version').text(response.data.new_version);
                        $status.html('<span style="color: green;">✅ Check complete. Latest version: ' + response.data.new_version + '</span>');
                        
                        // Check if update is now available
                        var currentVer = '<?php echo esc_js($plugin_data['Version']); ?>';
                        if (response.data.new_version && response.data.new_version !== currentVer) {
                            $('#hws-direct-update').prop('disabled', false);
                            $status.append(' <strong style="color: #d63638;">- Update available!</strong>');
                        }
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                    $btn.prop('disabled', false).text('🔍 Force Update Check');
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                    $btn.prop('disabled', false).text('🔍 Force Update Check');
                }
            });
        });
        
        // Direct Update
        $('#hws-direct-update').on('click', function() {
            if (!confirm('This will download the latest version from GitHub and update the plugin. Continue?')) {
                return;
            }
            
            var $btn = $(this);
            var $status = $('#hws-update-status');
            
            $btn.prop('disabled', true).text('⏳ Downloading & Installing...');
            $status.html('<span style="color: #666;">Downloading from GitHub...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 120000, // 2 minute timeout
                data: {
                    action: 'hws_direct_update_plugin',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        if (response.data.new_version) {
                            $('#hws-current-version').text(response.data.new_version);
                            $('#hws-latest-version').text(response.data.new_version).css('color', '#00a32a');
                        }
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                        $btn.prop('disabled', false).text('⬆️ Update Now from GitHub');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span style="color: red;">❌ Error: ' + error + '</span>');
                    $btn.prop('disabled', false).text('⬆️ Update Now from GitHub');
                }
            });
        });
        
        // Download ZIP
        $('#hws-download-plugin-zip').on('click', function() {
            var $btn = $(this);
            var $status = $('#hws-download-status');
            var folderName = $btn.data('folder');
            
            $btn.prop('disabled', true).text('⏳ Preparing...');
            $status.text('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_download_plugin_zip',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<a href="' + response.data.url + '" target="_blank" style="color: green;">✅ Download Ready</a>');
                        window.location.href = response.data.url;
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                    $btn.prop('disabled', false).text('⬇️ Download ' + folderName + '.zip');
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                    $btn.prop('disabled', false).text('⬇️ Download ' + folderName + '.zip');
                }
            });
        });
        
        // Store version data globally for SHA lookup
        var versionData = {};
        
        // Load Versions from GitHub
        $('#hws-load-versions').on('click', function() {
            var $btn = $(this);
            var $select = $('#hws-version-select');
            var $status = $('#hws-version-status');
            
            $btn.prop('disabled', true).text('🔄 Loading...');
            $status.text('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hws_load_github_versions',
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $select.empty();
                        versionData = {}; // Reset
                        $select.append('<option value="">-- Select Version (' + response.data.count + ' commits) --</option>');
                        $.each(response.data.versions, function(i, ver) {
                            // Store SHA for lookup
                            versionData[ver.name] = ver.sha;
                            $select.append('<option value="' + ver.name + '">' + ver.name + '</option>');
                        });
                        $status.html('<span style="color: green;">✅ Loaded ' + response.data.count + ' commits</span>');
                        $('#hws-download-version').prop('disabled', false);
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                    $btn.prop('disabled', false).text('🔄 Load Versions');
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                    $btn.prop('disabled', false).text('🔄 Load Versions');
                }
            });
        });
        
        // Download Selected Version
        $('#hws-download-version').on('click', function() {
            var $btn = $(this);
            var $select = $('#hws-version-select');
            var $status = $('#hws-version-status');
            var version = $select.val();
            var sha = versionData[version] || '';
            
            if (!version) {
                $status.html('<span style="color: orange;">⚠️ Please select a version first</span>');
                return;
            }
            
            $btn.prop('disabled', true).text('⏳ Preparing...');
            $status.html('<span style="color: #666;">Downloading from GitHub...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 60000,
                data: { 
                    action: 'hws_download_specific_version',
                    version: version,
                    sha: sha,
                    nonce: hwsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<a href="' + response.data.url + '" target="_blank" style="color: green;">✅ ' + response.data.filename + ' ready - Click to download</a>');
                        window.location.href = response.data.url;
                    } else {
                        $status.html('<span style="color: red;">❌ ' + response.data + '</span>');
                    }
                    $btn.prop('disabled', false).text('⬇️ Download Selected Version');
                },
                error: function() {
                    $status.html('<span style="color: red;">❌ AJAX Error</span>');
                    $btn.prop('disabled', false).text('⬇️ Download Selected Version');
                }
            });
        });
    });
    </script>
    <?php
}
