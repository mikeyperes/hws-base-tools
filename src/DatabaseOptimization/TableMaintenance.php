<?php

namespace HWS\BaseTools\DatabaseOptimization;

use WP_Error;

/**
 * Table checks, repairs, InnoDB conversion and space reclaiming, one table at a
 * time. Only tables that start with this site's prefix are ever touched, and a
 * table name is accepted only when it is in that list.
 */
final class TableMaintenance {
    private const REPAIRABLE  = [ 'myisam', 'aria', 'archive', 'csv' ];
    private const CONVERTIBLE = [ 'myisam', 'aria' ];
    private const INNODB_MIN_FREE = 8388608;

    /** @return array<int,array<string,mixed>> */
    public static function tables(): array {
        global $wpdb;
        self::fresh_statistics();
        $rows = (array) $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ), ARRAY_A );
        if ( is_multisite() && is_main_site() ) {
            // The main site's prefix also matches every subsite (wp_2_...); those belong to their own sites.
            $rows = array_filter( $rows, static fn( array $row ): bool => ! preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . '\d+_/', (string) $row['Name'] ) );
        }

        $core           = self::core_tables();
        $file_per_table = self::file_per_table();
        $tables         = [];
        foreach ( $rows as $row ) {
            $tables[] = self::describe( $row, $core, $file_per_table );
        }

        return $tables;
    }

    /** @return array<string,mixed>|WP_Error */
    public static function table( string $name ): array|WP_Error {
        global $wpdb;
        if ( ! self::is_site_table( $name ) ) {
            return new WP_Error( 'hws_dbo_unknown_table', 'That table does not belong to this site.' );
        }
        self::fresh_statistics();
        $row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $name ) ), ARRAY_A );

        return $row ? self::describe( $row, self::core_tables(), self::file_per_table() ) : new WP_Error( 'hws_dbo_missing_table', 'Table not found.' );
    }

    /**
     * Run one operation on one table.
     *
     * @return array{table:array<string,mixed>,message:string,status:string,before:array<string,mixed>}|WP_Error
     */
    public static function run( string $name, string $operation ): array|WP_Error {
        $before = self::table( $name );
        if ( is_wp_error( $before ) ) {
            return $before;
        }
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        switch ( $operation ) {
            case 'check':
                $result = self::check( $name );
                break;
            case 'repair':
                $result = self::repair( $name, $before );
                break;
            case 'convert':
                $result = self::convert( $name, $before );
                break;
            case 'optimize':
                $result = self::optimize( $name, $before );
                break;
            default:
                return new WP_Error( 'hws_dbo_unknown_operation', 'Unknown table operation.' );
        }

        $after = self::table( $name );

        return [
            'table'   => is_wp_error( $after ) ? $before : $after,
            'before'  => $before,
            'message' => $result['message'],
            'status'  => $result['status'],
        ];
    }

    /** @return array{status:string,message:string} */
    private static function check( string $name ): array {
        $rows = self::admin_statement( 'CHECK TABLE', $name );
        $bad  = self::problem_messages( $rows );
        if ( null === $rows ) {
            return [ 'status' => 'error', 'message' => 'The check could not run.' ];
        }
        if ( $bad ) {
            update_option( self::flag_option(), array_merge( self::flagged(), [ $name => implode( ' ', $bad ) ] ), false );

            return [ 'status' => 'error', 'message' => 'Needs repair: ' . implode( ' ', $bad ) ];
        }
        self::unflag( $name );

        return [ 'status' => 'done', 'message' => 'Healthy.' ];
    }

    /**
     * @param array<string,mixed> $table
     * @return array{status:string,message:string}
     */
    private static function repair( string $name, array $table ): array {
        $check = self::check( $name );
        if ( 'done' === $check['status'] ) {
            return [ 'status' => 'skipped', 'message' => 'Healthy, no repair needed.' ];
        }

        $engine = strtolower( (string) $table['engine'] );
        if ( in_array( $engine, self::REPAIRABLE, true ) ) {
            $bad = self::problem_messages( self::admin_statement( 'REPAIR TABLE', $name ) );
        } else {
            // InnoDB has no REPAIR TABLE; rebuilding the table is the supported fix.
            $bad = self::problem_messages( self::admin_statement( 'OPTIMIZE TABLE', $name ) );
        }
        $verify = self::check( $name );

        return 'done' === $verify['status']
            ? [ 'status' => 'done', 'message' => 'Repaired and verified.' ]
            : [ 'status' => 'error', 'message' => 'Still reports problems: ' . ( $bad ? implode( ' ', $bad ) : $verify['message'] ) ];
    }

    /**
     * @param array<string,mixed> $table
     * @return array{status:string,message:string}
     */
    private static function convert( string $name, array $table ): array {
        global $wpdb;
        if ( ! $table['convertible'] ) {
            return [ 'status' => 'skipped', 'message' => 'Already ' . $table['engine'] . '.' ];
        }
        if ( ! self::innodb_available() ) {
            return [ 'status' => 'error', 'message' => 'This database server does not offer InnoDB.' ];
        }

        $ok    = false !== $wpdb->query( 'ALTER TABLE `' . esc_sql( $name ) . '` ENGINE=InnoDB' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $after = self::table( $name );
        if ( $ok && ! is_wp_error( $after ) && 'innodb' === strtolower( (string) $after['engine'] ) ) {
            return [ 'status' => 'done', 'message' => 'Converted from ' . $table['engine'] . ' to InnoDB.' ];
        }

        return [ 'status' => 'error', 'message' => 'Conversion failed' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' ) ];
    }

    /**
     * @param array<string,mixed> $table
     * @return array{status:string,message:string}
     */
    private static function optimize( string $name, array $table ): array {
        if ( ! $table['rebuildable'] ) {
            return [ 'status' => 'skipped', 'message' => (string) $table['optimize_note'] ];
        }
        $bad = self::problem_messages( self::admin_statement( 'OPTIMIZE TABLE', $name ) );
        if ( $bad ) {
            return [ 'status' => 'error', 'message' => implode( ' ', $bad ) ];
        }
        $after = self::table( $name );
        if ( is_wp_error( $after ) ) {
            return [ 'status' => 'done', 'message' => 'Optimized.' ];
        }
        // InnoDB counts half-empty pages as used, so the real gain is the drop in total file size.
        $freed = max( 0, ( (int) $table['size'] + (int) $table['overhead'] ) - ( (int) $after['size'] + (int) $after['overhead'] ) );

        return [ 'status' => 'done', 'message' => 'Reclaimed ' . size_format( $freed, 1 ) . ' (' . size_format( (int) $table['size'], 1 ) . ' to ' . size_format( (int) $after['size'], 1 ) . ').' ];
    }

    /**
     * CHECK / REPAIR / OPTIMIZE return a result set. Null when the statement failed.
     *
     * @return array<int,array<string,string>>|null
     */
    private static function admin_statement( string $statement, string $name ): ?array {
        global $wpdb;
        $rows = $wpdb->get_results( $statement . ' `' . esc_sql( $name ) . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return '' !== (string) $wpdb->last_error && ! $rows ? null : (array) $rows;
    }

    /**
     * Only real errors count. InnoDB's "does not support optimize, doing recreate" note is normal.
     *
     * @param array<int,array<string,string>>|null $rows
     * @return string[]
     */
    private static function problem_messages( ?array $rows ): array {
        if ( null === $rows ) {
            global $wpdb;

            return [ $wpdb->last_error ?: 'Statement failed.' ];
        }
        $bad = [];
        foreach ( $rows as $row ) {
            $type = strtolower( (string) ( $row['Msg_type'] ?? '' ) );
            $text = (string) ( $row['Msg_text'] ?? '' );
            if ( 'error' === $type || ( 'status' === $type && ! in_array( strtolower( $text ), [ 'ok', 'table is already up to date' ], true ) ) ) {
                $bad[] = $text;
            }
        }

        return $bad;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,bool>  $core
     * @return array<string,mixed>
     */
    private static function describe( array $row, array $core, bool $file_per_table ): array {
        $name     = (string) $row['Name'];
        $engine   = (string) ( $row['Engine'] ?? '' );
        $lower    = strtolower( $engine );
        $overhead = (int) ( $row['Data_free'] ?? 0 );
        $size     = (int) ( $row['Data_length'] ?? 0 ) + (int) ( $row['Index_length'] ?? 0 );

        // The same rules WP-Optimize uses to decide whether OPTIMIZE can reclaim space.
        $note = '';
        if ( '' === $engine ) {
            $note = 'This is a view, not a table.';
        } elseif ( $overhead <= 0 ) {
            $note = 'No wasted space.';
        } elseif ( 'innodb' === $lower && ! $file_per_table ) {
            $note = 'InnoDB without file-per-table cannot give space back.';
        } elseif ( 'innodb' === $lower && ( $overhead < self::INNODB_MIN_FREE || $overhead < $size * 0.1 ) ) {
            // InnoDB keeps a few free extents in every table; rebuilding cannot give those back.
            $note = size_format( $overhead, 1 ) . ' is the normal InnoDB reserve, nothing to reclaim.';
        } elseif ( ! in_array( $lower, [ 'innodb', 'myisam', 'aria', 'archive' ], true ) ) {
            $note = $engine . ' tables cannot be optimized.';
        }

        $flagged = self::flagged();
        $owner   = TableOwners::owner( $name, isset( $core[ $name ] ) );

        return [
            'name'          => $name,
            'engine'        => '' !== $engine ? $engine : 'View',
            'rows'          => (int) ( $row['Rows'] ?? 0 ),
            'size'          => $size,
            'overhead'      => $overhead,
            'core'          => isset( $core[ $name ] ),
            'owner'         => $owner['name'],
            'owner_state'   => $owner['state'],
            'optimizable'   => '' === $note,
            'optimize_note' => '' === $note ? 'Has ' . size_format( $overhead, 1 ) . ' to reclaim.' : $note,
            'rebuildable'   => in_array( $lower, [ 'innodb', 'myisam', 'aria', 'archive' ], true ),
            'convertible'   => in_array( $lower, self::CONVERTIBLE, true ),
            'needs_repair'  => isset( $flagged[ $name ] ),
            'repair_note'   => (string) ( $flagged[ $name ] ?? '' ),
        ];
    }

    public static function is_site_table( string $name ): bool {
        global $wpdb;
        $names = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
        if ( is_multisite() && is_main_site() && preg_match( '/^' . preg_quote( $wpdb->prefix, '/' ) . '\d+_/', $name ) ) {
            return false;
        }

        return in_array( $name, $names, true );
    }

    /**
     * MySQL 8 caches table sizes for a day by default, so space freed by
     * OPTIMIZE would not show. Ask for current numbers in this session.
     */
    private static function fresh_statistics(): void {
        global $wpdb;
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;
        if ( null !== $wpdb->get_var( "SHOW VARIABLES LIKE 'information_schema_stats_expiry'" ) ) {
            $wpdb->query( 'SET SESSION information_schema_stats_expiry = 0' );
        }
    }

    public static function innodb_available(): bool {
        global $wpdb;
        foreach ( (array) $wpdb->get_results( 'SHOW ENGINES', ARRAY_A ) as $engine ) {
            if ( 'innodb' === strtolower( (string) ( $engine['Engine'] ?? '' ) ) ) {
                return in_array( strtoupper( (string) ( $engine['Support'] ?? '' ) ), [ 'YES', 'DEFAULT' ], true );
            }
        }

        return false;
    }

    public static function file_per_table(): bool {
        global $wpdb;
        $row = $wpdb->get_row( "SHOW VARIABLES LIKE 'innodb_file_per_table'", ARRAY_A );

        return in_array( strtoupper( (string) ( $row['Value'] ?? '' ) ), [ 'ON', '1' ], true );
    }

    /**
     * WordPress's own tables. $wpdb->tables() is not used because plugins such
     * as Action Scheduler add their tables to it.
     *
     * @return array<string,bool>
     */
    public static function core_tables(): array {
        global $wpdb;
        $core = [];
        foreach ( [ 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'links' ] as $table ) {
            $core[ $wpdb->prefix . $table ] = true;
        }
        foreach ( [ 'users', 'usermeta', 'blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'sitecategories', 'registration_log' ] as $table ) {
            $core[ $wpdb->base_prefix . $table ] = true;
        }

        return $core;
    }

    /** @return array<string,string> */
    private static function flagged(): array {
        $flags = get_option( self::flag_option(), [] );

        return is_array( $flags ) ? $flags : [];
    }

    private static function unflag( string $name ): void {
        $flags = self::flagged();
        if ( isset( $flags[ $name ] ) ) {
            unset( $flags[ $name ] );
            update_option( self::flag_option(), $flags, false );
        }
    }

    private static function flag_option(): string {
        return 'hws_database_optimization_flagged_tables';
    }
}
