<?php

declare( strict_types=1 );

namespace HWS\BaseTools\TeamMembers;

defined( 'ABSPATH' ) || exit;

/**
 * Team page defaults: what [hws_team_members] uses when an attribute is left out.
 */
final class TeamPageSettings {
    public const OPTION = 'hws_team_page_settings';

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'style'         => TeamMemberDirectory::DEFAULT_STYLE,
            'columns'       => 3,
            'limit'         => TeamMemberDirectory::DEFAULT_LIMIT,
            'show_excerpt'  => 1,
            'link_profiles' => 1,
            'order'         => 'ASC',
            'orderby'       => 'menu_order',
            'design'        => 'default',
            'roles_first'   => 'Founder, Co-Founder, CEO, President, COO, CTO',
        ];
    }

    /** @return array<string,mixed> */
    public static function all(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        if ( ! isset( $saved['style'] ) ) {
            $saved['style'] = (string) get_option( TeamMemberDirectory::STYLE_OPTION, TeamMemberDirectory::DEFAULT_STYLE );
        }

        return self::sanitize( array_merge( self::defaults(), $saved ) );
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function save( array $values ): array {
        $settings = self::sanitize( array_merge( self::all(), $values ) );
        update_option( self::OPTION, $settings, false );
        update_option( TeamMemberDirectory::STYLE_OPTION, $settings['style'], false );

        return $settings;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function sanitize( array $values ): array {
        $defaults = self::defaults();
        $order    = strtoupper( (string) ( $values['order'] ?? $defaults['order'] ) );
        $orderby  = sanitize_key( (string) ( $values['orderby'] ?? $defaults['orderby'] ) );
        $design   = sanitize_key( (string) ( $values['design'] ?? $defaults['design'] ) );

        return [
            'style'         => TeamMemberDirectory::normalize_style( (string) ( $values['style'] ?? $defaults['style'] ) ),
            'columns'       => max( 1, min( 4, (int) ( $values['columns'] ?? $defaults['columns'] ) ) ),
            'limit'         => TeamMemberDirectory::normalize_limit( $values['limit'] ?? $defaults['limit'] ),
            'show_excerpt'  => self::flag( $values['show_excerpt'] ?? $defaults['show_excerpt'] ),
            'link_profiles' => self::flag( $values['link_profiles'] ?? $defaults['link_profiles'] ),
            'order'         => 'DESC' === $order ? 'DESC' : 'ASC',
            'orderby'       => in_array( $orderby, [ 'menu_order', 'title', 'date' ], true ) ? $orderby : 'menu_order',
            'design'        => 'none' === $design ? 'none' : 'default',
            'roles_first'   => self::roles( (string) ( $values['roles_first'] ?? $defaults['roles_first'] ) ),
        ];
    }

    /** A comma list of role names, trimmed, empty entries and duplicates dropped. */
    public static function roles( string $value ): string {
        $roles = [];
        foreach ( explode( ',', sanitize_text_field( $value ) ) as $role ) {
            $role = trim( $role );
            if ( '' !== $role && ! in_array( strtolower( $role ), array_map( 'strtolower', $roles ), true ) ) {
                $roles[] = $role;
            }
        }

        return implode( ', ', $roles );
    }

    private static function flag( mixed $value ): int {
        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true ) ? 1 : 0;
    }
}
