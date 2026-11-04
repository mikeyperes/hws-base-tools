<?php

namespace HWS\BaseTools\Maintenance;

defined( 'ABSPATH' ) || exit;

/**
 * The one list of backup and file manager plugins. The blocker keeps them off
 * the site, the Backups tab shows each one with its install state, and the
 * scanner looks in each one's backup folders for each one's file formats.
 *
 * `folders` are relative to wp-content and may use `*`. `formats` are the file
 * extensions the plugin writes there. `signatures` are formats only that
 * plugin writes, so a stray copy anywhere in wp-content is still found.
 */
final class BackupPluginCatalog {
    public const TYPE_BACKUP       = 'backup';
    public const TYPE_FILE_MANAGER = 'file_manager';

    public static function plugins(): array {
        return [
            'all-in-one-wp-migration' => [
                'name'       => 'All-in-One WP Migration',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'ai1wm-backups' ],
                'formats'    => [ 'wpress' ],
                'signatures' => [ 'wpress' ],
            ],
            'updraftplus' => [
                'name'       => 'UpdraftPlus',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'updraft' ],
                'formats'    => [ 'zip', 'gz', 'sql', 'crypt' ],
                'signatures' => [],
            ],
            'de-updraftplus' => [
                'name'       => 'UpdraftPlus add-ons',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [],
                'formats'    => [],
                'signatures' => [],
            ],
            'duplicator' => [
                'name'       => 'Duplicator',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'backups-dup-lite', 'wp-snapshots' ],
                'formats'    => [ 'zip', 'daf', 'sql' ],
                'signatures' => [ 'daf' ],
            ],
            'duplicator-pro' => [
                'name'       => 'Duplicator Pro',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'backups-dup-pro' ],
                'formats'    => [ 'zip', 'daf', 'sql' ],
                'signatures' => [],
            ],
            'wp-staging' => [
                'name'       => 'WP STAGING',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'uploads/wp-staging/backups' ],
                'formats'    => [ 'wpstg', 'sql' ],
                'signatures' => [ 'wpstg' ],
            ],
            'backwpup' => [
                'name'       => 'BackWPup',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'uploads/backwpup*' ],
                'formats'    => [ 'zip', 'tar', 'gz', 'bz2', 'sql' ],
                'signatures' => [],
            ],
            'wpvivid-backuprestore' => [
                'name'       => 'WPvivid Backup & Migration',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'wpvividbackups', 'wpvivid' ],
                'formats'    => [ 'zip', 'gz', 'sql' ],
                'signatures' => [],
            ],
            'backup-backup' => [
                'name'       => 'Backup Migration',
                'type'       => self::TYPE_BACKUP,
                'folders'    => [ 'backup-migration*', 'backup-migration*/backups' ],
                'formats'    => [ 'zip' ],
                'signatures' => [],
            ],
            'wp-file-manager' => [
                'name'       => 'WP File Manager',
                'type'       => self::TYPE_FILE_MANAGER,
                'folders'    => [ 'uploads/wp-file-manager-pro/fm_backup' ],
                'formats'    => [ 'zip', 'gz', 'sql' ],
                'signatures' => [],
            ],
            'file-manager-advanced' => [
                'name'       => 'Advanced File Manager',
                'type'       => self::TYPE_FILE_MANAGER,
                'folders'    => [],
                'formats'    => [],
                'signatures' => [],
            ],
            'filester' => [
                'name'       => 'Filester File Manager',
                'type'       => self::TYPE_FILE_MANAGER,
                'folders'    => [],
                'formats'    => [],
                'signatures' => [],
            ],
        ];
    }

    /** @return array<string,string> slug => name */
    public static function names(): array {
        return array_map( static fn ( array $plugin ): string => $plugin['name'], self::plugins() );
    }

    /** The catalog slug a plugin folder belongs to (its slug or `<slug>-<anything>`), longest match first. */
    public static function slug_for_folder( string $folder ): ?string {
        $folder = strtolower( trim( $folder, '/\\' ) );
        $slugs  = array_keys( self::plugins() );
        usort( $slugs, static fn ( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
        foreach ( $slugs as $slug ) {
            if ( $folder === $slug || str_starts_with( $folder, $slug . '-' ) ) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Install state of each catalog plugin on this site.
     *
     * @return array<string,string> slug => `active` | `installed` | `not_installed`
     */
    public static function install_states(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $states = array_fill_keys( array_keys( self::plugins() ), 'not_installed' );
        foreach ( array_keys( (array) get_plugins() ) as $file ) {
            $folder = strstr( str_replace( '\\', '/', (string) $file ), '/', true );
            $slug   = self::slug_for_folder( false === $folder ? basename( (string) $file, '.php' ) : $folder );
            if ( null === $slug ) {
                continue;
            }
            if ( is_plugin_active( (string) $file ) || ( is_multisite() && is_plugin_active_for_network( (string) $file ) ) ) {
                $states[ $slug ] = 'active';
            } elseif ( 'active' !== $states[ $slug ] ) {
                $states[ $slug ] = 'installed';
            }
        }

        return $states;
    }
}
