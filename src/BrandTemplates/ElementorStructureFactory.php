<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

defined( 'ABSPATH' ) || exit;

final class ElementorStructureFactory {
    /** @return array<int,array<string,mixed>> */
    public static function elements( string $context ): array {
        return match ( sanitize_key( $context ) ) {
            BrandTemplateRegistry::AUTHOR      => self::author_elements(),
            BrandTemplateRegistry::PAGE        => self::page_elements(),
            BrandTemplateRegistry::SINGLE_POST => self::single_post_elements(),
            BrandTemplateRegistry::CATEGORY    => self::term_elements( BrandTemplateRegistry::CATEGORY ),
            BrandTemplateRegistry::TAG         => self::term_elements( BrandTemplateRegistry::TAG ),
            default                            => [],
        };
    }

    /** @return array<string,mixed> */
    public static function document_settings( string $context, int $preview_id = 0 ): array {
        $definition = BrandTemplateRegistry::get( $context );
        if ( null === $definition ) {
            return [];
        }

        $settings = [
            'page_template' => 'elementor_header_footer',
        ];
        if ( '' !== (string) $definition['preview_type'] ) {
            $settings['preview_type'] = (string) $definition['preview_type'];
        }
        if ( $preview_id > 0 ) {
            $settings['preview_id'] = $preview_id;
        }
        return $settings;
    }

    /** @return array<int,array<string,mixed>> */
    private static function page_elements(): array {
        return [
            self::intro(
                'a100001',
                'a100002',
                'a100003',
                'a100004',
                'Page Introduction',
                self::dynamic_tag( 'a10f001', 'post-title' )
            ),
            self::container(
                'a100005',
                'Page Content',
                array_merge(
                    self::boxed_settings(),
                    [
                        'html_tag'      => 'main',
                        'padding'       => self::spacing( 64, 0, 96, 0 ),
                        'padding_tablet'=> self::spacing( 50, 24, 80, 24 ),
                        'padding_mobile'=> self::spacing( 36, 20, 64, 20 ),
                    ]
                ),
                [ self::content_widget( 'a100006', 'Page Body' ) ]
            ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function single_post_elements(): array {
        $title = self::dynamic_tag( 'b10f001', 'post-title' );
        return [
            self::container(
                'b100001',
                'Article Introduction',
                array_merge(
                    self::boxed_settings(),
                    [
                        'html_tag'                   => 'header',
                        '_attributes'                => 'data-smpi-breadcrumbs-injected|1',
                        'flex_gap'                   => self::gap( 16 ),
                        'padding'                    => self::spacing( 46, 0, 52, 0 ),
                        'padding_tablet'             => self::spacing( 38, 24, 44, 24 ),
                        'padding_mobile'             => self::spacing( 28, 20, 34, 20 ),
                        'background_background'      => 'gradient',
                        'background_color'           => '#FAFAF8',
                        'background_color_b'         => '#F2F4F1',
                        'background_gradient_angle'  => [ 'unit' => 'deg', 'size' => 125, 'sizes' => [] ],
                        'border_border'              => 'solid',
                        'border_width'               => self::spacing( 0, 0, 1, 0 ),
                        'border_color'               => '#E7E9E5',
                    ]
                ),
                [
                    self::breadcrumbs_widget( 'b100002', 'Article Breadcrumbs' ),
                    self::heading_widget( 'b100003', 'Article Title', $title, 'h1', true ),
                    self::widget(
                        'b100004',
                        'post-info',
                        'Article Metadata',
                        [
                            'layout' => 'inline',
                            'icon_list' => [
                                [ '_id' => 'b10d001', 'type' => 'author' ],
                                [ '_id' => 'b10d002', 'type' => 'date', 'date_format' => 'F j, Y' ],
                            ],
                            '__globals__' => [
                                'text_color' => 'globals/colors?id=secondary',
                                'typography_typography' => 'globals/typography?id=text',
                            ],
                        ]
                    ),
                ]
            ),
            self::container(
                'b100005',
                'Article Body',
                array_merge(
                    self::boxed_settings( 880 ),
                    [
                        'html_tag'       => 'main',
                        'flex_gap'       => self::gap( 32 ),
                        'padding'        => self::spacing( 56, 0, 96, 0 ),
                        'padding_tablet' => self::spacing( 46, 24, 80, 24 ),
                        'padding_mobile' => self::spacing( 34, 20, 64, 20 ),
                    ]
                ),
                [
                    self::widget(
                        'b100006',
                        'theme-post-featured-image',
                        'Featured Image',
                        [
                            'image_size' => 'large',
                            'custom_css' => "selector img{display:block;height:auto;width:100%;}",
                        ]
                    ),
                    self::content_widget( 'b100007', 'Article Content' ),
                ]
            ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function author_elements(): array {
        return [
            self::container(
                'c100001',
                'Author Masthead',
                array_merge(
                    self::boxed_settings(),
                    [
                        'html_tag'                   => 'header',
                        '_attributes'                => 'data-smpi-breadcrumbs-injected|1',
                        'flex_direction'             => 'row',
                        'flex_direction_mobile'      => 'column',
                        'flex_align_items'           => 'center',
                        'flex_align_items_mobile'    => 'flex-start',
                        'flex_gap'                   => self::gap( 36 ),
                        'flex_gap_mobile'            => self::gap( 22 ),
                        'padding'                    => self::spacing( 52, 0, 58, 0 ),
                        'padding_tablet'             => self::spacing( 44, 24, 48, 24 ),
                        'padding_mobile'             => self::spacing( 30, 20, 38, 20 ),
                        'background_background'      => 'gradient',
                        'background_color'           => '#FAFAF8',
                        'background_color_b'         => '#F0F3EF',
                        'background_gradient_angle'  => [ 'unit' => 'deg', 'size' => 125, 'sizes' => [] ],
                        'border_border'              => 'solid',
                        'border_width'               => self::spacing( 0, 0, 1, 0 ),
                        'border_color'               => '#E5E8E3',
                    ]
                ),
                [
                    self::widget(
                        'c100002',
                        'image',
                        'Author Portrait',
                        [
                            '__dynamic__' => [ 'image' => self::dynamic_tag( 'c10f001', 'author-profile-picture' ) ],
                            'image_size' => 'medium',
                            'width' => self::size( 136 ),
                            'width_tablet' => self::size( 116 ),
                            'width_mobile' => self::size( 96 ),
                            'custom_css' => 'selector img{aspect-ratio:1/1;border:2px solid var(--e-global-color-primary);border-radius:999px;display:block;object-fit:cover;width:100%;}',
                        ]
                    ),
                    self::container(
                        'c100003',
                        'Author Details',
                        [
                            'content_width' => 'full',
                            'width' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                            'flex_gap' => self::gap( 12 ),
                        ],
                        [
                            self::breadcrumbs_widget( 'c100004', 'Author Breadcrumbs' ),
                            self::heading_widget( 'c100005', 'Author Name', self::dynamic_tag( 'c10f002', 'author-name' ), 'h1', true ),
                            self::widget(
                                'c100006',
                                'text-editor',
                                'Author Biography',
                                [
                                    '__dynamic__' => [ 'editor' => self::dynamic_tag( 'c10f003', 'author-info' ) ],
                                    'custom_css' => 'selector{color:var(--e-global-color-text);font-size:16px;line-height:1.7;max-width:760px;}selector p:last-child{margin-bottom:0;}',
                                ]
                            ),
                        ]
                    ),
                ]
            ),
            self::archive_posts_section( 'c100007', 'c100008', 'c100009', 'Latest From This Author' ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function term_elements( string $context ): array {
        $prefix = BrandTemplateRegistry::CATEGORY === $context ? 'd' : 'e';
        return [
            self::container(
                $prefix . '100001',
                ucfirst( $context ) . ' Introduction',
                array_merge(
                    self::boxed_settings(),
                    [
                        'html_tag'                   => 'header',
                        '_attributes'                => 'data-smpi-breadcrumbs-injected|1',
                        'flex_gap'                   => self::gap( 15 ),
                        'padding'                    => self::spacing( 46, 0, 52, 0 ),
                        'padding_tablet'             => self::spacing( 38, 24, 44, 24 ),
                        'padding_mobile'             => self::spacing( 28, 20, 34, 20 ),
                        'background_background'      => 'gradient',
                        'background_color'           => '#FAFAF8',
                        'background_color_b'         => '#F2F4F1',
                        'background_gradient_angle'  => [ 'unit' => 'deg', 'size' => 125, 'sizes' => [] ],
                        'border_border'              => 'solid',
                        'border_width'               => self::spacing( 0, 0, 1, 0 ),
                        'border_color'               => '#E7E9E5',
                    ]
                ),
                [
                    self::breadcrumbs_widget( $prefix . '100002', ucfirst( $context ) . ' Breadcrumbs' ),
                    self::heading_widget(
                        $prefix . '100003',
                        ucfirst( $context ) . ' Title',
                        self::dynamic_tag( $prefix . '10f001', 'archive-title' ),
                        'h1',
                        true
                    ),
                    self::widget(
                        $prefix . '100004',
                        'text-editor',
                        ucfirst( $context ) . ' Description',
                        [
                            '__dynamic__' => [ 'editor' => self::dynamic_tag( $prefix . '10f002', 'archive-description' ) ],
                            'custom_css' => 'selector{color:var(--e-global-color-text);font-size:16px;line-height:1.7;max-width:760px;}selector:empty{display:none;}',
                        ]
                    ),
                ]
            ),
            self::archive_posts_section(
                $prefix . '100005',
                $prefix . '100006',
                $prefix . '100007',
                BrandTemplateRegistry::CATEGORY === $context ? 'Latest In This Category' : 'Latest With This Tag'
            ),
        ];
    }

    /** @return array<string,mixed> */
    private static function intro( string $container_id, string $crumb_id, string $heading_id, string $tag_id, string $title, string $dynamic_title ): array {
        return self::container(
            $container_id,
            $title,
            array_merge(
                self::boxed_settings(),
                [
                    'html_tag'                   => 'header',
                    '_attributes'                => 'data-smpi-breadcrumbs-injected|1',
                    'flex_gap'                   => self::gap( 16 ),
                    'padding'                    => self::spacing( 46, 0, 54, 0 ),
                    'padding_tablet'             => self::spacing( 38, 24, 46, 24 ),
                    'padding_mobile'             => self::spacing( 28, 20, 36, 20 ),
                    'background_background'      => 'gradient',
                    'background_color'           => '#FAFAF8',
                    'background_color_b'         => '#F2F4F1',
                    'background_gradient_angle'  => [ 'unit' => 'deg', 'size' => 125, 'sizes' => [] ],
                    'border_border'              => 'solid',
                    'border_width'               => self::spacing( 0, 0, 1, 0 ),
                    'border_color'               => '#E7E9E5',
                ]
            ),
            [
                self::breadcrumbs_widget( $crumb_id, 'Rank Math Breadcrumbs' ),
                self::heading_widget( $heading_id, 'Dynamic Page Title', $dynamic_title, 'h1', true, $tag_id ),
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function archive_posts_section( string $section_id, string $heading_id, string $posts_id, string $title ): array {
        return self::container(
            $section_id,
            'Archive Results',
            array_merge(
                self::boxed_settings(),
                [
                    'html_tag'       => 'main',
                    'flex_gap'       => self::gap( 28 ),
                    'padding'        => self::spacing( 58, 0, 96, 0 ),
                    'padding_tablet' => self::spacing( 48, 24, 80, 24 ),
                    'padding_mobile' => self::spacing( 36, 20, 64, 20 ),
                ]
            ),
            [
                self::heading_widget( $heading_id, 'Archive Section Heading', $title, 'h2', false ),
                self::widget(
                    $posts_id,
                    'archive-posts',
                    'Current Query Posts',
                    [
                        'archive_classic_columns'             => '4',
                        'archive_classic_columns_tablet'      => '2',
                        'archive_classic_columns_mobile'      => '1',
                        'archive_classic_thumbnail'           => 'top',
                        'archive_classic_thumbnail_size_size' => 'medium_large',
                        'archive_classic_show_title'          => 'yes',
                        'archive_classic_title_tag'           => 'h3',
                        'archive_classic_meta_data'           => [ 'date' ],
                        'archive_classic_show_excerpt'        => 'yes',
                        'archive_classic_excerpt_length'      => 18,
                        'archive_classic_show_read_more'      => 'yes',
                        'archive_classic_read_more_text'      => 'Read article',
                        'archive_classic_row_gap'             => self::size( 24 ),
                        'archive_classic_column_gap'          => self::size( 18 ),
                        'pagination_type'                     => 'numbers_and_prev_next',
                        'pagination_page_limit'               => '5',
                        'pagination_prev_label'               => 'Previous',
                        'pagination_next_label'               => 'Next',
                        'custom_css'                          => self::archive_posts_css(),
                    ]
                ),
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function breadcrumbs_widget( string $id, string $title ): array {
        return self::widget(
            $id,
            'shortcode',
            $title,
            [
                'shortcode' => '[rank_math_breadcrumb]',
                'custom_css' => 'selector .rank-math-breadcrumb{color:var(--e-global-color-secondary);font-family:var(--e-global-typography-text-font-family);font-size:12px;font-weight:500;letter-spacing:.2px;}selector .rank-math-breadcrumb p{margin:0;}selector .rank-math-breadcrumb a{color:var(--e-global-color-primary);text-decoration:none;}selector .rank-math-breadcrumb a:hover{color:var(--e-global-color-text);text-decoration:underline;}',
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function heading_widget(
        string $id,
        string $title,
        string $content,
        string $tag,
        bool $accent,
        string $dynamic_id = ''
    ): array {
        $dynamic_content = str_contains( $content, '[elementor-tag' ) ? $content : '';
        $settings = [
            'title' => '' !== $dynamic_content ? $title : $content,
            'header_size' => $tag,
            '__globals__' => [
                'title_color' => 'globals/colors?id=text',
                'typography_typography' => 'globals/typography?id=primary',
            ],
            'typography_typography' => 'custom',
            'typography_font_size' => self::size( 'h1' === $tag ? 48 : 34 ),
            'typography_font_size_tablet' => self::size( 'h1' === $tag ? 40 : 30 ),
            'typography_font_size_mobile' => self::size( 'h1' === $tag ? 34 : 26 ),
            'typography_font_weight' => '600',
            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.14, 'sizes' => [] ],
        ];
        if ( $accent ) {
            $settings['custom_css'] = 'selector .elementor-heading-title{border-left:3px solid var(--e-global-color-primary);padding-left:18px;}@media(max-width:767px){selector .elementor-heading-title{padding-left:14px;}}';
        }
        if ( '' !== $dynamic_content ) {
            $settings['__dynamic__'] = [ 'title' => $dynamic_content ];
        } elseif ( '' !== $dynamic_id ) {
            $settings['__dynamic__'] = [ 'title' => self::dynamic_tag( $dynamic_id, 'post-title' ) ];
        }
        return self::widget( $id, 'heading', $title, $settings );
    }

    /** @return array<string,mixed> */
    private static function content_widget( string $id, string $title ): array {
        return self::widget(
            $id,
            'theme-post-content',
            $title,
            [
                '_css_classes' => 'hws-brand-content',
                'content_width' => 'full',
                'custom_css' => self::content_css(),
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function container( string $id, string $title, array $settings, array $elements ): array {
        $settings['_title'] = $title;
        return [
            'id'       => $id,
            'elType'   => 'container',
            'settings' => $settings,
            'elements' => $elements,
            'isInner'  => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function widget( string $id, string $type, string $title, array $settings ): array {
        $settings['_title'] = $title;
        return [
            'id'         => $id,
            'elType'     => 'widget',
            'settings'   => $settings,
            'elements'   => [],
            'widgetType' => $type,
        ];
    }

    /** @return array<string,mixed> */
    private static function boxed_settings( int $width = 1072 ): array {
        return [
            'content_width' => 'boxed',
            'boxed_width' => [ 'unit' => 'px', 'size' => $width, 'sizes' => [] ],
            'flex_direction' => 'column',
            'flex_gap' => self::gap( 20 ),
        ];
    }

    /** @return array<string,mixed> */
    private static function spacing( int $top, int $right, int $bottom, int $left ): array {
        return [
            'unit' => 'px',
            'top' => (string) $top,
            'right' => (string) $right,
            'bottom' => (string) $bottom,
            'left' => (string) $left,
            'isLinked' => $top === $right && $right === $bottom && $bottom === $left,
        ];
    }

    /** @return array<string,mixed> */
    private static function gap( int $value ): array {
        return [
            'column' => (string) $value,
            'row' => (string) $value,
            'isLinked' => true,
            'unit' => 'px',
            'size' => $value,
        ];
    }

    /** @return array<string,mixed> */
    private static function size( int $value ): array {
        return [ 'unit' => 'px', 'size' => $value, 'sizes' => [] ];
    }

    private static function dynamic_tag( string $id, string $name, array $settings = [] ): string {
        return sprintf(
            '[elementor-tag id="%s" name="%s" settings="%s"]',
            $id,
            $name,
            rawurlencode( (string) wp_json_encode( $settings ) )
        );
    }

    private static function content_css(): string {
        return <<<'CSS'
selector{color:var(--e-global-color-text);font-size:16px;line-height:1.75;overflow-wrap:anywhere;}
selector>.elementor-widget-container{display:flow-root;}
selector h2,selector h3,selector h4,selector h5,selector h6{clear:both;color:var(--e-global-color-text);font-family:var(--e-global-typography-primary-font-family,serif);font-weight:600;line-height:1.24;margin:1.55em 0 .55em;}
selector h2{font-size:34px;}selector h3{font-size:28px;}selector h4{font-size:23px;}selector h5{font-size:19px;}selector h6{font-size:16px;letter-spacing:.04em;text-transform:uppercase;}
selector p,selector ul,selector ol,selector blockquote,selector figure,selector table{margin:0 0 1.35em;}
selector ul,selector ol{padding-left:1.4em;}selector li+li{margin-top:.42em;}
selector a{color:var(--e-global-color-primary);text-decoration-thickness:1px;text-underline-offset:3px;}selector a:hover{text-decoration-thickness:2px;}
selector blockquote{border-left:3px solid var(--e-global-color-primary);font-family:var(--e-global-typography-primary-font-family,serif);font-size:1.18em;margin-left:0;padding:8px 0 8px 24px;}
selector img{height:auto;max-width:100%;}selector figure img{display:block;width:100%;}selector figcaption,selector .wp-caption-text{color:var(--e-global-color-secondary);font-size:13px;line-height:1.55;margin-top:8px;}
selector table{border-collapse:collapse;display:block;max-width:100%;overflow-x:auto;width:100%;}selector th,selector td{border:1px solid #DDE1DC;padding:10px 12px;text-align:left;}selector th{background:#F5F7F4;font-weight:700;}
selector hr{border:0;border-top:1px solid #DDE1DC;margin:2.2em 0;}
selector .alignleft{float:left;margin:0 28px 20px 0;}selector .alignright{float:right;margin:0 0 20px 28px;}selector .aligncenter{display:block;margin-left:auto;margin-right:auto;}
@media(max-width:767px){selector{font-size:15px;line-height:1.72;}selector h2{font-size:27px;}selector h3{font-size:23px;}selector h4{font-size:20px;}selector .alignleft,selector .alignright{float:none;margin:0 0 20px;width:100%;}}
CSS;
    }

    private static function archive_posts_css(): string {
        return <<<'CSS'
selector .elementor-posts-container{gap:22px 18px;}
selector .elementor-post{border:1px solid #E2E5E1;border-radius:8px;overflow:hidden;transition:border-color .18s ease,transform .18s ease;}
selector .elementor-post:hover{border-color:var(--e-global-color-primary);transform:translateY(-2px);}
selector .elementor-post__thumbnail img{aspect-ratio:16/10;object-fit:cover;width:100%;}
selector .elementor-post__text{padding:16px 17px 18px;}
selector .elementor-post__title{font-family:var(--e-global-typography-primary-font-family,serif);font-size:20px;line-height:1.25;margin:0 0 9px;}
selector .elementor-post__meta-data{color:var(--e-global-color-secondary);font-size:12px;}
selector .elementor-post__excerpt{font-size:14px;line-height:1.6;margin-top:10px;}
selector .elementor-pagination{margin-top:36px;}
@media(max-width:767px){selector .elementor-post__title{font-size:19px;}}
CSS;
    }
}
