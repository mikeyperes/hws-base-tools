<?php namespace hws_base_tools;

// Prevent loading this file directly and/or if the class is already defined
if ( ! defined( 'ABSPATH' ) || class_exists( 'WPGitHubUpdater' ) || class_exists( 'WP_GitHub_Updater' ) ) {
    return;
}

class WP_GitHub_Updater {

    /** 
     * GitHub Updater version 
     */
    const VERSION = 1.6;

    /**
     * @var array $config the config for the updater
     */
    public $config;

    /**
     * @var array $missing_config any config that is missing from the initialization of this instance
     */
    public $missing_config;

    /**
     * @var object $github_data temporary store the data fetched from GitHub, allows us to only load the data once per class instance
     */
    private $github_data;

    /**
     * Class Constructor
     *
     * @since 1.0
     * @param array $config the configuration required for the updater to work
     * @see has_minimum_config()
     */
    public function __construct( $config = array() ) {

        $defaults = array(
        //   'slug'               => plugin_basename( __FILE__ ),
         //   'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
            'sslverify'          => true,
            'access_token'       => '',
            // you must pass in all of the following via your Config::get_github_config():
            // 'api_url', 'raw_url', 'github_url', 'zip_url', 
            // 'requires', 'tested', 'readme', 
            // plus plugin metadata: 'plugin_name','version','author','homepage','description'
        );

        $this->config = wp_parse_args( $config, $defaults );

        // if the minimum config isn't set, issue a warning and bail
        if ( ! $this->has_minimum_config() ) {
            $message  = 'The GitHub Updater was initialized without the minimum required configuration. Missing: ';
            $message .= implode( ',', $this->missing_config );
            _doing_it_wrong( __CLASS__, $message, self::VERSION );
            return;
        }

        $this->set_defaults();

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'api_check' ] );
        add_filter( 'plugins_api',                     [ $this, 'get_plugin_info' ], 10, 3 );
        add_filter( 'upgrader_post_install',           [ $this, 'upgrader_post_install' ], 10, 3 );
        add_filter( 'http_request_timeout',            [ $this, 'http_request_timeout' ] );
        add_filter( 'http_request_args',               [ $this, 'http_request_sslverify' ], 10, 2 );
    }

    /**
     * Ensure required config keys are present.
     *
     * @return bool
     */
    public function has_minimum_config() {
        $this->missing_config = [];

        $required = [
            'api_url', 'raw_url', 'github_url', 'zip_url',
            'requires', 'tested', 'readme',
            'plugin_name', 'version', 'author', 'homepage', 'description',
        ];

        foreach ( $required as $key ) {
            if ( empty( $this->config[ $key ] ) ) {
                $this->missing_config[] = $key;
            }
        }

        return empty( $this->missing_config );
    }

    /**
     * Override transients if constant is defined.
     *
     * @return bool
     */
    public function overrule_transients() {
        return defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE;
    }

    /**
     * Set defaults: only ZIP URL, new_version, last_updated, description.
     *
     * @since 1.2
     */
    public function set_defaults() {
        // If we have an access token, adjust the zip URL to include it
        if ( ! empty( $this->config['access_token'] ) ) {
            extract( parse_url( $this->config['zip_url'] ) ); // $scheme, $host, $path
            $zip_url = $scheme . '://api.github.com/repos' . $path;
            $zip_url = add_query_arg( [ 'access_token' => $this->config['access_token'] ], $zip_url );
            $this->config['zip_url'] = $zip_url;
        }

        // Always fetch new version from raw GitHub
        if ( ! isset( $this->config['new_version'] ) ) {
            $this->config['new_version'] = $this->get_new_version();
        }

        // Always fetch last_updated date from GitHub API
        if ( ! isset( $this->config['last_updated'] ) ) {
            $this->config['last_updated'] = $this->get_date();
        }

        // Populate description from GitHub repo if not set
        if ( ! isset( $this->config['description'] ) ) {
            $this->config['description'] = $this->get_description();
        }
    }

    /**
     * Timeout for HTTP requests.
     *
     * @return int
     */
    public function http_request_timeout() {
        return 2;
    }

    /**
     * SSL verify override for zip downloads.
     *
     * @param array  $args
     * @param string $url
     * @return array
     */
    public function http_request_sslverify( $args, $url ) {
        if ( isset( $this->config['zip_url'] ) && $this->config['zip_url'] === $url ) {
            $args['sslverify'] = $this->config['sslverify'];
        }
        return $args;
    }

    /**
     * Fetch the “new” version number from GitHub by reading the Version header in your starter file.

   
    public function get_new_version() {
        // Determine which file to fetch for the Version: header
        $starter_file = isset( $this->config['plugin_starter_file'] )
            ? $this->config['plugin_starter_file']
            : Config::$plugin_starter_file;
    
        // Build the raw GitHub URL to that file
        $url = trailingslashit( $this->config['raw_url'] ) . ltrim( $starter_file, '/' );
    
        // Fetch it, respecting SSL settings
        $response = wp_remote_get( $url, [
            'sslverify' => $this->config['sslverify']
        ] );
    
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            write_log( "WP_GitHub_Updater: Error fetching version from GitHub. URL: $url", true );
            return false;
        }
    
        $body = wp_remote_retrieve_body( $response );
    
        // Parse the Version: header
        if ( preg_match( '/^Version:\s*(.+)$/mi', $body, $matches ) ) {
            $version = trim( $matches[1] );
            set_site_transient( md5( $this->config['slug'] ) . '_new_version', $version, HOUR_IN_SECONDS * 6 );
            return $version;
        }
    
        write_log( "WP_GitHub_Updater: No Version header found in $url", true );
        return false;
    }
  */


	/**
	 * Get New Version from GitHub
	 *
	 * @since 1.0
	 * @return int $version the version number
	 */
    public function get_new_version() {
        $query = trailingslashit($this->config['raw_url']) . Config::$plugin_starter_file;
        $response = wp_remote_get($query);
    
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            write_log("WP_GitHub_Updater: Error fetching version from GitHub.<br />URL: ".$query , "true");
            return false;
        }
    
        // Extract version from the plugin header
        if (preg_match('/^Version:\s*(.*)$/mi', wp_remote_retrieve_body($response), $matches)) {
            $version = trim($matches[1]);
            set_site_transient(md5($this->config['slug']).'_new_version', $version, 60*60*6);
            return $version;
        } else {
            write_log("WP_GitHub_Updater: No version found in the file.", "true");
            return false;
        }
    }




    
    /**
     * Low‐level GET with optional access token.
     *
     * @param string $query
     * @return array|WP_Error
     */
    public function remote_get( $query ) {
        if ( ! empty( $this->config['access_token'] ) ) {
            $query = add_query_arg( [ 'access_token' => $this->config['access_token'] ], $query );
        }
        return wp_remote_get( $query, [ 'sslverify' => $this->config['sslverify'] ] );
    }

    /**
     * Get full GitHub repo data (cached 6h).
     *
     * @return object|false
     */
    public function get_github_data() {
        if ( ! empty( $this->github_data ) ) {
            return $this->github_data;
        }
        $cache_key = md5( $this->config['slug'] ) . '_github_data';
        $github_data = get_site_transient( $cache_key );
        if ( $this->overrule_transients() || ! $github_data ) {
            $response = $this->remote_get( $this->config['api_url'] );
            if ( is_wp_error( $response ) ) {
                return false;
            }
            $github_data = json_decode( $response['body'] );
            set_site_transient( $cache_key, $github_data, 60 * 60 * 6 );
        }
        $this->github_data = $github_data;
        return $github_data;
    }

    /**
     * Get the repo’s last updated date.
     *
     * @return string|false
     */
    public function get_date() {
        $data = $this->get_github_data();
        return ! empty( $data->updated_at ) ? date( 'Y-m-d', strtotime( $data->updated_at ) ) : false;
    }

    /**
     * Get the repo’s description.
     *
     * @return string|false
     */
    public function get_description() {
        $data = $this->get_github_data();
        return ! empty( $data->description ) ? $data->description : false;
    }

    /**
     * Intercept WP’s update check and inject GitHub info if a newer version exists.
     *
     * @param object $transient
     * @return object
     */
    public function api_check( $transient ) {
        write_log( 'WP_GitHub_Updater: api_check called.', true );
        if ( empty( $transient->checked ) ) {
            write_log( 'WP_GitHub_Updater: No checked info in transient.', true );
            return $transient;
        }
        $compare = version_compare( $this->config['new_version'], $this->config['version'] );
        write_log( "WP_GitHub_Updater: Comparing versions. New: {$this->config['new_version']}, Current: {$this->config['version']}", true );
        if ( $compare === 1 ) {
            $response = (object) [
                'new_version' => $this->config['new_version'],
                'slug'        => $this->config['proper_folder_name'],
                'url'         => add_query_arg( [ 'access_token' => $this->config['access_token'] ], $this->config['github_url'] ),
                'package'     => $this->config['zip_url'],
            ];
            write_log( "WP_GitHub_Updater: Update available: {$this->config['new_version']}", true );
            $transient->response[ $this->config['slug'] ] = $response;
        } else {
            write_log( 'WP_GitHub_Updater: No update found.', true );
        }
        return $transient;
    }

    /**
     * Provide plugin details on the “View version details” screen.
     *
     * @param bool   $false
     * @param string $action
     * @param object $response
     * @return object|false
     */
    public function get_plugin_info( $false, $action, $response ) {
        if ( empty( $response->slug ) || $response->slug !== $this->config['slug'] ) {
            return false;
        }
        $response->slug          = $this->config['slug'];
        $response->plugin_name   = $this->config['plugin_name'];
        $response->version       = $this->config['new_version'];
        $response->author        = $this->config['author'];
        $response->homepage      = $this->config['homepage'];
        $response->requires      = $this->config['requires'];
        $response->tested        = $this->config['tested'];
        $response->downloaded    = 0;
        $response->last_updated  = $this->config['last_updated'];
        $response->sections      = [ 'description' => $this->config['description'] ];
        $response->download_link = $this->config['zip_url'];
        return $response;
    }

    /**
     * After the ZIP is downloaded, move & reactivate the plugin.
     *
     * @param bool  $true
     * @param mixed $hook_extra
     * @param array $result
     * @return array
     */
    public function upgrader_post_install( $true, $hook_extra, $result ) {
        global $wp_filesystem;
        $dest = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
        $wp_filesystem->move( $result['destination'], $dest );
        $result['destination'] = $dest;
        $activate = activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
        $fail    = __( 'The plugin was updated but could not be reactivated. Please reactivate manually.', 'github_plugin_updater' );
        $success = __( 'Plugin reactivated successfully.', 'github_plugin_updater' );
        echo is_wp_error( $activate ) ? $fail : $success;
        return $result;
    }
}
