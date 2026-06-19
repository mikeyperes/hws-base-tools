<?php
namespace hws_base_tools;

defined( 'ABSPATH' ) || exit;

function hws_ct_display_plugin_info() {
    if (
        ! class_exists( '\Hexa\PluginCore\Updater\UpdaterPanelRenderer' )
        || ! function_exists( __NAMESPACE__ . '\\hws_get_hexa_plugin_core_updater_config' )
    ) {
        echo '<div class="notice notice-error"><p>Hexa Plugin Core updater is not loaded for HWS Base Tools.</p></div>';
        return;
    }

    $renderer = new \Hexa\PluginCore\Updater\UpdaterPanelRenderer( hws_get_hexa_plugin_core_updater_config() );
    $renderer->render();

    if (
        ! class_exists( '\Hexa\PluginCore\Updater\CorePackagePanelRenderer' )
        || ! function_exists( __NAMESPACE__ . '\\hws_get_hexa_plugin_core_package_config' )
    ) {
        echo '<div class="notice notice-error"><p>Hexa WordPress Plugin Core package updater is not loaded.</p></div>';
        return;
    }

    $core_renderer = new \Hexa\PluginCore\Updater\CorePackagePanelRenderer( hws_get_hexa_plugin_core_package_config() );
    $core_renderer->render();
}
