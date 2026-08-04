<?php

namespace HWS\BaseTools\FrontendContent;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * HWS-owned reading progress feature and SMP compatibility migration.
 */
final class ReadingProgress implements ModuleInterface {
    public const FEATURE_OPTION = 'enable_reading_progress_bar';
    public const SCOPE_OPTION = 'hws_reading_progress_scope';
    public const ENTIRE_SITE_OPTION = 'hws_reading_progress_entire_site';
    public const FRONT_PAGE_OPTION = 'hws_reading_progress_front_page';
    public const POST_TYPES_OPTION = 'hws_reading_progress_post_types';
    public const STYLE_OPTION = 'hws_reading_progress_style';
    public const COLOR_OPTION = 'hws_reading_progress_color';
    public const MIGRATION_OPTION = 'hws_reading_progress_smp_migration';
    public const DEFAULT_SCOPE = 'posts';
    public const DEFAULT_POST_TYPES = [ 'post' ];
    public const DEFAULT_STYLE = 'thin';
    public const DEFAULT_COLOR = '#00ff41';
    public const SMP_OWNER_REMOVED_VERSION = '1.0.24';

    private static bool $frontend_registered = false;
    private static bool $markup_rendered = false;

    public function register(): void {
        add_action( 'init', [ self::class, 'migrate_from_smp' ], 1 );
    }

    public static function activate(): void {
        if ( self::$frontend_registered ) {
            return;
        }

        self::$frontend_registered = true;
        add_action( 'wp_head', [ self::class, 'print_styles' ], 31 );
        add_action( 'wp_body_open', [ self::class, 'print_markup' ], 1 );
        add_action( 'wp_footer', [ self::class, 'print_script' ], 31 );
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function designs(): array {
        return [
            'thin' => [
                'label'       => 'Thin top line',
                'description' => 'A 2px page-edge indicator matching the established site presentation.',
            ],
            'track' => [
                'label'       => 'Full-width track',
                'description' => 'A 5px bar with a quiet background rail behind the progress fill.',
            ],
            'glow' => [
                'label'       => 'Luminous edge',
                'description' => 'A slim line with a stronger glow for dark or image-heavy headers.',
            ],
            'floating' => [
                'label'       => 'Floating capsule',
                'description' => 'A rounded 6px rail inset from the viewport edges.',
            ],
            'segmented' => [
                'label'       => 'Segmented rail',
                'description' => 'A 6px sequence of compact blocks that fills across the page.',
            ],
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function scopes(): array {
        return [
            'posts' => [
                'label'       => 'Posts only',
                'description' => 'Show the progress bar on public single-post pages.',
            ],
            'posts_front_page' => [
                'label'       => 'Posts and front page',
                'description' => 'Show the progress bar on public single posts and the site front page.',
            ],
            'sitewide' => [
                'label'       => 'Sitewide',
                'description' => 'Show the progress bar throughout the public frontend.',
            ],
            'selected' => [
                'label'       => 'Selected content',
                'description' => 'Show the progress bar on the selected public content types and optional front page.',
            ],
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function post_type_choices(): array {
        $choices = [];
        $objects = function_exists( 'get_post_types' )
            ? get_post_types( [ 'public' => true ], 'objects' )
            : [];

        if ( is_array( $objects ) ) {
            foreach ( $objects as $key => $object ) {
                $post_type = sanitize_key( is_string( $key ) ? $key : (string) ( $object->name ?? '' ) );
                if ( '' === $post_type || 'attachment' === $post_type ) {
                    continue;
                }

                $label = (string) ( $object->labels->name ?? $object->label ?? $post_type );
                $single = (string) ( $object->labels->singular_name ?? $label );
                $choices[ $post_type ] = [
                    'label'       => $label,
                    'description' => 'Every public single ' . strtolower( $single ) . '.',
                ];
            }
        }

        foreach ( [
            'post' => [ 'label' => 'Posts', 'description' => 'Every public single post.' ],
            'page' => [ 'label' => 'Pages', 'description' => 'Every public single page.' ],
        ] as $post_type => $fallback ) {
            if ( ! isset( $choices[ $post_type ] ) ) {
                $choices[ $post_type ] = $fallback;
            }
        }

        $ordered = [];
        foreach ( [ 'post', 'page' ] as $post_type ) {
            if ( isset( $choices[ $post_type ] ) ) {
                $ordered[ $post_type ] = $choices[ $post_type ];
                unset( $choices[ $post_type ] );
            }
        }
        uasort( $choices, static fn( array $left, array $right ): int => strnatcasecmp( $left['label'], $right['label'] ) );

        return $ordered + $choices;
    }

    public static function normalize_scope( string $scope ): string {
        $scope = sanitize_key( $scope );

        return array_key_exists( $scope, self::scopes() ) ? $scope : self::DEFAULT_SCOPE;
    }

    public static function normalize_style( string $style ): string {
        $style = sanitize_key( $style );

        return array_key_exists( $style, self::designs() ) ? $style : self::DEFAULT_STYLE;
    }

    /** @return list<string> */
    public static function normalize_post_types( mixed $post_types ): array {
        $post_types = is_array( $post_types ) ? $post_types : [ $post_types ];
        $allowed = array_keys( self::post_type_choices() );
        $normalized = [];

        foreach ( $post_types as $post_type ) {
            $post_type = sanitize_key( (string) $post_type );
            if ( '' !== $post_type && in_array( $post_type, $allowed, true ) && ! in_array( $post_type, $normalized, true ) ) {
                $normalized[] = $post_type;
            }
        }

        return $normalized;
    }

    public static function normalize_color( string $color ): string {
        $color = sanitize_hex_color( $color );

        return is_string( $color ) && '' !== $color ? strtolower( $color ) : self::DEFAULT_COLOR;
    }

    /** @return array{scope:string,entire_site:bool,front_page:bool,post_types:list<string>,style:string,color:string} */
    public static function settings(): array {
        $legacy_scope = self::normalize_scope( (string) get_option( self::SCOPE_OPTION, self::DEFAULT_SCOPE ) );
        $missing = '__hws_reading_progress_missing__';
        $entire_site = get_option( self::ENTIRE_SITE_OPTION, $missing );
        $front_page = get_option( self::FRONT_PAGE_OPTION, $missing );
        $post_types = get_option( self::POST_TYPES_OPTION, $missing );

        $entire_site = $missing === $entire_site ? 'sitewide' === $legacy_scope : self::normalize_boolean( $entire_site );
        $front_page = $missing === $front_page ? 'posts_front_page' === $legacy_scope : self::normalize_boolean( $front_page );
        $post_types = $missing === $post_types ? self::DEFAULT_POST_TYPES : self::normalize_post_types( $post_types );

        return [
            'scope'       => self::scope_from_targets( $entire_site, $front_page, $post_types ),
            'entire_site' => $entire_site,
            'front_page'  => $front_page,
            'post_types'  => $post_types,
            'style'       => self::normalize_style( (string) get_option( self::STYLE_OPTION, self::DEFAULT_STYLE ) ),
            'color'       => self::normalize_color( (string) get_option( self::COLOR_OPTION, self::DEFAULT_COLOR ) ),
        ];
    }

    /** @return array{scope:string,entire_site:bool,front_page:bool,post_types:list<string>,style:string,color:string} */
    public static function save_settings( array $settings ): array {
        $legacy_scope = self::normalize_scope( (string) ( $settings['scope'] ?? self::DEFAULT_SCOPE ) );
        $entire_site = array_key_exists( 'entire_site', $settings )
            ? self::normalize_boolean( $settings['entire_site'] )
            : 'sitewide' === $legacy_scope;
        $front_page = array_key_exists( 'front_page', $settings )
            ? self::normalize_boolean( $settings['front_page'] )
            : 'posts_front_page' === $legacy_scope;
        $post_types = array_key_exists( 'post_types', $settings )
            ? self::normalize_post_types( $settings['post_types'] )
            : self::DEFAULT_POST_TYPES;

        $normalized = [
            'scope'       => self::scope_from_targets( $entire_site, $front_page, $post_types ),
            'entire_site' => $entire_site,
            'front_page'  => $front_page,
            'post_types'  => $post_types,
            'style'       => self::normalize_style( (string) ( $settings['style'] ?? self::DEFAULT_STYLE ) ),
            'color'       => self::normalize_color( (string) ( $settings['color'] ?? self::DEFAULT_COLOR ) ),
        ];

        update_option( self::SCOPE_OPTION, $normalized['scope'], false );
        update_option( self::ENTIRE_SITE_OPTION, $normalized['entire_site'], false );
        update_option( self::FRONT_PAGE_OPTION, $normalized['front_page'], false );
        update_option( self::POST_TYPES_OPTION, $normalized['post_types'], false );
        update_option( self::STYLE_OPTION, $normalized['style'], false );
        update_option( self::COLOR_OPTION, $normalized['color'], false );

        return $normalized;
    }

    public static function migrate_from_smp(): void {
        if ( get_option( self::MIGRATION_OPTION, false ) ) {
            return;
        }

        $legacy = get_option( 'smpi_settings', [] );
        if ( ! is_array( $legacy ) || ! array_key_exists( 'reading_progress_enabled', $legacy ) ) {
            return;
        }

        self::copy_option_if_missing( self::FEATURE_OPTION, (bool) $legacy['reading_progress_enabled'] );
        self::copy_option_if_missing(
            self::SCOPE_OPTION,
            self::normalize_scope( (string) ( $legacy['reading_progress_scope'] ?? self::DEFAULT_SCOPE ) )
        );
        self::copy_option_if_missing(
            self::STYLE_OPTION,
            self::normalize_style( (string) ( $legacy['reading_progress_style'] ?? self::DEFAULT_STYLE ) )
        );
        self::copy_option_if_missing(
            self::COLOR_OPTION,
            self::normalize_color( (string) ( $legacy['reading_progress_color'] ?? self::DEFAULT_COLOR ) )
        );

        update_option( self::MIGRATION_OPTION, self::SMP_OWNER_REMOVED_VERSION, false );
    }

    public static function scope_matches_current_request( string $scope ): bool {
        $scope = self::normalize_scope( $scope );
        if ( 'sitewide' === $scope ) {
            return true;
        }
        if ( 'posts_front_page' === $scope && is_front_page() ) {
            return true;
        }

        return is_singular( 'post' );
    }

    /** @param array{entire_site?:mixed,front_page?:mixed,post_types?:mixed} $settings */
    public static function targets_match_current_request( array $settings ): bool {
        if ( self::normalize_boolean( $settings['entire_site'] ?? false ) ) {
            return true;
        }
        if ( self::normalize_boolean( $settings['front_page'] ?? false ) && is_front_page() ) {
            return true;
        }

        $post_types = self::normalize_post_types( $settings['post_types'] ?? [] );

        return [] !== $post_types && is_singular( $post_types );
    }

    /** @param list<string> $post_types */
    public static function scope_from_targets( bool $entire_site, bool $front_page, array $post_types ): string {
        if ( $entire_site ) {
            return 'sitewide';
        }
        if ( [ 'post' ] === $post_types ) {
            return $front_page ? 'posts_front_page' : 'posts';
        }

        return 'selected';
    }

    public static function preview_html( string $style ): string {
        $style = self::normalize_style( $style );

        return '<span class="hws-reading-progress-preview hws-reading-progress--' . esc_attr( $style ) . '" style="--hws-reading-progress-scale:.68;--hws-reading-progress-value:68%"><span class="hws-reading-progress__track"><span class="hws-reading-progress__fill"></span></span></span>';
    }

    public static function preview_css(): string {
        return '.hws-reading-progress-preview{background:#f8fafc;box-sizing:border-box;display:block;min-height:34px;overflow:hidden;padding:16px 0;position:relative;width:100%}'
            . '.hws-reading-progress-preview *{box-sizing:border-box}'
            . '.hws-reading-progress-preview.hws-reading-progress--floating .hws-reading-progress__track{margin:0 12px;width:calc(100% - 24px)}'
            . self::shared_design_css();
    }

    public static function print_styles(): void {
        if ( ! self::should_render() ) {
            return;
        }

        echo '<style id="hws-reading-progress-css">' . self::frontend_css() . '</style>';
    }

    public static function print_markup(): void {
        if ( self::$markup_rendered || ! self::should_render() ) {
            return;
        }

        $settings = self::settings();
        $variables = '--hws-reading-progress-scale:0;--hws-reading-progress-value:0%;' . self::color_variables( $settings['color'] );
        $aria_label = is_singular( 'post' ) ? 'Article reading progress' : 'Page reading progress';

        echo '<div id="hws-reading-progress" class="hws-reading-progress hws-reading-progress--' . esc_attr( $settings['style'] ) . '" role="progressbar" aria-label="' . esc_attr( $aria_label ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="' . esc_attr( $variables ) . '"><span class="hws-reading-progress__track"><span class="hws-reading-progress__fill"></span></span></div>';
        self::$markup_rendered = true;
    }

    public static function print_script(): void {
        if ( ! self::should_render() ) {
            return;
        }
        if ( ! self::$markup_rendered ) {
            self::print_markup();
        }
        ?>
        <script id="hws-reading-progress-script" data-no-optimize="1" data-cfasync="false">
        (function(){
            var indicator=document.getElementById("hws-reading-progress");
            if(!indicator||indicator.dataset.hwsBound==="1"){return;}
            indicator.dataset.hwsBound="1";
            var root=document.documentElement;
            var ticking=false;
            var lastValue=-1;
            var resizeObserver=null;
            function update(){
                ticking=false;
                var bodyHeight=document.body?document.body.scrollHeight:0;
                var pageHeight=Math.max(root.scrollHeight,bodyHeight);
                var maximum=Math.max(0,pageHeight-window.innerHeight);
                var offset=Math.max(0,window.scrollY||window.pageYOffset||root.scrollTop||0);
                var value=maximum>0?Math.max(0,Math.min(100,(offset/maximum)*100)):0;
                if(Math.abs(value-lastValue)<0.01){return;}
                lastValue=value;
                indicator.style.setProperty("--hws-reading-progress-scale",(value/100).toFixed(5));
                indicator.style.setProperty("--hws-reading-progress-value",value.toFixed(3)+"%");
                indicator.setAttribute("aria-valuenow",String(Math.round(value)));
                indicator.classList.toggle("is-complete",value>=99.9);
            }
            function requestUpdate(){
                if(ticking){return;}
                ticking=true;
                window.requestAnimationFrame(update);
            }
            window.addEventListener("scroll",requestUpdate,{passive:true});
            window.addEventListener("resize",requestUpdate,{passive:true});
            window.addEventListener("pageshow",requestUpdate,{passive:true});
            window.addEventListener("load",requestUpdate,{once:true,passive:true});
            if("ResizeObserver" in window&&document.body){resizeObserver=new ResizeObserver(requestUpdate);resizeObserver.observe(document.body);}
            if(document.fonts&&document.fonts.ready){document.fonts.ready.then(requestUpdate);}
            update();
        })();
        </script>
        <?php
    }

    private static function should_render(): bool {
        if ( ! get_option( self::FEATURE_OPTION, false ) || ! self::is_public_dom_context() || self::legacy_smp_will_render() ) {
            return false;
        }

        return self::targets_match_current_request( self::settings() );
    }

    private static function legacy_smp_will_render(): bool {
        if ( ! class_exists( '\\smp_publication_integration\\Config', false ) ) {
            return false;
        }

        $version = defined( '\\smp_publication_integration\\Config::VERSION' )
            ? (string) constant( '\\smp_publication_integration\\Config::VERSION' )
            : '0.0.0';
        if ( version_compare( $version, self::SMP_OWNER_REMOVED_VERSION, '>=' ) ) {
            return false;
        }

        $legacy = get_option( 'smpi_settings', [] );

        return is_array( $legacy ) && ! empty( $legacy['reading_progress_enabled'] );
    }

    private static function is_public_dom_context(): bool {
        if ( ( function_exists( 'is_admin' ) && is_admin() )
            || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
            || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
            || ( defined( 'WP_CLI' ) && WP_CLI )
            || ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() )
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
            || ( function_exists( 'is_feed' ) && is_feed() )
            || ( function_exists( 'is_embed' ) && is_embed() )
        ) {
            return false;
        }

        foreach ( [ $_GET, $_POST ] as $source ) {
            if ( is_array( $source ) && ( isset( $source['elementor-preview'] ) || isset( $source['elementor_library'] ) || isset( $source['elementor_page_id'] ) ) ) {
                return false;
            }
        }

        return true;
    }

    private static function copy_option_if_missing( string $option, mixed $value ): void {
        $sentinel = '__hws_reading_progress_missing__';
        if ( $sentinel === get_option( $option, $sentinel ) ) {
            update_option( $option, $value, false );
        }
    }

    private static function normalize_boolean( mixed $value ): bool {
        return true === $value || 1 === $value || in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
    }

    private static function frontend_css(): string {
        return '.hws-reading-progress{box-sizing:border-box;left:0;pointer-events:none;position:fixed;right:0;top:0;width:auto;z-index:2147483000}'
            . '.hws-reading-progress *{box-sizing:border-box}'
            . '.hws-reading-progress--floating{left:12px;right:12px;top:8px}'
            . '@media(max-width:600px){.hws-reading-progress--floating{left:8px;right:8px;top:6px}}'
            . '@media(prefers-reduced-motion:reduce){.hws-reading-progress__fill{transition:none!important}}'
            . self::shared_design_css();
    }

    private static function color_variables( string $color ): string {
        return '--hws-reading-progress-color:' . $color
            . ';--hws-reading-progress-soft:' . self::rgba( $color, 0.18 )
            . ';--hws-reading-progress-glow:' . self::rgba( $color, 0.55 );
    }

    private static function rgba( string $color, float $alpha ): string {
        $hex = ltrim( self::normalize_color( $color ), '#' );

        return 'rgba(' . hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) ) . ',' . $alpha . ')';
    }

    private static function shared_design_css(): string {
        return '.hws-reading-progress__track,.hws-reading-progress__fill{display:block;width:100%}'
            . '.hws-reading-progress__track{overflow:hidden;position:relative}'
            . '.hws-reading-progress__fill{background:var(--hws-reading-progress-color,#00ff41);height:100%;transform:scaleX(var(--hws-reading-progress-scale,0));transform-origin:0 50%;transition:transform .08s linear}'
            . '.hws-reading-progress--thin .hws-reading-progress__track{background:transparent;height:2px}'
            . '.hws-reading-progress--thin .hws-reading-progress__fill{box-shadow:0 0 12px var(--hws-reading-progress-glow,rgba(0,255,65,.55))}'
            . '.hws-reading-progress--track .hws-reading-progress__track{background:var(--hws-reading-progress-soft,rgba(37,99,235,.18));height:5px}'
            . '.hws-reading-progress--glow .hws-reading-progress__track{background:transparent;height:3px;overflow:visible}'
            . '.hws-reading-progress--glow .hws-reading-progress__fill{box-shadow:0 0 5px var(--hws-reading-progress-color,#00ff41),0 0 18px var(--hws-reading-progress-glow,rgba(0,255,65,.55))}'
            . '.hws-reading-progress--floating .hws-reading-progress__track{background:var(--hws-reading-progress-soft,rgba(37,99,235,.18));border:1px solid var(--hws-reading-progress-soft,rgba(37,99,235,.18));border-radius:999px;box-shadow:0 2px 12px rgba(15,23,42,.18);height:6px}'
            . '.hws-reading-progress--floating .hws-reading-progress__fill{border-radius:999px}'
            . '.hws-reading-progress--segmented .hws-reading-progress__track{background:repeating-linear-gradient(90deg,var(--hws-reading-progress-soft,rgba(37,99,235,.18)) 0 24px,transparent 24px 29px);height:6px;overflow:visible}'
            . '.hws-reading-progress--segmented .hws-reading-progress__fill{-webkit-clip-path:inset(0 calc(100% - var(--hws-reading-progress-value,0%)) 0 0);background:repeating-linear-gradient(90deg,var(--hws-reading-progress-color,#00ff41) 0 24px,transparent 24px 29px);clip-path:inset(0 calc(100% - var(--hws-reading-progress-value,0%)) 0 0);transform:none;transition:clip-path .08s linear}';
    }
}
