<?php

namespace HWS\BaseTools\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor text tag for a word-limited ACF value.
 */
final class TrimmedAcfTextTag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name(): string {
        return 'hws-trimmed-acf-text';
    }

    public function get_title(): string {
        return __( 'HWS Trimmed ACF Text', 'hws-base-tools' );
    }

    public function get_group(): string {
        return 'post';
    }

    /** @return list<string> */
    public function get_categories(): array {
        return [ 'text' ];
    }

    public function get_panel_template_setting_key(): string {
        return 'key';
    }

    public function is_settings_required(): bool {
        return true;
    }

    protected function register_controls(): void {
        $this->add_control(
            'key',
            [
                'label'       => __( 'ACF field name or key', 'hws-base-tools' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'field_key:field_name',
                'ai'          => [ 'active' => false ],
            ]
        );
        $this->add_control(
            'word_limit',
            [
                'label'   => __( 'Word limit', 'hws-base-tools' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 500,
                'step'    => 1,
                'default' => 20,
            ]
        );
        $this->add_control(
            'suffix',
            [
                'label'       => __( 'Suffix', 'hws-base-tools' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '...',
                'placeholder' => '...',
                'ai'          => [ 'active' => false ],
            ]
        );
    }

    public function render(): void {
        $key = self::normalize_field_key( (string) $this->get_settings( 'key' ) );
        if ( '' === $key ) {
            return;
        }

        $value = function_exists( 'get_field' )
            ? get_field( $key, get_the_ID(), false )
            : get_post_meta( get_the_ID(), $key, true );

        if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
            return;
        }

        $limit = max( 1, min( 500, (int) $this->get_settings( 'word_limit' ) ) );
        $suffix = sanitize_text_field( (string) $this->get_settings( 'suffix' ) );

        echo esc_html( wp_trim_words( (string) $value, $limit, $suffix ) );
    }

    public static function normalize_field_key( string $key ): string {
        $key = trim( $key );
        if ( str_contains( $key, ':' ) ) {
            [, $key] = explode( ':', $key, 2 );
        }

        return sanitize_key( $key );
    }
}
