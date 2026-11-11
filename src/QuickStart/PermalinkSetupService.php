<?php
namespace HWS\BaseTools\QuickStart;
use Hexa\PluginCore\WordPressOperations\PermalinkOperations;
final class PermalinkSetupService {
    public function repair(): array {
        $structure = trim( (string) get_option( 'permalink_structure', '' ) );
        $result = ( new PermalinkOperations() )->repair( '' !== $structure ? $structure : '/%postname%/' );
        if ( empty( $result['success'] ) ) return $result;
        // CRITICAL - see BUGLOG.md SETUP-001. Hard flush can skip disk writes.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        global $wp_rewrite;
        if ( is_multisite() || ! is_object( $wp_rewrite ) || ! is_callable( [ $wp_rewrite, 'mod_rewrite_rules' ] ) ) {
            $result['success'] = false;
            $result['messages'][] = 'Rewrite setup requires single-site Apache or LiteSpeed.';
            return $result;
        }
        $path = get_home_path() . '.htaccess';
        $rules = trim( (string) $wp_rewrite->mod_rewrite_rules() );
        $written = '' !== $rules && insert_with_markers( $path, 'WordPress', explode( "\n", $rules ) );
        $content = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
        $verified = $written && preg_match( '/^# BEGIN WordPress\R(.*?)^# END WordPress/ms', $content, $match ) && str_contains( $match[1], 'RewriteRule' ) && str_contains( $match[1], 'index.php' );
        $result['success'] = (bool) $verified;
        $result['rewrite_file'] = [ 'written' => (bool) $written, 'verified' => (bool) $verified ];
        $result['messages'][] = $verified ? 'WordPress rewrite block written and verified; other server blocks preserved.' : 'WordPress rewrite block could not be written or verified.';
        return $result;
    }
}
