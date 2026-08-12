<?php

declare( strict_types=1 );

namespace HWS\BaseTools\BrandTemplates;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class BrandTemplatesAdmin {
    private const NOTICE_PREFIX = 'hws_brand_templates_notice_';

    public static function render(): void {
        CoreUi::render_assets();
        $settings = BrandTemplateSettings::all();
        $notice = self::consume_notice();
        ?>
        <div class="hpc-ui hws-brand-templates-admin">
            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'danger' === $notice['tone'] ? 'error' : $notice['tone'] ); ?> inline"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
            <?php endif; ?>
            <div class="hpc-hero">
                <div>
                    <h2>Brand Templates</h2>
                    <p>Provide safe WordPress fallbacks and optional native Elementor Theme Builder imports without replacing an existing branded template.</p>
                </div>
                <div><?php echo CoreUi::pill( 'HWS Base Tools ' . PluginMetadata::VERSION, 'dark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hpc-card" id="hws-brand-template-settings">
                <input type="hidden" name="action" value="hws_brand_templates_save">
                <?php wp_nonce_field( 'hws_brand_templates_save' ); ?>
                <h3>Runtime Defaults</h3>
                <p>Every option is opt-in. A fallback never supersedes an active Elementor Theme Builder document, and the default Page structure excludes the front page.</p>
                <div class="hpc-toggle-list">
                    <?php foreach ( BrandTemplateRegistry::all() as $context => $definition ) : ?>
                        <div class="hpc-toggle-row">
                            <div>
                                <?php
                                echo CoreUi::toggle(
                                    'settings[' . esc_attr( (string) $definition['option'] ) . ']',
                                    ! empty( $settings[ (string) $definition['option'] ] ),
                                    (string) $definition['label'],
                                    [ 'id' => 'hws-brand-template-' . $context ]
                                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                                <p class="hpc-small"><?php echo esc_html( (string) $definition['description'] ); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <h3>Content Element Styles</h3>
                <p>Single Post content styling remains here. Default Page presentation now has a dedicated visual chooser.</p>
                <div class="hpc-toggle-list">
                    <div class="hpc-toggle-row">
                        <div>
                            <a class="hpc-button secondary" href="<?php echo esc_url( add_query_arg( [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => 'page-layout-styling' ], admin_url( 'options-general.php' ) ) ); ?>">Open Page Layout Styling</a>
                            <p class="hpc-small">Choose Minimalist, five additional designs, or No Style without affecting Elementor pages.</p>
                        </div>
                    </div>
                    <div class="hpc-toggle-row">
                        <div>
                            <?php echo CoreUi::toggle( 'settings[single_content_styles_enabled]', ! empty( $settings['single_content_styles_enabled'] ), 'Default Single Post content styles', [ 'id' => 'hws-single-content-styles' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <p class="hpc-small">Applies only on standard Posts when explicitly enabled.</p>
                        </div>
                    </div>
                </div>
                <div class="hpc-actions hpc-actions-bottom">
                    <button type="submit" class="hpc-button">Save Runtime Defaults</button>
                </div>
            </form>

            <div class="hpc-callout" style="margin:14px 0;">
                <strong>Elementor import policy</strong>
                <p class="hpc-small">Imports use native containers, widgets, dynamic tags, current-query archives, Elementor global tokens, Rank Math breadcrumbs, responsive controls, preview contexts, and Theme Builder conditions. Existing matching conditions are reported as conflicts and remain untouched.</p>
            </div>

            <div class="hpc-stack">
                <?php foreach ( BrandTemplateRegistry::contexts() as $context ) : ?>
                    <?php self::render_import_card( $context ); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public static function handle_save(): void {
        self::authorize( 'hws_brand_templates_save' );
        $input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
            ? wp_unslash( $_POST['settings'] )
            : [];
        BrandTemplateSettings::update( $input );
        $results = ElementorTemplateImporter::synchronize_all();
        $conflicts = count(
            array_filter(
                $results,
                static fn( array $result ): bool => 'conflict' === ( $result['code'] ?? '' )
            )
        );
        self::set_notice(
            $conflicts ? 'warning' : 'success',
            $conflicts
                ? sprintf( 'Runtime defaults were saved. %d managed Elementor template(s) remain inactive because existing Theme Builder conditions take precedence.', $conflicts )
                : 'Runtime defaults were saved and managed Elementor conditions were synchronized.'
        );
        self::redirect();
    }

    public static function handle_import(): void {
        self::authorize( 'hws_brand_templates_import' );
        $context = isset( $_POST['context'] ) ? sanitize_key( (string) wp_unslash( $_POST['context'] ) ) : '';
        $replace = isset( $_POST['replace'] ) && '1' === (string) wp_unslash( $_POST['replace'] );
        $result = ElementorTemplateImporter::import( $context, $replace );
        self::set_notice( $result['success'] ? ( 'imported_with_conflict' === $result['code'] ? 'warning' : 'success' ) : 'danger', $result['message'] );
        self::redirect();
    }

    public static function handle_restore(): void {
        self::authorize( 'hws_brand_templates_restore' );
        $context = isset( $_POST['context'] ) ? sanitize_key( (string) wp_unslash( $_POST['context'] ) ) : '';
        $result = ElementorTemplateImporter::restore_latest( $context );
        self::set_notice( $result['success'] ? 'success' : 'danger', $result['message'] );
        self::redirect();
    }

    private static function render_import_card( string $context ): void {
        $status = ElementorTemplateImporter::status( $context );
        $definition = $status['definition'];
        if ( ! is_array( $definition ) ) {
            return;
        }

        $pills = '';
        if ( ! $status['available'] ) {
            $pills .= CoreUi::pill( 'Unavailable', 'danger' );
        } elseif ( $status['active'] ) {
            $pills .= CoreUi::pill( 'Active', 'success' );
        } elseif ( $status['template_id'] ) {
            $pills .= CoreUi::pill( 'Imported Draft', 'warning' );
        } else {
            $pills .= CoreUi::pill( 'Not Imported', 'dark' );
        }
        if ( $status['customized'] ) {
            $pills .= CoreUi::pill( 'Edited in Elementor', 'warning' );
        }

        ob_start();
        ?>
        <div class="hpc-grid two">
            <div class="hpc-subcard">
                <h4>Runtime</h4>
                <p><?php echo esc_html( (string) $definition['description'] ); ?></p>
                <ul class="hpc-list">
                    <li>Fallback: <strong><?php echo $status['enabled'] ? 'enabled' : 'disabled'; ?></strong></li>
                    <li>Template file: <code class="hpc-code"><?php echo esc_html( (string) ( $definition['template_file'] ?: 'Elementor only' ) ); ?></code></li>
                    <li>Theme condition: <code class="hpc-code"><?php echo esc_html( implode( ', ', $definition['conditions'] ) ); ?></code></li>
                </ul>
            </div>
            <div class="hpc-subcard">
                <h4>Managed Elementor Template</h4>
                <?php if ( ! $status['available'] ) : ?>
                    <p><?php echo esc_html( implode( ' ', $status['errors'] ) ); ?></p>
                <?php elseif ( $status['conflicts'] ) : ?>
                    <p>An existing Theme Builder template owns the same condition. HWS will not overwrite or deactivate it.</p>
                    <ul class="hpc-list">
                        <?php foreach ( $status['conflicts'] as $conflict ) : ?>
                            <li><a class="hpc-external" href="<?php echo esc_url( (string) $conflict['edit_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $conflict['template_title'] ); ?></a> <code>#<?php echo (int) $conflict['template_id']; ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p>No conflicting Theme Builder condition was detected.</p>
                <?php endif; ?>
                <?php if ( $status['template_id'] ) : ?>
                    <p>Managed template: <code class="hpc-code">#<?php echo (int) $status['template_id']; ?></code>; backups: <strong><?php echo (int) $status['backup_count']; ?></strong>.</p>
                <?php endif; ?>
                <div class="hpc-actions">
                    <?php if ( $status['available'] ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="hws_brand_templates_import">
                            <input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>">
                            <?php wp_nonce_field( 'hws_brand_templates_import' ); ?>
                            <?php if ( $status['customized'] ) : ?>
                                <input type="hidden" name="replace" value="1">
                                <button type="submit" class="hpc-button danger" onclick="return window.confirm('Replace the managed Elementor design after creating a rollback backup?');">Replace Managed Design</button>
                            <?php else : ?>
                                <button type="submit" class="hpc-button"><?php echo $status['template_id'] ? 'Refresh HWS Design' : 'Import Elementor Template'; ?></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <?php if ( $status['editor_url'] ) : ?>
                        <a class="hpc-button secondary hpc-external" href="<?php echo esc_url( $status['editor_url'] ); ?>" target="_blank" rel="noopener noreferrer">Edit in Elementor</a>
                    <?php endif; ?>
                    <?php if ( $status['example_url'] ) : ?>
                        <a class="hpc-button secondary hpc-external" href="<?php echo esc_url( $status['example_url'] ); ?>" target="_blank" rel="noopener noreferrer">View Live Context</a>
                    <?php endif; ?>
                    <?php if ( $status['backup_count'] > 0 ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="hws_brand_templates_restore">
                            <input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>">
                            <?php wp_nonce_field( 'hws_brand_templates_restore' ); ?>
                            <button type="submit" class="hpc-button secondary" onclick="return window.confirm('Restore the latest managed-template backup?');">Restore Latest Backup</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $body = (string) ob_get_clean();
        echo CoreUi::detail_card(
            [
                'title'       => (string) $definition['label'],
                'body_html'   => $body,
                'meta_html'   => $pills,
                'open'        => in_array( $context, [ BrandTemplateRegistry::CATEGORY, BrandTemplateRegistry::TAG ], true ),
                'persist_key' => 'hws-brand-template-' . $context,
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function authorize( string $nonce_action ): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_die(
                esc_html__( 'You are not allowed to manage brand templates.', 'hws-base-tools' ),
                esc_html__( 'Forbidden', 'hws-base-tools' ),
                [ 'response' => 403 ]
            );
        }
        check_admin_referer( $nonce_action );
    }

    private static function set_notice( string $tone, string $message ): void {
        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            [ 'tone' => sanitize_key( $tone ), 'message' => sanitize_text_field( $message ) ],
            MINUTE_IN_SECONDS
        );
    }

    /** @return array{tone:string,message:string}|null */
    private static function consume_notice(): ?array {
        $key = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient( $key );
        delete_transient( $key );
        return is_array( $notice ) && isset( $notice['tone'], $notice['message'] ) ? $notice : null;
    }

    private static function redirect(): never {
        wp_safe_redirect(
            add_query_arg(
                [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => 'brand-templates' ],
                admin_url( 'options-general.php' )
            )
        );
        exit;
    }
}
