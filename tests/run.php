<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$failures = [];
$passes = 0;

function expect_true( bool $condition, string $message ): void {
    global $failures, $passes;

    if ( $condition ) {
        ++$passes;
        echo "PASS {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL {$message}\n";
}

function source( string $relative ): string {
    global $root;
    $contents = file_get_contents( $root . '/' . $relative );

    return false === $contents ? '' : $contents;
}

$options = [];

function get_option( string $key, mixed $default = false ): mixed {
    global $options;

    return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    global $options;
    $changed = ! array_key_exists( $key, $options ) || $options[ $key ] !== $value;
    $options[ $key ] = $value;

    return $changed;
}

function wp_generate_password(): string {
    return 'generated-test-secret-1234567890';
}

function wp_salt(): string {
    return 'hws-test-only-salt';
}

function sanitize_key( string $value ): string {
    return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}

function sanitize_hex_color( mixed $value ): ?string {
    $value = trim( (string) $value );

    return preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ? $value : null;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    return $value;
}

require_once $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabDefinition.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabRegistry.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/SearchDisplay/SearchDisplayRenderer.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/SearchQuery/SearchQueryConfiguration.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/SearchQuery/SearchTermParser.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/SearchQuery/SearchQueryEngine.php';
require_once $root . '/lib/hexa-wordpress-plugin-core/src/SearchQuery/JetEngineSearchAdapter.php';
require_once $root . '/src/PluginRuntime/PluginMetadata.php';
require_once $root . '/src/AdminDashboard/DashboardModuleDefinition.php';
require_once $root . '/src/AdminDashboard/DashboardRegistry.php';
require_once $root . '/src/FeatureCatalog/FeatureValueResolver.php';
require_once $root . '/src/FrontendContent/SearchDisplayFeature.php';
require_once $root . '/src/FrontendContent/SearchQueryFeature.php';
require_once $root . '/src/Security/SecretStore.php';
require_once $root . '/src/Security/RemoteActionPolicy.php';

$metadata = HWS\BaseTools\PluginRuntime\PluginMetadata::class;
$header = source( 'hws-base-tools.php' );
expect_true( str_contains( $header, 'Version: ' . $metadata::VERSION ), 'plugin header and metadata versions match' );
expect_true( str_contains( $header, 'Requires at least: ' . $metadata::REQUIRES_WORDPRESS ), 'WordPress requirement is declared' );
expect_true( str_contains( $header, 'Requires PHP: ' . $metadata::REQUIRES_PHP ), 'PHP requirement is declared' );
expect_true( substr_count( source( 'initialization.php' ), "\n" ) < 30, 'legacy initialization entry stays thin' );
expect_true( str_contains( $header, "require_once \$hexa_plugin_core_root . '/bootstrap.php'" ), 'canonical entry registers the shared Core package runtime' );
expect_true( ! str_contains( $header, 'Hexa\\PluginCore\\' ), 'canonical entry does not load a Core class before package selection' );

$registry = HWS\BaseTools\AdminDashboard\DashboardRegistry::instance();
$tabs = $registry->navigation_tabs();
$tab_ids = array_keys( $tabs );
expect_true( array_slice( $tab_ids, 0, 2 ) === [ 'overview', 'quick-start' ], 'Quick Start is the second dashboard tab' );
expect_true( $registry->normalize( 'getting-started-checklist' ) === 'quick-start', 'legacy Quick Start route remains compatible' );
expect_true( isset( $tabs['snippets'] ) && $tabs['snippets']->deprecated, 'Legacy Snippets remains visible and deprecated' );
expect_true( isset( $tabs['shortcodes'] ), 'HWS Shortcodes tab is registered through the dashboard registry' );
expect_true( isset( $tabs['search'] ), 'HWS Search tab is registered through the dashboard registry' );
expect_true( isset( $tabs['brand-templates'] ), 'HWS Brand Templates tab is registered through the dashboard registry' );
expect_true( isset( $tabs['mail-authentication'] ), 'HWS Mail Authentication tab is registered through the dashboard registry' );
expect_true(
    $registry->implementation_files_for_ajax_action( 'hws_plugin_library_install_activate' ) === [ 'settings-dashboard-check-plugins.php' ]
    && $registry->implementation_files_for_ajax_action( 'hws_plugin_status_install_activate' ) === [ 'settings-dashboard-check-plugins.php' ]
    && $registry->implementation_files_for_ajax_action( 'hws_unrecommended_plugins_delete' ) === [ 'settings-dashboard-check-plugins.php' ],
    'plugin inventory AJAX actions load their controller module before dispatch'
);
$groups = $registry->navigation_groups();
$grouped_tab_ids = [];
foreach ( $groups as $group ) {
    $grouped_tab_ids = array_merge( $grouped_tab_ids, $group['tabs'] ?? [] );
}
$sorted_grouped_tab_ids = $grouped_tab_ids;
$sorted_tab_ids = $tab_ids;
sort( $sorted_grouped_tab_ids );
sort( $sorted_tab_ids );
expect_true(
    count( $grouped_tab_ids ) === count( array_unique( $grouped_tab_ids ) )
    && $sorted_grouped_tab_ids === $sorted_tab_ids,
    'grouped sidebar assigns every HWS tab exactly once'
);
expect_true( count( $groups ) === 6 && ( $groups[0]['label'] ?? '' ) === 'Overview', 'HWS tabs use six clear sidebar groups' );
$site_brand_groups = array_values( array_filter( $groups, static fn( array $group ): bool => 'Site & Brand' === ( $group['label'] ?? '' ) ) );
expect_true(
    1 === count( $site_brand_groups ) && in_array( 'brand-templates', $site_brand_groups[0]['tabs'] ?? [], true ),
    'Brand Templates has one canonical location in the Site & Brand sidebar group'
);
$security_groups = array_values( array_filter( $groups, static fn( array $group ): bool => 'Security' === ( $group['label'] ?? '' ) ) );
expect_true(
    1 === count( $security_groups ) && ( $security_groups[0]['tabs'] ?? [] ) === [ 'masked-login' ],
    'Masked Login has one dedicated Security sidebar location'
);
expect_true(
    $registry->implementation_files_for_tab( 'sitemaps' ) === [ 'settings-dashboard-site-profile.php', 'settings-dashboard-sitemaps.php' ],
    'Sitemaps tab loads its Site Profile dependency first'
);
expect_true(
    $registry->implementation_files_for_ajax_action( 'hws_sitemap_scan' ) === [ 'settings-dashboard-site-profile.php', 'settings-dashboard-sitemaps.php' ],
    'Sitemap AJAX actions load their Site Profile dependency first'
);
expect_true(
    $registry->implementation_files_for_tab( 'footer-text' ) === [ 'settings-dashboard-website-types.php', 'settings-dashboard-footer-text.php' ],
    'Footer Text loads its shared toggle dependency before rendering'
);
expect_true(
    $registry->implementation_files_for_tab( 'features' ) === [ 'settings-dashboard-website-types.php', 'settings-dashboard-features.php' ],
    'Features loads its shared toggle dependency before rendering'
);
expect_true(
    $registry->implementation_files_for_tab( 'search' ) === [ 'settings-dashboard-search.php' ]
    && $registry->implementation_files_for_ajax_action( 'hws_search_display_save' ) === [ 'settings-dashboard-search.php' ],
    'Search tab and its AJAX save action load the focused Search Display adapter'
);
expect_true(
    $registry->implementation_files_for_ajax_action( 'hws_search_behavior_save' ) === [ 'settings-dashboard-search.php' ],
    'Search Behavior AJAX saves load the focused Search tab adapter'
);
expect_true(
    $registry->implementation_files_for_tab( 'mail-authentication' ) === [ 'settings-dashboard-mail-authentication.php' ]
    && $registry->implementation_files_for_ajax_action( 'hws_mail_authentication_run_test' ) === [ 'settings-dashboard-mail-authentication.php' ],
    'Mail Authentication tab and AJAX action load the focused adapter'
);

$mail_auth_source = source( 'src/MailAuthentication/Smtp2goAuthenticationService.php' );
$mail_admin_source = source( 'src/MailAuthentication/MailAuthenticationAdmin.php' );
$getting_started_source = source( 'src/AdminDashboard/legacy-getting-started.php' );
expect_true(
    str_contains( $mail_auth_source, "private const VALIDATION_URL = 'https://api.smtp2go.com/v3/api_keys/view'" )
    && str_contains( $mail_auth_source, 'private function settings_step' )
    && str_contains( $mail_auth_source, 'private function api_key_step' )
    && str_contains( $mail_auth_source, 'private function test_email_step' ),
    'SMTP2GO authentication service owns the ordered settings, API-key, and test-email stages'
);
expect_true(
    str_contains( $mail_admin_source, 'DynamicButton::render' )
    && str_contains( $mail_admin_source, 'CoreUi::collapsible' )
    && str_contains( $mail_admin_source, 'Smtp2goAuthenticationService() )->run' ),
    'Mail Authentication tab uses Hexa Core UI and the shared SMTP2GO service'
);
expect_true(
    str_contains( $getting_started_source, "'test_smtp2go_authentication'" )
    && str_contains( $getting_started_source, '( new Smtp2goAuthenticationService() )->run( $recipient )' )
    && str_contains( $getting_started_source, 'No SMTP2GO API validation request was sent because the preceding stage failed.' )
    && str_contains( $getting_started_source, 'No test message was sent because the preceding stage failed.' ),
    'Quick Start calls the same full SMTP2GO authentication service'
);

$dashboard_source = source( 'src/AdminDashboard/legacy-dashboard.php' );
$dashboard_css = source( 'assets/admin/dashboard.css' );
expect_true(
    str_contains( $dashboard_source, "'layout'          => 'sidebar'" )
    && str_contains( $dashboard_source, "'sidebar_collapsible' => true" )
    && str_contains( $dashboard_source, "'sidebar_persist'     => true" ),
    'HWS dashboard uses the Hexa Core grouped, collapsible, persistent sidebar shell'
);
expect_true( str_contains( $dashboard_source, "'sidebar_identity'=> hws_dashboard_sidebar_identity()" ), 'HWS sidebar displays plugin and Core version identity' );
expect_true(
    str_contains( $dashboard_source, "get_site_transient( \$plugin_config->cache_key( 'github_version' ) )" )
    && ! str_contains( $dashboard_source, 'new PluginUpdateStatus' )
    && ! str_contains( $dashboard_source, 'new CorePackageStatus' ),
    'HWS sidebar identity uses only local and cached version data'
);
expect_true(
    ! in_array( 'settings-dashboard-plugin-info.php', $registry->implementation_files_for_tab( 'overview' ), true )
    && in_array( 'settings-dashboard-plugin-info.php', $registry->implementation_files_for_tab( 'update-center' ), true )
    && str_contains( source( 'src/Security/legacy-update-center.php' ), 'hws_ct_display_plugin_info();' )
    && ! str_contains( $dashboard_source, 'hws_ct_display_plugin_info();' ),
    'Git updater panels load in Update Center without blocking the default Overview'
);
expect_true(
    str_contains( $dashboard_css, '#hws-base-tools .hpc-host-tabs-shell.is-bar > .hpc-host-tabs' )
    && str_contains( $dashboard_css, '#hws-base-tools .hpc-host-rail .hpc-host-tabs' )
    && str_contains( $dashboard_css, 'border-left-color: #4055df' )
    && str_contains( $dashboard_css, '#hws-base-tools .hpc-host-tabs-shell.is-sidebar.is-sidebar-collapsed' )
    && str_contains( $dashboard_css, 'grid-template-columns: 44px minmax(0, 1fr)' ),
    'HWS preserves the flat titled Core sidebar instead of overriding it with wrapping tab cards'
);
expect_true(
    str_contains( $dashboard_source, 'class="hws-going-live-grid"' )
    && str_contains( $dashboard_css, 'grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr))' ),
    'Overview checklist grid remains responsive without horizontal overflow'
);

$team_directory_source = source( 'src/TeamMembers/TeamMemberDirectory.php' );
$team_feature_source = source( 'src/TeamMembers/TeamMemberFeature.php' );
expect_true(
    str_contains( $team_directory_source, "public const SHORTCODE = 'hws_team_members'" )
    && str_contains( $team_directory_source, "public const POST_TYPE = 'team-member'" ),
    'HWS owns the Team Member directory shortcode and canonical post type contract'
);
expect_true(
    str_contains( $team_directory_source, "'portrait_grid'" )
    && str_contains( $team_directory_source, "'editorial_list'" )
    && str_contains( $team_directory_source, "'compact_directory'" ),
    'HWS Team Member directory exposes exactly the three requested template identifiers'
);
expect_true(
    str_contains( $team_feature_source, 'Prerequisite check' )
    && str_contains( $team_feature_source, 'data-hws-team-template' )
    && str_contains( $team_feature_source, 'Copy-ready shortcodes' ),
    'HWS Features UI includes readiness, visual template selection, and shortcode documentation'
);
expect_true(
    str_contains( source( 'src/FeatureCatalog/ShortcodeCatalog.php' ), "'shortcode' => '[hws_team_members]'" ),
    'HWS Shortcodes catalog documents the owned Team Member shortcode'
);
$shared_content_types = source( 'src/ContentTypes/SharedContentTypes.php' );
expect_true(
    str_contains( $shared_content_types, "public const ORGANIZATION = 'organization'" )
    && str_contains( $shared_content_types, "public const TESTIMONIAL = 'testimonial'" )
    && str_contains( $shared_content_types, "public const TEAM_MEMBER = 'team-member'" )
    && str_contains( $shared_content_types, "public const SERVICES = 'services'" )
    && ! str_contains( $shared_content_types, "KNOWLEDGE_BASE" ),
    'HWS owns Organization, Testimonial, Team Member, and Services while SMP owns Knowledge Base'
);
expect_true(
    str_contains( source( 'src/PluginRuntime/CoreIntegration.php' ), 'SharedContentTypes::registry()' )
    && str_contains( source( 'src/PluginRuntime/CoreIntegration.php' ), 'SharedAcfStructures::registry()' )
    && ! str_contains( source( 'src/LegacyCompatibility/legacy-runtime.php' ), 'register-post-type-' ),
    'shared post types and ACF structures register only through Hexa WP Core'
);
$primary_entity_source = source( 'src/SiteProfile/PrimaryEntityIntegration.php' );
expect_true(
    str_contains( $primary_entity_source, "'wordpress_user' => [ 'label' => 'WordPress Author', 'kind' => 'user'" )
    && ! str_contains( $primary_entity_source, "'verified_profile' =>" )
    && ! str_contains( $primary_entity_source, "'organization' => [ 'label'" ),
    'HWS owns only the optional WordPress author source, not Verified Profile or Organization records'
);
expect_true(
    str_contains( $primary_entity_source, "'personal_website' => 'person'" )
    && str_contains( $primary_entity_source, "'company_website' => 'organization'" )
    && str_contains( $primary_entity_source, "'news_outlet' => 'publication'" )
    && str_contains( $primary_entity_source, "'allow_entity_type_selection' => false" )
    && str_contains( $primary_entity_source, "'allow_empty_site_type' => true" )
    && str_contains( $primary_entity_source, "'site_type_placeholder' => 'Select website type'" ),
    'HWS derives a read-only semantic type from website type'
);
$primary_entity_renderer = source( 'lib/hexa-wordpress-plugin-core/src/EntitySources/PrimaryEntityRenderer.php' );
$core_ui_source = source( 'lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php' );
$entity_profile_renderer = source( 'lib/hexa-wordpress-plugin-core/src/EntitySources/EntityProfileCardRenderer.php' );
$entity_inventory_renderer = source( 'lib/hexa-wordpress-plugin-core/src/EntitySources/EntityFieldInventoryRenderer.php' );
expect_true(
    ! str_contains( $primary_entity_renderer, 'hpc-primary-save' )
    && str_contains( $primary_entity_renderer, "document.addEventListener('hexa-search-selected'" )
    && str_contains( $primary_entity_renderer, "save(root,'selection')" )
    && str_contains( $primary_entity_renderer, 'preview_html' ),
    'primary author selection saves automatically and loads its profile without a manual save button'
);
expect_true(
    str_contains( $primary_entity_renderer, 'No primary author assigned' )
    && str_contains( $primary_entity_renderer, 'site_type_placeholder' )
    && str_contains( $core_ui_source, '.hpc-smart-search-selected[hidden]{display:none!important}' ),
    'unconfigured website and primary-author states are intentional and do not show an empty selected strip'
);
expect_true(
    str_contains( $entity_profile_renderer, '<dl class="hpc-entity-socials">' )
    && str_contains( $entity_profile_renderer, 'esc_html( $url )' )
    && str_contains( $primary_entity_renderer, '.hpc-entity-socials>div' ),
    'primary author social links use Core rows with complete visible URLs'
);
expect_true(
    str_contains( $primary_entity_source, "'show_field_inventory' => false" )
    && str_contains( source( 'src/AdminDashboard/ContentTypesTab.php' ), 'EntityFieldInventoryRenderer' )
    && str_contains( $entity_inventory_renderer, 'All available WordPress and ACF fields' ),
    'primary author field inventory is rendered by Core in Custom Post Types instead of Website & Primary Entity'
);
$primary_author_image = source( 'src/BrandAssets/PrimaryAuthorImage.php' );
expect_true(
    str_contains( $primary_author_image, 'PrimaryEntityIntegration::manager()->resolve()' )
    && str_contains( $primary_author_image, "get_field( 'profile_photo'" )
    && str_contains( $primary_author_image, 'get_avatar_url' )
    && str_contains( $primary_author_image, "'edit_url'" )
    && str_contains( $primary_author_image, "'view_url'" ),
    'favicon profile-image source resolves the canonical primary author and both profile destinations'
);
expect_true(
    str_contains( $dashboard_source, 'hws-primary-author-favicon-preview' )
    && str_contains( $dashboard_source, 'Use Primary Author Profile Image' )
    && str_contains( $dashboard_source, "CoreUi::external_link( \$primary_author['edit_url'], 'Open Backend Profile' )" )
    && str_contains( $dashboard_source, "CoreUi::external_link( \$primary_author['view_url'], 'Open Frontend Author Page' )" )
    && str_contains( $dashboard_source, "source: 'primary_user'" )
    && str_contains( $dashboard_source, "\$source === 'primary_user'" )
    && str_contains( $dashboard_source, 'hws_create_square_brand_asset_from_path' ),
    'favicon panel links to the primary author backend and frontend and applies the image through the existing PNG and ICO workflow'
);
$ui_cleanup_source = source( 'src/UiCleanup/legacy-ui-cleanup.php' );
expect_true(
    str_contains( $ui_cleanup_source, "'hide_woocommerce_customer_billing_info'" )
    && str_contains( $ui_cleanup_source, '#fieldset-billing, #fieldset-shipping' )
    && str_contains( $ui_cleanup_source, "[ 'Customer billing address', 'Customer shipping address' ]" )
    && str_contains( $ui_cleanup_source, "add_filter( 'woocommerce_customer_meta_fields'" )
    && str_contains( $ui_cleanup_source, "[ 'profile.php', 'user-edit.php' ]" ),
    'WooCommerce billing cleanup removes complete billing and shipping profile sections'
);
expect_true(
    str_contains( source( 'src/PluginRuntime/CoreIntegration.php' ), "hexa_plugin_core_register_integration_tests" )
    && file_exists( $root . '/src/Diagnostics/IntegrationTests.php' )
    && file_exists( $root . '/lib/hexa-wordpress-plugin-core/src/IntegrationTests/TestRunner.php' ),
    'HWS registers plugin-specific checks with the bundled Core integration-test framework'
);
expect_true(
    ! glob( $root . '/src/AcfFields/LegacySmp/register-post-type-*.php' )
    && ! glob( $root . '/smp-core/register-post-type-*.php' ),
    'redundant legacy post-type registrars are removed'
);
expect_true(
    str_contains( source( 'src/AdminDashboard/ContentTypesTab.php' ), 'ContentTypeRenderer' )
    && str_contains( source( 'src/AdminDashboard/ContentTypesTab.php' ), 'AcfFieldGroupRenderer' )
    && str_contains( source( 'src/AdminDashboard/ContentTypesTab.php' ), 'EntityFieldInventoryRenderer' )
    && str_contains( source( 'src/AcfFields/SharedAcfStructures.php' ), "'legacy_option'" ),
    'CPT and ACF controls use the generic Core UI while retaining legacy option state'
);
$search_settings = HWS\BaseTools\FrontendContent\SearchDisplayFeature::sanitize_settings(
    [
        'style'       => 'overlay',
        'accent'      => '#2f6df6',
        'placeholder' => ' Find stories ',
    ]
);
expect_true(
    $search_settings === [ 'style' => 'overlay', 'accent' => '#2f6df6', 'placeholder' => 'Find stories' ],
    'HWS Search Display sanitizes the saved template, accent, and placeholder contract'
);
$search_behavior = HWS\BaseTools\FrontendContent\SearchQueryFeature::sanitize_settings(
    [
        'enabled'          => '1',
        'scope'            => 'shortcode',
        'term_logic'       => 'any',
        'word_matching'    => 'prefix',
        'post_types'       => [ 'post', 'book', 'private' ],
        'fields'           => [ 'title', 'slug' ],
        'taxonomies'       => [ 'category', 'private_taxonomy' ],
        'authors'          => '1',
        'custom_fields'    => '_sku, publication_name',
        'results_per_page' => '18',
        'orderby'          => 'newest',
    ],
    [ 'post' => 'Posts', 'page' => 'Pages', 'book' => 'Books' ],
    [ 'category' => 'Categories' ]
);
expect_true(
    $search_behavior['enabled']
    && $search_behavior['term_logic'] === 'any'
    && $search_behavior['word_matching'] === 'prefix'
    && $search_behavior['post_types'] === [ 'post', 'book' ]
    && $search_behavior['fields'] === [ 'title', 'slug' ]
    && $search_behavior['taxonomies'] === [ 'category' ]
    && $search_behavior['custom_fields'] === [ '_sku', 'publication_name' ]
    && 18 === $search_behavior['results_per_page'],
    'HWS Search Behavior delegates a bounded post-type, source, matching, and result contract to Hexa Core'
);
expect_true(
    str_contains( source( 'src/FrontendContent/SearchDisplayFeature.php' ), "public const SHORTCODE = 'hexa_search'" )
    && str_contains( source( 'src/FrontendContent/SearchDisplayFeature.php' ), 'SearchDisplayRenderer::render(' )
    && str_contains( source( 'src/FrontendContent/SearchDisplayFeature.php' ), 'SearchQueryFeature::marker_fields()' )
    && str_contains( source( 'src/FrontendContent/search-display-settings.php' ), 'SearchDisplayRenderer::render(' )
    && str_contains( source( 'src/FeatureCatalog/ShortcodeCatalog.php' ), "'shortcode' => '[hexa_search]'" ),
    'HWS frontend shortcode, admin previews, and shortcode catalog share the marked Hexa Core Search Display contract'
);
expect_true(
    str_contains( source( 'src/FrontendContent/search-display-settings.php' ), '<div data-hws-search-form>' )
    && str_contains( source( 'src/FrontendContent/search-display-settings.php' ), 'save.addEventListener(\'click\'' )
    && ! str_contains( source( 'src/FrontendContent/search-display-settings.php' ), '<form data-hws-search-form>' ),
    'Search settings avoid invalid nested forms while Core previews render native search forms'
);
expect_true(
    str_contains( source( 'src/FrontendContent/search-query-settings.php' ), 'hws_search_behavior_save' )
    && str_contains( source( 'src/FrontendContent/search-query-settings.php' ), 'Five-Plugin Search Criteria Audit' )
    && str_contains( source( 'src/FrontendContent/search-query-settings.php' ), 'data-hws-search-query-save' )
    && str_contains( source( 'src/FrontendContent/search-query-settings.php' ), "body.append('post_types[]',value)" )
    && str_contains( source( 'src/FrontendContent/SearchQueryFeature.php' ), 'new SearchQueryEngine(' )
    && str_contains( source( 'src/FrontendContent/SearchQueryFeature.php' ), 'new JetEngineSearchAdapter(' )
    && str_contains( source( 'src/FrontendContent/search-query-settings.php' ), 'Search-template compatibility' )
    && str_contains( source( 'src/FrontendContent/search-display.php' ), 'register_query_engine' ),
    'Search Behavior exposes the five-plugin audit, refresh-free save, reusable Core query engine, and guarded JetEngine template adapter'
);
$getting_started_source = source( 'src/AdminDashboard/legacy-getting-started.php' );
expect_true(
    str_contains( $getting_started_source, "'show_search'          => true" )
    && str_contains( $getting_started_source, "'search_label'         => 'Search Quick Start'" ),
    'Quick Start enables the reusable Hexa Core checklist search'
);
$feature_catalog_source = source( 'src/FeatureCatalog/legacy-features.php' );
expect_true(
    str_contains( $feature_catalog_source, 'CoreUi::collapsible(' )
    && str_contains( $feature_catalog_source, "'open'      => false" )
    && str_contains( $feature_catalog_source, 'CoreUi::render_assets();' ),
    'Every HWS feature renders through a default-collapsed Hexa Core component'
);
$core_ui_source = source( 'lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php' );
expect_true( trim( source( 'lib/hexa-wordpress-plugin-core/VERSION' ) ) === '1.1.4', 'HWS bundles Hexa WordPress Plugin Core 1.1.4' );
expect_true(
    str_contains( source( 'lib/hexa-wordpress-plugin-core/src/GettingStartedChecklist/GettingStartedChecklistRenderer.php' ), 'data-gsc-filter-item' )
    && str_contains( $core_ui_source, 'new MutationObserver(function() { applyFilter(); })' )
    && str_contains( $core_ui_source, '[data-hpc-filter-hidden="1"]{display:none!important}' )
    && str_contains( $core_ui_source, '.hpc-section-title{min-width:0;overflow-wrap:anywhere;white-space:normal}' ),
    'Bundled Core checklist search visibly hides nested nonmatches, refreshes after template changes, and wraps narrow titles'
);
expect_true(
    str_contains( $core_ui_source, '.hpc-host-rail{align-self:start' )
    && str_contains( $core_ui_source, 'padding:7px;position:static}' )
    && ! str_contains( $core_ui_source, 'position:sticky;top:42px' ),
    'Bundled Core sidebar remains in normal document flow instead of sticking to the viewport'
);

$footer_editor_source = source( "src/FrontendContent/legacy-footer-text-settings.php" );
expect_true(
    ! str_contains( $footer_editor_source, "wp_editor(" )
    && str_contains( $footer_editor_source, "<textarea" )
    && str_contains( $footer_editor_source, "wp.editor.initialize(editorId" )
    && str_contains( $footer_editor_source, "hexa-core-host-tab-before-load" ),
    "Footer Text uses the supported dynamic editor lifecycle for AJAX tabs"
);

$secret_store = new HWS\BaseTools\Security\SecretStore( 'hws_master_secret_key' );
expect_true( $secret_store->set( 'a-long-test-secret-value' ), 'master secret can be stored' );
expect_true( get_option( 'hws_master_secret_key' ) !== 'a-long-test-secret-value', 'master secret is not stored as plaintext' );
expect_true( $secret_store->get() === 'a-long-test-secret-value', 'encrypted master secret round-trips' );
expect_true( ! HWS\BaseTools\Security\RemoteActionPolicy::legacy_get_routes_allowed(), 'legacy remote GET actions default to disabled' );
expect_true(
    HWS\BaseTools\FeatureCatalog\FeatureValueResolver::text( static fn() => static fn() => '<b>Lazy feature details</b>' ) === '<b>Lazy feature details</b>',
    'nested lazy feature metadata resolves to text without Closure conversion'
);
expect_true(
    '' === HWS\BaseTools\FeatureCatalog\FeatureValueResolver::text( static fn() => new stdClass() ),
    'non-scalar feature metadata resolves safely to an empty string'
);
$website_types_source = file_get_contents( $root . '/src/SiteProfile/legacy-website-types.php' );
$site_profile_source = file_get_contents( $root . '/src/SiteProfile/legacy-site-profile.php' );
$legacy_snippets_source = file_get_contents( $root . '/src/FeatureCatalog/legacy-snippets.php' );
expect_true(
    str_contains( $website_types_source, "FeatureValueResolver::text( \$snippet['info'] ?? '' )" ),
    'Website Types resolves nested lazy snippet metadata before escaping it'
);
expect_true(
    str_contains( $site_profile_source, "get_option( HWS_SITE_TYPE_OPTION, '' )" )
    && str_contains( $site_profile_source, "?? 'Not selected'" ),
    'new websites keep their website type empty while existing saved classifications remain valid'
);
expect_true(
    str_contains( $legacy_snippets_source, "FeatureValueResolver::text( \$snippet['info'] ?? '' )" ),
    'Legacy Snippets resolves nested lazy snippet metadata before escaping it'
);
expect_true(
    ! preg_match( "/'info'\s*=>\s*display_(?:acf|cpt)_structure\s*\(/", source( 'src/LegacyCompatibility/legacy-runtime.php' ) ),
    'ACF and CPT feature metadata stays deferred until dashboard helpers load'
);

$runtime_options = source( 'src/PluginRuntime/RuntimeOptions.php' );
expect_true( str_contains( $runtime_options, "'hws_update_urls_enabled'       => 'no'" ), 'public update URLs seed disabled' );
expect_true( str_contains( $runtime_options, "'hws_login_urls_enabled'        => 'no'" ), 'public login-control URLs seed disabled' );

$login_mask_source = source( 'src/Security/legacy-login-mask.php' );
$masked_login_dashboard_source = source( 'src/Security/legacy-masked-login.php' );
$masked_login_tab_source = source( 'settings-dashboard-masked-login.php' );
$login_logo_source = source( 'src/FrontendContent/legacy-login-logo.php' );
expect_true(
    ! str_contains( $login_mask_source, 'add_options_page' )
    && str_contains( $login_mask_source, 'register_legacy_redirect_page' )
    && str_contains( $login_mask_source, 'remove_submenu_page' )
    && str_contains( $login_mask_source, 'redirect_legacy_settings_page' )
    && str_contains( $login_mask_source, "'hws-login-masking'" ),
    'Masked Login has no visible standalone page and preserves a hidden redirect route into HWS'
);
expect_true(
    str_contains( $masked_login_tab_source, "require_once __DIR__ . '/snippet-login-mask.php';" )
    && str_contains( $masked_login_dashboard_source, 'hws-ml-settings-form' )
    && str_contains( $masked_login_dashboard_source, 'hws-ml-setting-slug' )
    && str_contains( $masked_login_dashboard_source, 'hws-ml-slug-preview' )
    && str_contains( $masked_login_dashboard_source, 'Login masking is active. Current login URL:' ),
    'Masked Login dashboard loads its implementation, embeds its configuration, and reports status clearly'
);
expect_true(
    str_contains( $masked_login_dashboard_source, 'CoreUi::collapsible' )
    && str_contains( $masked_login_dashboard_source, "'title' => 'Activity Log'" )
    && str_contains( $masked_login_dashboard_source, "'open' => false" )
    && ! str_contains( $masked_login_dashboard_source, '<h3>🔐 Masked Login</h3>' ),
    'Masked Login uses Core sections, keeps its log collapsed, and removes the legacy emoji hero'
);
expect_true(
    str_contains( $login_mask_source, 'self::prepare_enabled_login_branding();' )
    && strpos( $login_mask_source, 'self::prepare_enabled_login_branding();' ) < strpos( $login_mask_source, "require_once ABSPATH . 'wp-login.php';" )
    && str_contains( $login_mask_source, "get_option( 'enable_wp_admin_logo', false )" ),
    'masked login initializes enabled login branding before rendering WordPress login'
);
expect_true(
    str_contains( $login_logo_source, "has_action( 'login_enqueue_scripts', \$render_callback )" )
    && str_contains( $login_logo_source, "has_filter( 'login_headerurl', \$link_callback )" ),
    'custom login branding registers its hooks idempotently'
);
expect_true(
    str_contains( $login_mask_source, 'self::is_wp_toolkit_login_token_request( $o )' )
    && str_contains( $login_mask_source, "empty( \$options['compat_wptoolkit'] )" )
    && str_contains( $login_mask_source, "preg_match( '/\\A[a-f0-9]{64}\\z/i', \$token )" ),
    'masked login permits only a well-formed WP Toolkit token to reach its validator'
);

$force_handler = source( 'src/PluginPolicy/legacy-plugin-checks.php' );
$force_offset = strpos( $force_handler, 'function hws_ct_force_update_check' );
$force_source = false === $force_offset ? '' : substr( $force_handler, $force_offset, 700 );
expect_true( str_contains( $force_source, "current_user_can( 'update_plugins' )" ), 'force update AJAX checks capability' );
expect_true( str_contains( $force_source, 'hws_require_ajax_nonce_or_error()' ), 'force update AJAX verifies nonce' );

$generic_utilities_source = source( 'src/LegacyCompatibility/legacy-generic-functions.php' );
expect_true( ! str_contains( $generic_utilities_source, 'eval(' ), 'generic utilities contain no eval-based aliasing' );
expect_true(
    str_contains( $generic_utilities_source, "str_starts_with( \$host, '/' )" )
    && str_contains( $generic_utilities_source, "str_starts_with( \$host, 'unix://' )" )
    && str_contains( $generic_utilities_source, '$port           = $is_unix_socket ? 0' ),
    'Redis status checks preserve port zero for Unix socket connections'
);
expect_true( ! file_exists( $root . '/register-acf-functionality.php' ), 'unsafe dead short-tag ACF file is removed' );
expect_true( ! file_exists( $root . '/src/AcfFields/LegacySmpUserFields.php' ), 'superseded User - Admin ACF registration is removed' );
expect_true( ! str_contains( source( 'src/AcfFields/AcfModule.php' ), 'hws_enable_legacy_smp_user_fields' ), 'deprecated user-field compatibility option cannot reactivate an old group' );

$profile_fields = source( 'src/AcfFields/user-profile-2025.php' );
$profile_migration = source( 'src/AcfFields/UserProfile2025Migration.php' );
expect_true( str_contains( $profile_fields, "'title' => 'User Profile Fields (2025)'" ), 'canonical 2025 user profile group has an explicit title' );
expect_true( str_contains( $profile_fields, "'name' => 'wellfound'" ), 'canonical profile URLs retain Wellfound separately from The Org' );
expect_true( str_contains( $profile_fields, "'name'              => 'what_best_describe_you'" ), 'canonical 2025 fields retain publication profile classification' );
expect_true( str_contains( $profile_fields, "'name'              => 'team_member'" ), 'canonical 2025 fields retain the team-member compatibility flag' );
expect_true( str_contains( $profile_migration, "'wellfound'  => [ 'well_found_url', 'wellfound_url' ]" ), 'legacy Wellfound values map to the canonical Wellfound field' );
expect_true( str_contains( $profile_migration, "'group_590d64c31db0a'" ), 'deprecated Profile group is covered by canonical migration' );
expect_true( str_contains( $profile_migration, "'group_6419bc02b6e93'" ), 'deprecated Author group is covered by canonical migration' );
expect_true( str_contains( $profile_migration, "'group_65a8b18d98147'" ), 'superseded User - Admin group is covered by canonical migration' );
expect_true( str_contains( $profile_migration, 'acf_remove_local_field_group' ), 'deprecated local user-profile groups are suppressed after canonical activation' );
expect_true( ! str_contains( $profile_fields, "'key' => 'group_590d64c31db0a'" ), 'deprecated Profile field definition is removed from HWS' );
expect_true( ! file_exists( $root . '/src/AcfFields/legacy-migrations.php' ), 'unsafe legacy profile migration UI is removed' );
expect_true( ! file_exists( $root . '/delete-snippet-acf-migration-structures.php' ), 'legacy profile migration loader is removed' );

$root_implementation_files = [];
foreach ( glob( $root . '/*.php' ) ?: [] as $root_php_file ) {
    $filename = basename( $root_php_file );
    if ( in_array( $filename, [ 'hws-base-tools.php', 'initialization.php' ], true ) ) {
        continue;
    }

    $contents = (string) file_get_contents( $root_php_file );
    if ( substr_count( $contents, "\n" ) >= 8 || ! str_contains( $contents, '/src/' ) ) {
        $root_implementation_files[] = $filename;
    }
}
expect_true( [] === $root_implementation_files, 'root PHP compatibility files stay thin and delegate to src' );

$flat_smp_files = [];
foreach ( glob( $root . '/smp-core/*.php' ) ?: [] as $smp_php_file ) {
    $contents = (string) file_get_contents( $smp_php_file );
    $delegates_to_src = str_contains( $contents, '/src/AcfFields/LegacySmp/' )
        || str_contains( $contents, 'register_user_custom_fields_2025' );
    if ( substr_count( $contents, "\n" ) >= 8 || ! $delegates_to_src ) {
        $flat_smp_files[] = basename( $smp_php_file );
    }
}
expect_true( [] === $flat_smp_files, 'legacy SMP file paths are thin ACF-domain compatibility shims' );

$unnamespaced_source_files = [];
$source_iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS )
);
foreach ( $source_iterator as $source_file ) {
    if ( ! $source_file->isFile() || 'php' !== strtolower( $source_file->getExtension() ) ) {
        continue;
    }

    $relative = substr( $source_file->getPathname(), strlen( $root ) + 1 );
    if ( 'src/LegacyCompatibility/legacy-helper.php' === $relative ) {
        continue;
    }

    $contents = (string) file_get_contents( $source_file->getPathname() );
    if ( ! preg_match( '/\bnamespace\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*;/', $contents ) ) {
        $unnamespaced_source_files[] = $relative;
    }
}
expect_true( [] === $unnamespaced_source_files, 'all src PHP files except the intentional global fallback declare a namespace' );
expect_true(
    source( 'HEXA_PLUGIN_CORE_LIBRARY.md' ) === source( 'lib/hexa-wordpress-plugin-core/HEXA_PLUGIN_CORE_LIBRARY.md' ),
    'host Core library guide matches the vendored Core guide'
);
expect_true( is_readable( $root . '/docs/architecture.md' ), 'architecture contract is present' );
expect_true(
    ! str_contains( source( 'src/Maintenance/legacy-log-cleaner.php' ), "dirname( __FILE__ ) . '/hws-base-tools.php'" ),
    'relocated log cleaner uses the canonical plugin root'
);
expect_true(
    str_contains( source( 'src/AdminDashboard/LegacyEventBridge.php' ), 'namespace HWS\\BaseTools\\AdminDashboard;' ),
    'dashboard bridge uses the canonical AdminDashboard namespace'
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
    if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) || str_contains( $file->getPathname(), '/.git/' ) ) {
        continue;
    }

    $command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ) . ' 2>&1';
    exec( $command, $output, $exit_code );
    expect_true( 0 === $exit_code, 'PHP lint: ' . substr( $file->getPathname(), strlen( $root ) + 1 ) );
    $output = [];
}

echo "\n{$passes} checks passed; " . count( $failures ) . " failed.\n";

if ( $failures ) {
    exit( 1 );
}
