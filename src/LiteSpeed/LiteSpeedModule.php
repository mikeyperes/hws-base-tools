<?php

namespace HWS\BaseTools\LiteSpeed;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

final class LiteSpeedModule implements ModuleInterface {
    private static bool $registered = false;
    private static ?GettingStartedChecklistConfig $config = null;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( 'init', [ self::class, 'register_ajax' ], 30 );
    }

    public static function register_ajax(): void {
        ( new GettingStartedChecklistAjaxController( self::config() ) )->register();
    }

    public static function render(): void {
        ( new GettingStartedChecklistRenderer( self::config() ) )->render();
    }

    public static function config(): GettingStartedChecklistConfig {
        if ( self::$config instanceof GettingStartedChecklistConfig ) {
            return self::$config;
        }
        self::$config = new GettingStartedChecklistConfig( [
            'root_id'              => 'hws-litespeed-checklist',
            'title'                => 'LiteSpeed Optimization',
            'description'          => 'Choose a profile and run it in one batch, or audit and apply every group individually. Every result is saved and reflected immediately.',
            'capability'           => PluginMetadata::ADMIN_CAPABILITY,
            'nonce_action'         => 'hws_base_tools_litespeed',
            'nonce_field'          => 'nonce',
            'run_action'           => 'hws_litespeed_checklist_run_item',
            'status_action'        => 'hws_litespeed_checklist_status',
            'reset_action'         => 'hws_litespeed_checklist_reset',
            'state_option'         => 'hws_litespeed_checklist_state',
            'persistence_enabled'  => true,
            'template_id'          => LiteSpeedProfileRegistry::DEFAULT_PROFILE,
            'template_label'       => 'Optimization Profile',
            'template_load_label'  => 'Load Profile',
            'show_template_picker' => true,
            'show_search'          => true,
            'search_label'         => 'Search LiteSpeed Checklist',
            'search_placeholder'   => 'Search cache, CSS, JavaScript, media, Redis...',
            'templates'            => LiteSpeedProfileRegistry::checklist_templates(),
        ] );
        return self::$config;
    }
}
