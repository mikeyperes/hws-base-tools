<?php namespace hws_base_tools;

use Hexa\PluginCore\QuerySafety\QueryEligibility;
use HWS\BaseTools\FrontendContent\ReadingProgress;
use HWS\BaseTools\TeamMembers\TeamMemberDirectory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hws_get_syndtd_feed_tag(): string {
    $tag = sanitize_title( (string) get_option( 'hws_syndtd_feed_tag', 'syndtd' ) );

    return $tag !== '' ? $tag : 'syndtd';
}

function hws_get_syndtd_feed_limit(): int {
    $limit = (int) get_option( 'hws_syndtd_feed_limit', 100 );

    return max( 1, min( 500, $limit ) );
}

function disable_non_admin_admin_bar(): void {
    add_filter( 'show_admin_bar', function( $show ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return $show;
    }, 999 );

    add_action( 'wp', function() {
        if ( ! current_user_can( 'manage_options' ) ) {
            show_admin_bar( false );
        }
    }, 999 );
}

function enable_syndtd_feed_limit(): void {
    add_action( 'pre_get_posts', __NAMESPACE__ . '\\hws_force_syndtd_feed_items' );
}

function hws_force_syndtd_feed_items( $query ): void {
    if ( ! $query instanceof \WP_Query
        || ! QueryEligibility::allows_main_filtered_frontend_query( $query )
        || ! $query->is_feed()
    ) {
        return;
    }

    if ( $query->is_tag( hws_get_syndtd_feed_tag() ) ) {
        $query->set( 'posts_per_rss', hws_get_syndtd_feed_limit() );
    }
}

function enable_current_year_shortcode(): void {
    add_shortcode( 'current_year', __NAMESPACE__ . '\\display_year_shortcode' );
}

function hws_lowercase_upload_filename( string $filename ): string {
    if ( function_exists( 'mb_strtolower' ) ) {
        return mb_strtolower( $filename );
    }

    return strtolower( $filename );
}

function enable_lowercase_upload_filenames(): void {
    add_filter( 'sanitize_file_name', __NAMESPACE__ . '\\hws_lowercase_upload_filename', 20 );
}

function enable_team_member_directory_templates(): void {
    ( new TeamMemberDirectory() )->register();
}

function enable_reading_progress_bar(): void {
    ReadingProgress::activate();
}
