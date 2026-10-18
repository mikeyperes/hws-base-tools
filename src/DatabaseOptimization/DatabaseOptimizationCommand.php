<?php

namespace HWS\BaseTools\DatabaseOptimization;

use WP_CLI;

/**
 * Database cleanup and table maintenance from the command line. Same engine
 * as the Database Optimization tab.
 */
final class DatabaseOptimizationCommand {
    /**
     * Count what each cleanup item would remove and list table health.
     *
     * [--format=<format>]
     * : table or json.
     * ---
     * default: table
     * ---
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function scan( array $args, array $assoc ): void {
        $rows = [];
        foreach ( CleanupTasks::definitions() as $id => $task ) {
            $count  = CleanupTasks::count( $id );
            $rows[] = [ 'item' => $id, 'label' => $task['label'], 'found' => is_wp_error( $count ) ? 'error' : $count, 'default' => $task['default'] ? 'yes' : 'no' ];
        }
        $overview = DatabaseReport::overview();
        if ( 'json' === ( $assoc['format'] ?? 'table' ) ) {
            WP_CLI::line( (string) wp_json_encode( [ 'cleanup' => $rows, 'overview' => $overview, 'tables' => TableMaintenance::tables() ] ) );
            return;
        }
        WP_CLI\Utils\format_items( 'table', $rows, [ 'item', 'label', 'found', 'default' ] );
        WP_CLI::line( sprintf( 'Database %s in %d tables, %s reclaimable, %d MyISAM tables, %d flagged for repair, autoload %s.', size_format( $overview['size'], 1 ), $overview['tables'], size_format( $overview['overhead'], 1 ), $overview['myisam'], $overview['needs_repair'], size_format( $overview['autoload_size'], 1 ) ) );
    }

    /**
     * Remove cleanup items in batches.
     *
     * [--items=<items>]
     * : Comma-separated item ids. Defaults to every item marked default in scan.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function clean( array $args, array $assoc ): void {
        $definitions = CleanupTasks::definitions();
        $items       = isset( $assoc['items'] )
            ? array_filter( array_map( 'trim', explode( ',', $assoc['items'] ) ) )
            : array_keys( array_filter( $definitions, static fn( array $t ): bool => $t['default'] ) );

        foreach ( $items as $item ) {
            if ( ! CleanupTasks::exists( $item ) ) {
                WP_CLI::warning( "Unknown item {$item}." );
                continue;
            }
            $removed = 0;
            $failed  = 0;
            for ( $i = 0; $i < 1000; $i++ ) {
                $result = CleanupTasks::clean( $item );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::warning( $item . ': ' . $result->get_error_message() );
                    break;
                }
                $removed += $result['removed'];
                $failed  += $result['failed'];
                if ( $result['done'] ) {
                    break;
                }
            }
            WP_CLI::log( sprintf( '%s: removed %d%s, %d left.', $definitions[ $item ]['label'], $removed, $failed ? ", {$failed} could not be removed" : '', (int) CleanupTasks::count( $item ) ) );
        }
        WP_CLI::success( 'Cleanup finished.' );
    }

    /**
     * Check, repair, convert to InnoDB or optimize this site's tables, one at a time.
     *
     * [--operation=<operation>]
     * : check, repair, convert, optimize, or all (check, repair what is broken, convert, optimize).
     * ---
     * default: all
     * ---
     *
     * [--tables=<tables>]
     * : Comma-separated table names. Defaults to every site table; optimize then
     * : only rebuilds tables with space to give back.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function tables( array $args, array $assoc ): void {
        $operation  = $assoc['operation'] ?? 'all';
        $operations = 'all' === $operation ? [ 'check', 'repair', 'convert', 'optimize' ] : [ $operation ];
        $names      = isset( $assoc['tables'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['tables'] ) ) ) : array_column( TableMaintenance::tables(), 'name' );

        foreach ( $names as $name ) {
            foreach ( $operations as $op ) {
                if ( 'all' === $operation && 'repair' === $op ) {
                    $current = TableMaintenance::table( $name );
                    if ( is_wp_error( $current ) || ! $current['needs_repair'] ) {
                        continue;
                    }
                }
                if ( 'optimize' === $op && ! isset( $assoc['tables'] ) ) {
                    // Without named tables, only rebuild tables with space to give back.
                    $current = TableMaintenance::table( $name );
                    if ( is_wp_error( $current ) || ! $current['optimizable'] ) {
                        continue;
                    }
                }
                $result = TableMaintenance::run( $name, $op );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::warning( "{$name} {$op}: " . $result->get_error_message() );
                    continue 2;
                }
                if ( 'skipped' !== $result['status'] || 'all' !== $operation ) {
                    WP_CLI::log( "{$name} {$op}: {$result['status']} - {$result['message']}" );
                }
            }
        }
        WP_CLI::success( 'Table maintenance finished.' );
    }

    /**
     * Clean the default items, then run every table operation.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function run( array $args, array $assoc ): void {
        $this->clean( [], [] );
        $this->tables( [], [ 'operation' => 'all' ] );
    }
}
