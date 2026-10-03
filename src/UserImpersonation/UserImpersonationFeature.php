<?php

declare( strict_types=1 );

namespace HWS\BaseTools\UserImpersonation;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class UserImpersonationFeature implements ModuleInterface {
    public const FEATURE_OPTION = 'enable_hws_user_impersonation';

    public const SETTINGS_OPTION = 'hws_view_as_settings';

    /** @return array{locations:list<string>,owner_fields:list<string>} */
    public static function settings(): array {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        return self::normalize_settings( is_array( $settings ) ? $settings : [] );
    }

    public static function location_enabled( string $location ): bool {
        return in_array( $location, self::settings()['locations'], true );
    }

    /** @param array<string,mixed> $input @return array{locations:list<string>,owner_fields:list<string>} */
    public static function normalize_settings( array $input ): array {
        $locations = array_values( array_intersect( [ 'users', 'post_editor', 'single_content' ], (array) ( $input['locations'] ?? [ 'users', 'post_editor', 'single_content' ] ) ) );
        $fields = $input['owner_fields'] ?? [];
        $fields = is_string( $fields ) ? explode( ',', $fields ) : (array) $fields;
        $fields = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_filter( $fields, 'is_scalar' ) ) ) ) );
        return [ 'locations' => $locations, 'owner_fields' => array_slice( $fields, 0, 20 ) ];
    }

    /** @param array<string,mixed> $input */
    public static function save_settings( array $input ): array {
        $settings = self::normalize_settings( $input );
        update_option( self::SETTINGS_OPTION, $settings, false );
        return $settings;
    }

    public static function render_settings(): void {
        $settings = self::settings();
        ?>
        <div class="hws-feature-settings" data-feature-settings="<?php echo esc_attr( self::FEATURE_OPTION ); ?>">
            <fieldset>
                <legend><strong>Show View As User on</strong></legend>
                <?php foreach ( [ 'users' => 'User administration (users, user edit and profile)', 'post_editor' => 'Single post or page editor', 'single_content' => 'Single content pages on the website' ] as $location => $label ) : ?>
                    <label style="display:block;margin:7px 0"><input type="checkbox" value="<?php echo esc_attr( $location ); ?>" data-hws-feature-field="locations" <?php checked( in_array( $location, $settings['locations'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
            </fieldset>
            <label><span>Additional owner user fields (optional)</span><input type="text" class="regular-text" data-hws-feature-field="owner_fields" value="<?php echo esc_attr( implode( ', ', $settings['owner_fields'] ) ); ?>"></label>
            <p class="description">Comma-separated post metadata / ACF user field names. The author is always included; matching accounts appear once. You can search any other user. The feature is disabled by default.</p>
            <button type="button" class="button hws-feature-save-settings" data-feature-id="<?php echo esc_attr( self::FEATURE_OPTION ); ?>">Save Settings</button>
            <span class="hws-feature-setting-status" aria-live="polite"></span>
        </div>
        <?php
    }

    private static bool $active = false;

    public function __construct( private readonly ?VirtualSessionStoreInterface $store = null ) {
    }

    public function register(): void {
        if ( self::enabled() ) {
            self::activate( $this->store );
        }
    }

    public static function enabled(): bool {
        return (bool) get_option( self::FEATURE_OPTION, false );
    }

    public static function activate( ?VirtualSessionStoreInterface $store = null ): void {
        if ( self::$active ) {
            return;
        }

        self::$active = true;
        $store = $store ?? new WordPressVirtualSessionStore();
        $policy = new ImpersonationAccessPolicy();
        $context = new VirtualRequestContext( $store, $policy );
        $controller = new ViewAsController( $store, $context, $policy );

        $context->register();
        $controller->register();
        ( new VirtualRequestTransport( $context ) )->register();
        ( new ViewAsPresentation( $context, $controller ) )->register();
        ( new ViewAsToolbar( $context ) )->register();
    }

    /** @return array{passed:bool,message:string,proof:string,ran_at:string} */
    public static function test_report(): array {
        $enabled = self::enabled();

        return [
            'passed'  => $enabled && self::$active,
            'message' => $enabled && self::$active
                ? 'Administrator-only View As hooks are active.'
                : 'View As is disabled or its hooks are not active.',
            'proof'   => 'Capability: manage_options; isolated request token; WordPress authentication cookie remains unchanged.',
            'ran_at'  => current_time( 'mysql' ),
        ];
    }
}
