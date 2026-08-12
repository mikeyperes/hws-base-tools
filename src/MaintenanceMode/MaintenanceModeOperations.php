<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

final class MaintenanceModeOperations {
    public const OPERATIONS = [ 'prepare', 'state', 'permalinks', 'cache', 'verify' ];

    /** @return array<string,mixed> */
    public static function run( string $operation, bool $enabled, mixed $template ): array {
        $operation = sanitize_key( $operation );
        $template = MaintenanceSettings::normalize_template( $template );
        if ( ! in_array( $operation, self::OPERATIONS, true ) ) {
            throw new \InvalidArgumentException( 'Unknown maintenance operation.' );
        }

        if ( 'prepare' === $operation ) {
            $document = MaintenanceTemplateRenderer::document( $template );
            if ( ! str_contains( $document, 'hws-maintenance--' . $template ) ) {
                throw new \RuntimeException( 'The selected maintenance template could not be prepared.' );
            }
            self::start_transition( $enabled, $template );
            return self::complete_step( $operation, 'Access and the selected template were validated.', $enabled, $template );
        }

        if ( 'state' === $operation ) {
            MaintenanceSettings::update_state( $enabled, $template );
            return self::complete_step( $operation, $enabled ? 'Maintenance mode is now enabled.' : 'Maintenance mode is now disabled.', $enabled, $template );
        }

        if ( 'permalinks' === $operation ) {
            if ( ! function_exists( 'flush_rewrite_rules' ) ) {
                throw new \RuntimeException( 'WordPress permalink refresh is unavailable.' );
            }
            flush_rewrite_rules( true );
            $rules = get_option( 'rewrite_rules', [] );
            $count = is_array( $rules ) ? count( $rules ) : 0;
            return self::complete_step( $operation, 'Permalinks and rewrite rules were refreshed (' . $count . ' rules).', $enabled, $template );
        }

        if ( 'cache' === $operation ) {
            $purges = self::purge_caches();
            $message = $purges
                ? 'Site caches were purged: ' . implode( ', ', $purges ) . '.'
                : 'No supported persistent page cache required a purge.';
            return self::complete_step( $operation, $message, $enabled, $template );
        }

        $actual_enabled = MaintenanceSettings::enabled();
        $actual_template = MaintenanceSettings::selected_template();
        if ( $enabled !== $actual_enabled || $template !== $actual_template ) {
            throw new \RuntimeException( 'The saved maintenance state did not match the requested state.' );
        }
        $message = $enabled
            ? 'Verified: public requests receive the selected maintenance document with HTTP 503.'
            : 'Verified: normal public site rendering is restored.';
        return self::complete_step( $operation, $message, $enabled, $template, true );
    }

    /** @return array<string,mixed> */
    public static function select_template( mixed $template ): array {
        $template = MaintenanceSettings::update_template( $template );
        $purges = MaintenanceSettings::enabled() ? self::purge_caches() : [];
        return [
            'template' => $template,
            'enabled'  => MaintenanceSettings::enabled(),
            'message'  => MaintenanceSettings::enabled()
                ? 'The live maintenance page now uses ' . MaintenanceSettings::templates()[ $template ]['label'] . '. Caches were refreshed' . ( $purges ? ': ' . implode( ', ', $purges ) : '' ) . '.'
                : MaintenanceSettings::templates()[ $template ]['label'] . ' is selected for the next maintenance window.',
        ];
    }

    /** @return string[] */
    private static function purge_caches(): array {
        $purges = [];
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
            $purges[] = 'WordPress object cache';
        }
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
            $purges[] = 'WP Super Cache';
        }
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
            $purges[] = 'W3 Total Cache';
        }
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
            $purges[] = 'WP Rocket';
        }
        if ( function_exists( 'do_action' ) ) {
            do_action( 'litespeed_purge_all' );
            $purges[] = 'LiteSpeed';
        }
        return array_values( array_unique( $purges ) );
    }

    private static function start_transition( bool $enabled, string $template ): void {
        update_option(
            MaintenanceSettings::TRANSITION_OPTION,
            [
                'enabled'    => $enabled,
                'template'   => $template,
                'started_at' => self::timestamp(),
                'steps'      => [],
                'complete'   => false,
            ],
            false
        );
    }

    /** @return array<string,mixed> */
    private static function complete_step( string $operation, string $message, bool $enabled, string $template, bool $complete = false ): array {
        $transition = MaintenanceSettings::last_transition();
        $steps = isset( $transition['steps'] ) && is_array( $transition['steps'] ) ? $transition['steps'] : [];
        $steps[ $operation ] = [ 'message' => $message, 'completed_at' => self::timestamp() ];
        $transition = array_merge(
            $transition,
            [
                'enabled'  => $enabled,
                'template' => $template,
                'steps'    => $steps,
                'complete' => $complete,
            ]
        );
        if ( $complete ) {
            $transition['completed_at'] = self::timestamp();
        }
        update_option( MaintenanceSettings::TRANSITION_OPTION, $transition, false );

        return [
            'operation'  => $operation,
            'message'    => $message,
            'enabled'    => MaintenanceSettings::enabled(),
            'template'   => MaintenanceSettings::selected_template(),
            'transition' => $transition,
        ];
    }

    private static function timestamp(): string {
        return function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
    }
}
