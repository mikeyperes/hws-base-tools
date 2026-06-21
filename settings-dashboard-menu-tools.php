<?php namespace hws_base_tools;

use Hexa\PluginCore\SiteStructure\PageStructureManager;
use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;

defined( 'ABSPATH' ) || exit;

function hws_menu_tools_page_definitions(): array {
    if ( ! function_exists( 'get_pages' ) ) {
        return [];
    }

    $definitions = [];
    $pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title', 'sort_order' => 'ASC' ] );

    foreach ( $pages as $page ) {
        if ( ! $page instanceof \WP_Post ) {
            continue;
        }
        $page_id = (int) $page->ID;
        if ( $page_id <= 0 ) {
            continue;
        }
        $definitions[ 'page_' . $page_id ] = [
            'title' => $page->post_title ?: '(Untitled page #' . $page_id . ')',
            'slug'  => $page->post_name ?: 'page-' . $page_id,
        ];
    }

    return $definitions;
}

function hws_menu_tools_assigned_page_id( string $page_key ): int {
    if ( ! preg_match( '/^page_(\d+)$/', $page_key, $matches ) ) {
        return 0;
    }
    $page_id = (int) $matches[1];
    if ( $page_id <= 0 || ! function_exists( 'get_post' ) ) {
        return 0;
    }
    $page = get_post( $page_id );
    if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
        return 0;
    }
    return $page_id;
}

function hws_menu_tools_manager(): PageStructureManager {
    $pages = hws_menu_tools_page_definitions();
    return new PageStructureManager( [
        'pages' => $pages,
        'menu_structures' => [],
        'option_prefix' => 'hws_menu_tool_page_',
        'assignment_getter' => __NAMESPACE__ . '\\hws_menu_tools_assigned_page_id',
        'assignment_statuses' => [ 'publish' ],
        'select_post_statuses' => [ 'publish' ],
        'managed_meta_key' => '_hws_menu_tools_managed_page',
        'managed_key_meta_key' => '_hws_menu_tools_page_key',
        'logger' => static function( string $message ): void {
            if ( function_exists( __NAMESPACE__ . '\\write_log' ) ) {
                write_log( '[HWS Menu Tools] ' . $message );
            }
        },
    ] );
}

function hws_menu_tools_actions(): array {
    return [
        'create_navigation_menu' => 'hws_menu_tools_create_navigation_menu',
        'delete_navigation_menu' => 'hws_menu_tools_delete_navigation_menu',
        'create_menu_item' => 'hws_menu_tools_create_menu_item',
        'attach_page_to_menu_item' => 'hws_menu_tools_attach_page_to_menu_item',
        'attach_menu_structure' => 'hws_menu_tools_attach_menu_structure',
        'menu_inventory' => 'hws_menu_tools_menu_inventory',
    ];
}

function hws_register_menu_tools_ajax(): void {
    if ( ! class_exists( SiteStructureAjaxController::class ) || ! class_exists( PageStructureManager::class ) ) {
        return;
    }
    ( new SiteStructureAjaxController( hws_menu_tools_manager(), [
        'capability' => Config::$settings_page_capability,
        'nonce_action' => HWS_AJAX_NONCE,
        'nonce_field' => 'nonce',
        'logger' => static function( $error ): void {
            $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
            if ( function_exists( __NAMESPACE__ . '\\write_log' ) ) {
                write_log( '[HWS Menu Tools AJAX] ' . $message, true );
            }
        },
        'actions' => hws_menu_tools_actions(),
    ] ) )->register();
}
add_action( 'init', __NAMESPACE__ . '\\hws_register_menu_tools_ajax' );

function display_settings_menu_tools(): void {
    echo '<div class="hws-menu-tools-intro"><h2>WordPress Menu Builder</h2><p>Create WordPress menus, add custom URL menu items, attach published pages under existing menu items, and audit the current menu item structure. This tab uses Hexa WordPress Plugin Core SiteStructure menu tools.</p></div>';
    if ( ! class_exists( SiteStructureRenderer::class ) || ! class_exists( PageStructureManager::class ) ) {
        echo '<div class="notice notice-error"><p>Hexa WordPress Plugin Core SiteStructure tools are not loaded.</p></div>';
        return;
    }
    echo ( new SiteStructureRenderer( hws_menu_tools_manager(), [
        'instance_id' => 'hws-menu-tools-site-structure',
        'nonce' => wp_create_nonce( HWS_AJAX_NONCE ),
        'card_class' => 'hws-menu-tools-card',
        'table_class' => 'widefat striped hws-menu-tools-table',
        'show_pages' => false,
        'show_menus' => true,
        'actions' => hws_menu_tools_actions(),
        'labels' => [
            'menus_title' => 'Navigation Menus',
            'menus_heading' => 'WordPress Menu Items',
            'menus_description' => 'Create custom URL menu items, attach a specific published page beneath an existing menu item, and review the current WordPress menu item structure.',
        ],
    ] ) )->render();
}
