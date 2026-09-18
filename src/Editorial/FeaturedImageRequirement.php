<?php

namespace HWS\BaseTools\Editorial;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * Requires a usable featured image before selected content types can publish.
 */
final class FeaturedImageRequirement implements ModuleInterface {
    public const FEATURE_OPTION = 'enable_required_featured_image';
    public const POST_TYPES_OPTION = 'hws_required_featured_image_post_types';
    public const MIN_WIDTH_OPTION = 'hws_required_featured_image_min_width';
    public const MIN_HEIGHT_OPTION = 'hws_required_featured_image_min_height';
    public const ENFORCEMENT_START_OPTION = 'hws_required_featured_image_enforcement_start';
    public const MIGRATION_OPTION = 'hws_required_featured_image_migration';
    public const DEFAULT_POST_TYPES = [ 'post' ];
    public const LEGACY_PLUGIN_FILE = 'require-featured-image/require-featured-image.php';

    private static bool $active = false;
    private static bool $blocked = false;

    public function register(): void {
        add_action( 'init', [ self::class, 'migrate_legacy_settings' ], 1 );

        if ( self::enabled() ) {
            self::activate();
        }
    }

    public static function activate(): void {
        if ( self::$active ) {
            return;
        }

        self::$active = true;
        self::ensure_enforcement_start();

        add_filter( 'wp_insert_post_data', [ self::class, 'filter_insert_post_data' ], 99, 4 );
        add_filter( 'redirect_post_location', [ self::class, 'redirect_post_location' ], 99 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_editor_assets' ] );
        add_action( 'admin_notices', [ self::class, 'render_admin_notice' ] );

        foreach ( self::settings()['post_types'] as $post_type ) {
            add_filter( 'rest_pre_insert_' . $post_type, [ self::class, 'validate_rest_publish' ], 10, 2 );
        }
    }

    public static function enabled(): bool {
        return (bool) get_option( self::FEATURE_OPTION, false );
    }

    /** @return array{post_types:list<string>,min_width:int,min_height:int,enforcement_start:int} */
    public static function settings(): array {
        return [
            'post_types'        => self::normalize_post_types( get_option( self::POST_TYPES_OPTION, self::DEFAULT_POST_TYPES ) ),
            'min_width'         => self::normalize_dimension( get_option( self::MIN_WIDTH_OPTION, 0 ) ),
            'min_height'        => self::normalize_dimension( get_option( self::MIN_HEIGHT_OPTION, 0 ) ),
            'enforcement_start' => max( 0, (int) get_option( self::ENFORCEMENT_START_OPTION, 0 ) ),
        ];
    }

    /** @return array{post_types:list<string>,min_width:int,min_height:int,enforcement_start:int} */
    public static function save_settings( array $input ): array {
        $settings = [
            'post_types'        => self::normalize_post_types( $input['post_types'] ?? self::DEFAULT_POST_TYPES ),
            'min_width'         => self::normalize_dimension( $input['min_width'] ?? 0 ),
            'min_height'        => self::normalize_dimension( $input['min_height'] ?? 0 ),
            'enforcement_start' => max( 0, (int) get_option( self::ENFORCEMENT_START_OPTION, time() ) ),
        ];

        update_option( self::POST_TYPES_OPTION, $settings['post_types'], false );
        update_option( self::MIN_WIDTH_OPTION, $settings['min_width'], false );
        update_option( self::MIN_HEIGHT_OPTION, $settings['min_height'], false );
        update_option( self::ENFORCEMENT_START_OPTION, $settings['enforcement_start'], false );

        return $settings;
    }

    /** @return list<string> */
    public static function normalize_post_types( mixed $post_types ): array {
        $post_types = is_array( $post_types ) ? $post_types : [ $post_types ];
        $normalized = [];

        foreach ( $post_types as $post_type ) {
            $post_type = sanitize_key( (string) $post_type );
            if ( '' !== $post_type && ! in_array( $post_type, $normalized, true ) ) {
                $normalized[] = $post_type;
            }
        }

        return $normalized;
    }

    public static function normalize_dimension( mixed $dimension ): int {
        return max( 0, min( 10000, (int) $dimension ) );
    }

    public static function migrate_legacy_settings(): void {
        if ( get_option( self::MIGRATION_OPTION, false ) || ! self::legacy_plugin_active() ) {
            return;
        }

        $legacy_post_types = get_option( 'rfi_post_types', self::DEFAULT_POST_TYPES );
        $legacy_size = get_option( 'rfi_minimum_size', [ 'width' => 0, 'height' => 0 ] );
        $legacy_size = is_array( $legacy_size ) ? $legacy_size : [];
        $legacy_start = max( 0, (int) get_option( 'rfi_enforcement_start', time() ) );

        update_option( self::POST_TYPES_OPTION, self::normalize_post_types( $legacy_post_types ), false );
        update_option( self::MIN_WIDTH_OPTION, self::normalize_dimension( $legacy_size['width'] ?? 0 ), false );
        update_option( self::MIN_HEIGHT_OPTION, self::normalize_dimension( $legacy_size['height'] ?? 0 ), false );
        update_option( self::ENFORCEMENT_START_OPTION, $legacy_start, false );
        update_option( self::FEATURE_OPTION, true, false );
        update_option( self::MIGRATION_OPTION, PluginMetadata::VERSION, false );

        self::activate();
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $postarr */
    public static function filter_insert_post_data( array $data, array $postarr ): array {
        if ( 'publish' !== (string) ( $data['post_status'] ?? '' ) || ! self::should_enforce( $data ) ) {
            return $data;
        }

        $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        $thumbnail_id = self::candidate_thumbnail_id( $postarr, $post_id );
        if ( self::image_is_valid( $thumbnail_id ) ) {
            return $data;
        }

        $old_status = $post_id > 0 && function_exists( 'get_post_status' )
            ? (string) get_post_status( $post_id )
            : '';
        $data['post_status'] = '' !== $old_status && 'publish' !== $old_status ? $old_status : 'draft';
        self::$blocked = true;

        return $data;
    }

    public static function validate_rest_publish( mixed $prepared_post, mixed $request ): mixed {
        if ( 'publish' !== self::request_value( $request, 'status' ) ) {
            return $prepared_post;
        }

        $post_type = sanitize_key( (string) ( $prepared_post->post_type ?? '' ) );
        $post_date = (string) ( $prepared_post->post_date ?? '' );
        if ( ! self::post_type_is_required( $post_type ) || ! self::date_is_enforced( $post_date ) ) {
            return $prepared_post;
        }

        $post_id = (int) ( $prepared_post->ID ?? self::request_value( $request, 'id' ) );
        $featured_media = self::request_has_value( $request, 'featured_media' )
            ? (int) self::request_value( $request, 'featured_media' )
            : ( $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0 );

        if ( self::image_is_valid( $featured_media ) ) {
            return $prepared_post;
        }

        return new \WP_Error(
            'hws_required_featured_image',
            self::warning_message(),
            [ 'status' => 400 ]
        );
    }

    public static function image_is_valid( int $attachment_id ): bool {
        if ( 0 === $attachment_id ) {
            return false;
        }

        $settings = self::settings();
        if ( 0 === $settings['min_width'] && 0 === $settings['min_height'] ) {
            return true;
        }

        $image = function_exists( 'wp_get_attachment_image_src' )
            ? wp_get_attachment_image_src( $attachment_id, 'full' )
            : false;

        return is_array( $image )
            && (int) ( $image[1] ?? 0 ) >= $settings['min_width']
            && (int) ( $image[2] ?? 0 ) >= $settings['min_height'];
    }

    public static function enqueue_editor_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $post = get_post();
        if ( ! $post || ! self::post_type_is_required( (string) $post->post_type ) || ! self::date_is_enforced( (string) $post->post_date ) ) {
            return;
        }

        $handle = 'hws-required-featured-image';
        $settings = self::settings();
        wp_enqueue_script(
            $handle,
            plugin_dir_url( PluginMetadata::plugin_file() ) . 'assets/admin/required-featured-image.js',
            [ 'jquery', 'wp-data', 'wp-notices' ],
            PluginMetadata::VERSION,
            true
        );
        wp_localize_script(
            $handle,
            'hwsRequiredFeaturedImage',
            [
                'lockKey'   => 'hws-required-featured-image',
                'minWidth'  => $settings['min_width'],
                'minHeight' => $settings['min_height'],
                'message'   => self::warning_message(),
                'noticeId'  => 'hws-required-featured-image',
            ]
        );
    }

    public static function redirect_post_location( string $location ): string {
        return self::$blocked ? add_query_arg( 'hws_required_featured_image', '1', $location ) : $location;
    }

    public static function render_admin_notice(): void {
        if ( empty( $_GET['hws_required_featured_image'] ) ) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>'
            . esc_html__( 'Featured image required:', 'hws-base-tools' )
            . '</strong> '
            . esc_html( self::warning_message() )
            . '</p></div>';
    }

    public static function warning_message(): string {
        $settings = self::settings();
        if ( 0 === $settings['min_width'] && 0 === $settings['min_height'] ) {
            return __( 'Add a featured image before publishing.', 'hws-base-tools' );
        }

        return sprintf(
            /* translators: 1: minimum width, 2: minimum height. */
            __( 'Add a featured image that is at least %1$d × %2$d pixels before publishing.', 'hws-base-tools' ),
            $settings['min_width'],
            $settings['min_height']
        );
    }

    /** @return array<string,string> */
    public static function post_type_choices(): array {
        $choices = [];
        $objects = function_exists( 'get_post_types' )
            ? get_post_types( [ 'public' => true ], 'objects' )
            : [];

        foreach ( is_array( $objects ) ? $objects : [] as $key => $object ) {
            $post_type = sanitize_key( is_string( $key ) ? $key : (string) ( $object->name ?? '' ) );
            if ( '' === $post_type || ! post_type_supports( $post_type, 'thumbnail' ) ) {
                continue;
            }
            $choices[ $post_type ] = (string) ( $object->labels->name ?? $object->label ?? $post_type );
        }

        if ( ! isset( $choices['post'] ) ) {
            $choices['post'] = 'Posts';
        }

        return $choices;
    }

    public static function render_settings(): void {
        $settings = self::settings();
        ?>
        <div class="hws-feature-settings" data-feature-settings="<?php echo esc_attr( self::FEATURE_OPTION ); ?>">
            <fieldset>
                <legend><strong>Content types that require a featured image</strong></legend>
                <?php foreach ( self::post_type_choices() as $post_type => $label ) : ?>
                    <label style="display:block;margin:7px 0">
                        <input type="checkbox" value="<?php echo esc_attr( $post_type ); ?>" data-hws-feature-field="post_types" <?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <label>
                <span>Minimum width (pixels)</span>
                <input type="number" min="0" max="10000" step="1" data-hws-feature-field="min_width" value="<?php echo esc_attr( (string) $settings['min_width'] ); ?>">
            </label>
            <label>
                <span>Minimum height (pixels)</span>
                <input type="number" min="0" max="10000" step="1" data-hws-feature-field="min_height" value="<?php echo esc_attr( (string) $settings['min_height'] ); ?>">
            </label>
            <p class="description">A value of 0 accepts any image size. Existing Require Featured Image settings migrate once when that plugin is active.</p>
            <button type="button" class="button hws-feature-save-settings" data-feature-id="<?php echo esc_attr( self::FEATURE_OPTION ); ?>">Save Settings</button>
            <span class="hws-feature-setting-status" aria-live="polite"></span>
        </div>
        <?php
    }

    /** @return array{passed:bool,message:string,proof:string,ran_at:string} */
    public static function test_report(): array {
        $settings = self::settings();

        return [
            'passed'  => [] !== $settings['post_types'] && self::enabled(),
            'message' => [] !== $settings['post_types']
                ? 'Featured image publication enforcement is enabled with normalized settings.'
                : 'Choose at least one content type before using featured image enforcement.',
            'proof'   => 'Content types: ' . implode( ', ', $settings['post_types'] )
                . '; minimum: ' . $settings['min_width'] . ' × ' . $settings['min_height'] . ' pixels.',
            'ran_at'  => current_time( 'mysql' ),
        ];
    }

    /** @param array<string,mixed> $data */
    private static function should_enforce( array $data ): bool {
        return self::post_type_is_required( (string) ( $data['post_type'] ?? '' ) )
            && self::date_is_enforced( (string) ( $data['post_date'] ?? '' ) );
    }

    private static function post_type_is_required( string $post_type ): bool {
        return in_array( sanitize_key( $post_type ), self::settings()['post_types'], true );
    }

    private static function date_is_enforced( string $post_date ): bool {
        $start = self::settings()['enforcement_start'];
        if ( 0 === $start || '' === $post_date || '0000-00-00 00:00:00' === $post_date ) {
            return true;
        }

        $timestamp = strtotime( $post_date );

        return false === $timestamp || $timestamp > $start;
    }

    /** @param array<string,mixed> $postarr */
    private static function candidate_thumbnail_id( array $postarr, int $post_id ): int {
        if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( '_thumbnail_id', $postarr['meta_input'] ) ) {
            return (int) $postarr['meta_input']['_thumbnail_id'];
        }
        if ( array_key_exists( '_thumbnail_id', $postarr ) ) {
            return (int) $postarr['_thumbnail_id'];
        }

        return $post_id > 0 && function_exists( 'get_post_thumbnail_id' )
            ? (int) get_post_thumbnail_id( $post_id )
            : 0;
    }

    private static function ensure_enforcement_start(): void {
        if ( false === get_option( self::ENFORCEMENT_START_OPTION, false ) ) {
            update_option( self::ENFORCEMENT_START_OPTION, time(), false );
        }
    }

    private static function legacy_plugin_active(): bool {
        $active = (array) get_option( 'active_plugins', [] );
        if ( in_array( self::LEGACY_PLUGIN_FILE, $active, true ) ) {
            return true;
        }

        $network = function_exists( 'get_site_option' )
            ? (array) get_site_option( 'active_sitewide_plugins', [] )
            : [];

        return isset( $network[ self::LEGACY_PLUGIN_FILE ] );
    }

    private static function request_value( mixed $request, string $key ): mixed {
        if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
            return $request->get_param( $key );
        }
        if ( is_array( $request ) || $request instanceof \ArrayAccess ) {
            return $request[ $key ] ?? null;
        }

        return null;
    }

    private static function request_has_value( mixed $request, string $key ): bool {
        if ( is_object( $request ) && method_exists( $request, 'has_param' ) ) {
            return $request->has_param( $key );
        }
        if ( is_array( $request ) ) {
            return array_key_exists( $key, $request );
        }
        if ( $request instanceof \ArrayAccess ) {
            return $request->offsetExists( $key );
        }

        return false;
    }
}
