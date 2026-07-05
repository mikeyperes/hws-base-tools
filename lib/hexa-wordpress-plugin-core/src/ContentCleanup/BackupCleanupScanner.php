<?php

namespace Hexa\PluginCore\ContentCleanup;

final class BackupCleanupScanner {
    private BackupCleanupConfig $config;

    public function __construct( BackupCleanupConfig $config ) {
        $this->config = $config;
    }

    public function scan(): array {
        $log = [
            $this->log( 'info', 'Scanning configured backup locations.' ),
        ];

        foreach ( $this->config->locations() as $location ) {
            $dirs          = $this->directories_for_location( $location );
            $readable_dirs = array_values(
                array_filter(
                    $dirs,
                    static fn( string $dir ): bool => is_dir( $dir ) && is_readable( $dir )
                )
            );

            $log[] = $this->log(
                [] !== $readable_dirs ? 'info' : 'warning',
                [] !== $readable_dirs ? 'Prepared backup scan location.' : 'Backup scan location has no readable directories.',
                [
                    'source'               => (string) ( $location['name'] ?? $location['id'] ?? 'Backup location' ),
                    'configured_path'      => (string) ( $location['path'] ?? '' ),
                    'allowed_extensions'   => array_values( (array) ( $location['extensions'] ?? [] ) ),
                    'resolved_directories' => $dirs,
                    'readable_directories' => $readable_dirs,
                ]
            );
        }

        $rows = $this->rows();
        $log[] = $this->log( 'success', 'Detected ' . count( $rows ) . ' backup file(s).' );

        return [
            'rows'  => $rows,
            'count' => count( $rows ),
            'log'   => $log,
        ];
    }

    public function delete( string $file_id ): array|\WP_Error {
        $file_id = $this->clean_key( $file_id );
        if ( '' === $file_id ) {
            return new \WP_Error( 'missing_file_id', 'Missing backup file ID.' );
        }

        $row = null;
        foreach ( $this->rows() as $candidate ) {
            if ( $file_id === (string) $candidate['id'] ) {
                $row = $candidate;
                break;
            }
        }

        if ( null === $row ) {
            return new \WP_Error( 'backup_not_found', 'Backup file was not found in configured cleanup locations.' );
        }

        $path = (string) $row['path'];
        $log  = [
            $this->log( 'warning', 'Requested backup file deletion.', [ 'file' => $row['file'], 'source' => $row['source'] ] ),
        ];

        if ( ! is_file( $path ) ) {
            return new \WP_Error( 'backup_missing', 'Backup file no longer exists.' );
        }

        if ( ! is_writable( $path ) ) {
            return new \WP_Error( 'backup_not_writable', 'Backup file is not writable.' );
        }

        if ( ! is_writable( dirname( $path ) ) ) {
            return new \WP_Error( 'backup_directory_not_writable', 'Backup file directory is not writable.' );
        }

        if ( ! @unlink( $path ) ) {
            return new \WP_Error( 'backup_delete_failed', 'WordPress could not delete the backup file.' );
        }

        $log[] = $this->log( 'success', 'Deleted backup file.', [ 'file' => $row['file'], 'size' => $row['size_label'] ] );

        return [
            'id'      => $file_id,
            'message' => 'Deleted backup file: ' . $row['file'],
            'log'     => $log,
        ];
    }

    private function rows(): array {
        $rows = [];

        foreach ( $this->config->locations() as $location ) {
            $dirs = $this->directories_for_location( $location );
            foreach ( $dirs as $dir ) {
                if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
                    continue;
                }

                $files = @scandir( $dir );
                if ( ! is_array( $files ) ) {
                    continue;
                }

                foreach ( $files as $file ) {
                    if ( '.' === $file || '..' === $file ) {
                        continue;
                    }

                    $path = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . $file;
                    if ( ! is_file( $path ) ) {
                        continue;
                    }

                    $extension = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
                    if ( ! in_array( $extension, (array) $location['extensions'], true ) ) {
                        continue;
                    }

                    $modified = @filemtime( $path ) ?: 0;
                    $size     = @filesize( $path ) ?: 0;

                    $rows[] = [
                        'id'             => md5( $path ),
                        'source'         => (string) $location['name'],
                        'source_id'      => (string) $location['id'],
                        'file'           => $file,
                        'path'           => $path,
                        'directory'      => $dir,
                        'extension'      => $extension,
                        'size'           => $size,
                        'size_label'     => function_exists( 'size_format' ) ? size_format( $size ) : $this->bytes_label( $size ),
                        'modified'       => $modified,
                        'modified_label' => $modified > 0 && function_exists( 'wp_date' ) ? wp_date( 'M j, Y g:i a', $modified ) : ( $modified > 0 ? gmdate( 'M j, Y H:i', $modified ) : 'Unknown' ),
                        'age_days'       => $modified > 0 ? max( 0, (int) floor( ( time() - $modified ) / DAY_IN_SECONDS ) ) : null,
                        'writable'       => is_writable( $path ) && is_writable( dirname( $path ) ),
                    ];
                }
            }
        }

        usort(
            $rows,
            static fn( array $a, array $b ): int => (int) ( $b['modified'] ?? 0 ) <=> (int) ( $a['modified'] ?? 0 )
        );

        return $rows;
    }

    private function directories_for_location( array $location ): array {
        $path = (string) ( $location['path'] ?? '' );
        if ( '' === $path ) {
            return [];
        }

        if ( false !== strpos( $path, '*' ) ) {
            $dirs = glob( $path, GLOB_ONLYDIR );
            return is_array( $dirs ) ? array_values( $dirs ) : [];
        }

        return [ $path ];
    }

    private function log( string $level, string $message, array $context = [] ): array {
        return [
            'time'    => gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function bytes_label( int $bytes ): string {
        if ( $bytes < 1024 ) {
            return $bytes . ' B';
        }

        return round( $bytes / 1048576, 2 ) . ' MB';
    }
}
