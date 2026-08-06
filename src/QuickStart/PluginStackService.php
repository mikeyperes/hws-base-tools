<?php

namespace HWS\BaseTools\QuickStart;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

final class PluginStackService {
    /** @return array<string,mixed> */
    public function ensure( bool $include_smp = false ): array {
        $this->load_policy();
        if ( ! function_exists( '\hws_base_tools\hws_get_monitored_plugins' ) ) {
            return [ 'success' => false, 'message' => 'The HWS plugin policy is unavailable.', 'data' => [] ];
        }

        $definitions = [];
        foreach ( \hws_base_tools\hws_get_monitored_plugins() as $file => $policy ) {
            if ( 'essential' !== (string) ( $policy['category'] ?? '' ) ) {
                continue;
            }
            $definitions[] = [
                'file'   => (string) $file,
                'name'   => (string) ( $policy['name'] ?? $file ),
                'source' => $this->wordpress_slug( (string) ( $policy['download'] ?? '' ) ),
                'manual' => ! empty( $policy['pro'] ) || 'manual' === (string) ( $policy['download'] ?? '' ),
                'repo'   => '',
            ];
        }

        if ( $include_smp ) {
            $definitions[] = [ 'file' => 'smp-publication-integration/smp-publication-integration.php', 'name' => 'SMP Publication Integration', 'source' => '', 'manual' => false, 'repo' => 'mikeyperes/smp-publication-integration' ];
            $definitions[] = [ 'file' => 'smp-verified-profiles/smp-verified-profiles.php', 'name' => 'SMP Verified Profiles', 'source' => '', 'manual' => false, 'repo' => 'mikeyperes/smp-verified-profiles' ];
        }

        $items = [];
        $failed = [];
        $review = [];
        foreach ( $definitions as $definition ) {
            $file   = (string) $definition['file'];
            $before = PluginProvisioner::plugin_status_by_file( $file );
            $action = 'Already active.';

            if ( empty( $before['installed'] ) && ! empty( $definition['manual'] ) ) {
                $review[] = (string) $definition['name'];
                $items[]  = [ 'plugin' => $definition['name'], 'file' => $file, 'before' => $before, 'after' => $before, 'action' => 'Licensed/manual package requires review.' ];
                continue;
            }

            if ( empty( $before['installed'] ) ) {
                $installed = '' !== (string) $definition['repo']
                    ? PluginProvisioner::install_github_plugin( dirname( $file ), (string) $definition['repo'], [ 'branch' => 'main' ] )
                    : PluginProvisioner::install_wordpress_org_plugin( (string) $definition['source'], true );
                if ( is_wp_error( $installed ) ) {
                    $failed[] = (string) $definition['name'] . ': ' . $installed->get_error_message();
                    $items[]  = [ 'plugin' => $definition['name'], 'file' => $file, 'before' => $before, 'after' => PluginProvisioner::plugin_status_by_file( $file ), 'action' => $installed->get_error_message() ];
                    continue;
                }
                $action = 'Installed and activation requested.';
            }

            $after = PluginProvisioner::plugin_status_by_file( $file );
            if ( ! empty( $after['installed'] ) && empty( $after['active'] ) ) {
                $activated = PluginProvisioner::activate_plugin_file( $file );
                if ( is_wp_error( $activated ) ) {
                    $failed[] = (string) $definition['name'] . ': ' . $activated->get_error_message();
                    $action = $activated->get_error_message();
                } else {
                    $action = 'Activated.';
                }
                $after = PluginProvisioner::plugin_status_by_file( $file );
            }
            if ( empty( $after['active'] ) ) {
                $failed[] = (string) $definition['name'] . ': active state did not verify.';
            }
            $items[] = [ 'plugin' => $definition['name'], 'file' => $file, 'before' => $before, 'after' => $after, 'action' => $action ];
        }

        return [
            'success' => [] === $failed,
            'message' => [] === $failed
                ? 'Automatically installable required plugins are active.' . ( $review ? ' ' . count( $review ) . ' licensed plugin(s) remain in Review Center.' : '' )
                : count( $failed ) . ' plugin provision issue(s) remain.',
            'data'    => [ 'items' => $items, 'failed' => $failed, 'manual_review' => $review ],
        ];
    }

    private function wordpress_slug( string $download ): string {
        return preg_match( '#wordpress\.org/plugins/([^/]+)/?#', $download, $matches ) ? sanitize_key( (string) $matches[1] ) : '';
    }

    private function load_policy(): void {
        if ( defined( 'HWS_BASE_TOOLS_DIR' ) ) {
            $file = HWS_BASE_TOOLS_DIR . '/settings-dashboard-check-plugins.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
    }
}
