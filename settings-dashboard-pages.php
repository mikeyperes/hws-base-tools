<?php namespace hws_base_tools;

use Hexa\PluginCore\SiteStructure\PageStructureManager;
use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;

defined( "ABSPATH" ) || exit;

function hws_pages_page_definitions(): array {
    return [
        "terms" => [
            "title" => "Terms of Use",
            "slug" => "terms-of-use",
            "description" => "Site-level terms governing website access, content use, submissions, disclaimers, and limitations.",
            "template" => true,
        ],
        "privacy" => [
            "title" => "Privacy Policy",
            "slug" => "privacy-policy",
            "description" => "Site-level privacy practices covering analytics, cookies, forms, advertising, data rights, and contact paths.",
            "template" => true,
        ],
        "brand_assets" => [
            "title" => "Brand Assets",
            "slug" => "brand-assets",
            "description" => "Public brand asset and media kit page for logos, marks, screenshots, brand colors, and usage rules.",
            "template" => true,
        ],
        "headquarters" => [
            "title" => "Headquarters",
            "slug" => "headquarters",
            "description" => "Public headquarters and operating-location page for organization transparency and schema support.",
            "template" => true,
        ],
        "contact" => [
            "title" => "Contact",
            "slug" => "contact",
            "description" => "Primary public contact page for general, editorial, advertising, legal, privacy, and support inquiries.",
            "template" => true,
        ],
        "faqs" => [
            "title" => "FAQs",
            "slug" => "faqs",
            "description" => "Site-level FAQ page for common reader, account, contact, privacy, and publication questions.",
            "template" => true,
        ],
    ];
}

function hws_pages_menu_structures(): array {
    return [
        "footer" => [
            "title" => "Footer Site Pages",
            "description" => "Shared site pages commonly linked from the footer.",
            "page_keys" => [ "contact", "faqs", "brand_assets", "headquarters", "privacy", "terms" ],
        ],
        "legal" => [
            "title" => "Legal Pages",
            "description" => "Compliance and policy pages for legal or sub-footer navigation.",
            "page_keys" => [ "privacy", "terms" ],
        ],
        "support" => [
            "title" => "Support Pages",
            "description" => "Reader support and organization-information pages.",
            "page_keys" => [ "contact", "faqs", "headquarters" ],
        ],
    ];
}

function hws_pages_default_templates(): array {
    $site_name = esc_html( get_bloginfo( "name" ) ?: "this website" );
    $site_url = esc_html( home_url( "/" ) );

    return [
        "terms" => "<h2>Terms of Use</h2>\n\n<p>These Terms of Use govern access to and use of {$site_name} at <a href=\"{$site_url}\">{$site_url}</a>.</p>\n\n<h3>Use of the website</h3>\n<p>Use this page to define permitted use of website content, user conduct, submissions, intellectual property, disclaimers, and limitations of liability. Legal counsel should review this page before publishing.</p>",
        "privacy" => "<h2>Privacy Policy</h2>\n\n<p>This Privacy Policy explains how {$site_name} handles information collected through <a href=\"{$site_url}\">{$site_url}</a>.</p>\n\n<h3>Information we collect</h3>\n<p>Document analytics, cookies, forms, newsletter tools, advertising partners, retention, user rights, and the privacy contact path. Legal counsel should review this page before publishing.</p>",
        "brand_assets" => "<h2>Brand Assets</h2>\n\n<p>This page provides approved brand assets for {$site_name}, including logos, marks, colors, screenshots, and usage guidance.</p>\n\n<p>[site_logo key=\"logo\" size=\"medium\"]</p>\n\n<h3>Usage guidelines</h3>\n<ul><li>Use approved logo files without alteration.</li><li>Do not imply endorsement without written approval.</li><li>Contact the publication before using assets in campaigns, press kits, or paid placements.</li></ul>",
        "headquarters" => "<h2>Headquarters</h2>\n\n<p>This page identifies the public headquarters, operating location, or mailing address context for {$site_name}.</p>\n\n<h3>Location context</h3>\n<p>State whether the organization operates locally, nationally, globally, remotely, or from a public office. Include only approved public address information.</p>",
        "contact" => "<h2>Contact</h2>\n\n<p>Use this page to list approved public contact paths for {$site_name}.</p>\n\n<h3>Departments</h3>\n<ul><li>General inquiries</li><li>Editorial and corrections</li><li>Advertising and partnerships</li><li>Legal, privacy, and rights requests</li><li>Reader support</li></ul>",
        "faqs" => "<h2>FAQs</h2>\n\n<h3>How do I contact the team?</h3>\n<p>Use the Contact page for general, editorial, legal, advertising, and support inquiries.</p>\n\n<h3>How are corrections handled?</h3>\n<p>Explain the correction request path and review workflow here.</p>\n\n<h3>Where can I find brand assets?</h3>\n<p>Link to the Brand Assets page and describe approved usage.</p>",
    ];
}

function hws_pages_actions(): array {
    return [
        "assign_page" => "hws_site_pages_assign_page",
        "create_page" => "hws_site_pages_create_page",
        "delete_page" => "hws_site_pages_delete_page",
        "create_navigation_menu" => "hws_site_pages_create_navigation_menu",
        "delete_navigation_menu" => "hws_site_pages_delete_navigation_menu",
        "create_menu_item" => "hws_site_pages_create_menu_item",
        "attach_page_to_menu_item" => "hws_site_pages_attach_page_to_menu_item",
        "attach_menu_structure" => "hws_site_pages_attach_menu_structure",
        "menu_inventory" => "hws_site_pages_menu_inventory",
        "save_template" => "hws_site_pages_save_template",
        "apply_template" => "hws_site_pages_apply_template",
        "page_details" => "hws_site_pages_page_details",
        "update_page_slug" => "hws_site_pages_update_page_slug",
    ];
}

function hws_pages_manager(): PageStructureManager {
    return new PageStructureManager( [
        "pages" => hws_pages_page_definitions(),
        "menu_structures" => hws_pages_menu_structures(),
        "option_prefix" => "hws_site_page_assignment_",
        "template_option_prefix" => "hws_site_page_template_",
        "managed_meta_key" => "_hws_site_managed_page",
        "managed_key_meta_key" => "_hws_site_page_key",
        "created_page_status" => "draft",
        "select_post_statuses" => [ "publish", "draft", "private", "pending" ],
        "assignment_statuses" => [ "publish", "draft", "private", "pending" ],
        "reuse_existing_pages" => true,
        "default_templates" => hws_pages_default_templates(),
        "page_detail_renderer" => __NAMESPACE__ . "\\hws_pages_page_detail_html",
        "logger" => static function( string $message ): void {
            if ( function_exists( __NAMESPACE__ . "\\write_log" ) ) {
                write_log( "[HWS Pages] " . $message );
            }
        },
        "menu_guess_terms" => [
            "footer" => [ "footer", "bottom", "main" ],
            "legal" => [ "legal", "policy", "privacy", "terms", "sub-footer", "subfooter" ],
            "support" => [ "support", "help", "footer", "contact" ],
        ],
    ] );
}

function hws_register_pages_ajax(): void {
    if ( ! class_exists( SiteStructureAjaxController::class ) || ! class_exists( PageStructureManager::class ) ) {
        return;
    }

    ( new SiteStructureAjaxController( hws_pages_manager(), [
        "capability" => Config::$settings_page_capability,
        "nonce_action" => HWS_AJAX_NONCE,
        "nonce_field" => "nonce",
        "actions" => hws_pages_actions(),
        "logger" => static function( $error ): void {
            $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
            if ( function_exists( __NAMESPACE__ . "\\write_log" ) ) {
                write_log( "[HWS Pages AJAX] " . $message, true );
            }
        },
    ] ) )->register();
}
add_action( "init", __NAMESPACE__ . "\\hws_register_pages_ajax" );

function hws_pages_admin_url(): string {
    return admin_url( "options-general.php?page=" . Config::$settings_page_slug . "&tab=pages" );
}

function hws_pages_page_detail_html( int $page_id ): string {
    $post = get_post( $page_id );
    if ( ! $post instanceof \WP_Post || "page" !== $post->post_type ) {
        return "";
    }

    $status = (string) $post->post_status;
    $status_obj = get_post_status_object( $status );
    $status_label = $status_obj ? (string) $status_obj->label : ucfirst( $status );
    $edit_url = (string) get_edit_post_link( $page_id, "raw" );
    $view_url = (string) get_permalink( $page_id );
    $modified = get_the_modified_date( "M j, Y g:i a", $page_id );
    $author = get_the_author_meta( "display_name", (int) $post->post_author );

    return "<div class=\"hws-pages-detail\" style=\"display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 12px;border:1px solid #dbeafe;background:#eff6ff;border-radius:8px;\">"
        . "<strong>Owned by HWS Base Tools</strong>"
        . "<span>Status: <code>" . esc_html( $status_label ) . "</code></span>"
        . "<span>Author: <code>" . esc_html( $author ?: "unknown" ) . "</code></span>"
        . "<span>Modified: <code>" . esc_html( $modified ) . "</code></span>"
        . "<a class=\"button button-small\" target=\"_blank\" rel=\"noopener\" href=\"" . esc_url( $edit_url ) . "\">Edit</a>"
        . "<a class=\"button button-small\" target=\"_blank\" rel=\"noopener\" href=\"" . esc_url( $view_url ) . "\">View</a>"
        . "</div>";
}

function display_settings_pages(): void {
    echo "<div class=\"hws-pages-intro\"><h2>Site Pages</h2><p>Assign, create, template, and attach shared site-level pages owned by HWS Base Tools. Publication-specific pages remain in SMP Publication Integration.</p></div>";

    if ( ! class_exists( SiteStructureRenderer::class ) || ! class_exists( PageStructureManager::class ) ) {
        echo "<div class=\"notice notice-error\"><p>Hexa WordPress Plugin Core SiteStructure tools are not loaded.</p></div>";
        return;
    }

    echo ( new SiteStructureRenderer( hws_pages_manager(), [
        "instance_id" => "hws-site-pages",
        "nonce" => wp_create_nonce( HWS_AJAX_NONCE ),
        "card_class" => "hws-pages-card",
        "table_class" => "widefat striped hws-pages-table",
        "enable_templates" => true,
        "enable_template_editors" => true,
        "template_editor_media_buttons" => false,
        "template_editor_rows" => 8,
        "show_page_details" => true,
        "show_pages" => true,
        "show_menus" => true,
        "actions" => hws_pages_actions(),
        "labels" => [
            "pages_title" => "Shared Site Pages",
            "pages_heading" => "HWS-owned page assignments",
            "pages_description" => "Create draft pages or assign existing pages for Terms of Use, Privacy Policy, Brand Assets, Headquarters, Contact, and FAQs.",
            "menus_title" => "Site Page Navigation",
            "menus_heading" => "Attach shared page groups to menus",
            "menus_description" => "Create menus, add custom menu items, attach individual assigned pages, or attach HWS site page blueprints.",
        ],
    ] ) )->render();
}
