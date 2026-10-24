<?php

declare( strict_types=1 );

namespace HWS\BaseTools\NotFoundPage;

use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminComponents\TemplateSelectionControl;

defined( 'ABSPATH' ) || exit;

/** HWS Base Tools sidebar → Site & Brand → 404 Page. */
final class NotFoundPageTab {
    private const LABELS = [
        'plinth'   => [ 'Plinth', 'A large 404 on a thin brand-colour bar, centred.' ],
        'divider'  => [ 'Divider', 'A brand-colour 404, a fine line, then the message.' ],
        'headline' => [ 'Headline', 'One line: an italic brand-colour 404 and "Page not found".' ],
    ];

    public static function render(): void {
        CoreUi::render_assets();
        $s   = NotFoundSettings::all();
        $dir = dirname( __DIR__, 2 ) . '/assets/frontend/';
        $css = '';
        $templates = [];
        foreach ( NotFoundSettings::DESIGNS as $design ) {
            $css .= (string) file_get_contents( $dir . '404-' . $design . '.css' );
            $templates[ $design ] = [ 'label' => self::LABELS[ $design ][0], 'description' => self::LABELS[ $design ][1], 'preview_html' => NotFoundPage::markup( $design ) ];
        }

        $look = '<p>Empty uses the site\'s Elementor global colours: primary for the 404, text for the words.</p>'
            . ColorControl::render( [ 'key' => 'primary_color', 'label' => 'Brand colour', 'value' => (string) $s['primary_color'], 'allow_inherit' => true, 'inherited_value' => '', 'default' => '' ] )
            . ColorControl::render( [ 'key' => 'text_color', 'label' => 'Text colour', 'value' => (string) $s['text_color'], 'allow_inherit' => true, 'inherited_value' => '', 'default' => '' ] )
            . ColorControl::render( [ 'key' => 'background', 'label' => 'Background colour', 'value' => (string) $s['background'], 'allow_inherit' => true, 'inherited_value' => '#ffffff', 'default' => '#ffffff' ] );

        $warning = NotFoundPage::rank_math_redirects_404()
            ? '<p><strong>Rank Math currently sends missing pages to another page</strong> (Rank Math → General Settings → Redirections → Fallback Behavior), so this page will not show until that is set to "Default 404".</p>'
            : '';
        ?>
        <div class="hpc-ui hws-404-tab" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( NotFoundPage::NONCE_ACTION ) ); ?>">
            <style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin stylesheets ?> .hws-404-tab{max-width:860px}.hws-404-tab .hpc-card{margin:0 0 18px}.hws-404-tab .hws-404{<?php echo esc_html( NotFoundPage::variables( $s ) ); ?>;--min-h:0;--pad-y:48px;pointer-events:none}.hws-404-status{margin-left:10px}</style>
            <?php
            echo CoreUi::card( [
                'title'     => '404 Page',
                'body_html' => '<p>The page visitors see when a link is broken: "Page not found", one sentence and a button back to the homepage, between the site\'s own header and footer, with a real 404 status. Off by default; when it is off the site keeps its own 404 page.</p>'
                    . $warning
                    . CoreUi::toggle( 'enabled', (bool) $s['enabled'], 'Use this 404 page on this site', [ 'id' => 'hws-404-enabled' ] ),
            ] );
            echo CoreUi::card( [ 'title' => 'Design', 'body_html' => TemplateSelectionControl::render( [ 'id' => 'hws-404-design', 'name' => 'design', 'value' => (string) $s['design'], 'title' => '404 design', 'description' => 'Each sample uses the same markup and CSS as the live page, in this site\'s colours and fonts.', 'templates' => $templates, 'columns' => 1, 'preview_height' => 380 ] ) ] );
            echo CoreUi::card( [ 'title' => 'Colours', 'body_html' => $look ] );
            echo CoreUi::card( [
                'title'     => 'Save',
                'body_html' => DynamicButton::render( [ 'label' => 'Save 404 page', 'working_label' => 'Saving...', 'success_label' => 'Saved', 'error_label' => 'Not saved', 'class' => 'hpc-button hws-404-save' ] ) . '<span class="hws-404-status" aria-live="polite"></span>'
                    . '<p>Check it: <a href="' . esc_url( home_url( '/hws-404-check-' . wp_rand( 1000, 9999 ) . '/' ) ) . '" target="_blank" rel="noopener">open a missing page</a></p>',
            ] );
            ?>
            <script>
            (function () {
                var root = document.querySelector('.hws-404-tab');
                if (!root) return;
                root.querySelector('.hws-404-save').addEventListener('click', function () {
                    var button = this, status = root.querySelector('.hws-404-status'), core = window.HexaWpCoreDynamicButton;
                    var data = new FormData();
                    data.append('action', 'hws_base_tools_404_page_save');
                    data.append('nonce', root.getAttribute('data-nonce'));
                    root.querySelectorAll('input[name],select[name]').forEach(function (field) {
                        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
                        data.append(field.name, field.type === 'checkbox' ? '1' : field.value);
                    });
                    if (core) core.start(button, 'Saving...');
                    fetch(root.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: data })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            var ok = json && json.success && json.data && json.data.saved;
                            status.textContent = json && json.data && json.data.message ? json.data.message : 'Not saved.';
                            if (core) { if (ok) core.success(button, 'Saved'); else core.error(button, 'Not saved'); }
                        })
                        .catch(function () { status.textContent = 'Not saved: the request failed.'; if (core) core.error(button, 'Not saved'); });
                });
            })();
            </script>
        </div>
        <?php
    }
}
