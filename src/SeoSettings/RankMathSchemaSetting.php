<?php

namespace HWS\BaseTools\SeoSettings;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\SchemaTools\RankMathSchemaControl;
use Hexa\PluginCore\SchemaTools\SchemaPage;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * The site's one switch for Rank Math's schema output.
 *
 * Every Hexa plugin prints its schema through HexaWP Core's shared page
 * graph; this setting decides whether Rank Math may still print its own
 * schema block next to it. Rank Math itself stays active.
 */
final class RankMathSchemaSetting implements ModuleInterface {
    public const OPTION = 'hws_rank_math_schema_output';
    public const SAVE_ACTION = 'hws_rank_math_schema_output_save';

    public function register(): void {
        RankMathSchemaControl::register( [ self::class, 'mode' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ self::class, 'handle_save' ] );
    }

    public static function mode(): string {
        $mode = (string) get_option( self::OPTION, RankMathSchemaControl::WHERE_HEXA );
        return isset( RankMathSchemaControl::MODES[ $mode ] ) ? $mode : RankMathSchemaControl::WHERE_HEXA;
    }

    public static function handle_save(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_die( esc_html__( 'You are not allowed to change this setting.', 'hws-base-tools' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( self::SAVE_ACTION );
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : '';
        if ( isset( RankMathSchemaControl::MODES[ $mode ] ) ) {
            update_option( self::OPTION, $mode, true );
        }
        if ( function_exists( 'do_action' ) ) {
            do_action( 'litespeed_purge_all' );
        }
        wp_safe_redirect( add_query_arg( [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => 'seo', 'saved' => 'rank-math-schema' ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    public static function render(): void {
        CoreUi::render_assets();
        $mode = self::mode();
        $rank_math_active = defined( 'RANK_MATH_VERSION' );
        $sources = SchemaPage::sources();
        $saved = isset( $_GET['saved'] ) && 'rank-math-schema' === sanitize_key( wp_unslash( (string) $_GET['saved'] ) );

        $options = '';
        foreach ( RankMathSchemaControl::MODES as $value => $label ) {
            $options .= '<label style="display:block;margin:6px 0"><input type="radio" name="mode" value="' . esc_attr( $value ) . '"' . checked( $mode, $value, false ) . '> ' . esc_html( $label ) . '</label>';
        }

        $body  = '<p>Hexa plugins print one shared schema block per page (<code>hexa-schema</code>). This switch decides whether Rank Math also prints its own schema block. Where Rank Math\'s schema is off, the duplicate image schema of Featured Image From URL is removed too. Rank Math stays active for titles, descriptions and sitemaps either way.</p>';
        $body .= '<p>' . CoreUi::pill( $rank_math_active ? 'Rank Math active' : 'Rank Math not active', $rank_math_active ? 'dark' : 'warning' ) . ' ';
        $body .= CoreUi::pill( $sources ? 'Hexa schema from: ' . implode( ', ', $sources ) : 'No Hexa schema plugin active', $sources ? 'success' : 'warning' ) . '</p>';
        if ( $saved ) {
            $body .= '<p>' . CoreUi::pill( 'Saved', 'success' ) . '</p>';
        }
        $body .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $body .= '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
        $body .= wp_nonce_field( self::SAVE_ACTION, '_wpnonce', true, false );
        $body .= $options;
        $body .= '<p><button type="submit" class="hpc-button">Save</button></p></form>';

        echo '<div class="hpc-ui">' . CoreUi::card( [ 'title' => 'Rank Math schema output', 'body_html' => $body ] ) . '</div>';
    }
}
