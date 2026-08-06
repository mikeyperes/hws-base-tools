<?php

namespace HWS\BaseTools\ReviewCenter;

use HWS\BaseTools\QuickStart\QuickStartSnapshotStore;

final class ReviewTaskRunner {
    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public static function run( array $payload ): array {
        $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
        $inputs  = is_array( $payload['inputs'] ?? null ) ? $payload['inputs'] : [];
        $task    = self::clean_key( (string) ( $context['review_task'] ?? '' ) );

        $capability = self::authorize_capabilities( $task );
        if ( ! $capability['success'] ) {
            return $capability;
        }

        if ( self::is_multisite_code_removal( $task ) ) {
            return self::result(
                false,
                'Plugin and theme removal from Review Center is unsupported on multisite because it can affect other sites in the network.',
                [ 'code' => 'multisite_unsupported', 'cleanup_supported' => false ]
            );
        }

        if ( '' !== self::confirmation_text( $task ) ) {
            $confirmation = is_scalar( $inputs['confirmation'] ?? null ) ? trim( (string) $inputs['confirmation'] ) : '';
            if ( ! hash_equals( self::confirmation_text( $task ), $confirmation ) ) {
                return self::result(
                    false,
                    'The typed confirmation is missing or incorrect. No cleanup ran.',
                    [ 'code' => 'confirmation_required' ]
                );
            }
        }

        return match ( $task ) {
            'scan_comments'           => self::review_scan( 'scan_comments', 'delete_comments', ReviewScanner::comments(), 'No comments found.', 'Comments are present for review.' ),
            'delete_comments'         => self::delete_comments(),
            'scan_migration'          => self::review_scan( 'scan_migration', 'remove_migration', ReviewScanner::migration(), 'No migration plugins found.', 'Migration plugins are still installed.' ),
            'remove_migration'        => self::remove_plugins( 'scan_migration', 'remove_migration', ReviewScanner::migration(), false ),
            'scan_duplicate_caches'   => self::review_scan( 'scan_duplicate_caches', 'remove_duplicate_caches', ReviewScanner::duplicate_caches(), 'No duplicate cache plugins found.', 'Other cache/optimization plugins are installed.' ),
            'remove_duplicate_caches' => self::remove_duplicate_caches(),
            'scan_inactive_plugins'   => self::review_scan( 'scan_inactive_plugins', 'remove_inactive_plugins', ReviewScanner::inactive_plugins(), 'No removable inactive plugins found.', 'Inactive plugins are available for review.' ),
            'remove_inactive_plugins' => self::remove_plugins( 'scan_inactive_plugins', 'remove_inactive_plugins', ReviewScanner::inactive_plugins(), true ),
            'scan_inactive_themes'    => self::review_scan( 'scan_inactive_themes', 'remove_inactive_themes', ReviewScanner::inactive_themes(), 'No removable inactive themes found.', 'Inactive themes are available for review.' ),
            'remove_inactive_themes'  => self::remove_themes(),
            'scan_sample_content'     => self::review_scan( 'scan_sample_content', 'remove_sample_content', ReviewScanner::sample_content(), 'No untouched default sample content found.', 'Untouched default sample content is present.' ),
            'remove_sample_content'   => self::trash_sample_content(),
            'scan_cleanup_tools'      => self::review_scan( 'scan_cleanup_tools', 'remove_cleanup_tools', ReviewScanner::cleanup_tools(), 'No temporary cleanup plugins found.', 'Temporary cleanup plugins are installed.' ),
            'remove_cleanup_tools'    => self::remove_plugins( 'scan_cleanup_tools', 'remove_cleanup_tools', ReviewScanner::cleanup_tools(), false ),
            'scan_cleanup_files'      => self::review_scan( 'scan_cleanup_files', 'remove_cleanup_files', ReviewScanner::cleanup_files(), 'No eligible log or old backup files found.', 'Eligible log or old backup files are present.' ),
            'remove_cleanup_files'    => self::remove_files(),
            'scan_snapshot'           => self::review_scan( 'scan_snapshot', 'restore_snapshot', self::snapshot_scan(), 'No verified Quick Start before-state snapshot is stored.', 'A verified Quick Start option before-state is available.' ),
            'restore_snapshot'        => self::restore_snapshot(),
            default                   => self::result( false, 'Unknown Review Center task: ' . $task, [ 'code' => 'unknown_task' ] ),
        };
    }

    public static function confirmation_text( string $task ): string {
        return match ( self::clean_key( $task ) ) {
            'delete_comments'         => 'DELETE REVIEWED COMMENTS',
            'remove_migration'        => 'REMOVE REVIEWED MIGRATION PLUGINS',
            'remove_duplicate_caches' => 'REMOVE REVIEWED CACHE CONFLICTS',
            'remove_inactive_plugins' => 'REMOVE REVIEWED INACTIVE PLUGINS',
            'remove_inactive_themes'  => 'REMOVE REVIEWED INACTIVE THEMES',
            'remove_sample_content'   => 'TRASH REVIEWED SAMPLE CONTENT',
            'remove_cleanup_tools'    => 'REMOVE REVIEWED CLEANUP TOOLS',
            'remove_cleanup_files'    => 'DELETE REVIEWED CLEANUP FILES',
            'restore_snapshot'        => 'RESTORE REVIEWED OPTIONS',
            default                   => '',
        };
    }

    /** @return array<int,string> */
    public static function required_capabilities( string $task ): array {
        return match ( self::clean_key( $task ) ) {
            'delete_comments' => [ 'manage_options', 'moderate_comments' ],
            'remove_migration',
            'remove_duplicate_caches',
            'remove_cleanup_tools' => [ 'manage_options', 'delete_plugins', 'activate_plugins' ],
            'remove_inactive_plugins' => [ 'manage_options', 'delete_plugins' ],
            'remove_inactive_themes' => [ 'manage_options', 'delete_themes' ],
            'remove_sample_content' => [ 'manage_options', 'delete_posts', 'delete_pages' ],
            default => [ 'manage_options' ],
        };
    }

    /** @return array<string,mixed> */
    private static function review_scan( string $scan_task, string $cleanup_task, array $scan, string $clean_message, string $review_message ): array {
        $count   = isset( $scan['count'] ) ? (int) $scan['count'] : count( (array) ( $scan['matches'] ?? [] ) );
        $message = 0 === $count ? $clean_message : $review_message . ' (' . $count . ')';
        $data    = array_merge( $scan, [ 'review_count' => $count ] );

        if ( array_key_exists( 'destructive_supported', $scan ) && empty( $scan['destructive_supported'] ) ) {
            $reason = trim( (string) ( $scan['unsupported_reason'] ?? '' ) );
            return self::result(
                true,
                $message . ( '' !== $reason ? ' Cleanup unavailable: ' . $reason : '' ),
                array_merge( $data, [ 'cleanup_supported' => false, 'scan_ready' => false ] )
            );
        }

        $stored = ( new ReviewScanStore() )->save( $scan_task, $cleanup_task, $scan );
        if ( empty( $stored['success'] ) ) {
            return self::result(
                false,
                (string) ( $stored['message'] ?? 'The reviewed scan could not be stored.' ),
                array_merge( $data, [ 'code' => (string) ( $stored['code'] ?? 'scan_store_failed' ), 'scan_ready' => false ] )
            );
        }

        return self::result(
            true,
            $message . ' The cleanup authorization expires in 15 minutes.',
            array_merge(
                $data,
                [
                    'cleanup_supported' => true,
                    'scan_ready'        => true,
                    'scan_digest'       => (string) $stored['digest'],
                    'scan_expires_at'   => gmdate( 'c', (int) $stored['expires_at'] ),
                ]
            )
        );
    }

    /** @return array<string,mixed> */
    private static function delete_comments(): array {
        $scan = ReviewScanner::comments();
        $authorization = self::authorize_scan( 'scan_comments', 'delete_comments', $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }

        $ids = [];
        foreach ( (array) ( $authorization['binding']['matches'] ?? [] ) as $match ) {
            $id = is_array( $match ) ? (int) ( $match['id'] ?? 0 ) : 0;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        if ( [] === $ids ) {
            return self::result( true, 'No reviewed comments required deletion.', [ 'deleted' => [], 'failed' => [] ] );
        }
        if ( ! function_exists( 'wp_delete_comment' ) ) {
            return self::result( false, 'The WordPress comment deletion API is unavailable.', [ 'code' => 'api_unavailable' ] );
        }

        $deleted = [];
        $failed  = [];
        foreach ( $ids as $id ) {
            if ( wp_delete_comment( $id, true ) ) {
                $deleted[] = $id;
            } else {
                $failed[] = $id;
            }
        }
        return self::result(
            [] === $failed,
            count( $deleted ) . ' reviewed comment(s) permanently deleted; ' . count( $failed ) . ' failed.',
            [ 'deleted' => $deleted, 'failed' => $failed, 'permanent' => true ]
        );
    }

    /** @return array<string,mixed> */
    private static function remove_duplicate_caches(): array {
        $scan = ReviewScanner::duplicate_caches();
        if ( empty( $scan['litespeed_active'] ) ) {
            return self::result( false, 'LiteSpeed Cache must remain active before reviewed cache conflicts can be removed.', [ 'code' => 'litespeed_required' ] );
        }
        return self::remove_plugins( 'scan_duplicate_caches', 'remove_duplicate_caches', $scan, false );
    }

    /** @return array<string,mixed> */
    private static function remove_plugins( string $scan_task, string $cleanup_task, array $scan, bool $inactive_only ): array {
        self::load_plugin_api();
        $authorization = self::authorize_scan( $scan_task, $cleanup_task, $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }

        $targets = [];
        foreach ( (array) ( $authorization['binding']['matches'] ?? [] ) as $match ) {
            $file = is_array( $match ) ? (string) ( $match['file'] ?? '' ) : '';
            if ( '' === $file || str_starts_with( $file, 'hws-base-tools/' ) ) {
                continue;
            }
            if ( $inactive_only && self::is_active_plugin( $file ) ) {
                return self::result( false, 'A reviewed plugin became active. No plugins were removed.', [ 'code' => 'target_changed', 'file' => $file ] );
            }
            $targets[] = $file;
        }
        $targets = array_values( array_unique( $targets ) );
        if ( [] === $targets ) {
            return self::result( true, 'No reviewed plugins required removal.', [ 'deleted' => [], 'failed' => [] ] );
        }
        if ( ! function_exists( 'delete_plugins' ) ) {
            return self::result( false, 'The WordPress plugin deletion API is unavailable.', [ 'code' => 'api_unavailable' ] );
        }

        if ( ! $inactive_only ) {
            $active = array_values( array_filter( $targets, [ self::class, 'is_active_plugin' ] ) );
            if ( [] !== $active ) {
                if ( ! function_exists( 'deactivate_plugins' ) ) {
                    return self::result( false, 'The WordPress plugin deactivation API is unavailable.', [ 'code' => 'api_unavailable' ] );
                }
                deactivate_plugins( $active, false, false );
                $still_active = array_values( array_filter( $active, [ self::class, 'is_active_plugin' ] ) );
                if ( [] !== $still_active ) {
                    return self::result( false, 'One or more reviewed plugins could not be deactivated. No plugin deletion ran.', [ 'failed' => $still_active ] );
                }
            }
        }

        $result = delete_plugins( $targets );
        $failed = [];
        if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
            $failed = $targets;
        } elseif ( defined( 'WP_PLUGIN_DIR' ) ) {
            foreach ( $targets as $file ) {
                if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
                    $failed[] = $file;
                }
            }
        } elseif ( true !== $result ) {
            $failed = $targets;
        }

        $deleted = array_values( array_diff( $targets, $failed ) );
        return self::result( [] === $failed, count( $deleted ) . ' reviewed plugin(s) removed; ' . count( $failed ) . ' failed.', [ 'deleted' => $deleted, 'failed' => $failed ] );
    }

    /** @return array<string,mixed> */
    private static function remove_themes(): array {
        if ( defined( 'ABSPATH' ) && ! function_exists( 'delete_theme' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        if ( ! function_exists( 'delete_theme' ) ) {
            return self::result( false, 'The WordPress theme deletion API is unavailable.', [ 'code' => 'api_unavailable' ] );
        }

        $scan = ReviewScanner::inactive_themes();
        $authorization = self::authorize_scan( 'scan_inactive_themes', 'remove_inactive_themes', $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }

        $protected = (array) ( $authorization['binding']['protected'] ?? [] );
        $deleted   = [];
        $failed    = [];
        foreach ( (array) ( $authorization['binding']['matches'] ?? [] ) as $theme ) {
            $slug = is_array( $theme ) ? (string) ( $theme['slug'] ?? '' ) : '';
            if ( '' === $slug || in_array( $slug, $protected, true ) ) {
                continue;
            }
            $result = delete_theme( $slug );
            if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || false === $result ) {
                $failed[] = $slug;
            } else {
                $deleted[] = $slug;
            }
        }

        return self::result( [] === $failed, count( $deleted ) . ' reviewed inactive theme(s) removed; ' . count( $failed ) . ' failed.', [ 'deleted' => $deleted, 'failed' => $failed, 'protected' => $protected ] );
    }

    /** @return array<string,mixed> */
    private static function trash_sample_content(): array {
        $scan = ReviewScanner::sample_content();
        $authorization = self::authorize_scan( 'scan_sample_content', 'remove_sample_content', $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }
        if ( ! function_exists( 'wp_trash_post' ) ) {
            return self::result( false, 'The WordPress Trash API is unavailable.', [ 'code' => 'api_unavailable' ] );
        }

        $trashed = [];
        $failed  = [];
        foreach ( (array) ( $authorization['binding']['matches'] ?? [] ) as $post ) {
            $id = is_array( $post ) ? (int) ( $post['id'] ?? 0 ) : 0;
            if ( $id <= 0 ) {
                continue;
            }
            if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'delete_post', $id ) ) {
                $failed[] = $id;
                continue;
            }

            $result = wp_trash_post( $id );
            $is_trashed = false !== $result && null !== $result;
            if ( $is_trashed && function_exists( 'get_post_status' ) ) {
                $is_trashed = 'trash' === (string) get_post_status( $id );
            }
            if ( $is_trashed ) {
                $trashed[] = $id;
            } else {
                $failed[] = $id;
            }
        }

        return self::result( [] === $failed, count( $trashed ) . ' reviewed sample item(s) moved to Trash; ' . count( $failed ) . ' failed.', [ 'trashed' => $trashed, 'failed' => $failed, 'permanent' => false ] );
    }

    /** @return array<string,mixed> */
    private static function remove_files(): array {
        $scan = ReviewScanner::cleanup_files();
        $authorization = self::authorize_scan( 'scan_cleanup_files', 'remove_cleanup_files', $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }
        if ( ! function_exists( 'wp_delete_file' ) ) {
            return self::result( false, 'The WordPress file deletion API is unavailable.', [ 'code' => 'api_unavailable' ] );
        }

        $deleted = [];
        $failed  = [];
        foreach ( (array) ( $authorization['binding']['matches'] ?? [] ) as $file ) {
            $path = is_array( $file ) ? (string) ( $file['path'] ?? '' ) : '';
            if ( '' === $path || ! is_file( $path ) || ! is_writable( $path ) ) {
                $failed[] = $path;
                continue;
            }
            wp_delete_file( $path );
            if ( ! file_exists( $path ) ) {
                $deleted[] = $path;
            } else {
                $failed[] = $path;
            }
        }

        return self::result( [] === $failed, count( $deleted ) . ' reviewed log/backup file(s) removed; ' . count( $failed ) . ' failed.', [ 'deleted' => $deleted, 'failed' => $failed ] );
    }

    /** @return array<string,mixed> */
    private static function restore_snapshot(): array {
        $scan = self::snapshot_scan();
        $authorization = self::authorize_scan( 'scan_snapshot', 'restore_snapshot', $scan );
        if ( empty( $authorization['success'] ) ) {
            return $authorization;
        }
        return self::operation( ( new QuickStartSnapshotStore() )->restore_options(), 'Reviewed Quick Start options were restored and verified.' );
    }

    /** @return array<string,mixed> */
    private static function snapshot_scan(): array {
        $status    = ( new QuickStartSnapshotStore() )->status();
        $available = ! empty( $status['available'] ) && ! empty( $status['verified'] );
        return [
            'success'               => ! $available,
            'matches'               => $available ? [ [
                'snapshot_id' => (string) ( $status['snapshot_id'] ?? '' ),
                'digest'      => (string) ( $status['digest'] ?? '' ),
                'scope'       => is_array( $status['scope'] ?? null ) ? $status['scope'] : [],
            ] ] : [],
            'snapshot_id'            => (string) ( $status['snapshot_id'] ?? '' ),
            'snapshot_digest'        => (string) ( $status['digest'] ?? '' ),
            'snapshot_scope'         => is_array( $status['scope'] ?? null ) ? $status['scope'] : [],
            'captured_at'            => (string) ( $status['captured_at'] ?? '' ),
            'history_count'          => (int) ( $status['history_count'] ?? 0 ),
            'destructive_supported'  => $available,
            'unsupported_reason'     => $available ? '' : (string) ( $status['message'] ?? 'No verified snapshot is available.' ),
        ];
    }

    /** @return array<string,mixed> */
    private static function authorize_scan( string $scan_task, string $cleanup_task, array $fresh_scan ): array {
        if ( array_key_exists( 'destructive_supported', $fresh_scan ) && empty( $fresh_scan['destructive_supported'] ) ) {
            return self::result(
                false,
                (string) ( $fresh_scan['unsupported_reason'] ?? 'This cleanup is not supported in the current site context.' ),
                [ 'code' => 'cleanup_unsupported' ]
            );
        }

        $authorization = ( new ReviewScanStore() )->authorize_and_consume( $scan_task, $cleanup_task, $fresh_scan );
        if ( empty( $authorization['success'] ) ) {
            return self::result(
                false,
                (string) ( $authorization['message'] ?? 'The reviewed scan could not be verified.' ),
                [ 'code' => (string) ( $authorization['code'] ?? 'scan_invalid' ) ]
            );
        }
        return $authorization;
    }

    /** @return array<string,mixed> */
    private static function authorize_capabilities( string $task ): array {
        $missing = [];
        foreach ( self::required_capabilities( $task ) as $capability ) {
            if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $capability ) ) {
                $missing[] = $capability;
            }
        }
        if ( [] !== $missing ) {
            return self::result(
                false,
                'You do not have the WordPress capabilities required for this Review Center task.',
                [ 'code' => 'forbidden', 'required_capabilities' => self::required_capabilities( $task ), 'missing_capabilities' => $missing ]
            );
        }
        return [ 'success' => true ];
    }

    private static function is_multisite_code_removal( string $task ): bool {
        return function_exists( 'is_multisite' )
            && is_multisite()
            && in_array(
                $task,
                [ 'remove_migration', 'remove_duplicate_caches', 'remove_inactive_plugins', 'remove_inactive_themes', 'remove_cleanup_tools' ],
                true
            );
    }

    /** @return array<string,mixed> */
    private static function operation( mixed $operation, string $fallback ): array {
        if ( function_exists( 'is_wp_error' ) && is_wp_error( $operation ) ) {
            return self::result( false, $operation->get_error_message(), [ 'code' => $operation->get_error_code() ] );
        }
        if ( ! is_array( $operation ) ) {
            return self::result( false, $fallback, [ 'code' => 'unexpected_result' ] );
        }
        $message = trim( (string) ( $operation['message'] ?? '' ) );
        if ( '' === $message && is_array( $operation['messages'] ?? null ) ) {
            $message = implode( ' ', array_map( 'strval', $operation['messages'] ) );
        }
        return self::result( ! empty( $operation['success'] ), '' !== $message ? $message : $fallback, $operation );
    }

    /** @return array<string,mixed> */
    private static function result( bool $success, string $message, array $data = [] ): array {
        return [ 'success' => $success, 'message' => $message, 'data' => $data ];
    }

    public static function is_active_plugin( string $file ): bool {
        return ( function_exists( 'is_plugin_active' ) && is_plugin_active( $file ) )
            || ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) );
    }

    private static function load_plugin_api(): void {
        if ( defined( 'ABSPATH' ) && ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' )
            ? sanitize_key( $value )
            : (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
    }
}
