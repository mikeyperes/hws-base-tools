<?php
namespace hws_base_tools;

defined( 'ABSPATH' ) || exit;

function hws_ct_display_plugin_info() {
    if (
        ! class_exists( '\Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer' )
        || ! function_exists( __NAMESPACE__ . '\\hws_get_hexa_plugin_core_updater_config' )
    ) {
        echo '<div class="notice notice-error"><p>Hexa Plugin Core updater is not loaded for HWS Base Tools.</p></div>';
        return;
    }

    $renderer = new \Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer( hws_get_hexa_plugin_core_updater_config() );
    $renderer->render();

    if (
        ! class_exists( '\Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer' )
        || ! function_exists( __NAMESPACE__ . '\\hws_get_hexa_plugin_core_package_config' )
    ) {
        echo '<div class="notice notice-error"><p>Hexa WordPress Plugin Core package updater is not loaded.</p></div>';
        return;
    }

    $core_renderer = new \Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer( hws_get_hexa_plugin_core_package_config() );
    $core_renderer->render();
}
