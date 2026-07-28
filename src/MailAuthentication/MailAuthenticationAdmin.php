<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MailAuthentication;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class MailAuthenticationAdmin {
    private const NONCE_ACTION = 'hws_mail_authentication_test';
    private const AJAX_ACTION = 'hws_mail_authentication_run_test';

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'handle_test' ] );
        self::$registered = true;
    }

    public static function render(): void {
        self::register();
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $service    = new Smtp2goAuthenticationService();
        $status     = $service->current_status();
        $last       = ! empty( $status['last_result_current'] ) && is_array( $status['last_result'] ?? null ) ? $status['last_result'] : [];
        $steps      = self::display_steps( $status, $last );
        $recipient  = self::default_recipient( $last );
        $status_tone = ! empty( $status['healthy'] ) ? 'success' : 'danger';

        ob_start();
        ?>
        <div class="hws-mail-auth-summary" data-mail-auth-summary data-status="<?php echo ! empty( $status['healthy'] ) ? 'passed' : 'failed'; ?>">
            <div>
                <strong data-mail-auth-summary-title><?php echo ! empty( $status['healthy'] ) ? 'Authentication passed' : 'Authentication needs attention'; ?></strong>
                <p data-mail-auth-summary-message><?php echo esc_html( (string) $status['message'] ); ?></p>
            </div>
            <span data-mail-auth-summary-pill><?php echo CoreUi::pill( ! empty( $status['healthy'] ) ? 'Passed' : 'Not verified', $status_tone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        </div>

        <ol class="hws-mail-auth-steps" data-mail-auth-steps>
            <?php foreach ( $steps as $step ) : ?>
                <?php echo self::step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </ol>

        <div class="hws-mail-auth-controls">
            <label class="hpc-field" for="hws-mail-auth-recipient">
                <span>Test recipient</span>
                <input id="hws-mail-auth-recipient" type="email" value="<?php echo esc_attr( $recipient ); ?>" autocomplete="email" required data-mail-auth-recipient>
            </label>
            <p class="hpc-small">The test stops immediately when settings or API-key validation fails. A message is sent only after both checks pass.</p>
            <div class="hpc-actions">
                <?php
                echo DynamicButton::render(
                    [
                        'id'            => 'hws-mail-auth-run',
                        'label'         => 'Run Authentication Test',
                        'working_label' => 'Running Test...',
                        'success_label' => 'Authentication Passed',
                        'error_label'   => 'Authentication Failed',
                        'class'         => 'hpc-button',
                        'attrs'         => [ 'data-mail-auth-run' => true ],
                    ]
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo CoreUi::external_link( admin_url( 'admin.php?page=wp-mail-smtp' ), 'WP Mail SMTP Settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </div>

        <?php
        echo CoreUi::detail_card(
            [
                'title'       => 'What the test verifies',
                'open'        => false,
                'persist_key' => 'hws-mail-authentication-description',
                'variant'     => 'subtle',
                'body_html'   => '<ol class="hpc-list"><li><strong>Settings:</strong> WP Mail SMTP is active, SMTP2GO is selected, the From Email is valid, and an API key exists.</li><li><strong>API key:</strong> SMTP2GO confirms the key is active. Send-only endpoint restrictions are accepted as correct least-privilege configuration.</li><li><strong>Test email:</strong> WordPress sends through WP Mail SMTP, and the SMTP2GO mailer confirms the provider accepted the message.</li></ol>',
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>

        <details class="hws-mail-auth-log">
            <summary>
                <span>Technical Activity Log</span>
                <span class="hws-mail-auth-log-toggle" aria-hidden="true"><?php echo self::chevron_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </summary>
            <div class="hws-mail-auth-log-body" data-mail-auth-log aria-live="polite">
                <div class="hws-mail-auth-log-row is-info"><time>Ready</time><span>Mail authentication tester is ready.</span></div>
            </div>
        </details>
        <?php
        $body = (string) ob_get_clean();
        ?>
        <div id="hws-mail-authentication" class="hpc-ui hws-mail-authentication" data-mail-auth-root data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
            <style><?php echo self::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
            <?php
            echo CoreUi::collapsible(
                [
                    'title'       => 'SMTP2GO Mail Authentication',
                    'open'        => true,
                    'persist_key' => 'hws-mail-authentication-panel',
                    'meta_html'   => CoreUi::pill( ! empty( $status['healthy'] ) ? 'Passing' : 'Needs test', $status_tone ),
                    'body_html'   => $body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <script><?php echo self::script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        </div>
        <?php
    }

    public static function handle_test(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to test mail authentication.' ], 403 );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $recipient = isset( $_POST['recipient'] ) ? sanitize_email( (string) wp_unslash( $_POST['recipient'] ) ) : '';
        $result    = ( new Smtp2goAuthenticationService() )->run( $recipient );

        wp_send_json_success( $result );
    }

    /**
     * @param array<string,mixed> $status
     * @param array<string,mixed> $last
     * @return array<int,array<string,mixed>>
     */
    private static function display_steps( array $status, array $last ): array {
        $last_steps = is_array( $last['steps'] ?? null ) ? $last['steps'] : [];
        $by_id      = [];
        foreach ( $last_steps as $step ) {
            if ( is_array( $step ) && '' !== (string) ( $step['id'] ?? '' ) ) {
                $by_id[ (string) $step['id'] ] = $step;
            }
        }

        $settings = is_array( $status['settings'] ?? null ) ? $status['settings'] : [];

        return [
            $by_id['settings'] ?? $settings,
            $by_id['api_key'] ?? self::pending_step( 'api_key', 'API Key', 'Run the test to validate the configured key with SMTP2GO.' ),
            $by_id['test_email'] ?? self::pending_step( 'test_email', 'Test Email', 'A test message is sent only after the first two checks pass.' ),
        ];
    }

    /** @return array<string,mixed> */
    private static function pending_step( string $id, string $label, string $message ): array {
        return [ 'id' => $id, 'label' => $label, 'status' => 'pending', 'success' => false, 'message' => $message, 'details' => [] ];
    }

    /** @param array<string,mixed> $step */
    private static function step_html( array $step ): string {
        $id      = sanitize_key( (string) ( $step['id'] ?? '' ) );
        $status  = sanitize_key( (string) ( $step['status'] ?? 'pending' ) );
        $details = is_array( $step['details'] ?? null ) ? $step['details'] : [];

        ob_start();
        ?>
        <li class="hws-mail-auth-step" data-mail-auth-step="<?php echo esc_attr( $id ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
            <span class="hws-mail-auth-step-icon" aria-hidden="true">
                <span class="is-pending"><?php echo self::circle_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="is-running"><?php echo self::spinner_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="is-passed"><?php echo self::check_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="is-failed"><?php echo self::x_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="is-skipped"><?php echo self::minus_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </span>
            <div class="hws-mail-auth-step-content">
                <div class="hws-mail-auth-step-heading">
                    <strong><?php echo esc_html( (string) ( $step['label'] ?? $id ) ); ?></strong>
                    <span data-mail-auth-step-state><?php echo esc_html( self::status_label( $status ) ); ?></span>
                </div>
                <p data-mail-auth-step-message><?php echo esc_html( (string) ( $step['message'] ?? '' ) ); ?></p>
                <dl data-mail-auth-step-details><?php echo self::details_html( $details ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dl>
            </div>
        </li>
        <?php
        return (string) ob_get_clean();
    }

    private static function details_html( array $details ): string {
        $html = '';
        foreach ( $details as $label => $value ) {
            if ( ! is_scalar( $value ) || '' === (string) $value ) {
                continue;
            }
            $html .= '<div><dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
        }
        return $html;
    }

    /** @param array<string,mixed> $last */
    private static function default_recipient( array $last ): string {
        $last_recipient = sanitize_email( (string) ( $last['recipient'] ?? '' ) );
        if ( is_email( $last_recipient ) ) {
            return $last_recipient;
        }

        $user = wp_get_current_user();
        if ( $user instanceof \WP_User && is_email( (string) $user->user_email ) ) {
            return (string) $user->user_email;
        }

        return sanitize_email( (string) get_option( 'admin_email', '' ) );
    }

    private static function status_label( string $status ): string {
        return match ( $status ) {
            'passed'  => 'Passed',
            'failed'  => 'Failed',
            'skipped' => 'Skipped',
            'running' => 'Running',
            default   => 'Pending',
        };
    }

    private static function styles(): string {
        return <<<'CSS'
.hws-mail-authentication{max-width:980px}
.hws-mail-auth-summary{align-items:flex-start;background:#f8fafc;border:1px solid var(--hpc-line);border-left:4px solid var(--hpc-red);border-radius:8px;display:flex;gap:16px;justify-content:space-between;margin-bottom:14px;padding:13px 14px}
.hws-mail-auth-summary[data-status="passed"]{border-left-color:var(--hpc-green)}
.hws-mail-auth-summary strong{display:block;font-size:14px;margin-bottom:3px}
.hws-mail-auth-summary p{color:var(--hpc-muted);font-size:13px;line-height:1.5;margin:0}
.hws-mail-auth-steps{display:grid;gap:9px;list-style:none;margin:0 0 16px;padding:0}
.hws-mail-auth-step{align-items:flex-start;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:12px;grid-template-columns:30px minmax(0,1fr);padding:12px 14px}
.hws-mail-auth-step[data-status="passed"]{border-left:4px solid var(--hpc-green)}
.hws-mail-auth-step[data-status="failed"]{border-left:4px solid var(--hpc-red)}
.hws-mail-auth-step[data-status="running"]{border-left:4px solid var(--hpc-blue)}
.hws-mail-auth-step[data-status="skipped"]{border-left:4px solid #94a3b8}
.hws-mail-auth-step-icon{align-items:center;border:1px solid #cbd5e1;border-radius:50%;display:flex;height:28px;justify-content:center;width:28px}
.hws-mail-auth-step-icon>span{display:none}
.hws-mail-auth-step[data-status="pending"] .hws-mail-auth-step-icon .is-pending,.hws-mail-auth-step[data-status="running"] .hws-mail-auth-step-icon .is-running,.hws-mail-auth-step[data-status="passed"] .hws-mail-auth-step-icon .is-passed,.hws-mail-auth-step[data-status="failed"] .hws-mail-auth-step-icon .is-failed,.hws-mail-auth-step[data-status="skipped"] .hws-mail-auth-step-icon .is-skipped{display:block}
.hws-mail-auth-step[data-status="passed"] .hws-mail-auth-step-icon{background:#eaf8ef;border-color:#b8e2c7;color:var(--hpc-green)}
.hws-mail-auth-step[data-status="failed"] .hws-mail-auth-step-icon{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
.hws-mail-auth-step[data-status="running"] .hws-mail-auth-step-icon{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
.hws-mail-auth-step-icon svg{display:block;fill:currentColor;height:13px;width:13px}
.hws-mail-auth-step-icon .is-running svg{animation:hws-mail-auth-spin .75s linear infinite}
.hws-mail-auth-step-heading{align-items:center;display:flex;gap:12px;justify-content:space-between}
.hws-mail-auth-step-heading strong{font-size:14px}
.hws-mail-auth-step-heading span{color:var(--hpc-muted);font-size:11px;font-weight:800;text-transform:uppercase}
.hws-mail-auth-step-content>p{color:#3f4d63;font-size:13px;line-height:1.45;margin:5px 0 0}
.hws-mail-auth-step dl{display:grid;gap:5px;margin:9px 0 0}
.hws-mail-auth-step dl:empty{display:none}
.hws-mail-auth-step dl div{display:grid;font-size:12px;gap:8px;grid-template-columns:minmax(100px,150px) minmax(0,1fr)}
.hws-mail-auth-step dt{color:var(--hpc-muted);font-weight:700}
.hws-mail-auth-step dd{margin:0;overflow-wrap:anywhere}
.hws-mail-auth-controls{background:#fbfcfe;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 12px;padding:14px}
.hws-mail-auth-controls .hpc-field{max-width:620px}
.hws-mail-auth-log{border:1px solid var(--hpc-line);border-radius:8px;margin-top:12px;overflow:hidden}
.hws-mail-auth-log summary{align-items:center;cursor:pointer;display:flex;font-size:13px;font-weight:800;justify-content:space-between;list-style:none;padding:11px 13px}
.hws-mail-auth-log summary::-webkit-details-marker{display:none}
.hws-mail-auth-log-toggle svg{display:block;fill:currentColor;height:11px;transform:rotate(0deg);transition:transform .18s;width:11px}
.hws-mail-auth-log[open] .hws-mail-auth-log-toggle svg{transform:rotate(180deg)}
.hws-mail-auth-log-body{background:#0f1720;border-top:1px solid #243142;color:#dbe7f3;display:grid;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;gap:0;max-height:300px;overflow:auto}
.hws-mail-auth-log-row{display:grid;gap:12px;grid-template-columns:150px minmax(0,1fr);padding:8px 11px}
.hws-mail-auth-log-row+.hws-mail-auth-log-row{border-top:1px solid #243142}
.hws-mail-auth-log-row time{color:#93a4b9}
.hws-mail-auth-log-row.is-success span{color:#82df9e}.hws-mail-auth-log-row.is-error span{color:#ff9cac}.hws-mail-auth-log-row.is-warning span{color:#f6d36f}
@keyframes hws-mail-auth-spin{to{transform:rotate(360deg)}}
@media(max-width:640px){.hws-mail-auth-summary{display:grid}.hws-mail-auth-step dl div{grid-template-columns:1fr}.hws-mail-auth-log-row{grid-template-columns:1fr;gap:3px}}
CSS;
    }

    private static function script(): string {
        return <<<'JS'
(function(){
    var root=document.currentScript&&document.currentScript.closest('[data-mail-auth-root]');
    if(!root||root.dataset.mailAuthBound==='1')return;
    root.dataset.mailAuthBound='1';
    var button=root.querySelector('[data-mail-auth-run]');
    var recipient=root.querySelector('[data-mail-auth-recipient]');
    var log=root.querySelector('[data-mail-auth-log]');
    function label(status){return status==='passed'?'Passed':status==='failed'?'Failed':status==='skipped'?'Skipped':status==='running'?'Running':'Pending'}
    function appendLog(level,message,time){
        if(!log)return;
        var row=document.createElement('div');row.className='hws-mail-auth-log-row is-'+level;
        var stamp=document.createElement('time');stamp.textContent=time||new Date().toLocaleTimeString();
        var text=document.createElement('span');text.textContent=message||'';
        row.appendChild(stamp);row.appendChild(text);log.appendChild(row);log.scrollTop=log.scrollHeight;
    }
    function setStep(step){
        var row=root.querySelector('[data-mail-auth-step="'+step.id+'"]');if(!row)return;
        var status=step.status||'pending';row.dataset.status=status;
        var state=row.querySelector('[data-mail-auth-step-state]');if(state)state.textContent=label(status);
        var message=row.querySelector('[data-mail-auth-step-message]');if(message)message.textContent=step.message||'';
        var details=row.querySelector('[data-mail-auth-step-details]');
        if(details){details.textContent='';Object.keys(step.details||{}).forEach(function(key){var value=step.details[key];if(value===null||value===undefined||value==='')return;var wrap=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=key;dd.textContent=String(value);wrap.appendChild(dt);wrap.appendChild(dd);details.appendChild(wrap);});}
        appendLog(status==='passed'?'success':status==='failed'?'error':status==='skipped'?'warning':'info',(step.label||step.id)+': '+(step.message||label(status)),step.timestamp||'');
    }
    function setSummary(result){
        var summary=root.querySelector('[data-mail-auth-summary]');if(!summary)return;
        summary.dataset.status=result.success?'passed':'failed';
        var title=summary.querySelector('[data-mail-auth-summary-title]');if(title)title.textContent=result.success?'Authentication passed':'Authentication failed';
        var message=summary.querySelector('[data-mail-auth-summary-message]');if(message)message.textContent=result.message||'';
        var pill=summary.querySelector('[data-mail-auth-summary-pill]');if(pill)pill.innerHTML='<span class="hpc-pill '+(result.success?'success':'danger')+'">'+(result.success?'Passed':'Failed')+'</span>';
    }
    if(button)button.addEventListener('click',function(){
        if(!recipient||!recipient.reportValidity())return;
        if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button,'Running Test...');
        if(log)log.textContent='';appendLog('info','Starting the three-stage SMTP2GO authentication test.');
        ['settings','api_key','test_email'].forEach(function(id,index){setStep({id:id,label:index===0?'Settings':index===1?'API Key':'Test Email',status:index===0?'running':'pending',message:index===0?'Inspecting WP Mail SMTP configuration...':'Waiting for the previous step.',details:{}});});
        var data=new URLSearchParams();data.set('action',root.dataset.action||'');data.set('nonce',root.dataset.nonce||'');data.set('recipient',recipient.value||'');
        fetch(root.dataset.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()})
            .then(function(response){return response.json();})
            .then(function(payload){if(!payload||!payload.success||!payload.data)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Authentication request failed.');var result=payload.data;(result.steps||[]).forEach(setStep);setSummary(result);if(window.HexaWpCoreDynamicButton){result.success?window.HexaWpCoreDynamicButton.success(button,'Authentication Passed',false):window.HexaWpCoreDynamicButton.error(button,'Authentication Failed',false);}})
            .catch(function(error){appendLog('error',error&&error.message?error.message:'Authentication request failed.');if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Authentication Failed',false);});
    });
})();
JS;
    }

    private static function check_svg(): string {
        return '<svg viewBox="0 0 448 512" focusable="false"><path d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.2 0z"/></svg>';
    }

    private static function x_svg(): string {
        return '<svg viewBox="0 0 384 512" focusable="false"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256z"/></svg>';
    }

    private static function circle_svg(): string {
        return '<svg viewBox="0 0 512 512" focusable="false"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"/></svg>';
    }

    private static function spinner_svg(): string {
        return '<svg viewBox="0 0 512 512" focusable="false"><path d="M304 48a48 48 0 1 0-96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0-96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0-96 0 48 48 0 1 0 96 0zM142.9 108.9a48 48 0 1 0-67.9-67.9 48 48 0 1 0 67.9 67.9zm294.2 362.2a48 48 0 1 0-67.9-67.9 48 48 0 1 0 67.9 67.9zM108.9 369.1a48 48 0 1 0-67.9 67.9 48 48 0 1 0 67.9-67.9zm362.2-226.2a48 48 0 1 0-67.9-67.9 48 48 0 1 0 67.9 67.9z"/></svg>';
    }

    private static function minus_svg(): string {
        return '<svg viewBox="0 0 448 512" focusable="false"><path d="M432 256c0 13.3-10.7 24-24 24H40c-13.3 0-24-10.7-24-24s10.7-24 24-24h368c13.3 0 24 10.7 24 24z"/></svg>';
    }

    private static function chevron_svg(): string {
        return '<svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>';
    }
}
