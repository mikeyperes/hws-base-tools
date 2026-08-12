<?php

declare( strict_types=1 );

namespace HWS\BaseTools\PageLayoutStyling;

defined( 'ABSPATH' ) || exit;

final class PageLayoutSettings {
    public const OPTION = 'hws_page_layout_style';
    public const DEFAULT_STYLE = 'minimalist';
    public const NO_STYLE = 'none';

    /** @return array<string,array{label:string,description:string}> */
    public static function definitions(): array {
        return [
            'minimalist' => [
                'label'       => 'Minimalist',
                'description' => 'Quiet spacing, crisp typography, and a focused reading column.',
            ],
            'editorial' => [
                'label'       => 'Editorial Journal',
                'description' => 'A refined serif treatment with a traditional publication rhythm.',
            ],
            'modern-card' => [
                'label'       => 'Modern Card',
                'description' => 'A bright elevated surface with soft borders and contemporary spacing.',
            ],
            'bold-accent' => [
                'label'       => 'Bold Accent',
                'description' => 'Strong brand-color rules and decisive heading treatments.',
            ],
            'soft-canvas' => [
                'label'       => 'Soft Canvas',
                'description' => 'A warm, relaxed reading panel with subtle tinted surfaces.',
            ],
            'classic-serif' => [
                'label'       => 'Classic Serif',
                'description' => 'Book-inspired typography, restrained rules, and generous line height.',
            ],
            self::NO_STYLE => [
                'label'       => 'No Style',
                'description' => 'Leave the active theme entirely responsible for Page presentation.',
            ],
        ];
    }

    /** @return string[] */
    public static function keys(): array {
        return array_keys( self::definitions() );
    }

    public static function normalize( mixed $style ): string {
        $style = sanitize_key( (string) $style );
        return array_key_exists( $style, self::definitions() ) ? $style : self::DEFAULT_STYLE;
    }

    public static function selected(): string {
        return self::normalize( get_option( self::OPTION, self::DEFAULT_STYLE ) );
    }

    public static function update( mixed $style ): string {
        $style = self::normalize( $style );
        update_option( self::OPTION, $style, false );
        return $style;
    }
}
