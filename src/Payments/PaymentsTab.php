<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Payments;

use Hexa\PluginCore\ApiKeys\ApiKeyFields;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

defined( 'ABSPATH' ) || exit;

/** HWS Base Tools sidebar → Operations → Payments. */
final class PaymentsTab {
    public static function render(): void {
        CoreUi::render_assets();
        PaymentsModule::register_fields();
        $on = Stripe::enabled();
        ?>
        <div class="hpc-ui hws-payments-tab" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( PaymentsModule::NONCE_ACTION ) ); ?>">
            <style>.hws-payments-tab{max-width:860px}.hws-payments-tab .hpc-card{margin:0 0 18px}.hws-payments-tab .hpc-api-key{margin:0 0 22px}</style>
            <?php
            echo CoreUi::card( [
                'title'     => 'Stripe payments',
                'body_html' => '<p>One Stripe connection for every HWS plugin that takes payments (Verified Profiles, Contributor Network). Off by default; while off, no plugin can charge and the webhook address does not exist.</p>'
                    . CoreUi::toggle( 'stripe_enabled', $on, 'Use Stripe payments on this site', [ 'id' => 'hws-stripe-enabled' ] )
                    . '<p class="hws-payments-status" aria-live="polite"></p>',
            ] );
            if ( $on ) {
                echo CoreUi::card( [
                    'title'     => 'Stripe keys',
                    'body_html' => '<p>Mode: <strong>' . esc_html( '' === Stripe::secret_key() ? 'no key yet' : Stripe::mode() ) . '</strong>. Keys are stored encrypted. Each key shows how to get it.</p>'
                        . ApiKeyFields::render( Stripe::SECRET )
                        . ApiKeyFields::render( Stripe::PUBLISHABLE )
                        . ApiKeyFields::render( Stripe::WEBHOOK ),
                ] );
                echo CoreUi::card( [
                    'title'     => 'Webhook address',
                    'body_html' => '<p>Stripe sends payment events here; each one is checked against the signing secret before any plugin acts on it.</p><p><code>' . esc_html( Stripe::webhook_url() ) . '</code> ' . CoreUi::copy_button( Stripe::webhook_url() ) . '</p>',
                ] );
            }
            ?>
            <script>
            (function () {
                var root = document.querySelector('.hws-payments-tab');
                var toggle = root && root.querySelector('#hws-stripe-enabled');
                if (!toggle) return;
                toggle.addEventListener('change', function () {
                    var data = new FormData(), status = root.querySelector('.hws-payments-status');
                    data.append('action', 'hws_base_tools_payments_save');
                    data.append('nonce', root.getAttribute('data-nonce'));
                    if (toggle.checked) data.append('stripe_enabled', '1');
                    status.textContent = 'Saving...';
                    fetch(root.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: data })
                        .then(function (r) { return r.json(); })
                        .then(function (json) { status.textContent = json && json.data ? json.data.message : 'Not saved.'; if (json && json.success) window.location.reload(); })
                        .catch(function () { status.textContent = 'Not saved: the request failed.'; });
                });
            })();
            </script>
        </div>
        <?php
    }
}
