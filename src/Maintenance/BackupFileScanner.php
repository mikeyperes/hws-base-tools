<?php

namespace HWS\BaseTools\Maintenance;

defined( 'ABSPATH' ) || exit;

/**
 * Finds backup files of every catalog plugin: each plugin's backup folders for
 * its formats, then a bounded sweep of wp-content and the site root for
 * formats only one plugin writes (a `.wpress` moved into uploads, for example).
 */
final class BackupFileScanner {
    /** Sweep limits keep the scan fast on sites with large uploads folders. */
    private const SWEEP_DEPTH     = 5;
    private const SWEEP_MAX_ITEMS = 50000;

    /** Folders under wp-content the sweep never enters. */
    private const SWEEP_SKIP = [ 'plugins', 'themes', 'mu-plugins', 'cache', 'litespeed', 'languages', 'upgrade' ];

    /** @return array<int,array<string,mixed>> newest first */
    public static function scan(): array {
        $plugins = BackupPluginCatalog::plugins();
        $found   = [];

        foreach ( $plugins as $slug => $plugin ) {
            foreach ( $plugin['folders'] as $pattern ) {
                foreach ( self::folders( $pattern ) as $dir ) {
                    foreach ( ( @scandir( $dir ) ?: [] ) as $file ) {
                        $path = $dir . '/' . $file;
                        if ( '.' === $file || '..' === $file || ! is_file( $path ) ) {
                            continue;
                        }
                        if ( in_array( self::format( $file ), $plugin['formats'], true ) ) {
                            self::add( $found, $slug, $plugin['name'], $path );
                        }
                    }
                }
            }
        }

        $owners = [];
        foreach ( $plugins as $slug => $plugin ) {
            foreach ( $plugin['signatures'] as $format ) {
                $owners[ $format ] = $slug;
            }
        }
        foreach ( self::sweep( $owners ) as $path => $slug ) {
            self::add( $found, $slug, $plugins[ $slug ]['name'], $path );
        }

        $found = array_values( $found );
        usort( $found, static fn ( array $a, array $b ): int => $b['modified'] <=> $a['modified'] );

        return $found;
    }

    /** Lowercase extension of a file name (`a.tar.gz` → `gz`). */
    public static function format( string $file ): string {
        return strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    }

    /** @return string[] existing folders matching a wp-content relative pattern */
    private static function folders( string $pattern ): array {
        $path = WP_CONTENT_DIR . '/' . $pattern;
        $dirs = str_contains( $pattern, '*' ) ? (array) glob( $path, GLOB_ONLYDIR ) : [ $path ];

        return array_values( array_filter( $dirs, 'is_dir' ) );
    }

    /**
     * Files with a signature format in the site root or wp-content.
     *
     * @param array<string,string> $owners format => catalog slug
     * @return array<string,string> path => catalog slug
     */
    private static function sweep( array $owners ): array {
        if ( [] === $owners ) {
            return [];
        }

        $found = [];
        foreach ( ( @scandir( ABSPATH ) ?: [] ) as $file ) {
            $format = self::format( (string) $file );
            if ( isset( $owners[ $format ] ) && is_file( ABSPATH . $file ) ) {
                $found[ ABSPATH . $file ] = $owners[ $format ];
            }
        }

        $visited = 0;
        $queue   = [ [ WP_CONTENT_DIR, 0 ] ];
        while ( [] !== $queue && $visited < self::SWEEP_MAX_ITEMS ) {
            [ $dir, $depth ] = array_shift( $queue );
            foreach ( ( @scandir( $dir ) ?: [] ) as $file ) {
                if ( '.' === $file || '..' === $file || ++$visited > self::SWEEP_MAX_ITEMS ) {
                    continue;
                }
                $path = $dir . '/' . $file;
                if ( is_dir( $path ) && ! is_link( $path ) ) {
                    if ( $depth < self::SWEEP_DEPTH && ! ( 0 === $depth && in_array( $file, self::SWEEP_SKIP, true ) ) ) {
                        $queue[] = [ $path, $depth + 1 ];
                    }
                    continue;
                }
                $format = self::format( (string) $file );
                if ( isset( $owners[ $format ] ) ) {
                    $found[ $path ] = $owners[ $format ];
                }
            }
        }

        return $found;
    }

    /** Adds one file, once, keyed by its real path. */
    private static function add( array &$found, string $slug, string $name, string $path ): void {
        $key = realpath( $path ) ?: $path;
        if ( isset( $found[ $key ] ) ) {
            return;
        }

        $size     = (int) @filesize( $path );
        $modified = (int) @filemtime( $path );
        $found[ $key ] = [
            'plugin'      => $slug,
            'plugin_name' => $name,
            'file'        => basename( $path ),
            'format'      => self::format( $path ),
            'path'        => $path,
            'size'        => $size,
            'size_human'  => size_format( $size ),
            'modified'    => $modified,
            'age_days'    => (int) floor( ( time() - $modified ) / DAY_IN_SECONDS ),
        ];
    }
}
