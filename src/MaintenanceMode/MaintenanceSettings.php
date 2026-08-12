<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

final class MaintenanceSettings {
    public const ENABLED_OPTION = 'hws_maintenance_mode_enabled';
    public const TEMPLATE_OPTION = 'hws_maintenance_mode_template';
    public const TRANSITION_OPTION = 'hws_maintenance_mode_last_transition';
    public const DEFAULT_TEMPLATE = 'focused';

    /** @return array<string,array{label:string,description:string}> */
    public static function templates(): array {
        return [
            'focused' => [
                'label'       => 'Focused',
                'description' => 'A crisp centered announcement with a quiet progress treatment.',
            ],
            'editorial' => [
                'label'       => 'Editorial',
                'description' => 'A publication-inspired split layout with bold serif typography.',
            ],
            'blueprint' => [
                'label'       => 'Blueprint',
                'description' => 'A technical status board with structured service indicators.',
            ],
            'aurora' => [
                'label'       => 'Aurora',
                'description' => 'A vivid atmospheric design with layered gradients and glass panels.',
            ],
            'minimal' => [
                'label'       => 'Minimal',
                'description' => 'An ultra-clean black-and-white notice with oversized type.',
            ],
        ];
    }

    public static function enabled(): bool {
        return in_array( get_option( self::ENABLED_OPTION, '0' ), [ true, 1, '1', 'yes', 'on' ], true );
    }

    public static function selected_template(): string {
        return self::normalize_template( get_option( self::TEMPLATE_OPTION, self::DEFAULT_TEMPLATE ) );
    }

    public static function normalize_template( mixed $template ): string {
        $template = sanitize_key( (string) $template );
        return array_key_exists( $template, self::templates() ) ? $template : self::DEFAULT_TEMPLATE;
    }

    public static function update_state( bool $enabled, mixed $template ): void {
        update_option( self::TEMPLATE_OPTION, self::normalize_template( $template ), false );
        update_option( self::ENABLED_OPTION, $enabled ? '1' : '0', false );
    }

    public static function update_template( mixed $template ): string {
        $template = self::normalize_template( $template );
        update_option( self::TEMPLATE_OPTION, $template, false );
        return $template;
    }

    /** @return array<string,mixed> */
    public static function last_transition(): array {
        $transition = get_option( self::TRANSITION_OPTION, [] );
        return is_array( $transition ) ? $transition : [];
    }
}
