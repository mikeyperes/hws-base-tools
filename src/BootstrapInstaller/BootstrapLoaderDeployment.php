<?php

namespace HWS\BaseTools\BootstrapInstaller;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class BootstrapLoaderDeployment {
    public const FILE_NAME = 'hws-base-tools-bootstrap.php';

    /** @return array<string,mixed> */
    public function install(): array {
        $source = PluginMetadata::root_path() . '/deploy/' . self::FILE_NAME;
        $mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
        $target = trailingslashit( $mu_dir ) . self::FILE_NAME;

        if ( ! is_readable( $source ) ) {
            return [ 'success' => false, 'message' => 'Bundled bootstrap loader source is missing.', 'data' => [] ];
        }
        if ( ! is_dir( $mu_dir ) && ! wp_mkdir_p( $mu_dir ) ) {
            return [ 'success' => false, 'message' => 'The WordPress MU-plugin directory could not be created.', 'data' => [ 'directory' => $mu_dir ] ];
        }
        if ( ! is_writable( $mu_dir ) ) {
            return [ 'success' => false, 'message' => 'The WordPress MU-plugin directory is not writable.', 'data' => [ 'directory' => $mu_dir ] ];
        }

        $existing_hash = is_readable( $target ) ? hash_file( 'sha256', $target ) : '';
        $source_hash   = hash_file( 'sha256', $source );
        if ( $existing_hash !== $source_hash ) {
            $token  = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : str_replace( '.', '', uniqid( '', true ) );
            $stage  = $mu_dir . '/.' . self::FILE_NAME . '.stage-' . $token;
            $backup = $mu_dir . '/.' . self::FILE_NAME . '.backup-' . $token;
            if ( ! copy( $source, $stage ) || ! is_readable( $stage ) || hash_file( 'sha256', $stage ) !== $source_hash ) {
                if ( is_file( $stage ) ) {
                    wp_delete_file( $stage );
                }
                return [ 'success' => false, 'message' => 'The staged bootstrap loader could not be written and verified.', 'data' => [ 'target' => $target ] ];
            }
            $had_existing = is_file( $target );
            if ( $had_existing && ! @rename( $target, $backup ) ) {
                wp_delete_file( $stage );
                return [ 'success' => false, 'message' => 'The existing bootstrap loader could not be moved to its rollback file.', 'data' => [ 'target' => $target ] ];
            }
            if ( ! @rename( $stage, $target ) ) {
                if ( $had_existing ) {
                    @rename( $backup, $target );
                }
                if ( is_file( $stage ) ) {
                    wp_delete_file( $stage );
                }
                return [ 'success' => false, 'message' => 'The verified bootstrap loader could not be moved into place.', 'data' => [ 'target' => $target ] ];
            }
            if ( $had_existing && is_file( $backup ) ) {
                wp_delete_file( $backup );
            }
        }
        $verified = is_readable( $target ) && hash_file( 'sha256', $target ) === $source_hash;
        return [
            'success' => $verified,
            'message' => $verified ? 'Secure HWS bootstrap loader installed and verified as an MU plugin.' : 'Bootstrap loader hash verification failed.',
            'data'    => [
                'target'    => $target,
                'admin_url' => admin_url( 'tools.php?page=hws-bootstrap-installer' ),
                'changed'   => $existing_hash !== $source_hash,
            ],
        ];
    }
}
