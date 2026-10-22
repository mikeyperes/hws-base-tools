<?php

namespace HWS\BaseTools\TeamMembers;

defined( 'ABSPATH' ) || exit;

final class TeamMemberDirectory {
    public const FEATURE_OPTION = 'enable_team_member_directory_templates';
    public const STYLE_OPTION = 'hws_team_member_directory_style';
    public const SHORTCODE = 'hws_team_members';
    public const POST_TYPE = 'team-member';
    public const DEFAULT_STYLE = 'portrait_grid';
    public const DEFAULT_LIMIT = 24;
    public const MAX_LIMIT = 100;

    public function register(): void {
        add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
    }

    private static bool $styles_printed = false;

    /** The grid's styles, once per page, printed with the first team list (never on pages without one). */
    public static function style_tag(): string {
        if ( self::$styles_printed ) {
            return '';
        }
        self::$styles_printed = true;
        return '<style id="hws-team-directory-styles">' . self::styles() . '</style>';
    }

    public function render_shortcode( array|string $atts = [] ): string {
        if ( ! get_option( self::FEATURE_OPTION, false ) ) {
            return '';
        }

        $html = self::render( is_array( $atts ) ? $atts : [] );
        return '' === $html ? '' : self::style_tag() . $html;
    }

    /**
     * Render the listing grid. Attributes left out use the Team tab's page settings.
     *
     * @param array<string,mixed> $atts
     */
    public static function render( array $atts = [] ): string {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return '';
        }

        $page = TeamPageSettings::all();
        $atts = shortcode_atts(
            [
                'style'         => (string) $page['style'],
                'featured_only' => '0',
                'category'      => '',
                'limit'         => (string) $page['limit'],
                'columns'       => (string) $page['columns'],
                'show_excerpt'  => (string) $page['show_excerpt'],
                'link_profiles' => (string) $page['link_profiles'],
                'order'         => (string) $page['order'],
                'orderby'       => (string) $page['orderby'],
                'design'        => (string) $page['design'],
                'roles_first'   => (string) $page['roles_first'],
            ],
            $atts,
            self::SHORTCODE
        );

        $style = self::normalize_style( (string) $atts['style'] );
        $limit = self::normalize_limit( $atts['limit'] );

        $order = 'DESC' === strtoupper( (string) $atts['order'] ) ? 'DESC' : 'ASC';
        $orderby = sanitize_key( (string) $atts['orderby'] );
        if ( ! in_array( $orderby, [ 'menu_order', 'title', 'date' ], true ) ) {
            $orderby = 'menu_order';
        }

        $query_args = [
            'post_type'           => self::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => '' !== trim( (string) $atts['roles_first'] ) ? self::MAX_LIMIT : $limit,
            'orderby'             => 'menu_order' === $orderby ? [ 'menu_order' => $order, 'title' => 'ASC' ] : $orderby,
            'order'               => $order,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];

        if ( self::attribute_enabled( $atts['featured_only'] ) ) {
            $query_args['meta_query'] = [
                [
                    'key'     => 'featured',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ];
        }

        $category = sanitize_title( (string) $atts['category'] );
        if ( '' !== $category && is_object_in_taxonomy( self::POST_TYPE, 'category' ) ) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => [ $category ],
                ],
            ];
        }

        $items = self::query_items( $query_args, (string) $atts['roles_first'], $limit );
        if ( ! $items ) {
            return '';
        }

        return self::render_items(
            $items,
            $style,
            max( 1, min( 4, (int) $atts['columns'] ) ),
            self::attribute_enabled( $atts['show_excerpt'] ),
            self::attribute_enabled( $atts['link_profiles'] ),
            false,
            self::normalize_design( (string) $atts['design'] )
        );
    }

    /** @return array<int,array<string,mixed>> Display items, roles first, cut to $limit. */
    private static function query_items( array $query_args, string $roles_first, int $limit ): array {
        $items = [];
        foreach ( ( new \WP_Query( $query_args ) )->posts as $post ) {
            if ( $post instanceof \WP_Post ) {
                $items[] = self::item_from_post( $post ) + [ 'id' => (int) $post->ID ];
            }
        }
        return array_slice( self::roles_first( $items, $roles_first ), 0, $limit );
    }

    /**
     * Read API (hws_team_members()): published team members as data, in the team page order
     * (Team tab defaults; roles listed in "roles first" lead). Empty while the Team feature is off.
     *
     * @param array{featured_only?:bool,category?:string,limit?:int} $args
     * @return array<int,array{id:int,name:string,position:string,featured:bool,excerpt:string,url:string,photo:string,photo_id:int,order:int,edit_url:string}>
     */
    public static function members( array $args = [] ): array {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return [];
        }
        $page  = TeamPageSettings::all();
        $query = [
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => self::MAX_LIMIT,
            'orderby' => 'menu_order' === $page['orderby'] ? [ 'menu_order' => $page['order'], 'title' => 'ASC' ] : $page['orderby'],
            'order' => $page['order'], 'ignore_sticky_posts' => true, 'no_found_rows' => true,
        ];
        if ( ! empty( $args['featured_only'] ) ) {
            $query['meta_query'] = [ [ 'key' => 'featured', 'value' => '1', 'compare' => '=' ] ];
        }
        $category = sanitize_title( (string) ( $args['category'] ?? '' ) );
        if ( '' !== $category && is_object_in_taxonomy( self::POST_TYPE, 'category' ) ) {
            $query['tax_query'] = [ [ 'taxonomy' => 'category', 'field' => 'slug', 'terms' => [ $category ] ] ];
        }
        $limit = isset( $args['limit'] ) && (int) $args['limit'] > 0 ? min( self::MAX_LIMIT, (int) $args['limit'] ) : self::MAX_LIMIT;
        $rows  = [];
        foreach ( self::query_items( $query, (string) $page['roles_first'], $limit ) as $item ) {
            $id       = (int) $item['id'];
            $photo_id = (int) get_post_thumbnail_id( $id );
            $rows[]   = [
                'id'       => $id,
                'name'     => (string) $item['name'],
                'position' => (string) $item['position'],
                'featured' => '1' === (string) get_post_meta( $id, 'featured', true ),
                'excerpt'  => (string) $item['excerpt'],
                'url'      => (string) get_permalink( $id ),
                'photo'    => $photo_id ? (string) wp_get_attachment_image_url( $photo_id, 'medium_large' ) : '',
                'photo_id' => $photo_id,
                'order'    => (int) get_post_field( 'menu_order', $id ),
                'edit_url' => (string) get_edit_post_link( $id, 'raw' ),
            ];
        }
        return $rows;
    }

    /**
     * People whose position matches a listed role come first, in the list's order;
     * everyone else keeps the query order after them.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    public static function roles_first( array $items, string $roles ): array {
        $rank = [];
        foreach ( array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $roles ) ) ) ) ) as $index => $role ) {
            $rank[ $role ] = $rank[ $role ] ?? $index;
        }
        if ( ! $rank ) {
            return $items;
        }

        $keyed = [];
        foreach ( array_values( $items ) as $position => $item ) {
            $role    = strtolower( trim( (string) ( $item['position'] ?? '' ) ) );
            $keyed[] = [ $rank[ $role ] ?? count( $rank ), $position, $item ];
        }
        usort( $keyed, static fn( array $a, array $b ): int => [ $a[0], $a[1] ] <=> [ $b[0], $b[1] ] );

        return array_column( $keyed, 2 );
    }

    /** `default` prints the built-in look; `none` prints the same markup with no plugin styling. */
    public static function normalize_design( string $design ): string {
        return 'none' === sanitize_key( $design ) ? 'none' : 'default';
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    public static function template_options(): array {
        return [
            'portrait_grid' => [
                'label'       => 'Minimal portrait grid',
                'description' => 'Clean image-first profiles with restrained typography and no decorative buttons.',
            ],
            'editorial_list' => [
                'label'       => 'Editorial list',
                'description' => 'One person per row with a portrait, role, short biography, and quiet profile link.',
            ],
            'compact_directory' => [
                'label'       => 'Compact directory',
                'description' => 'Dense name-and-role rows for larger teams where quick scanning matters.',
            ],
        ];
    }

    public static function normalize_style( string $style ): string {
        $style = sanitize_key( $style );

        return array_key_exists( $style, self::template_options() ) ? $style : self::DEFAULT_STYLE;
    }

    public static function normalize_limit( mixed $limit ): int {
        if ( ! is_int( $limit ) && ! ( is_string( $limit ) && preg_match( '/^-?[0-9]+$/D', $limit ) ) ) {
            return self::DEFAULT_LIMIT;
        }

        $limit = (int) $limit;

        return $limit > 0 ? min( self::MAX_LIMIT, $limit ) : self::DEFAULT_LIMIT;
    }

    public static function selected_style(): string {
        return self::normalize_style( (string) get_option( self::STYLE_OPTION, self::DEFAULT_STYLE ) );
    }

    public static function preview_html( string $style, string $design = 'default' ): string {
        $items = [
            [
                'name'       => 'Jordan Lee',
                'position'   => 'Executive Editor',
                'excerpt'    => 'Leads editorial standards, newsroom planning, and publication strategy.',
                'url'        => '#',
                'image_html' => '',
                'initials'   => 'JL',
            ],
            [
                'name'       => 'Morgan Chen',
                'position'   => 'Managing Editor',
                'excerpt'    => 'Coordinates the editorial calendar and works directly with contributors.',
                'url'        => '#',
                'image_html' => '',
                'initials'   => 'MC',
            ],
        ];

        return self::render_items( $items, self::normalize_style( $style ), 2, true, false, true, self::normalize_design( $design ) );
    }

    /**
     * @return array<string,mixed>
     */
    public static function integrity_report(): array {
        $post_type_active = post_type_exists( self::POST_TYPE );
        $counts = $post_type_active ? wp_count_posts( self::POST_TYPE ) : null;
        $published = is_object( $counts ) && isset( $counts->publish ) ? (int) $counts->publish : 0;
        $featured = 0;

        if ( $post_type_active ) {
            $featured_query = new \WP_Query(
                [
                    'post_type'              => self::POST_TYPE,
                    'post_status'            => 'publish',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'no_found_rows'          => false,
                    'suppress_filters'       => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'meta_key'               => 'featured',
                    'meta_value'             => '1',
                ]
            );
            $featured = max( 0, (int) $featured_query->found_posts );
        }

        return [
            'feature_enabled'    => (bool) get_option( self::FEATURE_OPTION, false ),
            'shortcode'          => '[' . self::SHORTCODE . ']',
            'shortcode_registered'=> shortcode_exists( self::SHORTCODE ),
            'post_type'          => self::POST_TYPE,
            'post_type_active'   => $post_type_active,
            'cpt_option_enabled' => (bool) get_option( 'smp_enable_cpt_teammember', false ),
            'acf_option_enabled' => (bool) get_option( 'smp_enable_acf_teammember', false ),
            'published'          => $published,
            'featured'           => $featured,
            'style'              => self::selected_style(),
        ];
    }

    public static function styles(): string {
        return '.hws-team-directory--styled{--hws-team-columns:3;box-sizing:border-box;color:#171717;display:grid;gap:28px;letter-spacing:0;list-style:none;margin:0;padding:0;width:100%}'
            . '.hws-team-directory--styled *{box-sizing:border-box;letter-spacing:0}'
            . '.hws-team-directory--styled .hws-team-card{margin:0;min-width:0;padding:0}'
            . '.hws-team-directory--styled .hws-team-card a{color:inherit;text-decoration:none}'
            . '.hws-team-directory--styled .hws-team-card a:focus-visible{outline:2px solid currentColor;outline-offset:3px}'
            . '.hws-team-directory--styled .hws-team-member-media{background:#f1f3f4;display:block;overflow:hidden;position:relative}'
            . '.hws-team-directory--styled .hws-team-member-media img{display:block;height:100%;max-width:none;object-fit:cover;width:100%}'
            . '.hws-team-directory--styled .hws-team-member-placeholder{align-items:center;background:#eceff1;color:#4b5563;display:flex;font-size:24px;font-weight:700;height:100%;justify-content:center;width:100%}'
            . '.hws-team-directory--styled .hws-team-member-name{font-size:18px;font-weight:700;line-height:1.25;margin:0}'
            . '.hws-team-directory--styled .hws-team-member-position{color:#5b6168;font-size:13px;font-weight:600;line-height:1.4;margin:4px 0 0}'
            . '.hws-team-directory--styled .hws-team-member-excerpt{color:#50555a;font-size:14px;line-height:1.55;margin:12px 0 0}'
            . '.hws-team-directory--styled .hws-team-member-link{display:inline-block;font-size:13px;font-weight:700;margin-top:13px}'
            . '.hws-team-directory--styled .hws-team-member-link span{display:inline-block;margin-left:5px}'
            . '.hws-team-directory--portrait_grid{grid-template-columns:repeat(var(--hws-team-columns),minmax(0,1fr))}'
            . '.hws-team-directory--portrait_grid .hws-team-member-media{aspect-ratio:4/5;border-radius:4px}'
            . '.hws-team-directory--portrait_grid .hws-team-member-content{border-bottom:1px solid #dedede;padding:13px 0 16px}'
            . '.hws-team-directory--editorial_list{gap:0;grid-template-columns:minmax(0,1fr)}'
            . '.hws-team-directory--editorial_list .hws-team-card{border-top:1px solid #d8d8d8;display:grid;gap:24px;grid-template-columns:152px minmax(0,1fr);padding:22px 0}'
            . '.hws-team-directory--editorial_list .hws-team-card:last-child{border-bottom:1px solid #d8d8d8}'
            . '.hws-team-directory--editorial_list .hws-team-member-media{aspect-ratio:4/5;border-radius:4px}'
            . '.hws-team-directory--editorial_list .hws-team-member-content{align-self:center}'
            . '.hws-team-directory--compact_directory{gap:0;grid-template-columns:minmax(0,1fr)}'
            . '.hws-team-directory--compact_directory .hws-team-card{align-items:center;border-top:1px solid #e2e2e2;display:grid;gap:15px;grid-template-columns:56px minmax(0,1fr);padding:12px 0}'
            . '.hws-team-directory--compact_directory .hws-team-card:last-child{border-bottom:1px solid #e2e2e2}'
            . '.hws-team-directory--compact_directory .hws-team-member-media{aspect-ratio:1/1;border-radius:50%}'
            . '.hws-team-directory--compact_directory .hws-team-member-placeholder{font-size:14px}'
            . '.hws-team-directory--compact_directory .hws-team-member-name{font-size:15px}'
            . '.hws-team-directory--compact_directory .hws-team-member-position{font-size:12px;margin-top:2px}'
            . '.hws-team-directory--compact_directory .hws-team-member-excerpt,.hws-team-directory--compact_directory .hws-team-member-link{display:none}'
            . '.hws-team-directory--preview{max-width:760px}'
            . '@media(max-width:900px){.hws-team-directory--portrait_grid{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            . '@media(max-width:600px){.hws-team-directory--portrait_grid{grid-template-columns:minmax(0,1fr)}.hws-team-directory--editorial_list .hws-team-card{gap:16px;grid-template-columns:92px minmax(0,1fr);padding:16px 0}.hws-team-directory--editorial_list .hws-team-member-excerpt{display:none}}';
    }

    /**
     * @return array<string,mixed>
     */
    private static function item_from_post( \WP_Post $post ): array {
        $post_id = (int) $post->ID;
        $name = trim( (string) get_the_title( $post ) );
        $position = trim( (string) get_post_meta( $post_id, 'position', true ) );
        $excerpt = trim( (string) get_the_excerpt( $post ) );

        if ( '' === $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 28 );
        }

        $image_html = '';
        $image_id = get_post_thumbnail_id( $post_id );
        if ( $image_id ) {
            $image_html = wp_get_attachment_image(
                $image_id,
                'medium_large',
                false,
                [
                    'alt'      => $name,
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ]
            );
        }

        return [
            'name'       => $name,
            'position'   => $position,
            'excerpt'    => $excerpt,
            'url'        => (string) get_permalink( $post ),
            'image_html' => is_string( $image_html ) ? $image_html : '',
            'initials'   => self::initials( $name ),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function render_items( array $items, string $style, int $columns, bool $show_excerpt, bool $link_profiles, bool $preview = false, string $design = 'default' ): string {
        if ( empty( $items ) ) {
            return '';
        }

        // design="none" keeps the markup and drops every plugin style class, so a site design owns the look.
        $classes = 'hws-team-directory';
        if ( 'none' !== $design ) {
            $classes .= ' hws-team-directory--styled hws-team-directory--' . sanitize_html_class( $style );
        }
        if ( $preview ) {
            $classes .= ' hws-team-directory--preview';
        }

        $html = '<div class="' . esc_attr( $classes ) . '" data-layout="' . esc_attr( $style ) . '" data-columns="' . esc_attr( (string) $columns ) . '" style="--hws-team-columns:' . esc_attr( (string) $columns ) . '" role="list">';
        foreach ( $items as $item ) {
            $html .= self::render_item( $item, $show_excerpt, $link_profiles );
        }

        return $html . '</div>';
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function render_item( array $item, bool $show_excerpt, bool $link_profiles ): string {
        $name = trim( (string) ( $item['name'] ?? '' ) );
        $position = trim( (string) ( $item['position'] ?? '' ) );
        $excerpt = trim( (string) ( $item['excerpt'] ?? '' ) );
        $url = trim( (string) ( $item['url'] ?? '' ) );
        $image_html = trim( (string) ( $item['image_html'] ?? '' ) );
        $initials = trim( (string) ( $item['initials'] ?? self::initials( $name ) ) );
        $can_link = $link_profiles && '' !== $url && '#' !== $url;

        $media = '' !== $image_html
            ? $image_html
            : '<span class="hws-team-member-placeholder" aria-hidden="true">' . esc_html( $initials ) . '</span>';

        if ( $can_link ) {
            $media = '<a class="hws-team-member-media" href="' . esc_url( $url ) . '" aria-label="View ' . esc_attr( $name ) . '">' . $media . '</a>';
            $title = '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
        } else {
            $media = '<span class="hws-team-member-media">' . $media . '</span>';
            $title = esc_html( $name );
        }

        $html = '<article class="hws-team-card" role="listitem" itemscope itemtype="https://schema.org/Person">' . $media;
        $html .= '<div class="hws-team-member-content"><h3 class="hws-team-member-name" itemprop="name">' . $title . '</h3>';
        if ( '' !== $position ) {
            $html .= '<p class="hws-team-member-position" itemprop="jobTitle">' . esc_html( $position ) . '</p>';
        }
        if ( $show_excerpt && '' !== $excerpt ) {
            $html .= '<p class="hws-team-member-excerpt" itemprop="description">' . esc_html( $excerpt ) . '</p>';
        }
        if ( $can_link ) {
            $html .= '<a class="hws-team-member-link" itemprop="url" href="' . esc_url( $url ) . '">View profile <span aria-hidden="true">&rarr;</span></a>';
        }

        return $html . '</div></article>';
    }

    private static function initials( string $name ): string {
        $parts = preg_split( '/\s+/', trim( $name ) ) ?: [];
        $parts = array_values( array_filter( $parts ) );
        if ( empty( $parts ) ) {
            return 'TM';
        }

        $letters = function_exists( 'mb_substr' )
            ? mb_substr( (string) $parts[0], 0, 1 ) . mb_substr( (string) end( $parts ), 0, 1 )
            : substr( (string) $parts[0], 0, 1 ) . substr( (string) end( $parts ), 0, 1 );

        return strtoupper( $letters );
    }

    private static function attribute_enabled( mixed $value ): bool {
        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
    }
}
