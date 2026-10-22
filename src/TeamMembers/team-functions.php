<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hws_team_members' ) ) {
    /**
     * Published team members from HWS Base Tools (Team tab), in the team page order:
     * [ [ 'id', 'name', 'position', 'featured', 'excerpt', 'url', 'photo', 'photo_id', 'order', 'edit_url' ], ... ].
     * Args: featured_only (bool), category (slug), limit (int). Empty while the Team feature is off.
     */
    function hws_team_members( array $args = [] ): array {
        return \HWS\BaseTools\TeamMembers\TeamMemberDirectory::members( $args );
    }
}

if ( ! function_exists( 'hws_team_enabled' ) ) {
    /** Whether the Team feature (post type, fields and listing) is on. */
    function hws_team_enabled(): bool {
        return \HWS\BaseTools\TeamMembers\TeamTab::enabled();
    }
}

if ( ! function_exists( 'hws_team_page_url' ) ) {
    /** The public team archive (/team/), or '' while the feature is off. */
    function hws_team_page_url(): string {
        $post_type = \HWS\BaseTools\TeamMembers\TeamMemberDirectory::POST_TYPE;
        return post_type_exists( $post_type ) ? (string) get_post_type_archive_link( $post_type ) : '';
    }
}

if ( ! function_exists( 'hws_team_edit_url' ) ) {
    /** Where the team is managed; pair with Hexa WP Core's SourceBanner when showing it elsewhere. */
    function hws_team_edit_url(): string {
        return admin_url( 'options-general.php?page=hws-core-tools&tab=team' );
    }
}
