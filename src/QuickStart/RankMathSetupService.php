<?php

namespace HWS\BaseTools\QuickStart;

/** Completes Rank Math's optional registration gate without connecting an account. */
final class RankMathSetupService {
    /** @return array<string,mixed> */
    public function configure(): array {
        if ( ! function_exists( 'rank_math' ) || ! class_exists( '\RankMath\Helper' ) ) {
            return [ 'success' => true, 'message' => 'Rank Math is not active; registration unchanged.', 'data' => [ 'action' => 'not_active' ] ];
        }
        if ( \RankMath\Helper::is_site_connected() ) {
            return [ 'success' => true, 'message' => 'Connected Rank Math account preserved.', 'data' => [ 'action' => 'connected_preserved' ] ];
        }
        // Match the option and scope read by Rank Math's registration gate.
        $network = \RankMath\Helper::is_plugin_active_for_network();
        $before = $network ? get_blog_option( get_main_site_id(), 'rank_math_registration_skip' ) : get_option( 'rank_math_registration_skip' );
        if ( ! (bool) $before ) {
            if ( $network ) {
                update_blog_option( get_main_site_id(), 'rank_math_registration_skip', true );
            } else {
                update_option( 'rank_math_registration_skip', true );
            }
        }
        $ready = ! \RankMath\Helper::is_invalid_registration();
        return [
            'success' => $ready,
            'message' => $ready ? 'Rank Math optional registration skipped; SEO output is enabled on the next request.' : 'Rank Math registration skip did not verify.',
            'data' => [ 'action' => (bool) $before ? 'already_skipped' : 'registration_skipped', 'registration_ready' => $ready ],
        ];
    }
}
