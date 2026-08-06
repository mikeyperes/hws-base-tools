<?php

namespace HWS\BaseTools\QuickStart;

final class QuickStartSnapshotStore {
    public const OPTION = 'hws_quick_start_snapshot';
    public const SCHEMA_VERSION = 2;
    public const SNAPSHOT_KIND = 'wordpress_option_before_state';
    public const MAX_HISTORY = 5;

    /** @return array<string,mixed> */
    public function capture(): array {
        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'snapshot_kind'  => self::SNAPSHOT_KIND,
            'snapshot_id'    => $this->new_snapshot_id(),
            'label'          => 'Quick Start WordPress option before-state',
            'captured_at'    => gmdate( 'c' ),
            'captured_unix'  => time(),
            'scope'          => $this->current_scope(),
            'wordpress'      => isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '',
            'php'            => PHP_VERSION,
            'options'        => [],
            'constants'      => [],
            'plugins'        => $this->plugin_versions(),
            'themes'         => $this->theme_versions(),
        ];

        foreach ( self::restorable_options() as $option ) {
            $snapshot['options'][ $option ] = $this->read_option_state( $option );
        }

        foreach ( [ 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_DEBUG', 'WP_DEBUG_DISPLAY', 'WP_DEBUG_LOG', 'WP_AUTO_UPDATE_CORE', 'DISABLE_WP_CRON' ] as $constant ) {
            $snapshot['constants'][ $constant ] = defined( $constant ) ? constant( $constant ) : null;
        }

        $snapshot['payload_digest'] = self::snapshot_digest( $snapshot );
        $history = $this->verified_history( true );
        array_unshift( $history, $snapshot );
        $history = array_slice( $history, 0, self::MAX_HISTORY );

        $storage = [
            'schema_version' => self::SCHEMA_VERSION,
            'storage_kind'   => 'quick_start_before_state_history',
            'snapshots'      => $history,
        ];
        if ( ! function_exists( 'update_option' ) || ! function_exists( 'get_option' ) ) {
            throw new \RuntimeException( 'WordPress option storage is unavailable for the Quick Start before-state.' );
        }

        update_option( self::OPTION, $storage, false );
        $persisted = $this->raw_history();
        $latest    = $persisted[0] ?? [];
        $check     = is_array( $latest ) ? $this->verify_snapshot( $latest, true ) : [ 'success' => false ];
        if ( empty( $check['success'] ) || (string) ( $latest['snapshot_id'] ?? '' ) !== (string) $snapshot['snapshot_id'] ) {
            throw new \RuntimeException( 'The Quick Start before-state could not be verified after storage.' );
        }

        return $snapshot;
    }

    /** @return array<string,mixed> */
    public function latest(): array {
        $history = $this->raw_history();
        $latest  = $history[0] ?? [];
        if ( ! is_array( $latest ) || empty( $this->verify_snapshot( $latest, true )['success'] ) ) {
            return [];
        }
        return $latest;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(): array {
        return $this->verified_history( true );
    }

    /** @return array<string,mixed> */
    public function status(): array {
        $history = $this->raw_history();
        if ( [] === $history ) {
            return [
                'available'     => false,
                'verified'      => false,
                'message'       => 'No versioned Quick Start WordPress option before-state is stored.',
                'history_count' => 0,
            ];
        }

        $latest = $history[0];
        if ( ! is_array( $latest ) ) {
            return [
                'available'     => false,
                'verified'      => false,
                'message'       => 'The latest Quick Start before-state has an invalid format.',
                'history_count' => 0,
            ];
        }

        $check = $this->verify_snapshot( $latest, true );
        if ( empty( $check['success'] ) ) {
            return [
                'available'     => false,
                'verified'      => false,
                'message'       => (string) ( $check['message'] ?? 'The latest Quick Start before-state failed verification.' ),
                'history_count' => count( $this->verified_history( true ) ),
            ];
        }

        return [
            'available'     => true,
            'verified'      => true,
            'message'       => 'The latest Quick Start WordPress option before-state is verified for this site.',
            'snapshot_id'   => (string) $latest['snapshot_id'],
            'digest'        => (string) $latest['payload_digest'],
            'captured_at'   => (string) $latest['captured_at'],
            'scope'         => (array) $latest['scope'],
            'history_count' => count( $this->verified_history( true ) ),
            'label'         => (string) $latest['label'],
        ];
    }

    /**
     * Restores only the approved WordPress options, verifies every resulting
     * value, and rolls back the attempted restore if any verification fails.
     *
     * @return array<string,mixed>
     */
    public function restore_options(): array {
        $history  = $this->raw_history();
        $snapshot = $history[0] ?? [];
        if ( ! is_array( $snapshot ) ) {
            return $this->restore_failure( 'No versioned Quick Start WordPress option before-state is available.', 'snapshot_missing' );
        }

        $check = $this->verify_snapshot( $snapshot, true );
        if ( empty( $check['success'] ) ) {
            return $this->restore_failure(
                (string) ( $check['message'] ?? 'The Quick Start before-state failed verification.' ),
                (string) ( $check['code'] ?? 'snapshot_invalid' )
            );
        }
        if ( ! function_exists( 'update_option' ) || ! function_exists( 'delete_option' ) || ! function_exists( 'get_option' ) ) {
            return $this->restore_failure( 'WordPress option APIs are unavailable.', 'api_unavailable' );
        }

        $options = (array) $snapshot['options'];
        $before  = [];
        foreach ( self::restorable_options() as $option ) {
            $before[ $option ] = $this->read_option_state( $option );
        }

        $restored = [];
        $attempted = [];
        $failed   = [];
        foreach ( self::restorable_options() as $option ) {
            $desired = $options[ $option ];
            $attempted[] = $option;
            $this->apply_option_state( $option, $desired );
            if ( ! self::option_states_equal( $desired, $this->read_option_state( $option ) ) ) {
                $failed[] = $option;
                break;
            }
            $restored[] = $option;
        }
        if ( [] === $failed ) {
            foreach ( self::restorable_options() as $option ) {
                if ( ! self::option_states_equal( $options[ $option ], $this->read_option_state( $option ) ) ) {
                    $failed[] = $option;
                }
            }
        }

        if ( [] !== $failed ) {
            foreach ( array_reverse( $attempted ) as $option ) {
                $this->apply_option_state( $option, $before[ $option ] );
            }
            $rollback_failed = [];
            foreach ( $attempted as $option ) {
                if ( ! self::option_states_equal( $before[ $option ], $this->read_option_state( $option ) ) ) {
                    $rollback_failed[] = $option;
                }
            }
            return [
                'success'           => false,
                'message'           => 'Option restore verification failed; attempted changes were rolled back.',
                'code'              => 'restore_verification_failed',
                'snapshot_id'       => (string) $snapshot['snapshot_id'],
                'restored'          => [],
                'failed'            => $failed,
                'rollback_verified' => [] === $rollback_failed,
                'rollback_failed'   => $rollback_failed,
            ];
        }

        return [
            'success'       => true,
            'message'       => count( $restored ) . ' approved WordPress option(s) restored and verified from the reviewed before-state.',
            'snapshot_id'   => (string) $snapshot['snapshot_id'],
            'restored'      => $restored,
            'verified'      => true,
            'scope'         => (array) $snapshot['scope'],
        ];
    }

    /** @return array<int,string> */
    public static function restorable_options(): array {
        return [
            'default_comment_status',
            'default_ping_status',
            'close_comments_for_old_posts',
            'auto_update_plugins',
            'auto_update_themes',
            'enable_auto_update_plugins',
            'enable_auto_update_themes',
            'permalink_structure',
            'rewrite_rules',
            'hws_litespeed_active_profile',
        ];
    }

    /** @param array<string,mixed> $snapshot
     *  @return array<string,mixed>
     */
    private function verify_snapshot( array $snapshot, bool $require_current_scope ): array {
        if ( self::SCHEMA_VERSION !== (int) ( $snapshot['schema_version'] ?? 0 )
            || self::SNAPSHOT_KIND !== (string) ( $snapshot['snapshot_kind'] ?? '' )
            || '' === (string) ( $snapshot['snapshot_id'] ?? '' )
        ) {
            return [ 'success' => false, 'code' => 'snapshot_version', 'message' => 'The latest Quick Start snapshot is not a supported versioned before-state.' ];
        }

        $digest = (string) ( $snapshot['payload_digest'] ?? '' );
        if ( 64 !== strlen( $digest ) || ! hash_equals( $digest, self::snapshot_digest( $snapshot ) ) ) {
            return [ 'success' => false, 'code' => 'snapshot_integrity', 'message' => 'The latest Quick Start before-state failed its integrity check.' ];
        }

        $options = is_array( $snapshot['options'] ?? null ) ? $snapshot['options'] : [];
        $keys    = array_keys( $options );
        $allowed = self::restorable_options();
        sort( $keys, SORT_STRING );
        sort( $allowed, SORT_STRING );
        if ( $keys !== $allowed ) {
            return [ 'success' => false, 'code' => 'snapshot_options', 'message' => 'The before-state option scope is incomplete or contains an unapproved key.' ];
        }
        foreach ( $options as $state ) {
            if ( ! self::valid_option_state( $state ) ) {
                return [ 'success' => false, 'code' => 'snapshot_option_state', 'message' => 'The before-state contains an invalid option value record.' ];
            }
        }

        if ( $require_current_scope && ! self::option_states_equal( (array) ( $snapshot['scope'] ?? [] ), $this->current_scope() ) ) {
            return [ 'success' => false, 'code' => 'snapshot_scope', 'message' => 'The before-state belongs to a different WordPress site or network scope.' ];
        }

        return [ 'success' => true ];
    }

    /** @return array<int,array<string,mixed>> */
    private function raw_history(): array {
        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }
        $storage = get_option( self::OPTION, [] );
        if ( ! is_array( $storage )
            || self::SCHEMA_VERSION !== (int) ( $storage['schema_version'] ?? 0 )
            || 'quick_start_before_state_history' !== (string) ( $storage['storage_kind'] ?? '' )
            || ! is_array( $storage['snapshots'] ?? null )
        ) {
            return [];
        }
        return array_values( $storage['snapshots'] );
    }

    /** @return array<int,array<string,mixed>> */
    private function verified_history( bool $require_current_scope ): array {
        $verified = [];
        foreach ( $this->raw_history() as $snapshot ) {
            if ( ! is_array( $snapshot ) || empty( $this->verify_snapshot( $snapshot, $require_current_scope )['success'] ) ) {
                continue;
            }
            $verified[] = $snapshot;
        }
        return array_slice( $verified, 0, self::MAX_HISTORY );
    }

    /** @return array{exists:bool,value:mixed} */
    private function read_option_state( string $option ): array {
        $marker = '__hws_missing_option_' . hash( 'sha256', $option . '|' . self::OPTION );
        $value  = function_exists( 'get_option' ) ? get_option( $option, $marker ) : $marker;
        return [
            'exists' => $marker !== $value,
            'value'  => $marker === $value ? null : $value,
        ];
    }

    /** @param array{exists:bool,value:mixed} $state */
    private function apply_option_state( string $option, array $state ): void {
        if ( ! empty( $state['exists'] ) ) {
            update_option( $option, $state['value'] );
            return;
        }
        delete_option( $option );
    }

    private static function valid_option_state( mixed $state ): bool {
        return is_array( $state )
            && array_key_exists( 'exists', $state )
            && is_bool( $state['exists'] )
            && array_key_exists( 'value', $state )
            && ( $state['exists'] || null === $state['value'] );
    }

    private static function option_states_equal( mixed $left, mixed $right ): bool {
        return hash_equals( hash( 'sha256', serialize( $left ) ), hash( 'sha256', serialize( $right ) ) );
    }

    /** @param array<string,mixed> $snapshot */
    private static function snapshot_digest( array $snapshot ): string {
        unset( $snapshot['payload_digest'] );
        return hash( 'sha256', serialize( $snapshot ) );
    }

    /** @return array<string,mixed> */
    private function current_scope(): array {
        $home    = function_exists( 'get_option' ) ? rtrim( (string) get_option( 'home', '' ), '/' ) : '';
        $siteurl = function_exists( 'get_option' ) ? rtrim( (string) get_option( 'siteurl', '' ), '/' ) : '';
        return [
            'blog_id'      => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
            'network_id'   => function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 0,
            'home_hash'    => hash( 'sha256', $home ),
            'siteurl_hash' => hash( 'sha256', $siteurl ),
        ];
    }

    private function new_snapshot_id(): string {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return (string) wp_generate_uuid4();
        }
        try {
            return bin2hex( random_bytes( 16 ) );
        } catch ( \Throwable ) {
            return hash( 'sha256', uniqid( 'hws-before-state-', true ) );
        }
    }

    /** @return array<string,mixed> */
    private function restore_failure( string $message, string $code ): array {
        return [ 'success' => false, 'message' => $message, 'code' => $code, 'restored' => [] ];
    }

    /** @return array<string,string> */
    private function plugin_versions(): array {
        if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $versions = [];
        foreach ( function_exists( 'get_plugins' ) ? get_plugins() : [] as $file => $data ) {
            $versions[ (string) $file ] = (string) ( $data['Version'] ?? '' );
        }
        ksort( $versions );
        return $versions;
    }

    /** @return array<string,string> */
    private function theme_versions(): array {
        $versions = [];
        foreach ( function_exists( 'wp_get_themes' ) ? wp_get_themes() : [] as $slug => $theme ) {
            $versions[ (string) $slug ] = is_object( $theme ) && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';
        }
        ksort( $versions );
        return $versions;
    }
}
