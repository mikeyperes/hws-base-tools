<?php

namespace hws_base_tools;

use Hexa\PluginCore\ContentCleanup\ContentCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ContentCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ContentCleanupRenderer;

defined( 'ABSPATH' ) || exit;

const HWS_CONTENT_CLEANUP_NONCE_ACTION = 'hws_base_tools_content_cleanup';

function hws_content_cleanup_config(): ContentCleanupConfig {
    return new ContentCleanupConfig(
        [
            'root_id'                => 'hws-content-cleanup',
            'title'                  => 'Cleanup',
            'description'            => 'Detect old WordPress pages, review title, slug, publish date, modified date, and clean them up through live AJAX actions.',
            'capability'             => 'manage_options',
            'nonce_action'           => HWS_CONTENT_CLEANUP_NONCE_ACTION,
            'nonce_field'            => 'nonce',
            'scan_action'            => 'hws_content_cleanup_scan',
            'trash_action'           => 'hws_content_cleanup_trash',
            'delete_action'          => 'hws_content_cleanup_delete',
            'post_types'             => [ 'page' => 'Pages' ],
            'statuses'               => [
                'publish' => 'Published',
                'draft'   => 'Draft',
                'private' => 'Private',
                'pending' => 'Pending',
                'trash'   => 'Trash',
                'any'     => 'Any visible status',
            ],
            'default_post_type'      => 'page',
            'default_status'         => 'publish',
            'default_published_days' => 365,
            'default_modified_days'  => 0,
            'default_limit'          => 50,
            'max_limit'              => 250,
            'protected_post_ids'     => __NAMESPACE__ . '\\hws_content_cleanup_protected_page_ids',
            'empty_message'          => 'No old pages matched the selected cleanup filters.',
        ]
    );
}

function hws_content_cleanup_protected_page_ids(): array {
    $ids = [];

    foreach ( [ 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy' ] as $option ) {
        $value = function_exists( 'get_option' ) ? absint( get_option( $option ) ) : 0;
        if ( $value > 0 ) {
            $ids[] = $value;
        }
    }

    return array_values( array_unique( $ids ) );
}

function hws_register_content_cleanup_ajax(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    ( new ContentCleanupAjaxController( hws_content_cleanup_config() ) )->register();

    $registered = true;
}
add_action( 'init', __NAMESPACE__ . '\\hws_register_content_cleanup_ajax', 20 );

function display_settings_cleanup(): void {
    hws_register_content_cleanup_ajax();

    ( new ContentCleanupRenderer( hws_content_cleanup_config() ) )->render();
}
