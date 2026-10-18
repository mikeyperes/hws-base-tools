<?php

namespace HWS\BaseTools\DatabaseOptimization;

/**
 * Read-only facts about the database: totals, settings loaded on every page,
 * tables WordPress did not create, and media whose post is gone. Nothing here
 * deletes anything.
 */
final class DatabaseReport {
    /** @return array<string,mixed> */
    public static function overview(): array {
        global $wpdb;
        $tables   = TableMaintenance::tables();
        $engines  = [];
        $size     = 0;
        $overhead = 0;
        $repair   = 0;
        foreach ( $tables as $table ) {
            $engines[ $table['engine'] ] = ( $engines[ $table['engine'] ] ?? 0 ) + 1;
            $size                       += (int) $table['size'];
            $overhead                   += $table['optimizable'] ? (int) $table['overhead'] : 0;
            $repair                     += $table['needs_repair'] ? 1 : 0;
        }
        $autoload = self::autoload_totals();

        return [
            'size'           => $size,
            'tables'         => count( $tables ),
            'overhead'       => $overhead,
            'engines'        => $engines,
            'myisam'         => count( array_filter( $tables, static fn( array $t ): bool => $t['convertible'] ) ),
            'needs_repair'   => $repair,
            'autoload_size'  => $autoload['size'],
            'autoload_count' => $autoload['count'],
            'server'         => (string) $wpdb->db_server_info(),
            'innodb'         => TableMaintenance::innodb_available(),
            'file_per_table' => TableMaintenance::file_per_table(),
        ];
    }

    /** @return array<int,array{name:string,size:int}> */
    public static function autoload_top( int $limit = 15 ): array {
        global $wpdb;
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_name AS name, LENGTH( option_value ) AS size FROM {$wpdb->options} WHERE autoload IN ( " . self::autoload_values() . ' ) ORDER BY size DESC LIMIT %d', $limit ), ARRAY_A );

        return array_map( static fn( array $r ): array => [ 'name' => (string) $r['name'], 'size' => (int) $r['size'] ], $rows );
    }

    /**
     * Tables with this site's prefix that WordPress did not create, with the
     * installed plugin whose folder name matches, when one does.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extra_tables(): array {
        global $wpdb;
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = [];
        foreach ( get_plugins() as $file => $data ) {
            $slug             = strtolower( (string) strtok( $file, '/' ) );
            $plugins[ $slug ] = [ 'name' => (string) $data['Name'], 'active' => is_plugin_active( $file ) ];
        }

        $extra = [];
        foreach ( TableMaintenance::tables() as $table ) {
            if ( $table['core'] ) {
                continue;
            }
            $suffix = strtolower( substr( $table['name'], strlen( $wpdb->prefix ) ) );
            $token  = (string) strtok( $suffix, '_' );
            $owner  = null;
            foreach ( $plugins as $slug => $plugin ) {
                $flat = str_replace( '-', '', $slug );
                if ( strlen( $token ) >= 3 && ( str_starts_with( $flat, $token ) || str_starts_with( $token, $flat ) || str_contains( $slug, $token ) ) ) {
                    $owner = $plugin;
                    break;
                }
            }
            $extra[] = [
                'name'   => $table['name'],
                'rows'   => $table['rows'],
                'size'   => $table['size'],
                'owner'  => $owner ? $owner['name'] : '',
                'active' => $owner ? $owner['active'] : false,
            ];
        }

        return $extra;
    }

    /** Media items attached to a post that no longer exists. The files may still be used elsewhere. */
    public static function detached_media(): int {
        global $wpdb;

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_parent WHERE a.post_type = 'attachment' AND a.post_parent > 0 AND p.ID IS NULL" );
    }

    /** @return array{size:int,count:int} */
    private static function autoload_totals(): array {
        global $wpdb;
        $row = $wpdb->get_row( "SELECT COUNT(*) AS c, COALESCE( SUM( LENGTH( option_value ) ), 0 ) AS s FROM {$wpdb->options} WHERE autoload IN ( " . self::autoload_values() . ' )', ARRAY_A );

        return [ 'size' => (int) ( $row['s'] ?? 0 ), 'count' => (int) ( $row['c'] ?? 0 ) ];
    }

    private static function autoload_values(): string {
        $values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : [ 'yes' ];

        return "'" . implode( "','", array_map( 'esc_sql', $values ) ) . "'";
    }
}
