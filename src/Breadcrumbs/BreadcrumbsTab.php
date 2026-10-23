<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Breadcrumbs;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeBreakdown;
use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminComponents\TemplateSelectionControl;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/** HWS Base Tools sidebar → Site & Brand → Breadcrumbs. */
final class BreadcrumbsTab {
    private const LABELS = [
        'bc-b1' => [ 'Title band', 'The page title above the trail on a light accent band.' ],
        'bc-b2' => [ 'Clean bar', 'A slim trail with an accent on the current page.' ],
        'bc-b3' => [ 'Underlined', 'A trail with an accent underline.' ],
        'bc-b4' => [ 'Soft panel', 'The trail on a soft grey panel.' ],
        'bc-b5' => [ 'Trail then title', 'The trail with the page title below it.' ],
        'bc-b6' => [ 'Flush minimal', 'A plain trail with no background.' ],
    ];

    public static function render(): void {
        CoreUi::render_assets();
        $s          = BreadcrumbSettings::all();
        $css        = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/frontend/breadcrumbs.css' );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        unset( $post_types['attachment'] );

        $templates = [];
        foreach ( BreadcrumbSettings::STYLES as $style ) {
            $templates[ $style ] = [
                'label'        => self::LABELS[ $style ][0],
                'description'  => self::LABELS[ $style ][1],
                'preview_html' => BreadcrumbTrail::sample( $style ),
            ];
        }

        $where = CoreUi::toggle( 'hide_home', (bool) $s['hide_home'], 'Hide on the home page', [ 'id' => 'hws-bc-hide-home' ] )
            . CoreUi::toggle( 'hide_term_archives', (bool) $s['hide_term_archives'], 'Hide on category and tag pages', [ 'id' => 'hws-bc-hide-terms' ] );
        foreach ( $post_types as $type => $object ) {
            $where .= CoreUi::toggle( 'disabled_post_types[]', in_array( $type, (array) $s['disabled_post_types'], true ), 'Hide on every ' . $object->labels->singular_name, [ 'id' => 'hws-bc-hide-' . $type, 'value' => $type ] );
        }
        $where .= '<p><label for="hws-bc-disabled-ids"><strong>Hide on these pages or posts (IDs, comma separated)</strong></label><br><input type="text" id="hws-bc-disabled-ids" name="disabled_ids" class="regular-text" value="' . esc_attr( implode( ', ', (array) $s['disabled_ids'] ) ) . '"></p>';

        $look = ColorControl::render( [ 'key' => 'accent_color', 'label' => 'Accent colour', 'value' => (string) $s['accent_color'], 'allow_inherit' => true, 'inherited_value' => '#d63428', 'default' => '#d63428' ] )
            . ColorControl::render( [ 'key' => 'background_color', 'label' => 'Background colour', 'value' => (string) $s['background_color'], 'allow_inherit' => true, 'inherited_value' => '#ffffff', 'default' => '#ffffff' ] )
            . ColorControl::render( [ 'key' => 'text_color', 'label' => 'Text colour', 'value' => (string) $s['text_color'], 'allow_inherit' => true, 'inherited_value' => '#111827', 'default' => '#111827' ] )
            . '<p><label for="hws-bc-font-size"><strong>Text size (px)</strong></label><br><input type="number" min="9" max="24" id="hws-bc-font-size" name="font_size" value="' . esc_attr( (string) $s['font_size'] ) . '"></p>';

        $custom = '<p>Every selector must include <code>' . esc_html( BreadcrumbTrail::CSS_SCOPE_MARKER ) . '</code>.</p><textarea name="css_override" rows="6" class="large-text code" spellcheck="false">' . esc_textarea( (string) $s['css_override'] ) . '</textarea>';
        ?>
        <div class="hpc-ui hws-breadcrumbs-tab" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( Breadcrumbs::NONCE_ACTION ) ); ?>">
            <style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin stylesheet ?> .hws-breadcrumbs-tab{max-width:860px}.hws-breadcrumbs-tab .hpc-card{margin:0 0 18px}.hws-breadcrumbs-tab .smpi-breadcrumbs-band{pointer-events:none}.hws-bc-status{margin-left:10px}</style>
            <?php
            echo CoreUi::card( [
                'title'     => 'Breadcrumbs',
                'body_html' => '<p>A "Home &rsaquo; Section &rsaquo; Page" trail placed directly under the site header. It uses Rank Math\'s trail when Rank Math is active' . ( BreadcrumbTrail::rank_math_available() ? ' (active here)' : '' ) . '. Off by default; when it is off nothing reaches the site and the shortcodes print nothing.</p>'
                    . ( ! empty( $s['migrated_from_smp'] ) ? '<p><em>These settings were carried over from SMP Publication Integration, where breadcrumbs used to live.</em></p>' : '' )
                    . CoreUi::toggle( 'enabled', (bool) $s['enabled'], 'Show breadcrumbs on this site', [ 'id' => 'hws-bc-enabled' ] ),
            ] );
            echo CoreUi::card( [ 'title' => 'Design', 'body_html' => TemplateSelectionControl::render( [ 'id' => 'hws-bc-style', 'name' => 'style', 'value' => (string) $s['style'], 'title' => 'Breadcrumb design', 'description' => 'Each sample uses the same markup and CSS as the live site.', 'templates' => $templates, 'columns' => 1, 'preview_height' => 120 ] ) ] );
            echo CoreUi::card( [ 'title' => 'Colours and size', 'body_html' => $look ] );
            echo CoreUi::card( [ 'title' => 'Where it shows', 'body_html' => $where ] );
            echo CoreUi::card( [ 'title' => 'Custom CSS', 'body_html' => $custom ] );
            echo CoreUi::card( [
                'title'     => 'Save',
                'body_html' => DynamicButton::render( [ 'label' => 'Save breadcrumbs', 'working_label' => 'Saving...', 'success_label' => 'Saved', 'error_label' => 'Not saved', 'class' => 'hpc-button hws-bc-save' ] ) . '<span class="hws-bc-status" aria-live="polite"></span>',
            ] );
            echo ShortcodeBreakdown::render( [
                'tag'         => Breadcrumbs::SHORTCODE,
                'title'       => 'Breadcrumbs shortcode',
                'description' => 'Places the breadcrumb band where you put it (an Elementor Shortcode widget, for example). A page with its own placement gets no automatic band. Prints nothing while breadcrumbs are off.',
                'aliases'     => [ Breadcrumbs::LEGACY_SHORTCODE ],
                'attributes'  => [ 'style' => [ 'default' => (string) $s['style'], 'description' => 'Design for this placement.', 'options' => BreadcrumbSettings::STYLES ] ],
            ] );
            ?>
            <script>
            (function () {
                var root = document.querySelector('.hws-breadcrumbs-tab');
                if (!root) return;
                root.querySelector('.hws-bc-save').addEventListener('click', function () {
                    var button = this, status = root.querySelector('.hws-bc-status'), core = window.HexaWpCoreDynamicButton;
                    var data = new FormData();
                    data.append('action', 'hws_base_tools_breadcrumbs_save');
                    data.append('nonce', root.getAttribute('data-nonce'));
                    root.querySelectorAll('input[name],textarea[name],select[name]').forEach(function (field) {
                        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
                        data.append(field.name, field.type === 'checkbox' && field.name.indexOf('[]') < 0 ? '1' : field.value);
                    });
                    if (core) core.start(button, 'Saving...');
                    fetch(root.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: data })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            var ok = json && json.success && json.data && json.data.saved;
                            var message = json && json.data && json.data.message ? json.data.message : 'Not saved.';
                            status.textContent = message;
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
