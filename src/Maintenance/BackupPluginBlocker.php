<?php

namespace HWS\BaseTools\Maintenance;

defined( 'ABSPATH' ) || exit;

/**
 * Backup plugin blocker: keeps All-in-One WP Migration (and its extensions) and
 * UpdraftPlus off the site. Default off. Every block is silent: no notice
 * names the block or this plugin.
 *
 * While on, installing either plugin (upload, plugin search or WP-CLI) fails,
 * activating it is refused, an active copy is deactivated, and its backup
 * requests and scheduled backup events are dropped. Installed folders and
 * existing backup files stay as they are; the Backups tab still cleans those.
 */
final class BackupPluginBlocker {
    public const FEATURE_OPTION = 'enable_backup_plugin_blocker';

    /** Blocked plugin folders; a folder matches its slug or `<slug>-<anything>`. */
    public const PLUGINS = [
        'all-in-one-wp-migration' => 'All-in-One WP Migration',
        'updraftplus'             => 'UpdraftPlus',
        'de-updraftplus'          => 'UpdraftPlus add-ons',
        'duplicator'              => 'Duplicator',
        'wp-staging'              => 'WP STAGING',
        'backwpup'                => 'BackWPup',
        'wpvivid-backuprestore'   => 'WPvivid Backup & Migration',
        'backup-backup'           => 'Backup Migration',
        'wp-file-manager'         => 'WP File Manager',
        'file-manager-advanced'   => 'Advanced File Manager',
        'filester'                => 'Filester File Manager',
    ];

    /** Cron hook prefixes of the blocked plugins. */
    private const CRON_PREFIXES = [ 'ai1wm', 'updraft', 'duplicator', 'backwpup', 'wpstg', 'wpvivid' ];

    /** Backup and restore request actions of the blocked plugins. */
    private const REQUEST_ACTIONS = [
        'ai1wm_export',
        'ai1wm_import',
        'ai1wm_backups',
        'updraft_ajax',
        'updraft_backupnow',
    ];

    private static bool $active = false;

    public static function activate(): void {
        if ( self::$active ) {
            return;
        }

        self::$active = true;
        add_filter( 'upgrader_source_selection', [ self::class, 'block_install' ], 1, 4 );
        add_filter( 'pre_update_option_active_plugins', [ self::class, 'strip_active' ] );
        add_filter( 'pre_update_site_option_active_sitewide_plugins', [ self::class, 'strip_network_active' ] );
        add_filter( 'pre_schedule_event', [ self::class, 'block_schedule' ], 10, 2 );
        add_action( 'init', [ self::class, 'stop_backups' ], 0 );
        add_action( 'admin_init', [ self::class, 'deactivate_installed' ], 0 );
    }

    public static function is_blocked_folder( string $folder ): bool {
        $folder = strtolower( trim( $folder, '/\\' ) );
        foreach ( array_keys( self::PLUGINS ) as $slug ) {
            if ( $folder === $slug || str_starts_with( $folder, $slug . '-' ) ) {
                return true;
            }
        }

        return false;
    }

    /** `folder/file.php` or a bare `file.php` belongs to a blocked plugin. */
    public static function is_blocked_plugin_file( string $plugin_file ): bool {
        $folder = strstr( str_replace( '\\', '/', $plugin_file ), '/', true );

        return self::is_blocked_folder( false === $folder ? basename( $plugin_file, '.php' ) : $folder );
    }

    public static function is_blocked_hook( string $hook ): bool {
        $hook = strtolower( $hook );
        foreach ( self::CRON_PREFIXES as $prefix ) {
            if ( str_starts_with( $hook, $prefix ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|\WP_Error $source
     * @return string|\WP_Error
     */
    public static function block_install( $source, $remote_source = '', $upgrader = null, $hook_extra = [] ) {
        if ( is_wp_error( $source ) || ( is_array( $hook_extra ) && isset( $hook_extra['theme'] ) ) ) {
            return $source;
        }
        if ( ! self::is_blocked_folder( basename( untrailingslashit( (string) $source ) ) ) ) {
            return $source;
        }

        // Silent: WordPress's own generic failure text, no mention of the block.
        return new \WP_Error( 'incompatible_archive', __( 'The package could not be installed.' ) );
    }

    /**
     * @param mixed $plugins
     * @return mixed
     */
    public static function strip_active( $plugins ) {
        if ( ! is_array( $plugins ) ) {
            return $plugins;
        }

        return array_values( array_filter(
            $plugins,
            static fn ( $file ): bool => ! is_string( $file ) || ! self::is_blocked_plugin_file( $file )
        ) );
    }

    /**
     * @param mixed $plugins `file => timestamp`
     * @return mixed
     */
    public static function strip_network_active( $plugins ) {
        if ( ! is_array( $plugins ) ) {
            return $plugins;
        }
        foreach ( array_keys( $plugins ) as $file ) {
            if ( is_string( $file ) && self::is_blocked_plugin_file( $file ) ) {
                unset( $plugins[ $file ] );
            }
        }

        return $plugins;
    }

    /**
     * @param null|bool|\WP_Error $pre
     * @param object              $event
     * @return null|bool|\WP_Error
     */
    public static function block_schedule( $pre, $event ) {
        if ( null !== $pre || ! is_object( $event ) || ! self::is_blocked_hook( (string) ( $event->hook ?? '' ) ) ) {
            return $pre;
        }

        return false;
    }

    /** Drop the blocked plugins' backup requests and scheduled events for this and later requests. */
    public static function stop_backups(): void {
        foreach ( self::REQUEST_ACTIONS as $action ) {
            remove_all_actions( 'wp_ajax_' . $action );
            remove_all_actions( 'wp_ajax_nopriv_' . $action );
            remove_all_actions( 'admin_post_' . $action );
            remove_all_actions( 'admin_post_nopriv_' . $action );
        }

        $cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
        if ( ! is_array( $cron ) ) {
            return;
        }
        foreach ( $cron as $events ) {
            foreach ( array_keys( (array) $events ) as $hook ) {
                if ( self::is_blocked_hook( (string) $hook ) ) {
                    remove_all_actions( (string) $hook );
                    wp_unschedule_hook( (string) $hook );
                }
            }
        }
    }

    /** Deactivate any blocked plugin that is still active. */
    public static function deactivate_installed(): void {
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = (array) get_option( 'active_plugins', [] );
        if ( is_multisite() ) {
            $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
        }
        $blocked = array_values( array_filter(
            $active,
            static fn ( $file ): bool => is_string( $file ) && self::is_blocked_plugin_file( $file )
        ) );
        if ( [] !== $blocked ) {
            deactivate_plugins( $blocked, true, is_multisite() );
        }
    }

    public static function definition(): array {
        $list = implode( '', array_map( static fn ( string $name ): string => '<li>' . esc_html( $name ) . '</li>', self::PLUGINS ) );

        return [
            'id'               => self::FEATURE_OPTION,
            'name'             => 'Block Backup and File Manager Plugins',
            'description'      => 'Backups are made by the server with JetBackup. These backup and file manager plugins cannot be installed, activated or run:',
            'info'             => '<ul>' . $list . '</ul>',
            'function'         => 'enable_backup_plugin_blocker',
            'scope_admin_only' => false,
        ];
    }
}
