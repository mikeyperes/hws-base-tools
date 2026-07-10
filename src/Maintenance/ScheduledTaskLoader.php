<?php

namespace HWS\BaseTools\Maintenance;

use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class ScheduledTaskLoader {
    public static function load_for_request(): void {
        $doing_cron = function_exists( 'wp_doing_cron' ) && wp_doing_cron();
        $doing_cli  = defined( 'WP_CLI' ) && WP_CLI;

        if ( ! $doing_cron && ! $doing_cli ) {
            return;
        }

        foreach ( [
            'settings-dashboard-log-delete-cron.php',
            'settings-dashboard-elementor-db-cron.php',
            'settings-dashboard-backups.php',
        ] as $file ) {
            $path = PluginMetadata::root_path() . '/' . $file;
            if ( is_readable( $path ) ) {
                require_once $path;
            }
        }
    }
}
