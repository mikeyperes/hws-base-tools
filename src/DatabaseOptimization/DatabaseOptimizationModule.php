<?php

namespace HWS\BaseTools\DatabaseOptimization;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

/**
 * Database Optimization: the Base Tools dashboard tab, its AJAX steps and the
 * `wp hws db-optimize` command all call the same engine.
 */
final class DatabaseOptimizationModule implements ModuleInterface {
    public const NONCE_ACTION = 'hws_database_optimization';
    public const ACTION       = 'hws_database_optimization_';

    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( 'init', [ self::class, 'register_ajax' ], 30 );
        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
            \WP_CLI::add_command( 'hws db-optimize', DatabaseOptimizationCommand::class );
        }
    }

    public static function register_ajax(): void {
        AjaxActionRegistry::create(
            [
                'capability'   => PluginMetadata::ADMIN_CAPABILITY,
                'nonce_action' => self::NONCE_ACTION,
                'nonce_field'  => 'nonce',
            ]
        )->register(
            [
                self::ACTION . 'overview' => [ 'callback' => [ self::class, 'ajax_overview' ] ],
                self::ACTION . 'count'    => [ 'callback' => [ self::class, 'ajax_count' ] ],
                self::ACTION . 'clean'    => [ 'callback' => [ self::class, 'ajax_clean' ] ],
                self::ACTION . 'tables'   => [ 'callback' => [ self::class, 'ajax_tables' ] ],
                self::ACTION . 'table'    => [ 'callback' => [ self::class, 'ajax_table' ] ],
                self::ACTION . 'reports'  => [ 'callback' => [ self::class, 'ajax_reports' ] ],
                self::ACTION . 'settings' => [ 'callback' => [ self::class, 'ajax_settings' ] ],
            ]
        );
    }

    public static function render(): void {
        DatabaseOptimizationAdmin::render();
    }

    /** @return array<string,mixed> */
    public static function ajax_overview( AjaxRequest $request ): array {
        return [ 'overview' => DatabaseReport::overview(), 'tasks' => CleanupTasks::definitions(), 'settings' => OptimizationSettings::get() ];
    }

    /** @return array<string,mixed> */
    public static function ajax_count( AjaxRequest $request ): array {
        $task  = $request->key( 'task', '', 'post' );
        $start = microtime( true );
        $count = CleanupTasks::count( $task );
        if ( is_wp_error( $count ) ) {
            throw AjaxFailure::bad_request( $count->get_error_message() );
        }

        return [ 'task' => $task, 'count' => $count, 'ms' => (int) round( ( microtime( true ) - $start ) * 1000 ) ];
    }

    /** @return array<string,mixed> */
    public static function ajax_clean( AjaxRequest $request ): array {
        $task   = $request->key( 'task', '', 'post' );
        $result = CleanupTasks::clean( $task );
        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message() );
        }
        $remaining = $result['done'] ? CleanupTasks::count( $task ) : null;

        return $result + [ 'task' => $task, 'remaining' => is_wp_error( $remaining ) ? null : $remaining ];
    }

    /** @return array<string,mixed> */
    public static function ajax_tables( AjaxRequest $request ): array {
        return [ 'tables' => TableMaintenance::tables() ];
    }

    /** @return array<string,mixed> */
    public static function ajax_table( AjaxRequest $request ): array {
        $result = TableMaintenance::run( $request->text( 'table', '', 'post' ), $request->key( 'operation', '', 'post' ) );
        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message() );
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public static function ajax_reports( AjaxRequest $request ): array {
        return [
            'autoload'       => DatabaseReport::autoload_top(),
            'leftover'       => DatabaseReport::leftover_tables(),
            'detached_media' => DatabaseReport::detached_media(),
        ];
    }

    /** @return array<string,mixed> */
    public static function ajax_settings( AjaxRequest $request ): array {
        $input = [];
        foreach ( array_keys( OptimizationSettings::fields() ) as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $input[ $key ] = $request->int( $key, 0, 'post' );
            }
        }

        return [ 'settings' => OptimizationSettings::save( $input ), 'tasks' => CleanupTasks::definitions() ];
    }
}
