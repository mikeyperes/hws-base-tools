<?php

namespace HWS\BaseTools\QuickStart;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class QuickStartModule implements ModuleInterface {
    private static bool $registered = false;

    private static ?GettingStartedChecklistConfig $config = null;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }

        self::$registered = true;
        add_action( 'init', [ self::class, 'register_ajax' ], 30 );
        add_action( 'init', [ self::class, 'record_cron_heartbeat' ], 1 );
    }

    public static function register_ajax(): void {
        ( new GettingStartedChecklistAjaxController( self::config() ) )->register();
    }

    public static function render(): void {
        ( new GettingStartedChecklistRenderer( self::config() ) )->render();
    }

    public static function record_cron_heartbeat(): void {
        if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
            update_option( 'hws_cron_last_seen', time(), false );
        }
    }

    public static function config(): GettingStartedChecklistConfig {
        if ( self::$config instanceof GettingStartedChecklistConfig ) {
            return self::$config;
        }

        self::$config = new GettingStartedChecklistConfig(
            [
                'root_id'              => 'hws-quick-start',
                'title'                => 'Quick Start Master Checklist',
                'description'          => 'Choose a site profile, run every safe task in order, or run any item by itself. Progress is saved after every request so an interrupted run can resume.',
                'capability'           => PluginMetadata::ADMIN_CAPABILITY,
                'nonce_action'         => 'hws_base_tools_quick_start',
                'nonce_field'          => 'nonce',
                'run_action'           => 'hws_quick_start_run_item',
                'status_action'        => 'hws_quick_start_status',
                'reset_action'         => 'hws_quick_start_reset',
                'state_option'         => 'hws_quick_start_state',
                'persistence_enabled'  => true,
                'template_id'          => QuickStartProfileRegistry::DEFAULT_PROFILE,
                'template_label'       => 'Site Profile',
                'template_load_label'  => 'Load Profile',
                'show_template_picker' => true,
                'show_search'          => true,
                'search_label'         => 'Search Quick Start',
                'search_placeholder'   => 'Search checks and setup actions...',
                'search_empty_message' => 'No Quick Start item matches this search.',
                'templates'            => QuickStartProfileRegistry::profiles(),
            ]
        );

        return self::$config;
    }
}
