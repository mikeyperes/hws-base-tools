<?php namespace hws_base_tools;

/**
 * GitHub Plugin Updater
 * 
 * Enables automatic updates for plugins hosted on GitHub.
 * Designed to be abstract - pass all config via hws_init_github_updater()
 * 
 * @version 2.0.0
 * @since 8.9.5.3
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent duplicate class loading (check with namespace)
if ( class_exists( __NAMESPACE__ . '\\WP_GitHub_Updater' ) ) {
    return;
}

/**
 * Initialize the GitHub Updater with configuration array
 * 
 * Usage:
 *   hws_init_github_updater([
 *       'plugin_file'    => __FILE__,                    // Required: Main plugin file path
 *       'github_repo'    => 'username/repo-name',        // Required: GitHub repo path
 *       'github_branch'  => 'main',                      // Optional: Branch name (default: main)
 *       'access_token'   => '',                          // Optional: For private repos
 *       'requires'       => '5.0',                       // Optional: Min WP version
 *       'tested'         => '6.4',                       // Optional: Tested up to WP version
 *   ]);
 * 
 * @param array $config Configuration array
 * @return WP_GitHub_Updater|false Updater instance or false on failure
 */
function hws_init_github_updater( $config = [] ) {
    
    // Validate minimum requirements
    if ( empty( $config['plugin_file'] ) || empty( $config['github_repo'] ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'HWS GitHub Updater: Missing required config (plugin_file or github_repo)' );
        }
        return false;
    }
    
    // Ensure we can read plugin headers
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Get plugin data from file headers
    $plugin_data = get_plugin_data( $config['plugin_file'] );
    
    // Build the full configuration
    $github_repo   = trim( $config['github_repo'], '/' );
    $github_branch = isset( $config['github_branch'] ) ? $config['github_branch'] : 'main';
    
    $runtime_slug        = plugin_basename( $config['plugin_file'] );
    $runtime_folder_name = dirname( $runtime_slug );
    $proper_folder_name  = ! empty( $config['proper_folder_name'] )
        ? trim( (string) $config['proper_folder_name'], '/' )
        : $runtime_folder_name;

    $full_config = [
        // Plugin identification
        'slug'                     => $runtime_slug,
        'runtime_folder_name'      => $runtime_folder_name,
        'proper_folder_name'       => $proper_folder_name,
        'plugin_starter_file'      => basename( $config['plugin_file'] ),
        'canonical_plugin_basename'=> $proper_folder_name . '/' . basename( $config['plugin_file'] ),
        
        // GitHub endpoints
        'api_url'            => 'https://api.github.com/repos/' . $github_repo,
        'raw_url'            => 'https://raw.githubusercontent.com/' . $github_repo . '/' . $github_branch,
        'github_url'         => 'https://github.com/' . $github_repo,
        'zip_url'            => 'https://github.com/' . $github_repo . '/archive/' . $github_branch . '.zip',
        
        // Plugin metadata from headers
        'plugin_name'        => $plugin_data['Name'],
        'version'            => $plugin_data['Version'],
        'author'             => $plugin_data['Author'],
        'homepage'           => $plugin_data['PluginURI'],
        'description'        => $plugin_data['Description'],
        
        // WordPress compatibility
        'requires'           => isset( $config['requires'] ) ? $config['requires'] : '5.0',
        'tested'             => isset( $config['tested'] ) ? $config['tested'] : '6.4',
        'readme'             => 'README.md',
        
        // HTTP settings
        'sslverify'          => isset( $config['sslverify'] ) ? $config['sslverify'] : true,
        'access_token'       => isset( $config['access_token'] ) ? $config['access_token'] : '',
        'timeout'            => isset( $config['timeout'] ) ? $config['timeout'] : 10,
    ];
    
    // Create and return updater instance
    return new WP_GitHub_Updater( $full_config );
}


/**
 * GitHub Updater Class
 * 
 * Handles all update checking and installation logic
 */
class WP_GitHub_Updater {

    /** 
     * Updater version 
     */
    const VERSION = '2.0.0';

    /** @var array Configuration values */
    private $config;

    /** @var object|null Cached GitHub API response */
    private $github_data = null;

    /** @var string|null Cached new version number */
    private $new_version = null;

    /**
     * Constructor
     *
     * @param array $config Full configuration array from hws_init_github_updater()
     */
    public function __construct( array $config ) {
        $this->config = $config;

        // Validate configuration
        if ( ! $this->validate_config() ) {
            return;
        }

        // Prepare runtime values
        $this->prepare_config();

        // Hook into WordPress update system
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'source_selection' ], 10, 4 );
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
        
        // HTTP request filters
        add_filter( 'http_request_timeout', [ $this, 'http_timeout' ] );
        add_filter( 'http_request_args', [ $this, 'http_args' ], 10, 2 );
    }

    /**
     * Validate required configuration values
     *
     * @return bool True if valid, false otherwise
     */
    private function validate_config() {
        $required = [
            'slug',
            'proper_folder_name',
            'api_url',
            'raw_url',
            'zip_url',
            'plugin_name',
            'version',
        ];

        $missing = [];
        foreach ( $required as $key ) {
            if ( empty( $this->config[ $key ] ) ) {
                $missing[] = $key;
            }
        }

        if ( ! empty( $missing ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HWS GitHub Updater: Missing config keys: ' . implode( ', ', $missing ) );
            }
            return false;
        }

        return true;
    }

    /**
     * Prepare runtime configuration values
     */
    private function prepare_config() {
        $this->config['slug'] = trim( (string) $this->config['slug'], '/' );
        $this->config['runtime_folder_name'] = trim( (string) $this->config['runtime_folder_name'], '/' );
        $this->config['proper_folder_name'] = trim( (string) $this->config['proper_folder_name'], '/' );
        $this->config['canonical_plugin_basename'] = $this->config['proper_folder_name'] . '/' . $this->config['plugin_starter_file'];

        // Add access token to zip URL if provided (for private repos)
        if ( ! empty( $this->config['access_token'] ) ) {
            $this->config['zip_url'] = add_query_arg( 
                'access_token', 
                $this->config['access_token'], 
                $this->config['zip_url'] 
            );
        }
    }

    /**
     * Set HTTP timeout for GitHub requests
     *
     * @param int $timeout Current timeout value
     * @return int Modified timeout value
     */
    public function http_timeout( $timeout ) {
        return isset( $this->config['timeout'] ) ? $this->config['timeout'] : 10;
    }

    /**
     * Modify HTTP request args for our requests
     *
     * @param array  $args HTTP request arguments
     * @param string $url  Request URL
     * @return array Modified arguments
     */
    public function http_args( $args, $url ) {
        // Apply SSL verify setting to our requests
        if ( strpos( $url, 'github.com' ) !== false || strpos( $url, 'githubusercontent.com' ) !== false ) {
            $args['sslverify'] = $this->config['sslverify'];
            
            // Add authorization header for private repos
            if ( ! empty( $this->config['access_token'] ) ) {
                $args['headers']['Authorization'] = 'token ' . $this->config['access_token'];
            }
        }
        return $args;
    }

    /**
     * Fetch the latest version from GitHub
     *
     * @return string|false Version string or false on failure
     */
    private function get_remote_version() {
        // Check cache first
        $transient_key = 'hws_gu_version_' . md5( $this->config['slug'] );
        $cached = get_site_transient( $transient_key );
        
        if ( $cached !== false && ! ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE ) ) {
            return $cached;
        }

        // Fetch the main plugin file from GitHub
        $url = add_query_arg(
            'cb',
            gmdate( 'YmdHi' ),
            trailingslashit( $this->config['raw_url'] ) . $this->config['plugin_starter_file']
        );
        
        $response = wp_remote_get( $url, [
            'sslverify' => $this->config['sslverify'],
            'timeout'   => $this->config['timeout'],
        ] );

        // Handle errors gracefully
        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HWS GitHub Updater: Failed to fetch version - ' . $response->get_error_message() );
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HWS GitHub Updater: HTTP ' . $response_code . ' when fetching version' );
            }
            return false;
        }

        // Extract version from plugin header
        $body = wp_remote_retrieve_body( $response );
        if ( preg_match( '/^[\s\*]*Version:\s*(.+)$/mi', $body, $matches ) ) {
            $version = trim( $matches[1] );
            
            // Cache for 30 minutes (faster update detection)
            set_site_transient( $transient_key, $version, 30 * MINUTE_IN_SECONDS );
            
            return $version;
        }

        return false;
    }

    /**
     * Fetch GitHub repository data
     *
     * @return object|false Repository data or false on failure
     */
    private function get_github_data() {
        if ( $this->github_data !== null ) {
            return $this->github_data;
        }

        $transient_key = 'hws_gu_repo_' . md5( $this->config['slug'] );
        $cached = get_site_transient( $transient_key );

        if ( $cached !== false && ! ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE ) ) {
            $this->github_data = $cached;
            return $this->github_data;
        }

        $response = wp_remote_get( $this->config['api_url'], [
            'sslverify' => $this->config['sslverify'],
            'timeout'   => $this->config['timeout'],
            'headers'   => ! empty( $this->config['access_token'] ) 
                ? [ 'Authorization' => 'token ' . $this->config['access_token'] ] 
                : [],
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( empty( $data ) || isset( $data->message ) ) {
            return false;
        }

        // Cache for 30 minutes (faster update detection)
        set_site_transient( $transient_key, $data, 30 * MINUTE_IN_SECONDS );
        $this->github_data = $data;

        return $this->github_data;
    }

    /**
     * Check for updates and inject into WordPress transient
     *
     * @param object $transient Update transient data
     * @return object Modified transient
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get remote version
        $remote_version = $this->get_remote_version();
        
        if ( $remote_version === false ) {
            return $transient;
        }

        // Compare versions
        if ( version_compare( $remote_version, $this->config['version'], '>' ) ) {
            $github_data = $this->get_github_data();
            
            $plugin_info = (object) [
                'slug'        => $this->config['proper_folder_name'],
                'plugin'      => $this->config['slug'],
                'new_version' => $remote_version,
                'url'         => $this->config['github_url'],
                'package'     => $this->config['zip_url'],
                'icons'       => [],
                'banners'     => [],
                'tested'      => $this->config['tested'],
                'requires'    => $this->config['requires'],
            ];

            $transient->response[ $this->config['slug'] ] = $plugin_info;
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View version details" popup
     *
     * @param false|object $result Plugin info result
     * @param string       $action API action being performed
     * @param object       $args   Request arguments
     * @return object|false Plugin info or false
     */
    public function plugin_info( $result, $action, $args ) {
        // Only respond to plugin_information requests for our plugin
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        // Check if this request is for our plugin (by folder name)
        if ( ! isset( $args->slug ) ) {
            return $result;
        }

        $valid_slugs = array_unique( [
            $this->config['proper_folder_name'],
            $this->config['runtime_folder_name'],
        ] );

        if ( ! in_array( $args->slug, $valid_slugs, true ) ) {
            return $result;
        }

        $remote_version = $this->get_remote_version();
        $github_data = $this->get_github_data();

        return (object) [
            'name'              => $this->config['plugin_name'],
            'slug'              => $this->config['proper_folder_name'],
            'version'           => $remote_version ?: $this->config['version'],
            'author'            => $this->config['author'],
            'author_profile'    => $this->config['homepage'],
            'homepage'          => $this->config['homepage'],
            'requires'          => $this->config['requires'],
            'tested'            => $this->config['tested'],
            'downloaded'        => 0,
            'last_updated'      => $github_data ? date( 'Y-m-d', strtotime( $github_data->updated_at ) ) : '',
            'sections'          => [
                'description' => $this->config['description'],
                'changelog'   => $github_data && isset( $github_data->description ) 
                    ? $github_data->description 
                    : 'See GitHub repository for changelog.',
            ],
            'download_link'     => $this->config['zip_url'],
        ];
    }

    /**
     * Normalize the extracted GitHub archive folder before WordPress copies it
     * into wp-content/plugins. GitHub packages include the branch suffix, but
     * the live plugin must always install into the canonical folder.
     *
     * @param string       $source        Working directory of the unpacked package.
     * @param string       $remote_source Original remote source.
     * @param \WP_Upgrader $upgrader      Upgrader instance.
     * @param array        $hook_extra    Extra hook arguments.
     * @return string|\WP_Error
     */
    public function source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->config['slug'] ) {
            return $source;
        }

        if ( basename( $source ) === $this->config['proper_folder_name'] ) {
            return $source;
        }

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $target = trailingslashit( dirname( $source ) ) . $this->config['proper_folder_name'];

        if ( $wp_filesystem->exists( $target ) ) {
            $wp_filesystem->delete( $target, true );
        }

        if ( ! $wp_filesystem->move( $source, $target, true ) ) {
            return new \WP_Error(
                'hws_updater_rename_failed',
                sprintf(
                    'Unable to rename update package folder from %s to %s.',
                    basename( $source ),
                    $this->config['proper_folder_name']
                )
            );
        }

        return $target;
    }

    /**
     * Handle post-installation folder normalization and reactivation.
     *
     * @param bool  $response   Installation response
     * @param array $hook_extra Extra hook arguments
     * @param array $result     Installation result
     * @return array Modified result
     */
    public function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Only process our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->config['slug'] ) {
            return $result;
        }

        // Get the correct destination
        $proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( untrailingslashit( $result['destination'] ) !== untrailingslashit( $proper_destination ) ) {
            if ( $wp_filesystem->exists( $proper_destination ) ) {
                $wp_filesystem->delete( $proper_destination, true );
            }

            $wp_filesystem->move( $result['destination'], $proper_destination, true );
            $result['destination'] = $proper_destination;
        }

        $result['destination_name'] = $this->config['proper_folder_name'];

        $legacy_destination = WP_PLUGIN_DIR . '/' . $this->config['runtime_folder_name'];
        if (
            $this->config['runtime_folder_name'] !== $this->config['proper_folder_name']
            && untrailingslashit( $legacy_destination ) !== untrailingslashit( $proper_destination )
            && $wp_filesystem->is_dir( $legacy_destination )
        ) {
            $wp_filesystem->delete( $legacy_destination, true );
        }

        // Reactivate plugin
        $activate = activate_plugin( $this->config['canonical_plugin_basename'] );
        
        if ( is_wp_error( $activate ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HWS GitHub Updater: Failed to reactivate plugin after update' );
            }
        }

        return $result;
    }

    /**
     * Clear update caches (useful for debugging)
     */
    public function clear_cache() {
        delete_site_transient( 'hws_gu_version_' . md5( $this->config['slug'] ) );
        delete_site_transient( 'hws_gu_repo_' . md5( $this->config['slug'] ) );
        delete_site_transient( 'hws_gu_version_' . md5( $this->config['canonical_plugin_basename'] ) );
        delete_site_transient( 'hws_gu_repo_' . md5( $this->config['canonical_plugin_basename'] ) );
        delete_site_transient( 'update_plugins' );
    }

    /**
     * Public getter for the latest remote version
     * 
     * @return string|false Version string or false on failure
     */
    public function get_new_version() {
        return $this->get_remote_version();
    }

    /**
     * Public getter for config values
     * 
     * @param string $key Config key to retrieve
     * @return mixed Config value or null if not set
     */
    public function get_config( $key = null ) {
        if ( $key === null ) {
            return $this->config;
        }
        return isset( $this->config[ $key ] ) ? $this->config[ $key ] : null;
    }
}
