<?php namespace hws_base_tools;

// Prevent loading this file directly or if the class already exists
if ( ! defined( 'ABSPATH' ) || class_exists( 'WPGitHubUpdater' ) || class_exists( 'WP_GitHub_Updater' ) ) {
    return;
}

class WP_GitHub_Updater {

    /** 
     * GitHub Updater version 
     */
    const VERSION = 1.6;

    /** @var array $config The full set of config values, passed in via get_github_config() */
    public $config;

    /** @var array $missing_config List of required config keys that were not supplied */
    public $missing_config;

    /** @var object $github_data Cached GitHub API response */
    private $github_data;

    /**
     * Constructor.
     *
     * @param array $config Must include:
     *   - slug, proper_folder_name,
     *   - api_url, raw_url, github_url, zip_url,
     *   - requires, tested, readme,
     *   - plugin_starter_file,
     *   - plugin_name, version, author, homepage, description,
     *   - (optional) sslverify, access_token
     */
    public function __construct( $config = [] ) {
        // Only sslverify & access_token get defaults here:
        $defaults = [
            'sslverify'    => true,
            'access_token' => '',
        ];
        $this->config = wp_parse_args( $config, $defaults );

        // Ensure absolutely every required key was passed in:
        if ( ! $this->has_minimum_config() ) {
            $msg = 'The GitHub Updater was initialized without the minimum required configuration. Missing: '
                 . implode( ',', $this->missing_config );
            _doing_it_wrong( __CLASS__, $msg, self::VERSION );
            return;
        }

        // Prepare zip_url (with token), new_version, last_updated, description
        $this->set_defaults();

        // Hook into WP updater
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'api_check' ] );
        add_filter( 'plugins_api',                     [ $this, 'get_plugin_info' ], 10, 3 );
        add_filter( 'upgrader_post_install',           [ $this, 'upgrader_post_install' ], 10, 3 );
        add_filter( 'http_request_timeout',            [ $this, 'http_request_timeout' ] );
        add_filter( 'http_request_args',               [ $this, 'http_request_sslverify' ], 10, 2 );
    }

    /**
     * Make sure all required config keys exist.
     *
     * @return bool
     */
    public function has_minimum_config() {
        $required = [
            'slug',
            'proper_folder_name',
            'api_url',
            'raw_url',
            'github_url',
            'zip_url',
            'requires',
            'tested',
            'readme',
            'plugin_starter_file',
            'plugin_name',
            'version',
            'author',
            'homepage',
            'description',
        ];

        $this->missing_config = [];
        foreach ( $required as $key ) {
            if ( empty( $this->config[ $key ] ) ) {
                $this->missing_config[] = $key;
            }
        }

        return empty( $this->missing_config );
    }

    /**
     * Populate any runtime defaults:
     * - Attach access_token to zip_url
     * - Fetch new_version, last_updated, description if missing
     */
    public function set_defaults() {
        if ( ! empty( $this->config['access_token'] ) ) {
            extract( parse_url( $this->config['zip_url'] ) ); // gives $scheme, $host, $path
            $zip = $scheme . '://api.github.com/repos' . $path;
            $zip = add_query_arg( [ 'access_token' => $this->config['access_token'] ], $zip );
            $this->config['zip_url'] = $zip;
        }

        if ( ! isset( $this->config['new_version'] ) ) {
            $this->config['new_version'] = $this->get_new_version();
        }
        if ( ! isset( $this->config['last_updated'] ) ) {
            $this->config['last_updated'] = $this->get_date();
        }
        if ( ! isset( $this->config['description'] ) ) {
            $this->config['description'] = $this->get_description();
        }
    }

    /**
     * Short HTTP timeout for GitHub calls.
     *
     * @return int
     */
    public function http_request_timeout() {
        return 2;
    }

    /**
     * Enforce our sslverify setting on zip_url requests.
     */
    public function http_request_sslverify( $args, $url ) {
        if ( isset( $this->config['zip_url'] ) && $url === $this->config['zip_url'] ) {
            $args['sslverify'] = $this->config['sslverify'];
        }
        return $args;
    }

    /**
     * Fetch the “Version:” header from your plugin starter file on GitHub.
     *
     * @return string|false
     */
    public function get_new_version() {
        $file = ltrim( $this->config['plugin_starter_file'], '/' );
        $url  = trailingslashit( $this->config['raw_url'] ) . $file;
        $resp = wp_remote_get( $url, [ 'sslverify' => $this->config['sslverify'] ] );

        if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
            return false;
        }

        if ( preg_match( '/^Version:\s*(.+)$/mi', wp_remote_retrieve_body( $resp ), $m ) ) {
            $ver = trim( $m[1] );
            set_site_transient( md5( $this->config['slug'] ) . '_new_version', $ver, HOUR_IN_SECONDS * 6 );
            return $ver;
        }

        return false;
    }

    /**
     * Simple wrapper to GET with optional token.
     */
    public function remote_get( $query ) {
        if ( ! empty( $this->config['access_token'] ) ) {
            $query = add_query_arg( [ 'access_token' => $this->config['access_token'] ], $query );
        }
        return wp_remote_get( $query, [ 'sslverify' => $this->config['sslverify'] ] );
    }

    /**
     * Retrieve and cache GitHub repository metadata.
     */
    public function get_github_data() {
        if ( ! empty( $this->github_data ) ) {
            return $this->github_data;
        }

        $key = md5( $this->config['slug'] ) . '_github_data';
        $data = get_site_transient( $key );

        if ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE || ! $data ) {
            $resp = $this->remote_get( $this->config['api_url'] );
            if ( is_wp_error( $resp ) ) {
                return false;
            }
            $data = json_decode( $resp['body'] );
            set_site_transient( $key, $data, HOUR_IN_SECONDS * 6 );
        }

        $this->github_data = $data;
        return $data;
    }

    /**
     * Get last‐updated date from GitHub data.
     */
    public function get_date() {
        $d = $this->get_github_data();
        return ! empty( $d->updated_at ) ? date( 'Y-m-d', strtotime( $d->updated_at ) ) : false;
    }

    /**
     * Get repository description from GitHub data.
     */
    public function get_description() {
        $d = $this->get_github_data();
        return ! empty( $d->description ) ? $d->description : false;
    }

    /**
     * Hook into WP’s update check and inject GitHub update if available.
     */
    public function api_check( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        if ( version_compare( $this->config['new_version'], $this->config['version'], '>' ) ) {
            $r = (object) [
                'new_version' => $this->config['new_version'],
                'slug'        => $this->config['proper_folder_name'],
                'url'         => add_query_arg( [ 'access_token' => $this->config['access_token'] ], $this->config['github_url'] ),
                'package'     => $this->config['zip_url'],
            ];
            $transient->response[ $this->config['slug'] ] = $r;
        }

        return $transient;
    }

    /**
     * Provide plugin details for the “View version details” screen.
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
     * After ZIP download: move into place and reactivate.
     */
    public function upgrader_post_install( $true, $hook_extra, $result ) {
        global $wp_filesystem;
        $dest = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
        $wp_filesystem->move( $result['destination'], $dest );
        $result['destination'] = $dest;
        activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
        return $result;
    }

}
