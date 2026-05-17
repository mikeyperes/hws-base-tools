<?php namespace hws_base_tools;

// Register AJAX handlers
add_action( 'wp_ajax_hws_download_plugin_zip', __NAMESPACE__ . '\\ajax_download_plugin_zip' );
add_action( 'wp_ajax_hws_force_update_check', __NAMESPACE__ . '\\ajax_force_update_check' );
add_action( 'wp_ajax_hws_direct_update_plugin', __NAMESPACE__ . '\\ajax_direct_update_plugin' );
add_action( 'wp_ajax_hws_get_update_progress', __NAMESPACE__ . '\\ajax_get_update_progress' );
add_action( 'wp_ajax_hws_load_github_versions', __NAMESPACE__ . '\\ajax_load_github_versions' );
add_action( 'wp_ajax_hws_download_specific_version', __NAMESPACE__ . '\\ajax_download_specific_version' );

function hws_plugin_info_require_nonce() {
    hws_require_ajax_nonce_or_error();
}

/* ===========================================================================
 * Update progress log — written to a short-lived transient by the updater so
 * the JS polling endpoint can stream real-time progress to the activity log.
 * ========================================================================= */

function hws_update_progress_key(): string {
    return 'hws_base_tools_update_progress';
}

function hws_update_log_get(): array {
    $data = get_transient( hws_update_progress_key() );
    if ( ! is_array( $data ) ) {
        return [ 'started_at' => 0, 'updated_at' => 0, 'state' => 'idle', 'message' => '', 'steps' => [] ];
    }
    return $data;
}

function hws_update_log_save( array $data ): void {
    $data['updated_at'] = time();
    set_transient( hws_update_progress_key(), $data, 10 * MINUTE_IN_SECONDS );
}

function hws_update_log_reset(): void {
    hws_update_log_save( [
        'started_at' => time(),
        'state'      => 'running',
        'message'    => '',
        'steps'      => [],
    ] );
}

/** Append a step. Marks any previous "running" step as "done" first. */
function hws_update_log_step( string $message, string $status = 'running' ): void {
    $data = hws_update_log_get();
    if ( ! isset( $data['steps'] ) || ! is_array( $data['steps'] ) ) {
        $data['steps'] = [];
    }
    foreach ( $data['steps'] as &$existing ) {
        if ( isset( $existing['status'] ) && 'running' === $existing['status'] ) {
            $existing['status'] = 'done';
        }
    }
    unset( $existing );
    $data['steps'][] = [
        't'       => time(),
        'message' => $message,
        'status'  => $status, // running | done | warn | error
    ];
    hws_update_log_save( $data );
}

function hws_update_log_finish( string $state, string $message ): void {
    $data = hws_update_log_get();
    if ( ! isset( $data['steps'] ) || ! is_array( $data['steps'] ) ) {
        $data['steps'] = [];
    }
    foreach ( $data['steps'] as &$existing ) {
        if ( isset( $existing['status'] ) && 'running' === $existing['status'] ) {
            $existing['status'] = ( 'error' === $state ) ? 'error' : 'done';
        }
    }
    unset( $existing );
    $data['state']   = $state;
    $data['message'] = $message;
    hws_update_log_save( $data );
}

/** AJAX: return the current update progress log (consumed by the polling UI). */
function ajax_get_update_progress() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized' );
        return;
    }
    hws_plugin_info_require_nonce();
    wp_send_json_success( hws_update_log_get() );
}

function hws_plugin_info_get_managed_basenames(): array {
    return array_values(
        array_unique(
            array_filter(
                [
                    Config::get_plugin_basename(),
                    Config::get_canonical_plugin_basename(),
                ]
            )
        )
    );
}

function hws_plugin_info_get_update_response( $transient = null ) {
    if ( null === $transient ) {
        $transient = get_site_transient( 'update_plugins' );
    }

    if ( ! is_object( $transient ) || empty( $transient->response ) || ! is_array( $transient->response ) ) {
        return false;
    }

    foreach ( hws_plugin_info_get_managed_basenames() as $basename ) {
        if ( isset( $transient->response[ $basename ] ) ) {
            return $transient->response[ $basename ];
        }
    }

    return false;
}

function hws_plugin_info_clear_update_caches(): void {
    $github_repo   = Config::$github_repo;
    $github_branch = Config::$github_branch;

    foreach ( hws_plugin_info_get_managed_basenames() as $basename ) {
        delete_site_transient( 'hws_gu_version_' . md5( $basename ) );
        delete_site_transient( 'hws_gu_repo_' . md5( $basename ) );
    }

    delete_site_transient( 'hws_github_ver_' . md5( $github_repo . $github_branch ) );
    delete_site_transient( 'update_plugins' );
    delete_option( '_site_transient_update_plugins' );

    wp_clean_update_cache();

    if ( function_exists( 'wp_clean_plugins_cache' ) ) {
        wp_clean_plugins_cache( true );
    }
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
    
    $github_repo   = Config::$github_repo;
    $github_branch = Config::$github_branch;

    hws_plugin_info_clear_update_caches();

    wp_update_plugins();

    $new_version   = hws_get_github_version_fresh( $github_repo, $github_branch );
    $core_response = hws_plugin_info_get_update_response();

    wp_send_json_success( [
        'message'       => 'Update check complete',
        'new_version'   => $new_version ?: 'Unknown',
        'core_detected' => (bool) $core_response,
        'core_version'  => $core_response->new_version ?? '',
        'core_plugin'   => $core_response->plugin ?? '',
    ]);
}

/**
 * Get GitHub version WITHOUT using cache (force fresh)
 */
function hws_get_github_version_fresh( $repo, $branch = 'main' ) {
    $url = add_query_arg(
        'cb',
        time(),
        'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/initialization.php'
    );
    
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
 * AJAX: Direct update plugin from GitHub.
 *
 * Discrete, logged steps so the polling UI can stream progress. Performs an
 * atomic-style swap (backup-and-rename) instead of delete-then-copy, so a
 * mid-install failure can be rolled back. Stays on the same filesystem
 * (wp-content/upgrade) to keep rename() atomic — no slow cross-fs copies.
 * Never touches the active_plugins option: as long as the new folder lands
 * at the canonical path with the same starter file, WordPress just loads the
 * new code on the next request. Also normalises the `hws-base-tools-main`
 * folder name that GitHub returns, and cleans up any stray duplicate
 * `hws-base-tools-*` folder created by older versions of the updater.
 */
function ajax_direct_update_plugin() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized — you need the update_plugins capability.' );
        return;
    }

    hws_plugin_info_require_nonce();

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    @set_time_limit( 300 );

    $github_repo          = Config::$github_repo;
    $github_branch        = Config::$github_branch;
    $correct_folder_name  = Config::$plugin_folder_name;
    $starter_file         = Config::$plugin_starter_file;
    $canonical_plugin_dir = WP_PLUGIN_DIR . '/' . $correct_folder_name;
    $canonical_plugin_file = Config::get_canonical_plugin_basename();
    $runtime_folder_name  = Config::get_runtime_plugin_folder_name();
    $runtime_plugin_dir   = WP_PLUGIN_DIR . '/' . $runtime_folder_name;
    $download_url         = 'https://github.com/' . $github_repo . '/archive/refs/heads/' . $github_branch . '.zip';

    // Work inside wp-content/upgrade so renames stay on the same filesystem
    // as the plugins directory (rename() is then atomic; no slow cross-fs copy).
    $upgrade_root = WP_CONTENT_DIR . '/upgrade';
    if ( ! wp_mkdir_p( $upgrade_root ) ) {
        $upgrade_root = get_temp_dir();
    }
    $work_dir    = trailingslashit( $upgrade_root ) . 'hws-base-tools-update-' . time() . '-' . wp_rand( 1000, 9999 );
    $temp_zip    = $work_dir . '/source.zip';
    $extract_dir = $work_dir . '/extracted';

    $fail = function ( $message ) use ( $work_dir ) {
        if ( is_dir( $work_dir ) ) {
            hws_delete_directory( $work_dir );
        }
        hws_update_log_finish( 'error', $message );
        wp_send_json_error( $message );
    };

    hws_update_log_reset();
    hws_update_log_step( 'Preparing workspace…' );

    if ( ! wp_mkdir_p( $work_dir ) || ! wp_mkdir_p( $extract_dir ) ) {
        $fail( 'Could not create the update workspace under wp-content/upgrade.' );
        return;
    }
    if ( $runtime_folder_name !== $correct_folder_name ) {
        hws_update_log_step( 'Detected non-canonical install folder "' . $runtime_folder_name . '" — will normalise to "' . $correct_folder_name . '".', 'warn' );
    }

    // --- 1. Download --------------------------------------------------------
    hws_update_log_step( 'Downloading the latest build from ' . $github_repo . ' (' . $github_branch . ')…' );
    $response = wp_remote_get( $download_url, [
        'timeout'  => 180,
        'stream'   => true,
        'filename' => $temp_zip,
    ] );
    if ( is_wp_error( $response ) ) {
        $fail( 'Download failed: ' . $response->get_error_message() );
        return;
    }
    $code = wp_remote_retrieve_response_code( $response );
    if ( $code && 200 !== (int) $code ) {
        $fail( 'Download failed: GitHub returned HTTP ' . $code . '.' );
        return;
    }
    if ( ! file_exists( $temp_zip ) || filesize( $temp_zip ) < 1024 ) {
        $fail( 'Download failed: the archive did not arrive (or was empty).' );
        return;
    }
    hws_update_log_step( 'Downloaded ' . size_format( filesize( $temp_zip ), 1 ) . '.', 'done' );

    // --- 2. Extract ---------------------------------------------------------
    hws_update_log_step( 'Extracting the archive…' );
    WP_Filesystem();
    $unzip = unzip_file( $temp_zip, $extract_dir );
    if ( is_wp_error( $unzip ) ) {
        $fail( 'Extract failed: ' . $unzip->get_error_message() );
        return;
    }

    // --- 3. Locate + normalise the extracted folder name --------------------
    hws_update_log_step( 'Locating the plugin files…' );
    $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
    if ( empty( $extracted_folders ) ) {
        $fail( 'Extract failed: no plugin folder was found inside the archive.' );
        return;
    }
    $source_folder = $extracted_folders[0]; // e.g. hws-base-tools-main
    if ( ! file_exists( $source_folder . '/' . $starter_file ) ) {
        $fail( 'The downloaded archive does not look like this plugin (missing ' . $starter_file . ').' );
        return;
    }
    $staged_folder = $extract_dir . '/' . $correct_folder_name;
    if ( $source_folder !== $staged_folder ) {
        if ( ! @rename( $source_folder, $staged_folder ) ) {
            $fail( 'Could not normalise the extracted folder name to "' . $correct_folder_name . '".' );
            return;
        }
    }
    $new_plugin_data = get_plugin_data( $staged_folder . '/' . $starter_file, false, false );
    $new_version     = isset( $new_plugin_data['Version'] ) && $new_plugin_data['Version'] !== '' ? $new_plugin_data['Version'] : 'unknown';

    // --- 4. Back up the current canonical folder ----------------------------
    $backup_dir = '';
    if ( is_dir( $canonical_plugin_dir ) ) {
        hws_update_log_step( 'Backing up the current version (v' . ( $plugin_data_current = get_plugin_data( $canonical_plugin_dir . '/' . $starter_file, false, false )['Version'] ?? 'unknown' ) . ')…' );
        $backup_dir = WP_PLUGIN_DIR . '/' . $correct_folder_name . '.bak-' . time();
        if ( ! @rename( $canonical_plugin_dir, $backup_dir ) ) {
            // Same-fs rename failed — fall back to a copy of the old dir then delete.
            if ( is_wp_error( copy_dir( $canonical_plugin_dir, $backup_dir ) ) ) {
                $fail( 'Could not back up the current plugin folder; aborting before any changes.' );
                return;
            }
            global $wp_filesystem;
            $wp_filesystem->delete( $canonical_plugin_dir, true );
        }
    }

    // --- 5. Install the new version ----------------------------------------
    hws_update_log_step( 'Installing v' . $new_version . ' into ' . $correct_folder_name . '/…' );
    $installed = @rename( $staged_folder, $canonical_plugin_dir );
    if ( ! $installed ) {
        $copy = copy_dir( $staged_folder, $canonical_plugin_dir );
        $installed = ! is_wp_error( $copy );
    }
    if ( ! $installed ) {
        // Roll back the canonical folder.
        if ( $backup_dir && is_dir( $backup_dir ) && ! is_dir( $canonical_plugin_dir ) ) {
            @rename( $backup_dir, $canonical_plugin_dir );
        }
        $fail( 'Install failed: could not place the new plugin files. The previous version has been restored.' );
        return;
    }

    // --- 6. Remove any stray runtime/duplicate folders ---------------------
    // Sweep duplicates like hws-base-tools-main, hws-base-tools-master, etc.
    $sweep_targets = [];
    if ( $runtime_folder_name !== $correct_folder_name ) {
        $sweep_targets[] = $runtime_plugin_dir;
    }
    foreach ( (array) glob( WP_PLUGIN_DIR . '/' . $correct_folder_name . '-*', GLOB_ONLYDIR ) as $stray ) {
        // Don't touch our own canonical folder.
        if ( $stray === $canonical_plugin_dir ) continue;
        $sweep_targets[] = $stray;
    }
    $sweep_targets = array_unique( $sweep_targets );
    if ( $sweep_targets ) {
        hws_update_log_step( 'Removing ' . count( $sweep_targets ) . ' duplicate folder(s) created by previous updaters…' );
        foreach ( $sweep_targets as $stray ) {
            if ( is_dir( $stray ) ) {
                hws_delete_directory( $stray );
            }
        }
    }

    // If the runtime basename differs from canonical (because the plugin was
    // previously loaded from hws-base-tools-main/), the active_plugins option
    // still points to the wrong file. Fix it so WordPress loads the new code
    // from the canonical location on the next request.
    $current_active = (array) get_option( 'active_plugins', [] );
    $runtime_basename = Config::get_plugin_basename();
    if ( $runtime_basename !== $canonical_plugin_file && in_array( $runtime_basename, $current_active, true ) ) {
        hws_update_log_step( 'Repointing active_plugins from "' . $runtime_basename . '" to "' . $canonical_plugin_file . '"…' );
        $current_active = array_values( array_unique( array_map(
            function ( $p ) use ( $runtime_basename, $canonical_plugin_file ) {
                return ( $p === $runtime_basename ) ? $canonical_plugin_file : $p;
            },
            $current_active
        ) ) );
        update_option( 'active_plugins', $current_active );
    } elseif ( ! in_array( $canonical_plugin_file, $current_active, true ) ) {
        hws_update_log_step( 'Adding "' . $canonical_plugin_file . '" to active_plugins…' );
        $current_active[] = $canonical_plugin_file;
        update_option( 'active_plugins', $current_active );
    }

    // --- 7. Clean up + clear caches ----------------------------------------
    hws_update_log_step( 'Cleaning up…' );
    if ( $backup_dir && is_dir( $backup_dir ) ) {
        hws_delete_directory( $backup_dir );
    }
    hws_delete_directory( $work_dir );

    // Sweep any older .bak-* folders from earlier runs.
    foreach ( (array) glob( WP_PLUGIN_DIR . '/' . $correct_folder_name . '.bak-*', GLOB_ONLYDIR ) as $stale ) {
        hws_delete_directory( $stale );
    }

    hws_plugin_info_clear_update_caches();

    // Re-read the version from the freshly installed file to confirm.
    $confirmed = get_plugin_data( $canonical_plugin_dir . '/' . $starter_file, false, false );
    $confirmed_version = isset( $confirmed['Version'] ) && $confirmed['Version'] !== '' ? $confirmed['Version'] : $new_version;

    hws_update_log_step( 'Done — this site is now on v' . $confirmed_version . '.', 'done' );
    hws_update_log_finish( 'done', 'Updated to v' . $confirmed_version . '.' );

    wp_send_json_success( [
        'message'       => 'Updated to v' . $confirmed_version . '.',
        'new_version'   => $confirmed_version,
        'active_plugin' => $canonical_plugin_file,
        'reload'        => true,
    ] );
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
    
    $url = add_query_arg(
        'cb',
        time(),
        'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/initialization.php'
    );
    
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

    $github_repo          = Config::$github_repo;
    $github_branch        = Config::$github_branch;
    $runtime_folder_name  = Config::get_runtime_plugin_folder_name();
    $canonical_folder     = Config::$plugin_folder_name;
    $new_version          = hws_get_github_version( $github_repo, $github_branch ) ?: 'Checking…';
    $core_update          = hws_plugin_info_get_update_response();
    $core_detected        = (bool) $core_update;
    $update_available     = $new_version !== 'Checking…' && version_compare( $new_version, $plugin_data['Version'], '>' );

    preg_match( '/href=["\']([^"\']+)["\']/', $plugin_data['Author'], $matches );
    $author_url  = $matches[1] ?? '#';
    $author_name = strip_tags( $plugin_data['Author'] );
    ?>
    <style>
        .hws-pi { max-width: 1100px; }
        .hws-pi-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 10px;
            padding: 18px 20px;
            margin: 0 0 16px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.02);
        }
        .hws-pi-card > h3 {
            margin: 0 0 14px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #1d2327;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .hws-pi-meta {
            display: grid;
            grid-template-columns: max-content minmax(0, 1fr);
            gap: 8px 16px;
            font-size: 13px;
            color: #1d2327;
            margin: 0;
        }
        .hws-pi-meta dt { font-weight: 600; color: #646970; margin: 0; }
        .hws-pi-meta dd { margin: 0; min-width: 0; word-break: break-word; }
        .hws-pi-meta code {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 5px;
            padding: 1px 7px;
            font-size: 12px;
        }
        .hws-pi-meta a { text-decoration: none; }
        .hws-pi-meta a:hover { text-decoration: underline; }
        .hws-pi-meta .hws-pi-warn { color: #8a5a00; font-size: 12px; margin-left: 6px; }

        .hws-pi-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
            white-space: nowrap;
        }
        .hws-pi-badge.is-current { background: #edfaef; color: #1f7a3a; border: 1px solid #abdcb7; }
        .hws-pi-badge.is-stale   { background: #fef5e6; color: #8a5a00; border: 1px solid #f0d291; }
        .hws-pi-badge.is-update  { background: #fdecec; color: #a01313; border: 1px solid #f0a8a8; }

        .hws-pi-compare {
            display: flex;
            align-items: stretch;
            gap: 14px;
        }
        .hws-pi-side {
            flex: 1 1 0;
            min-width: 0;
            border: 1px solid #e2e4e7;
            border-radius: 10px;
            background: linear-gradient(180deg, #fbfcfd 0%, #f5f6f8 100%);
            padding: 18px 16px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .hws-pi-side.is-newer {
            background: linear-gradient(180deg, #f1fbf3 0%, #e6f7ea 100%);
            border-color: #9bd8aa;
            box-shadow: inset 0 0 0 1px #9bd8aa;
        }
        .hws-pi-side-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7177;
        }
        .hws-pi-side.is-newer .hws-pi-side-label { color: #1f7a3a; }
        .hws-pi-side-num {
            font-size: 30px;
            line-height: 1.05;
            font-weight: 700;
            color: #1d2327;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.01em;
        }
        .hws-pi-side.is-newer .hws-pi-side-num { color: #15803d; }
        .hws-pi-side-cap {
            font-size: 11.5px;
            color: #8a8f94;
            word-break: break-word;
        }
        .hws-pi-arrow {
            align-self: center;
            font-size: 22px;
            line-height: 1;
            color: #c2c6cc;
            flex: 0 0 auto;
            user-select: none;
        }
        .hws-pi-arrow.is-active { color: #15803d; }
        .hws-pi-hint {
            margin: 14px 0 0;
            font-size: 13px;
            color: #8a5a00;
            background: #fef9ef;
            border: 1px solid #f0d291;
            border-radius: 8px;
            padding: 10px 14px;
            line-height: 1.5;
        }
        .hws-pi-hint.is-warn { color: #a01313; background: #fdecec; border-color: #f0a8a8; }
        @media (max-width: 640px) {
            .hws-pi-compare { flex-direction: column; }
            .hws-pi-arrow { transform: rotate(90deg); padding: 4px 0; }
        }

        .hws-pi-section {
            border: 1px solid #d7dbe0;
            border-radius: 10px;
            padding: 16px 18px;
            margin: 0 0 14px;
            background: #fff;
        }
        .hws-pi-section.tone-blue  { background: #f5f9fe; border-color: #c6daf2; }
        .hws-pi-section.tone-gray  { background: #f7f7f8; border-color: #dcdcde; }
        .hws-pi-section.tone-amber { background: #fffaf0; border-color: #ecd49a; }
        .hws-pi-section > h4 {
            margin: 0 0 4px;
            font-size: 13.5px;
            font-weight: 600;
            color: #1d2327;
        }
        .hws-pi-section .hws-pi-desc {
            margin: 0 0 12px;
            font-size: 12.5px;
            color: #646970;
            line-height: 1.5;
        }
        .hws-pi-btnrow {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .hws-pi-foot {
            margin: 12px 0 0;
            font-size: 11.5px;
            color: #787c82;
            line-height: 1.65;
        }
        .hws-pi-status { margin-top: 10px; font-size: 13px; min-height: 16px; }
        .hws-pi-status a { font-weight: 600; }

        .hws-pi-log {
            margin-top: 12px;
            border: 1px solid #d7dbe0;
            border-radius: 10px;
            background: #ffffff;
            overflow: hidden;
        }
        .hws-pi-log-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #f6f8fb;
            border-bottom: 1px solid #e3e6ea;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #50575e;
        }
        .hws-pi-log-head .hws-pi-spin { margin-left: auto; }
        .hws-pi-log-body {
            list-style: none;
            margin: 0;
            padding: 6px 0;
            max-height: 280px;
            overflow-y: auto;
        }
        .hws-pi-log-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 14px;
            font-size: 13px;
            line-height: 1.45;
            color: #2c3338;
        }
        .hws-pi-log-row + .hws-pi-log-row { border-top: 1px solid #f0f1f3; }
        .hws-pi-log-ico {
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            margin-top: 1px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            line-height: 1;
        }
        .hws-pi-log-row.is-running .hws-pi-log-msg { color: #1d2327; font-weight: 500; }
        .hws-pi-log-row.is-done    .hws-pi-log-ico { color: #1f7a3a; }
        .hws-pi-log-row.is-warn    .hws-pi-log-ico { color: #8a5a00; }
        .hws-pi-log-row.is-error   .hws-pi-log-ico { color: #b32d2e; }
        .hws-pi-log-row.is-error   .hws-pi-log-msg { color: #b32d2e; }
        .hws-pi-log-msg { flex: 1 1 auto; min-width: 0; word-break: break-word; }
        .hws-pi-log-time {
            flex: 0 0 auto;
            font-size: 11px;
            color: #8a8f94;
            font-variant-numeric: tabular-nums;
            margin-top: 1px;
        }
        .hws-pi-log-foot {
            padding: 9px 14px;
            border-top: 1px solid #e3e6ea;
            background: #f9fafb;
            font-size: 13px;
        }
        .hws-pi-log-foot.is-done  { color: #1f7a3a; background: #f1fbf3; }
        .hws-pi-log-foot.is-error { color: #b32d2e; background: #fdf3f3; }

        .hws-pi-spin {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(34, 113, 177, 0.25);
            border-top-color: #2271b1;
            border-radius: 50%;
            animation: hws-pi-spin 0.7s linear infinite;
            vertical-align: middle;
        }
        @keyframes hws-pi-spin { to { transform: rotate(360deg); } }
        .hws-pi-spin.is-sm { width: 12px; height: 12px; border-width: 2px; }
    </style>

    <!-- Plugin Info Panel -->
    <div class="panel">
        <h2 class="panel-title">HWS - Base Tools Plugin Info</h2>
        <div class="panel-content hws-pi">

            <!-- Plugin metadata -->
            <div class="hws-pi-card">
                <h3>Plugin</h3>
                <dl class="hws-pi-meta">
                    <dt>Name</dt>
                    <dd><?php echo esc_html( $plugin_data['Name'] ); ?></dd>
                    <dt>Installed folder</dt>
                    <dd>
                        <code><?php echo esc_html( $runtime_folder_name ); ?></code>
                        <?php if ( $runtime_folder_name !== $canonical_folder ) : ?>
                            <span class="hws-pi-warn">⚠ should be <code><?php echo esc_html( $canonical_folder ); ?></code> — Update Now will normalise this.</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Repository</dt>
                    <dd><a href="https://github.com/<?php echo esc_attr( $github_repo ); ?>" target="_blank" rel="noopener">github.com/<?php echo esc_html( $github_repo ); ?></a> · <?php echo esc_html( $github_branch ); ?></dd>
                    <dt>Author</dt>
                    <dd><a href="<?php echo esc_url( $author_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $author_name ); ?></a></dd>
                </dl>
            </div>

            <!-- Version Status -->
            <div class="hws-pi-card">
                <h3>
                    Version Status
                    <span id="hws-pi-badge" class="hws-pi-badge <?php echo $update_available ? ( $core_detected ? 'is-update' : 'is-stale' ) : 'is-current'; ?>">
                        <?php
                        if ( $update_available && $core_detected ) {
                            echo '⬆ Update available (WP sees it)';
                        } elseif ( $update_available ) {
                            echo '⬆ Update available';
                        } else {
                            echo '✓ Up to date';
                        }
                        ?>
                    </span>
                </h3>
                <div class="hws-pi-compare">
                    <div class="hws-pi-side">
                        <span class="hws-pi-side-label">On this site</span>
                        <span id="hws-current-version" class="hws-pi-side-num"><?php echo esc_html( $plugin_data['Version'] ); ?></span>
                        <span class="hws-pi-side-cap">Currently installed</span>
                    </div>
                    <span id="hws-pi-arrow" class="hws-pi-arrow <?php echo $update_available ? 'is-active' : ''; ?>" aria-hidden="true">&rarr;</span>
                    <div id="hws-pi-side-repo" class="hws-pi-side <?php echo $update_available ? 'is-newer' : ''; ?>">
                        <span class="hws-pi-side-label">In the Git repo</span>
                        <span id="hws-latest-version" class="hws-pi-side-num"><?php echo esc_html( $new_version ); ?></span>
                        <span class="hws-pi-side-cap"><?php echo esc_html( $github_repo ); ?> · <?php echo esc_html( $github_branch ); ?></span>
                    </div>
                </div>
                <p id="hws-pi-hint" class="hws-pi-hint <?php echo ( $update_available && ! $core_detected ) ? 'is-warn' : ''; ?>" style="<?php echo $update_available ? '' : 'display:none;'; ?>">
                    <?php if ( $update_available && $core_detected ) : ?>
                        A newer version is available on GitHub <strong>and</strong> WordPress core has registered it. Use <strong>Update Now from GitHub</strong> below — the install runs with a live activity log.
                    <?php elseif ( $update_available ) : ?>
                        GitHub has a newer version but WordPress core hasn't registered it as an update yet. Use <strong>Force Update Check</strong> to push it into the WP update list, or run <strong>Update Now from GitHub</strong> to install it directly.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Update Actions -->
            <div class="hws-pi-section tone-blue">
                <h4>🔄 Update Actions</h4>
                <p class="hws-pi-desc">Re-check GitHub and re-run WordPress core update detection, or pull and install the latest version directly with a live progress log.</p>
                <div class="hws-pi-btnrow">
                    <button type="button" id="hws-force-update-check" class="button button-secondary">🔍 Force Update Check</button>
                    <button type="button" id="hws-direct-update" class="button button-primary" <?php echo $update_available ? '' : 'disabled'; ?>>⬆️ Update Now from GitHub</button>
                    <a href="<?php echo admin_url( 'update-core.php?force-check=1' ); ?>" class="button button-secondary" target="_blank">📋 WP Update Page</a>
                </div>
                <div id="hws-update-status" class="hws-pi-status"></div>

                <!-- Live activity log for the install (hidden until an update runs) -->
                <div id="hws-update-log" class="hws-pi-log" style="display:none;">
                    <div class="hws-pi-log-head">
                        <span>Update Activity</span>
                        <span id="hws-update-log-spin" class="hws-pi-spin"></span>
                    </div>
                    <ul id="hws-update-log-body" class="hws-pi-log-body"></ul>
                    <div id="hws-update-log-foot" class="hws-pi-log-foot" style="display:none;"></div>
                </div>

                <p class="hws-pi-foot">
                    <strong>Force Update Check:</strong> Clears caches, re-fetches GitHub, and re-runs WordPress's plugin update detection. The badge will say "WP sees it" once core registers the update.<br>
                    <strong>Update Now:</strong> Downloads the latest build from GitHub and installs it into <code><?php echo esc_html( $canonical_folder ); ?></code>, step by step. A backup of the previous folder is taken before any change is made.
                </p>
            </div>

            <!-- Download Plugin -->
            <div class="hws-pi-section tone-gray">
                <h4>📦 Download Plugin ZIP</h4>
                <p class="hws-pi-desc">Grab the current code from GitHub with the correct folder name (no <code>-main</code> suffix).</p>
                <div class="hws-pi-btnrow">
                    <button type="button" id="hws-download-plugin-zip" class="button button-secondary" data-folder="<?php echo esc_attr( Config::$plugin_folder_name ); ?>">⬇️ Download <?php echo esc_html( Config::$plugin_folder_name ); ?>.zip</button>
                    <span id="hws-download-status"></span>
                </div>
            </div>

            <!-- Version History -->
            <div class="hws-pi-section tone-amber">
                <h4>📜 Version History — download an older build</h4>
                <p class="hws-pi-desc">Pick a commit from GitHub to download. Useful for rollbacks.</p>
                <div class="hws-pi-btnrow">
                    <select id="hws-version-select" style="min-width: 240px;">
                        <option value="">-- Click "Load Versions" --</option>
                    </select>
                    <button type="button" id="hws-load-versions" class="button button-secondary">🔄 Load Versions</button>
                    <button type="button" id="hws-download-version" class="button button-secondary" disabled>⬇️ Download Selected Version</button>
                </div>
                <div id="hws-version-status" class="hws-pi-status"></div>
            </div>

        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var hwsPiCurrentVer = '<?php echo esc_js($plugin_data['Version']); ?>';
        var hwsPiCoreDetected = <?php echo $core_detected ? 'true' : 'false'; ?>;

        function hwsPiVerCmp(a, b) {
            var pa = String(a || '').split('.').map(Number);
            var pb = String(b || '').split('.').map(Number);
            for (var i = 0; i < Math.max(pa.length, pb.length); i++) {
                var x = pa[i] || 0, y = pb[i] || 0;
                if (x > y) return 1;
                if (x < y) return -1;
            }
            return 0;
        }

        function hwsPiSyncVersionUI(currentVer, latestVer, coreDetected) {
            if (currentVer) $('#hws-current-version').text(currentVer);
            if (latestVer)  $('#hws-latest-version').text(latestVer);
            if (typeof coreDetected !== 'undefined') hwsPiCoreDetected = !!coreDetected;

            var stale = latestVer && hwsPiVerCmp(latestVer, currentVer) > 0;
            var $badge = $('#hws-pi-badge');
            var $arrow = $('#hws-pi-arrow');
            var $repo  = $('#hws-pi-side-repo');
            var $hint  = $('#hws-pi-hint');

            $badge.removeClass('is-current is-stale is-update');
            $hint.removeClass('is-warn');

            if (stale && hwsPiCoreDetected) {
                $badge.addClass('is-update').html('⬆ Update available (WP sees it)');
                $arrow.addClass('is-active');
                $repo.addClass('is-newer');
                $hint.html('A newer version is available on GitHub <strong>and</strong> WordPress core has registered it. Use <strong>Update Now from GitHub</strong> below — the install runs with a live activity log.').show();
                $('#hws-direct-update').prop('disabled', false);
            } else if (stale) {
                $badge.addClass('is-stale').html('⬆ Update available');
                $arrow.addClass('is-active');
                $repo.addClass('is-newer');
                $hint.addClass('is-warn').html('GitHub has a newer version but WordPress core hasn\'t registered it as an update yet. Use <strong>Force Update Check</strong> to push it into the WP update list, or run <strong>Update Now from GitHub</strong> to install it directly.').show();
                $('#hws-direct-update').prop('disabled', false);
            } else {
                $badge.addClass('is-current').html('✓ Up to date');
                $arrow.removeClass('is-active');
                $repo.removeClass('is-newer');
                $hint.hide();
                $('#hws-direct-update').prop('disabled', true);
            }
        }

        // ---- Force Update Check ------------------------------------------
        $('#hws-force-update-check').on('click', function() {
            var $btn = $(this);
            var $status = $('#hws-update-status');

            $btn.prop('disabled', true).html('<span class="hws-pi-spin is-sm"></span> Checking…');
            $status.html('<span style="color:#646970;">Clearing caches and re-checking GitHub + WordPress core…</span>');

            $.ajax({
                url: ajaxurl, type: 'POST', dataType: 'json',
                data: { action: 'hws_force_update_check', nonce: hwsNonce }
            }).done(function(response) {
                if (response && response.success) {
                    var latest = response.data.new_version || hwsPiCurrentVer;
                    hwsPiSyncVersionUI(hwsPiCurrentVer, latest, !!response.data.core_detected);

                    var msg = '<span style="color:#1f7a3a;">✅ Check complete — GitHub: v' + latest + '.</span>';
                    if (hwsPiVerCmp(latest, hwsPiCurrentVer) > 0) {
                        if (response.data.core_detected) {
                            msg += ' <strong style="color:#a01313;">WordPress core sees the update.</strong>';
                        } else {
                            msg += ' <strong style="color:#8a5a00;">GitHub is newer, but WordPress core hasn\'t registered it yet.</strong>';
                        }
                    } else {
                        msg += ' <span style="color:#1f7a3a;">Site is in sync.</span>';
                    }
                    $status.html(msg);
                } else {
                    $status.html('<span style="color:#b32d2e;">❌ ' + (response && response.data ? response.data : 'Check failed.') + '</span>');
                }
                $btn.prop('disabled', false).html('🔍 Force Update Check');
            }).fail(function() {
                $status.html('<span style="color:#b32d2e;">❌ AJAX error during check.</span>');
                $btn.prop('disabled', false).html('🔍 Force Update Check');
            });
        });

        // ---- Update Now (download + install with a live activity log) ----
        var hwsUpdatePoll = null;
        var hwsUpdateRenderedSteps = 0;

        function hwsFmtTime(unixSeconds) {
            var d = unixSeconds ? new Date(unixSeconds * 1000) : new Date();
            var p = function(n) { return (n < 10 ? '0' : '') + n; };
            return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
        }

        function hwsLogIcon(status) {
            if (status === 'done')  return '<span class="hws-pi-log-ico">✓</span>';
            if (status === 'warn')  return '<span class="hws-pi-log-ico">▲</span>';
            if (status === 'error') return '<span class="hws-pi-log-ico">✕</span>';
            return '<span class="hws-pi-log-ico"><span class="hws-pi-spin is-sm"></span></span>';
        }

        function hwsRenderUpdateLog(data) {
            if (!data || !data.steps) return;
            var $body = $('#hws-update-log-body');
            if (data.steps.length < hwsUpdateRenderedSteps) {
                $body.empty();
                hwsUpdateRenderedSteps = 0;
            }
            if (hwsUpdateRenderedSteps > 0) {
                var prev = data.steps[hwsUpdateRenderedSteps - 1];
                if (prev) {
                    var $prevRow = $body.children().eq(hwsUpdateRenderedSteps - 1);
                    $prevRow.removeClass('is-running is-done is-warn is-error').addClass('is-' + prev.status);
                    $prevRow.find('.hws-pi-log-ico').replaceWith(hwsLogIcon(prev.status));
                }
            }
            for (var i = hwsUpdateRenderedSteps; i < data.steps.length; i++) {
                var s = data.steps[i];
                var $row = $('<li class="hws-pi-log-row is-' + s.status + '">' +
                    hwsLogIcon(s.status) +
                    '<span class="hws-pi-log-msg"></span>' +
                    '<span class="hws-pi-log-time">' + hwsFmtTime(s.t) + '</span>' +
                    '</li>');
                $row.find('.hws-pi-log-msg').text(s.message);
                $body.append($row);
            }
            hwsUpdateRenderedSteps = data.steps.length;
            $body.scrollTop($body[0].scrollHeight);

            if (data.state === 'done' || data.state === 'error') {
                $('#hws-update-log-spin').hide();
                var $foot = $('#hws-update-log-foot');
                $foot.removeClass('is-done is-error').addClass(data.state === 'done' ? 'is-done' : 'is-error');
                $foot.text(data.message || (data.state === 'done' ? 'Done.' : 'Failed.')).show();
            }
        }

        function hwsPollUpdateProgress() {
            $.ajax({
                url: ajaxurl, type: 'POST', dataType: 'json', timeout: 8000,
                data: { action: 'hws_get_update_progress', nonce: hwsNonce }
            }).done(function(resp) {
                if (resp && resp.success) hwsRenderUpdateLog(resp.data);
            });
        }

        function hwsStopUpdatePoll() {
            if (hwsUpdatePoll) { clearInterval(hwsUpdatePoll); hwsUpdatePoll = null; }
        }

        $('#hws-direct-update').on('click', function() {
            if (!confirm('This will download the latest build from GitHub and replace this plugin in place. Continue?')) {
                return;
            }

            var $btn = $(this);
            var $status = $('#hws-update-status');

            $btn.prop('disabled', true).html('<span class="hws-pi-spin is-sm"></span> Updating…');
            $status.html('<span style="color:#646970;">Starting update…</span>');

            hwsUpdateRenderedSteps = 0;
            $('#hws-update-log-body').empty();
            $('#hws-update-log-foot').hide().removeClass('is-done is-error').text('');
            $('#hws-update-log-spin').show();
            $('#hws-update-log').show();

            hwsStopUpdatePoll();
            hwsPollUpdateProgress();
            hwsUpdatePoll = setInterval(hwsPollUpdateProgress, 700);
            var hardStop = setTimeout(hwsStopUpdatePoll, 200000);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                timeout: 200000,
                data: { action: 'hws_direct_update_plugin', nonce: hwsNonce }
            }).done(function(response) {
                hwsPollUpdateProgress();
                window.setTimeout(function() {
                    hwsStopUpdatePoll(); clearTimeout(hardStop);
                    if (response && response.success) {
                        var v = response.data && response.data.new_version ? response.data.new_version : null;
                        if (v) {
                            hwsPiCurrentVer = v;
                            // After a successful install both sides should match.
                            hwsPiSyncVersionUI(v, v, false);
                        }
                        var statusMsg = '<span style="color:#1f7a3a;">✅ ' + ((response.data && response.data.message) || 'Update complete.') + '</span>';
                        if (response.data && response.data.active_plugin) {
                            statusMsg += ' <span style="color:#50575e;"> Active plugin file: <code>' + response.data.active_plugin + '</code></span>';
                        }
                        statusMsg += ' &nbsp; <a href="' + window.location.href + '">Reload this page</a>';
                        $status.html(statusMsg);
                        $btn.html('✓ Updated').prop('disabled', true);
                    } else {
                        var err = (response && response.data) ? response.data : 'Update failed.';
                        $status.html('<span style="color:#b32d2e;">❌ ' + err + '</span>');
                        $('#hws-update-log-spin').hide();
                        $('#hws-update-log-foot').addClass('is-error').text(err).show();
                        $btn.prop('disabled', false).html('⬆️ Update Now from GitHub');
                    }
                }, 400);
            }).fail(function(xhr, statusText, error) {
                hwsPollUpdateProgress();
                window.setTimeout(function() {
                    hwsStopUpdatePoll(); clearTimeout(hardStop);
                    var msg = (statusText === 'timeout')
                        ? 'The request timed out. Use "Force Update Check" to confirm whether the install finished.'
                        : ('Connection error during update' + (error ? ': ' + error : '') + '. Use "Force Update Check" to confirm the result.');
                    $status.html('<span style="color:#b32d2e;">❌ ' + msg + '</span>');
                    $('#hws-update-log-spin').hide();
                    $('#hws-update-log-foot').addClass('is-error').text(msg).show();
                    $btn.prop('disabled', false).html('⬆️ Update Now from GitHub');
                }, 400);
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
