<?php
/*
Plugin Name: HWS Base Tools Bootstrap Loader
Description: Securely installs or updates HWS Base Tools from its canonical GitHub release.
Version: 2.0.0
Author: Hexa Web Systems
*/

namespace HWS\BootstrapLoader;

defined( 'ABSPATH' ) || exit;

const ADMIN_PAGE        = 'hws-bootstrap-installer';
const ACTION            = 'hws_bootstrap_install';
const REPOSITORY        = 'mikeyperes/hws-base-tools';
const PLUGIN            = 'hws-base-tools/hws-base-tools.php';
const PENDING_OPTION    = 'hws_bootstrap_pending_health_check';
const LAST_RESULT_OPTION = 'hws_bootstrap_last_health_result';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_admin_page' );
add_action( 'admin_post_' . ACTION, __NAMESPACE__ . '\\handle_install' );
add_action( 'admin_post_nopriv_' . ACTION, __NAMESPACE__ . '\\handle_install' );
register_pending_health_check();

function register_admin_page(): void {
    add_management_page(
        'Install HWS Base Tools',
        'HWS Bootstrap',
        'install_plugins',
        ADMIN_PAGE,
        __NAMESPACE__ . '\\render_admin_page'
    );
}

function render_admin_page(): void {
    if ( ! current_user_can( 'install_plugins' ) ) {
        wp_die(
            esc_html__( 'You cannot install plugins on this site.', 'hws-bootstrap-loader' ),
            esc_html__( 'HWS Bootstrap Access Denied', 'hws-bootstrap-loader' ),
            [ 'response' => 403 ]
        );
    }

    $installed = is_file( WP_PLUGIN_DIR . '/' . PLUGIN );
    $health    = get_shared_option( LAST_RESULT_OPTION, [] );
    ?>
    <div class="wrap">
        <h1>HWS Base Tools Bootstrap</h1>
        <?php if ( isset( $_GET['hws_bootstrap'] ) && 'success' === sanitize_key( wp_unslash( $_GET['hws_bootstrap'] ) ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>HWS Base Tools was installed from a verified GitHub release. Its rollback copy will be retained until the next request passes the health check.</p></div>
        <?php endif; ?>
        <?php if ( is_array( $health ) && ! empty( $health['message'] ) ) : ?>
            <div class="notice <?php echo ! empty( $health['success'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( (string) $health['message'] ); ?></p></div>
        <?php endif; ?>
        <p><?php echo esc_html( $installed ? 'HWS Base Tools is installed. This will replace it with the latest verified GitHub release.' : 'Install HWS Base Tools directly from its latest verified GitHub release.' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( ACTION ); ?>">
            <?php wp_nonce_field( ACTION ); ?>
            <?php submit_button( $installed ? 'Update and Activate HWS Base Tools' : 'Install and Activate HWS Base Tools', 'primary', 'submit', false ); ?>
        </form>
        <p><code><?php echo esc_html( admin_url( 'tools.php?page=' . ADMIN_PAGE ) ); ?></code></p>
    </div>
    <?php
}

function handle_install(): void {
    $signed = authorize_signed_request();
    if ( ! $signed ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
            wp_die(
                esc_html__( 'A plugin administrator or valid signed bootstrap URL is required.', 'hws-bootstrap-loader' ),
                esc_html__( 'HWS Bootstrap Access Denied', 'hws-bootstrap-loader' ),
                [ 'response' => 403 ]
            );
        }
        check_admin_referer( ACTION );
    }

    $result = install_latest();
    $format = isset( $_REQUEST['format'] ) && ! is_array( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'html';
    if ( 'json' === $format ) {
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ], 500 );
        }
        wp_send_json_success( $result );
    }
    if ( 'text' === $format ) {
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo is_wp_error( $result ) ? 'ERROR: ' . esc_html( $result->get_error_message() ) : 'SUCCESS: ' . esc_html( (string) $result['message'] );
        exit;
    }

    if ( is_wp_error( $result ) ) {
        wp_die( esc_html( $result->get_error_message() ), 'HWS Bootstrap Failed', [ 'response' => 500, 'back_link' => true ] );
    }
    wp_safe_redirect( add_query_arg( [ 'page' => ADMIN_PAGE, 'hws_bootstrap' => 'success' ], admin_url( 'tools.php' ) ) );
    exit;
}

/** @return array<string,mixed>|\WP_Error */
function install_latest(): array|\WP_Error {
    $pending = get_shared_option( PENDING_OPTION, [] );
    if ( is_array( $pending ) && ! empty( $pending['token'] ) ) {
        if ( ! pending_paths_are_safe( $pending ) ) {
            delete_shared_option( PENDING_OPTION );
            update_shared_option( LAST_RESULT_OPTION, [ 'success' => false, 'message' => 'An invalid bootstrap rollback record was discarded without touching plugin files.', 'checked_at' => time() ] );
        } else {
            return new \WP_Error( 'hws_bootstrap_health_pending', 'The previous update is awaiting its next-request health check. Reload once before starting another update.' );
        }
    }

    prepare_wordpress_filesystem();
    $release = latest_release();
    if ( is_wp_error( $release ) ) {
        return $release;
    }

    $zip = download_url( (string) $release['zip_url'], 60 );
    if ( is_wp_error( $zip ) ) {
        return $zip;
    }

    $workspace = trailingslashit( get_temp_dir() ) . 'hws-bootstrap-' . wp_generate_uuid4();
    if ( ! wp_mkdir_p( $workspace ) ) {
        wp_delete_file( $zip );
        return new \WP_Error( 'hws_bootstrap_temp_failed', 'Could not create the protected bootstrap workspace.' );
    }

    $unzipped = unzip_file( $zip, $workspace );
    wp_delete_file( $zip );
    if ( is_wp_error( $unzipped ) ) {
        delete_workspace( $workspace );
        return $unzipped;
    }

    $source = locate_source( $workspace );
    if ( '' === $source ) {
        delete_workspace( $workspace );
        return new \WP_Error( 'hws_bootstrap_source_missing', 'The release package did not contain hws-base-tools.php.' );
    }

    $validation = validate_package( $source, (string) $release['tag'] );
    if ( is_wp_error( $validation ) ) {
        delete_workspace( $workspace );
        return $validation;
    }

    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        delete_workspace( $workspace );
        return new \WP_Error( 'hws_bootstrap_filesystem_failed', 'WordPress filesystem access is unavailable.' );
    }

    $target           = trailingslashit( WP_PLUGIN_DIR ) . 'hws-base-tools';
    $backup           = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/hws-base-tools-bootstrap-backup-' . wp_generate_uuid4();
    $had_existing     = $wp_filesystem->is_dir( $target );
    $activation_state = plugin_activation_state();

    if ( $had_existing ) {
        wp_mkdir_p( dirname( $backup ) );
        if ( ! $wp_filesystem->move( $target, $backup, false ) ) {
            delete_workspace( $workspace );
            return new \WP_Error( 'hws_bootstrap_backup_failed', 'The existing HWS Base Tools directory could not be moved to a rollback location.' );
        }
    }

    $copied = copy_dir( $source, $target );
    if ( is_wp_error( $copied ) || ! $wp_filesystem->is_file( $target . '/hws-base-tools.php' ) ) {
        restore_previous_install( $target, $backup, $had_existing, $activation_state );
        delete_workspace( $workspace );
        return is_wp_error( $copied ) ? $copied : new \WP_Error( 'hws_bootstrap_copy_failed', 'The verified plugin entry was not present after copying.' );
    }

    $installed_validation = validate_package( $target, (string) $release['tag'] );
    if ( is_wp_error( $installed_validation ) ) {
        restore_previous_install( $target, $backup, $had_existing, $activation_state );
        delete_workspace( $workspace );
        return $installed_validation;
    }

    $activated = activate_plugin( PLUGIN );
    if ( is_wp_error( $activated ) ) {
        restore_previous_install( $target, $backup, $had_existing, $activation_state );
        delete_workspace( $workspace );
        return $activated;
    }

    $pending = [
        'token'            => wp_generate_uuid4(),
        'target'           => $target,
        'backup'           => $backup,
        'had_existing'     => $had_existing,
        'activation_state' => $activation_state,
        'expected_version' => (string) $validation['version'],
        'expected_hash'    => (string) $validation['core_hash'],
        'created_at'       => time(),
    ];
    if ( ! update_shared_option( PENDING_OPTION, $pending ) ) {
        restore_previous_install( $target, $backup, $had_existing, $activation_state );
        delete_workspace( $workspace );
        return new \WP_Error( 'hws_bootstrap_health_state_failed', 'The rollback health-check state could not be stored, so the previous installation was restored.' );
    }

    delete_shared_option( LAST_RESULT_OPTION );
    delete_workspace( $workspace );

    return [
        'message' => 'HWS Base Tools ' . $validation['version'] . ' was installed from signed release ' . $release['tag'] . ' and activated; rollback is retained until the next request passes health verification.',
        'plugin'  => PLUGIN,
        'version' => (string) $validation['version'],
        'release' => (string) $release['tag'],
        'updated' => $had_existing,
        'pending_health_check' => true,
    ];
}

/** @return array{tag:string,zip_url:string}|\WP_Error */
function latest_release(): array|\WP_Error {
    $response = wp_safe_remote_get(
        'https://api.github.com/repos/' . REPOSITORY . '/releases/latest',
        [
            'timeout' => 20,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'HWS-Base-Tools-Bootstrap',
            ],
        ]
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new \WP_Error( 'hws_bootstrap_release_unavailable', 'GitHub did not return a published HWS Base Tools release.' );
    }
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $tag  = is_array( $data ) ? trim( (string) ( $data['tag_name'] ?? '' ) ) : '';
    if ( ! preg_match( '/^v?[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.-]+)?$/', $tag ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
        return new \WP_Error( 'hws_bootstrap_release_invalid', 'The latest GitHub response was not an immutable production release.' );
    }
    return [
        'tag'     => $tag,
        'zip_url' => 'https://github.com/' . REPOSITORY . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip',
    ];
}

/** @return array{version:string,core_hash:string}|\WP_Error */
function validate_package( string $source, string $release_tag ): array|\WP_Error {
    $entry     = trailingslashit( $source ) . 'hws-base-tools.php';
    $core_root = trailingslashit( $source ) . 'lib/hexa-wordpress-plugin-core';
    foreach ( [ $entry, $core_root . '/VERSION', $core_root . '/PACKAGE_HASH', $core_root . '/bootstrap.php' ] as $required ) {
        if ( ! is_readable( $required ) ) {
            return new \WP_Error( 'hws_bootstrap_package_incomplete', 'The release is missing a required HWS Base Tools or Hexa WP Core file.' );
        }
    }
    if ( ! is_dir( $core_root . '/src' ) ) {
        return new \WP_Error( 'hws_bootstrap_package_incomplete', 'The release is missing the Hexa WP Core source directory.' );
    }

    $data     = get_plugin_data( $entry, false, false );
    $version  = trim( (string) ( $data['Version'] ?? '' ) );
    $expected = ltrim( $release_tag, 'vV' );
    if ( '' === $version || $version !== $expected ) {
        return new \WP_Error( 'hws_bootstrap_release_version_mismatch', 'The plugin version does not match the immutable GitHub release tag.' );
    }

    $declared_hash = trim( (string) file_get_contents( $core_root . '/PACKAGE_HASH' ) );
    $actual_hash   = core_source_hash( $core_root );
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $declared_hash ) || ! hash_equals( $declared_hash, $actual_hash ) ) {
        return new \WP_Error( 'hws_bootstrap_core_integrity_failed', 'The bundled Hexa WP Core source failed PACKAGE_HASH verification.' );
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS )
    );
    foreach ( $iterator as $file ) {
        if ( ! $file instanceof \SplFileInfo || ! $file->isFile() || $file->isLink() || 'php' !== strtolower( $file->getExtension() ) ) {
            continue;
        }
        $contents = file_get_contents( $file->getPathname() );
        if ( false === $contents ) {
            return new \WP_Error( 'hws_bootstrap_php_unreadable', 'A PHP file in the release could not be read for validation.' );
        }
        try {
            token_get_all( $contents, TOKEN_PARSE );
        } catch ( \ParseError $error ) {
            return new \WP_Error( 'hws_bootstrap_php_invalid', 'A PHP file in the release failed syntax validation: ' . $file->getFilename() );
        }
    }

    return [ 'version' => $version, 'core_hash' => $actual_hash ];
}

function core_source_hash( string $core_root ): string {
    $core_root = rtrim( str_replace( '\\', '/', $core_root ), '/' );
    $files     = [];
    foreach ( [ 'bootstrap.php', 'src' ] as $relative ) {
        $path = $core_root . '/' . $relative;
        if ( is_file( $path ) ) {
            $files[] = $path;
            continue;
        }
        if ( ! is_dir( $path ) ) {
            continue;
        }
        $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( $file instanceof \SplFileInfo && $file->isFile() && ! $file->isLink() && 'php' === strtolower( $file->getExtension() ) ) {
                $files[] = str_replace( '\\', '/', $file->getPathname() );
            }
        }
    }
    sort( $files, SORT_STRING );
    $context = hash_init( 'sha256' );
    foreach ( $files as $file ) {
        hash_update( $context, substr( $file, strlen( $core_root ) ) );
        hash_update_file( $context, $file );
    }
    return hash_final( $context );
}

function authorize_signed_request(): bool {
    if ( 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
        return false;
    }
    $request_action = isset( $_GET['action'] ) && ! is_array( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $expires        = isset( $_GET['expires'] ) && ! is_array( $_GET['expires'] ) ? (int) $_GET['expires'] : 0;
    $nonce          = isset( $_GET['request_id'] ) && ! is_array( $_GET['request_id'] ) ? sanitize_key( wp_unslash( $_GET['request_id'] ) ) : '';
    $signature      = isset( $_GET['signature'] ) && ! is_array( $_GET['signature'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['signature'] ) ) ) : '';
    if ( ACTION !== $request_action || $expires < time() || $expires > time() + 600 || ! preg_match( '/^[a-f0-9]{32,64}$/', $nonce ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
        return false;
    }

    $secret = bootstrap_secret();
    if ( strlen( $secret ) < 32 ) {
        return false;
    }
    $expected = hash_hmac( 'sha256', signature_payload( canonical_home_url(), $expires, $nonce ), $secret );
    if ( ! hash_equals( $expected, $signature ) ) {
        return false;
    }

    $replay_key = 'hws_bootstrap_used_' . hash( 'sha256', $nonce );
    if ( get_transient( $replay_key ) ) {
        return false;
    }
    set_transient( $replay_key, 1, 15 * MINUTE_IN_SECONDS );
    return true;
}

function signature_payload( string $canonical_home, int $expires, string $nonce ): string {
    return ACTION . '|GET|' . $canonical_home . '|' . $expires . '|' . $nonce;
}

function canonical_home_url(): string {
    return canonicalize_site_url( home_url( '/' ) );
}

function canonicalize_site_url( string $url ): string {
    $parts  = wp_parse_url( $url );
    $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
    $host   = strtolower( (string) ( $parts['host'] ?? '' ) );
    $origin = $scheme . '://' . $host;
    if ( isset( $parts['port'] ) ) {
        $origin .= ':' . (int) $parts['port'];
    }
    $path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
    return rtrim( $origin . ( '/' === $path ? '' : $path ), '/' );
}

function bootstrap_secret(): string {
    $secret = defined( 'HWS_BOOTSTRAP_SECRET' ) ? (string) HWS_BOOTSTRAP_SECRET : '';
    if ( '' === $secret ) {
        $environment = getenv( 'HWS_BOOTSTRAP_SECRET' );
        $secret = false !== $environment ? (string) $environment : '';
    }
    return trim( $secret );
}

function register_pending_health_check(): void {
    $pending = get_shared_option( PENDING_OPTION, [] );
    if ( ! is_array( $pending ) || empty( $pending['token'] ) ) {
        return;
    }
    if ( ! pending_paths_are_safe( $pending ) ) {
        delete_shared_option( PENDING_OPTION );
        update_shared_option( LAST_RESULT_OPTION, [ 'success' => false, 'message' => 'An invalid bootstrap rollback record was discarded without touching plugin files.', 'checked_at' => time() ] );
        return;
    }
    $GLOBALS['hws_bootstrap_plugins_loaded'] = false;
    add_action(
        'plugins_loaded',
        static function (): void {
            $GLOBALS['hws_bootstrap_plugins_loaded'] = true;
        },
        PHP_INT_MAX
    );
    register_shutdown_function( __NAMESPACE__ . '\\complete_pending_health_check', (string) $pending['token'] );
}

function complete_pending_health_check( string $token ): void {
    $pending = get_shared_option( PENDING_OPTION, [] );
    if ( ! is_array( $pending ) || ! hash_equals( (string) ( $pending['token'] ?? '' ), $token ) || ! pending_paths_are_safe( $pending ) ) {
        return;
    }

    $error         = error_get_last();
    $target        = (string) $pending['target'];
    $target_fatal  = is_array( $error ) && in_array( (int) ( $error['type'] ?? 0 ), [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true )
        && str_starts_with( str_replace( '\\', '/', (string) ( $error['file'] ?? '' ) ), str_replace( '\\', '/', $target ) . '/' );
    $plugins_loaded = ! empty( $GLOBALS['hws_bootstrap_plugins_loaded'] );
    $healthy        = $plugins_loaded && ! $target_fatal && installed_health_is_valid( $pending );

    prepare_wordpress_filesystem();
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        return;
    }

    if ( ! $healthy ) {
        restore_previous_install(
            $target,
            (string) $pending['backup'],
            ! empty( $pending['had_existing'] ),
            (string) ( $pending['activation_state'] ?? 'inactive' )
        );
        update_shared_option( LAST_RESULT_OPTION, [ 'success' => false, 'message' => 'The HWS update failed its next-request health check and was rolled back automatically.', 'checked_at' => time() ] );
        delete_shared_option( PENDING_OPTION );
        return;
    }

    $backup = (string) $pending['backup'];
    if ( '' !== $backup && $wp_filesystem->is_dir( $backup ) ) {
        $wp_filesystem->delete( $backup, true );
    }
    update_shared_option( LAST_RESULT_OPTION, [ 'success' => true, 'message' => 'HWS Base Tools passed its next-request health check; the rollback copy was safely removed.', 'checked_at' => time() ] );
    delete_shared_option( PENDING_OPTION );
}

/** @param array<string,mixed> $pending */
function installed_health_is_valid( array $pending ): bool {
    prepare_wordpress_filesystem();
    $target = (string) $pending['target'];
    if ( ! is_file( $target . '/hws-base-tools.php' ) ) {
        return false;
    }
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( ! is_plugin_active( PLUGIN ) && ! is_plugin_active_for_network( PLUGIN ) ) {
        return false;
    }
    $data = get_plugin_data( $target . '/hws-base-tools.php', false, false );
    if ( (string) ( $data['Version'] ?? '' ) !== (string) ( $pending['expected_version'] ?? '' ) ) {
        return false;
    }
    $core_root = $target . '/lib/hexa-wordpress-plugin-core';
    $declared  = is_readable( $core_root . '/PACKAGE_HASH' ) ? trim( (string) file_get_contents( $core_root . '/PACKAGE_HASH' ) ) : '';
    $actual    = core_source_hash( $core_root );
    return '' !== $declared
        && hash_equals( $declared, (string) ( $pending['expected_hash'] ?? '' ) )
        && hash_equals( $declared, $actual );
}

/** @param array<string,mixed> $pending */
function pending_paths_are_safe( array $pending ): bool {
    $target = rtrim( str_replace( '\\', '/', (string) ( $pending['target'] ?? '' ) ), '/' );
    $backup = rtrim( str_replace( '\\', '/', (string) ( $pending['backup'] ?? '' ) ), '/' );
    $expected_target = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ) . '/hws-base-tools';
    $backup_prefix   = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' ) . '/upgrade/hws-base-tools-bootstrap-backup-';
    return $target === $expected_target && ( '' === $backup || str_starts_with( $backup, $backup_prefix ) );
}

function plugin_activation_state(): string {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( is_plugin_active_for_network( PLUGIN ) ) {
        return 'network';
    }
    return is_plugin_active( PLUGIN ) ? 'site' : 'inactive';
}

function restore_previous_install( string $target, string $backup, bool $had_existing, string $activation_state ): void {
    prepare_wordpress_filesystem();
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        return;
    }
    if ( function_exists( 'deactivate_plugins' ) && 'inactive' === $activation_state ) {
        deactivate_plugins( PLUGIN, true, false );
    }
    if ( $wp_filesystem->is_dir( $target ) ) {
        $wp_filesystem->delete( $target, true );
    }
    if ( $had_existing && $wp_filesystem->is_dir( $backup ) ) {
        $wp_filesystem->move( $backup, $target, false );
    }
}

function prepare_wordpress_filesystem(): void {
    if ( ! function_exists( 'download_url' ) || ! function_exists( 'unzip_file' ) || ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    WP_Filesystem();
}

function locate_source( string $workspace ): string {
    foreach ( glob( trailingslashit( $workspace ) . '*' ) ?: [] as $candidate ) {
        if ( is_dir( $candidate ) && is_file( trailingslashit( $candidate ) . 'hws-base-tools.php' ) ) {
            return $candidate;
        }
    }
    return '';
}

function delete_workspace( string $workspace ): void {
    global $wp_filesystem;
    if ( $wp_filesystem ) {
        $wp_filesystem->delete( $workspace, true );
        return;
    }
    prepare_wordpress_filesystem();
    if ( $wp_filesystem ) {
        $wp_filesystem->delete( $workspace, true );
    }
}

function get_shared_option( string $name, mixed $default = false ): mixed {
    return is_multisite() ? get_site_option( $name, $default ) : get_option( $name, $default );
}

function update_shared_option( string $name, mixed $value ): bool {
    return is_multisite() ? update_site_option( $name, $value ) : update_option( $name, $value, false );
}

function delete_shared_option( string $name ): bool {
    return is_multisite() ? delete_site_option( $name ) : delete_option( $name );
}
