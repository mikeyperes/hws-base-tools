<?php

declare( strict_types=1 );

namespace HWS\BaseTools\MaintenanceMode;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class MaintenanceModeAdmin {
    private const NONCE_ACTION = 'hws_maintenance_mode_manage';

    public static function render(): void {
        CoreUi::render_assets();
        $enabled = MaintenanceSettings::enabled();
        $selected = MaintenanceSettings::selected_template();
        $templates = MaintenanceSettings::templates();
        $transition = MaintenanceSettings::last_transition();
        ?>
        <div class="hpc-ui hws-mm" data-enabled="<?php echo $enabled ? '1' : '0'; ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
            <style>
                .hws-mm{--mm-accent:#4055df;color:#182238}.hws-mm *{box-sizing:border-box}.hws-mm-hero{align-items:center;background:linear-gradient(135deg,#111827,#263653 64%,#4055df 150%);border-radius:18px;color:#fff;display:flex;gap:24px;justify-content:space-between;margin-bottom:18px;padding:28px 30px}.hws-mm-kicker{color:#cbd5e1;font-size:11px;font-weight:800;letter-spacing:.14em;margin:0 0 8px;text-transform:uppercase}.hws-mm-hero h2{color:#fff;font-size:28px;line-height:1.15;margin:0 0 8px}.hws-mm-hero p{color:#e2e8f0;margin:0;max-width:720px}.hws-mm-state{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:999px;font-weight:800;padding:9px 14px;white-space:nowrap}.hws-mm-state.is-on{background:#15803d;border-color:#4ade80}.hws-mm-panel{background:#fff;border:1px solid #d9dfe8;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.05);margin-bottom:18px;padding:22px}.hws-mm-panel-head{align-items:flex-start;border-bottom:1px solid #edf0f4;display:flex;gap:16px;justify-content:space-between;margin-bottom:18px;padding-bottom:15px}.hws-mm-panel h3{font-size:19px;margin:0 0 5px}.hws-mm-panel-head p{color:#64748b;margin:0;max-width:760px}.hws-mm-toggle-wrap{align-items:center;display:flex;gap:12px}.hws-mm-toggle{display:inline-flex;position:relative}.hws-mm-toggle input{height:1px;opacity:0;position:absolute;width:1px}.hws-mm-toggle-track{background:#cbd5e1;border-radius:999px;cursor:pointer;height:32px;position:relative;transition:.2s;width:58px}.hws-mm-toggle-track:after{background:#fff;border-radius:50%;box-shadow:0 2px 8px rgba(15,23,42,.25);content:"";height:24px;left:4px;position:absolute;top:4px;transition:.2s;width:24px}.hws-mm-toggle input:checked+.hws-mm-toggle-track{background:#16a34a}.hws-mm-toggle input:checked+.hws-mm-toggle-track:after{transform:translateX(26px)}.hws-mm-toggle input:focus-visible+.hws-mm-toggle-track{outline:3px solid rgba(64,85,223,.3);outline-offset:2px}.hws-mm-toggle input:disabled+.hws-mm-toggle-track{cursor:wait;opacity:.6}.hws-mm-toggle-label{font-weight:800;min-width:63px}.hws-mm-checklist{display:grid;gap:9px;grid-template-columns:repeat(5,minmax(0,1fr));margin:16px 0 0;padding:0}.hws-mm-step{align-items:flex-start;background:#f8fafc;border:1px solid #e3e8ef;border-radius:12px;display:flex;gap:9px;min-height:76px;padding:12px}.hws-mm-step-icon{align-items:center;background:#e2e8f0;border-radius:50%;color:#64748b;display:flex;flex:0 0 22px;font-size:11px;font-weight:900;height:22px;justify-content:center}.hws-mm-step strong{display:block;font-size:12px;line-height:1.25}.hws-mm-step small{color:#64748b;display:block;font-size:10px;line-height:1.35;margin-top:4px}.hws-mm-step.is-running{border-color:#f59e0b}.hws-mm-step.is-running .hws-mm-step-icon{animation:mm-pulse 1s infinite;background:#fef3c7;color:#b45309}.hws-mm-step.is-done{background:#f0fdf4;border-color:#86efac}.hws-mm-step.is-done .hws-mm-step-icon{background:#16a34a;color:#fff}.hws-mm-step.is-error{background:#fef2f2;border-color:#fca5a5}.hws-mm-step.is-error .hws-mm-step-icon{background:#dc2626;color:#fff}.hws-mm-summary{color:#64748b;font-size:12px;margin:12px 0 0}.hws-mm-summary.is-error{color:#b91c1c;font-weight:700}.hws-mm-grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr))}.hws-mm-card{border:1px solid #d9dfe8;border-radius:16px;display:flex;flex-direction:column;min-width:0;overflow:hidden;position:relative}.hws-mm-card:has(input:checked){border-color:var(--mm-accent);box-shadow:0 0 0 2px rgba(64,85,223,.16)}.hws-mm-card-choice{align-items:flex-start;cursor:pointer;display:flex;gap:11px;padding:15px}.hws-mm-card-choice input{margin-top:2px}.hws-mm-card-choice strong{display:block}.hws-mm-card-choice small{color:#64748b;display:block;line-height:1.4;margin-top:3px}.hws-mm-preview{background:#eef1f6;border-bottom:1px solid #d9dfe8;height:360px;overflow:hidden;position:relative}.hws-mm-preview iframe{border:0;height:720px;pointer-events:none;transform:scale(.5);transform-origin:0 0;width:200%}.hws-mm-code{border-top:1px solid #e7ebf1}.hws-mm-code summary{cursor:pointer;font-size:12px;font-weight:800;padding:12px 15px}.hws-mm-code-head{align-items:center;background:#111827;color:#dbe4f0;display:flex;justify-content:space-between;padding:9px 12px}.hws-mm-code-head span{font-size:11px}.hws-mm-code button{font-size:11px;min-height:26px}.hws-mm-code pre{background:#0b1120;color:#cbd5e1;font:11px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;margin:0;max-height:390px;overflow:auto;padding:15px;white-space:pre}.hws-mm-template-status{color:#64748b;font-size:12px;margin:14px 0 0;min-height:18px}.hws-mm-notes{background:#f8fafc;border-left:4px solid var(--mm-accent);border-radius:0 12px 12px 0;margin-top:18px;padding:14px 16px}.hws-mm-notes p{margin:5px 0 0}@keyframes mm-pulse{50%{opacity:.45}}@media(max-width:1100px){.hws-mm-checklist{grid-template-columns:1fr 1fr}.hws-mm-step:last-child{grid-column:1/-1}}@media(max-width:782px){.hws-mm-hero{align-items:flex-start;flex-direction:column;padding:22px}.hws-mm-state{white-space:normal}.hws-mm-panel{padding:17px}.hws-mm-panel-head{display:block}.hws-mm-toggle-wrap{margin-top:15px}.hws-mm-checklist{grid-template-columns:1fr}.hws-mm-step:last-child{grid-column:auto}.hws-mm-grid{grid-template-columns:1fr}.hws-mm-preview{height:300px}.hws-mm-preview iframe{height:600px}}
            </style>

            <section class="hws-mm-hero">
                <div><p class="hws-mm-kicker">Operations</p><h2>Full Site Maintenance Mode</h2><p>Temporarily replace every public page with a complete maintenance document while administrators retain normal access. Public HTML responses use HTTP 503, search engines are told not to index the temporary page, and REST/XML-RPC access is protected.</p></div>
                <div class="hws-mm-state<?php echo $enabled ? ' is-on' : ''; ?>" data-mm-state><?php echo $enabled ? 'Maintenance active' : 'Site live'; ?></div>
            </section>

            <section class="hws-mm-panel">
                <header class="hws-mm-panel-head"><div><h3>Maintenance mode</h3><p>The switch runs each real server operation in sequence. Keep this page open until all five checks are green.</p></div><div class="hws-mm-toggle-wrap"><label class="hws-mm-toggle"><input type="checkbox" role="switch" data-mm-toggle <?php checked( $enabled ); ?>><span class="hws-mm-toggle-track" aria-hidden="true"></span></label><span class="hws-mm-toggle-label" data-mm-toggle-label><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span></div></header>
                <ol class="hws-mm-checklist" aria-live="polite">
                    <?php foreach ( [ 'prepare' => 'Validate', 'state' => 'Save state', 'permalinks' => 'Permalinks', 'cache' => 'Clear caches', 'verify' => 'Verify' ] as $operation => $label ) : ?>
                        <li class="hws-mm-step" data-mm-step="<?php echo esc_attr( $operation ); ?>"><span class="hws-mm-step-icon">•</span><span><strong><?php echo esc_html( $label ); ?></strong><small>Waiting</small></span></li>
                    <?php endforeach; ?>
                </ol>
                <p class="hws-mm-summary" data-mm-summary><?php echo ! empty( $transition['complete'] ) && ! empty( $transition['completed_at'] ) ? 'Last completed process: ' . esc_html( (string) $transition['completed_at'] ) : 'No completed enable/disable process is recorded yet.'; ?></p>
            </section>

            <section class="hws-mm-panel">
                <header class="hws-mm-panel-head"><div><h3>Choose the exact maintenance page</h3><p>Each preview below is rendered from the same complete HTML and CSS document sent to public visitors. Expand “View exact front-end code” to inspect or copy the actual response source.</p></div></header>
                <div class="hws-mm-grid">
                    <?php foreach ( $templates as $template => $definition ) : $document = MaintenanceTemplateRenderer::document( $template ); ?>
                        <article class="hws-mm-card">
                            <div class="hws-mm-preview"><iframe title="<?php echo esc_attr( $definition['label'] . ' maintenance page preview' ); ?>" sandbox srcdoc="<?php echo esc_attr( $document ); ?>"></iframe></div>
                            <label class="hws-mm-card-choice"><input type="radio" name="hws_maintenance_template" value="<?php echo esc_attr( $template ); ?>" <?php checked( $selected, $template ); ?>><span><strong><?php echo esc_html( $definition['label'] ); ?></strong><small><?php echo esc_html( $definition['description'] ); ?></small></span></label>
                            <details class="hws-mm-code"><summary>View exact front-end code</summary><div class="hws-mm-code-head"><span>Complete HTTP response document</span><button type="button" class="button" data-mm-copy="hws-mm-code-<?php echo esc_attr( $template ); ?>">Copy code</button></div><pre><code id="hws-mm-code-<?php echo esc_attr( $template ); ?>"><?php echo esc_html( $document ); ?></code></pre></details>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="hws-mm-template-status" data-mm-template-status>Selected: <?php echo esc_html( $templates[ $selected ]['label'] ); ?><?php echo $enabled ? ' — this is the live public maintenance page.' : ' — ready for the next maintenance window.'; ?></p>
                <div class="hws-mm-notes"><strong>Access and recovery</strong><p>Logged-in administrators, WordPress Admin, AJAX, cron, and WP-CLI remain available so the site can be maintained and the mode can always be disabled. Public pages, feeds, REST, and XML-RPC are placed into maintenance state.</p></div>
            </section>

            <script>
            (function(){
                var script=document.currentScript,root=script&&script.closest('.hws-mm');if(!root||root.dataset.bound==='1'){return;}root.dataset.bound='1';
                var toggle=root.querySelector('[data-mm-toggle]'),toggleLabel=root.querySelector('[data-mm-toggle-label]'),state=root.querySelector('[data-mm-state]'),summary=root.querySelector('[data-mm-summary]'),templateStatus=root.querySelector('[data-mm-template-status]');
                var operations=[['prepare','Validate'],['state','Save state'],['permalinks','Permalinks'],['cache','Clear caches'],['verify','Verify']];
                function selected(){var input=root.querySelector('input[name="hws_maintenance_template"]:checked');return input?input.value:'focused';}
                function request(action,data){var body=new URLSearchParams(Object.assign({action:action,nonce:root.dataset.nonce},data||{}));return fetch(root.dataset.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json().catch(function(){throw new Error('The server returned an unreadable response.');});}).then(function(payload){if(!payload||!payload.success){throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'The server operation failed.');}return payload.data;});}
                function step(id,status,message){var item=root.querySelector('[data-mm-step="'+id+'"]');if(!item){return;}item.classList.remove('is-running','is-done','is-error');if(status){item.classList.add('is-'+status);}var icon=item.querySelector('.hws-mm-step-icon'),small=item.querySelector('small');icon.textContent=status==='done'?'✓':status==='error'?'!':status==='running'?'…':'•';small.textContent=message||'Waiting';}
                function paint(enabled){root.dataset.enabled=enabled?'1':'0';toggle.checked=enabled;toggleLabel.textContent=enabled?'Enabled':'Disabled';state.textContent=enabled?'Maintenance active':'Site live';state.classList.toggle('is-on',enabled);}
                async function process(desired){toggle.disabled=true;summary.classList.remove('is-error');summary.textContent=(desired?'Enabling':'Disabling')+' maintenance mode…';operations.forEach(function(item){step(item[0],'','Waiting');});
                    try{for(var i=0;i<operations.length;i++){var operation=operations[i][0];step(operation,'running','Running on the server…');var data=await request('hws_maintenance_process',{operation:operation,enabled:desired?'1':'0',template:selected()});step(operation,'done',data.message);paint(!!data.enabled);}summary.textContent='Completed: maintenance mode is '+(desired?'enabled.':'disabled.');templateStatus.textContent='Selected template saved'+(desired?' and serving to public visitors.':'.');}
                    catch(error){var running=root.querySelector('.hws-mm-step.is-running');if(running){step(running.dataset.mmStep,'error',error.message);}summary.textContent='Process stopped: '+error.message;summary.classList.add('is-error');paint(root.dataset.enabled==='1');}
                    finally{toggle.disabled=false;}
                }
                toggle.addEventListener('change',function(){process(toggle.checked);});
                root.addEventListener('change',function(event){if(!event.target.matches('input[name="hws_maintenance_template"]')){return;}templateStatus.textContent='Saving selected template…';request('hws_maintenance_select_template',{template:selected()}).then(function(data){templateStatus.textContent=data.message;}).catch(function(error){templateStatus.textContent='Template was not saved: '+error.message;templateStatus.style.color='#b91c1c';});});
                root.addEventListener('click',function(event){var button=event.target.closest('[data-mm-copy]');if(!button){return;}var code=root.querySelector('#'+button.dataset.mmCopy);if(!code){return;}var value=code.textContent||'',done=function(){var old=button.textContent;button.textContent='Copied';window.setTimeout(function(){button.textContent=old;},1500);},fallback=function(){var area=document.createElement('textarea');area.value=value;area.setAttribute('readonly','');area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{document.execCommand('copy');done();}catch(e){button.textContent='Copy failed';}area.remove();};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(done).catch(fallback);}else{fallback();}});
            }());
            </script>
        </div>
        <?php
    }

    public static function handle_process(): void {
        self::authorize();
        try {
            $operation = isset( $_POST['operation'] ) ? sanitize_key( (string) wp_unslash( $_POST['operation'] ) ) : '';
            $enabled = isset( $_POST['enabled'] ) && '1' === (string) wp_unslash( $_POST['enabled'] );
            $template = isset( $_POST['template'] ) ? wp_unslash( $_POST['template'] ) : '';
            wp_send_json_success( MaintenanceModeOperations::run( $operation, $enabled, $template ) );
        } catch ( \Throwable $exception ) {
            wp_send_json_error( [ 'message' => $exception->getMessage(), 'enabled' => MaintenanceSettings::enabled() ], 500 );
        }
    }

    public static function handle_template_selection(): void {
        self::authorize();
        try {
            $template = isset( $_POST['template'] ) ? wp_unslash( $_POST['template'] ) : '';
            wp_send_json_success( MaintenanceModeOperations::select_template( $template ) );
        } catch ( \Throwable $exception ) {
            wp_send_json_error( [ 'message' => $exception->getMessage() ], 500 );
        }
    }

    private static function authorize(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to manage maintenance mode.' ], 403 );
        }
        $nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_send_json_error( [ 'message' => 'The maintenance request expired. Refresh the page and try again.' ], 403 );
        }
    }
}
