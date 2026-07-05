<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class GettingStartedChecklistRenderer {
    private GettingStartedChecklistConfig $config;

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id = $this->config->root_id();
        $steps   = $this->config->steps();
        $nonce   = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-gsc" data-hpc-getting-started-checklist data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-run-action="<?php echo esc_attr( $this->config->run_action() ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php echo $this->assets( $root_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php
            ob_start();
            ?>
                <?php if ( '' !== $this->config->description() ) : ?>
                    <p class="hpc-gsc-description"><?php echo esc_html( $this->config->description() ); ?></p>
                <?php endif; ?>

                <div class="hpc-gsc-actions">
                    <?php echo DynamicButton::render( [ 'label' => 'Run Checklist', 'working_label' => 'Running...', 'success_label' => 'Checklist Finished', 'error_label' => 'Checklist Failed', 'class' => 'hpc-button', 'attrs' => [ 'data-gsc-run-all' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <button type="button" class="hpc-button secondary" data-gsc-reset>Reset UI</button>
                </div>

                <?php if ( [] === $steps ) : ?>
                    <div class="hpc-callout"><?php echo esc_html( $this->config->empty_message() ); ?></div>
                <?php else : ?>
                    <div class="hpc-gsc-list" data-gsc-list>
                        <?php foreach ( $steps as $step ) : ?>
                            <?php echo $this->step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <details class="hpc-gsc-log">
                    <summary class="hpc-gsc-log-head">
                        <div>
                            <h3>Technical Activity Log</h3>
                            <span>Reports each AJAX request, callback result, subtask transition, and failure message.</span>
                        </div>
                        <span class="hpc-gsc-log-controls">
                            <span class="hpc-gsc-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                            <button type="button" class="hpc-button secondary" data-gsc-clear-log>Clear</button>
                        </span>
                    </summary>
                    <div class="hpc-gsc-log-body" data-gsc-log-body aria-live="polite">
                        <?php echo $this->log_row( [ 'time' => 'Ready', 'level' => 'info', 'message' => 'Checklist runner is ready.', 'context' => [] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </details>
            <?php
            $body = (string) ob_get_clean();

            echo CoreUi::collapsible(
                [
                    'title'       => $this->config->title(),
                    'open'        => true,
                    'persist_key' => $root_id . '-panel',
                    'meta_html'   => CoreUi::pill( count( $steps ) . ' steps', 'dark' ),
                    'body_html'   => $body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php
    }

    private function step_html( GettingStartedChecklistStep $step ): string {
        $subtasks = $step->subtasks;

        ob_start();
        ?>
        <details class="hpc-gsc-step" data-gsc-step-card data-step-id="<?php echo esc_attr( $step->id ); ?>" open>
            <summary class="hpc-gsc-row hpc-gsc-step-row" data-gsc-item data-gsc-step-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="" data-request-type="<?php echo esc_attr( $step->type ); ?>" data-has-action="<?php echo $step->has_callback() ? '1' : '0'; ?>" data-has-subtasks="<?php echo [] !== $subtasks ? '1' : '0'; ?>" data-status="pending">
                <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="hpc-gsc-main">
                    <div class="hpc-gsc-title-line">
                        <strong><?php echo esc_html( $step->label ); ?></strong>
                        <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $step->type ) ); ?></span>
                        <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                    </div>
                    <?php if ( '' !== $step->description ) : ?>
                        <p><?php echo esc_html( $step->description ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="hpc-gsc-row-action">
                    <span class="hpc-gsc-section-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                    <?php echo DynamicButton::render( [ 'label' => [] !== $subtasks ? $step->action_label . ' Step' : $step->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-step' => true, 'data-step-id' => $step->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </summary>

            <?php if ( [] !== $subtasks ) : ?>
                <div class="hpc-gsc-subtasks" data-gsc-subtasks="<?php echo esc_attr( $step->id ); ?>">
                    <?php foreach ( $subtasks as $subtask ) : ?>
                        <div class="hpc-gsc-row hpc-gsc-subtask-row" data-gsc-item data-gsc-subtask-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="<?php echo esc_attr( $subtask->id ); ?>" data-request-type="<?php echo esc_attr( $subtask->type ); ?>" data-has-action="<?php echo $subtask->has_callback() ? '1' : '0'; ?>" data-status="pending">
                            <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="hpc-gsc-main">
                                <div class="hpc-gsc-title-line">
                                    <strong><?php echo esc_html( $subtask->label ); ?></strong>
                                    <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $subtask->type ) ); ?></span>
                                    <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                                </div>
                                <?php if ( '' !== $subtask->description ) : ?>
                                    <p><?php echo esc_html( $subtask->description ); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="hpc-gsc-row-action">
                                <?php echo DynamicButton::render( [ 'label' => $subtask->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-item' => true, 'data-step-id' => $step->id, 'data-subtask-id' => $subtask->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </details>
        <?php
        return (string) ob_get_clean();
    }

    private function assets( string $root_id ): string {
        ob_start();
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-list{display:grid;gap:12px;margin:0 0 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{align-items:flex-start;display:grid;gap:12px;grid-template-columns:34px minmax(0,1fr) auto;padding:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step-row{background:#fbfcfe}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtasks{border-top:1px solid #edf1f6;display:grid;gap:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{border-top:1px solid #edf1f6;margin-left:34px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{align-items:center;display:flex;gap:10px;justify-content:flex-end}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:28px;justify-content:center;transition:background .18s,border-color .18s,color .18s;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle svg{display:block;fill:currentColor;height:12px;transform:rotate(180deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step:not([open]) .hpc-gsc-section-toggle svg{transform:rotate(0deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:hover .hpc-gsc-section-toggle{background:#e4ebff;border-color:#c7d4ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:focus-visible{box-shadow:inset 0 0 0 2px var(--hpc-blue);outline:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main{min-width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line strong{font-size:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main p{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-state{background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#475569;font-size:11px;font-weight:800;line-height:1;padding:5px 8px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-type{background:#fff;border:1px solid #d8e1ec;border-radius:5px;color:#536173;font-size:11px;font-weight:800;line-height:1;padding:5px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-state{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-state{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-state{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon{align-items:center;background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#64748b;display:inline-flex;height:30px;justify-content:center;margin-top:1px;width:30px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon svg{display:block;fill:currentColor;height:14px;width:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-x,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-spinner{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-pending{background:currentColor;border-radius:999px;display:block;height:8px;width:8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-status-icon{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-status-icon{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-status-icon{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-spinner{animation:hpc-gsc-spin .72s linear infinite;border:3px solid currentColor;border-right-color:transparent;border-radius:999px;display:block;height:17px;width:17px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-x{display:block}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-pending{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head{align-items:center;background:#111c2a;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-head{border-bottom:1px solid #263241}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head h3{color:#f8fafc;font-size:15px;margin:0 0 4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head span{color:#9fb1c6;font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-controls{align-items:center;display:inline-flex;gap:10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron{align-items:center;background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#cbd5e1;display:inline-flex;height:28px;justify-content:center;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron svg{fill:currentColor;height:12px;transform:rotate(0deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-chevron svg{transform:rotate(180deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-body{max-height:360px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:minmax(70px,92px) minmax(58px,82px) minmax(0,1fr);padding:12px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level{border-radius:5px;font-size:11px;font-weight:900;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-message{font-size:13px;font-weight:650;margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-context{color:#9fb1c6;font-size:12px;overflow-wrap:anywhere;white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-gsc-spin{to{transform:rotate(360deg)}}
            @media(max-width:760px){#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{grid-template-columns:34px minmax(0,1fr)}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{grid-column:2;justify-content:flex-start}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{margin-left:0}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $root_id ); ?>');
            if (!root || root.dataset.gscReady === '1') return;
            root.dataset.gscReady = '1';
            var logBody = null;
            function text(value){ return value === null || value === undefined ? '' : String(value); }
            function esc(value){ var div = document.createElement('div'); div.textContent = text(value); return div.innerHTML; }
            function css(value){ if (window.CSS && CSS.escape) return CSS.escape(text(value)); return text(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }
            function now(){ var date = new Date(); return date.toTimeString().slice(0,8); }
            function getLogBody(){
                if (!logBody || !root.contains(logBody)) {
                    logBody = root.querySelector('[data-gsc-log-body]');
                }
                return logBody;
            }
            function dynamicStart(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, label); else if (button) button.disabled = true; }
            function dynamicSuccess(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button, label || 'Done'); else if (button) button.disabled = false; }
            function dynamicError(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, label || 'Failed'); else if (button) button.disabled = false; }
            function dynamicReset(button){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.reset(button); else if (button) button.disabled = false; }
            function logRow(entry){
                entry = entry || {};
                var context = entry.context && Object.keys(entry.context).length ? JSON.stringify(entry.context, null, 2) : '';
                return '<div class="hpc-gsc-log-row"><div class="hpc-gsc-log-time">' + esc(entry.time || now()) + '</div><div><span class="hpc-gsc-log-level ' + esc((entry.level || 'info').toLowerCase()) + '">' + esc(entry.level || 'info') + '</span></div><div><div class="hpc-gsc-log-message">' + esc(entry.message || '') + '</div>' + (context ? '<div class="hpc-gsc-log-context">' + esc(context) + '</div>' : '') + '</div></div>';
            }
            function addLog(entry){
                var target = getLogBody();
                if (!target) return;
                target.insertAdjacentHTML('beforeend', logRow(entry));
                target.scrollTop = target.scrollHeight;
            }
            function addLogs(logs){ (logs || []).forEach(addLog); }
            function clearLog(){
                var target = getLogBody();
                if (!target) return;
                target.innerHTML = logRow({time:'Ready', level:'info', message:'Checklist runner is ready.', context:{}});
            }
            function setRowState(row, state, message){
                if (!row) return;
                row.dataset.status = state || 'pending';
                var label = row.querySelector('[data-gsc-state]');
                if (label) label.textContent = message || ({pending:'Pending', running:'Running', success:'Complete', failed:'Failed'}[state] || 'Pending');
            }
            function resetRows(){
                root.querySelectorAll('[data-gsc-item]').forEach(function(row){ setRowState(row, 'pending', 'Pending'); });
                root.querySelectorAll('[data-hpc-dynamic-button]').forEach(dynamicReset);
            }
            function postItem(stepId, subtaskId){
                var body = new URLSearchParams();
                body.set('action', root.dataset.runAction || '');
                body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                body.set('step_id', stepId || '');
                body.set('subtask_id', subtaskId || '');
                return fetch(root.dataset.ajaxUrl || window.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                }).then(function(response){ return response.json(); }).then(function(payload){
                    if (!payload || !payload.success) {
                        var message = payload && payload.data && (payload.data.message || payload.data.error) ? (payload.data.message || payload.data.error) : 'AJAX request failed.';
                        throw new Error(message);
                    }
                    return payload.data || {};
                });
            }
            function runItem(row){
                var stepId = row ? row.dataset.stepId : '';
                var subtaskId = row ? row.dataset.subtaskId : '';
                setRowState(row, 'running', 'Running');
                return postItem(stepId, subtaskId).then(function(data){
                    addLogs(data.logs);
                    if (data.success) {
                        setRowState(row, 'success', data.message || 'Complete');
                        return true;
                    }
                    setRowState(row, 'failed', data.message || 'Failed');
                    return false;
                }).catch(function(error){
                    setRowState(row, 'failed', error.message || 'Failed');
                    addLog({level:'error', message:error.message || 'Checklist item failed.', context:{step_id:stepId, subtask_id:subtaskId}});
                    return false;
                });
            }
            function runParentAction(stepRow){
                var stepId = stepRow ? stepRow.dataset.stepId : '';
                return postItem(stepId, '').then(function(data){
                    addLogs(data.logs);
                    if (!data.success) {
                        addLog({level:'error', message:data.message || 'Parent step action failed.', context:{step_id:stepId}});
                    }
                    return !!data.success;
                }).catch(function(error){
                    addLog({level:'error', message:error.message || 'Parent step action failed.', context:{step_id:stepId}});
                    return false;
                });
            }
            async function runStep(stepRow){
                if (!stepRow) return false;
                var stepId = stepRow.dataset.stepId || '';
                var card = root.querySelector('[data-gsc-step-card][data-step-id="' + css(stepId) + '"]');
                var subtasks = card ? Array.prototype.slice.call(card.querySelectorAll('[data-gsc-subtask-row]')) : [];
                if (!subtasks.length) {
                    return runItem(stepRow);
                }
                setRowState(stepRow, 'running', 'Running subtasks');
                addLog({level:'info', message:'Starting parent step subtasks.', context:{step_id:stepId, subtask_count:subtasks.length}});
                var allOk = true;
                if (stepRow.dataset.hasAction === '1') {
                    allOk = await runParentAction(stepRow);
                    if (!allOk) {
                        setRowState(stepRow, 'failed', 'Parent action failed');
                        return false;
                    }
                }
                for (var i = 0; i < subtasks.length; i++) {
                    var ok = await runItem(subtasks[i]);
                    if (!ok) allOk = false;
                }
                setRowState(stepRow, allOk ? 'success' : 'failed', allOk ? 'All subtasks complete' : 'Subtask failed');
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Parent step completed.' : 'Parent step completed with failures.', context:{step_id:stepId}});
                return allOk;
            }
            async function runAll(button){
                resetRows();
                clearLog();
                dynamicStart(button, 'Running...');
                addLog({level:'info', message:'Starting full getting started checklist run.', context:{steps:root.querySelectorAll('[data-gsc-step-row]').length}});
                var rows = Array.prototype.slice.call(root.querySelectorAll('[data-gsc-step-row]'));
                var allOk = true;
                for (var i = 0; i < rows.length; i++) {
                    var ok = await runStep(rows[i]);
                    if (!ok) allOk = false;
                }
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Checklist run finished successfully.' : 'Checklist run finished with failures.', context:{}});
                if (allOk) dynamicSuccess(button, 'Checklist Finished'); else dynamicError(button, 'Checklist Failed');
            }
            root.addEventListener('click', function(event){
                var clear = event.target.closest('[data-gsc-clear-log]');
                if (clear) {
                    event.preventDefault();
                    event.stopPropagation();
                    clearLog();
                    return;
                }
                var reset = event.target.closest('[data-gsc-reset]');
                if (reset) {
                    event.preventDefault();
                    resetRows();
                    clearLog();
                    return;
                }
                var runAllButton = event.target.closest('[data-gsc-run-all]');
                if (runAllButton) {
                    event.preventDefault();
                    runAll(runAllButton);
                    return;
                }
                var runStepButton = event.target.closest('[data-gsc-run-step]');
                if (runStepButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runStepButton, 'Running...');
                    var stepRow = root.querySelector('[data-gsc-step-row][data-step-id="' + css(runStepButton.dataset.stepId || '') + '"]');
                    runStep(stepRow).then(function(ok){ if (ok) dynamicSuccess(runStepButton, 'Done'); else dynamicError(runStepButton, 'Failed'); });
                    return;
                }
                var runItemButton = event.target.closest('[data-gsc-run-item]');
                if (runItemButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runItemButton, 'Running...');
                    var selector = '[data-gsc-subtask-row][data-step-id="' + css(runItemButton.dataset.stepId || '') + '"][data-subtask-id="' + css(runItemButton.dataset.subtaskId || '') + '"]';
                    var row = root.querySelector(selector);
                    runItem(row).then(function(ok){ if (ok) dynamicSuccess(runItemButton, 'Done'); else dynamicError(runItemButton, 'Failed'); });
                }
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function status_icon(): string {
        return '<span class="hpc-gsc-status-icon" aria-hidden="true">'
            . '<span class="hpc-gsc-icon-pending"></span>'
            . '<span class="hpc-gsc-icon-spinner"></span>'
            . '<span class="hpc-gsc-icon-check"><svg viewBox="0 0 512 512" focusable="false"><path d="M470.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L192 338.7 425.4 105.4c12.5-12.5 32.8-12.5 45.2 0z"></path></svg></span>'
            . '<span class="hpc-gsc-icon-x"><svg viewBox="0 0 384 512" focusable="false"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256l105.3-105.4z"></path></svg></span>'
            . '</span>';
    }

    private function type_label( string $type ): string {
        return match ( $type ) {
            GettingStartedChecklistStep::TYPE_STATUS_CHECK => 'Status Check',
            GettingStartedChecklistStep::TYPE_SETUP_ACTION => 'Setup Action',
            GettingStartedChecklistStep::TYPE_FEATURE_TOGGLE => 'Feature Toggle',
            GettingStartedChecklistStep::TYPE_CONFIG_MUTATION => 'Config Mutation',
            GettingStartedChecklistStep::TYPE_AJAX_REQUEST => 'AJAX Request',
            GettingStartedChecklistStep::TYPE_CUSTOM => 'Custom',
            default => 'Callback',
        };
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function log_row( array $entry ): string {
        $level   = strtolower( (string) ( $entry['level'] ?? 'info' ) );
        $context = isset( $entry['context'] ) && is_array( $entry['context'] ) && [] !== $entry['context']
            ? wp_json_encode( $entry['context'], JSON_PRETTY_PRINT )
            : '';

        return '<div class="hpc-gsc-log-row">'
            . '<div class="hpc-gsc-log-time">' . esc_html( (string) ( $entry['time'] ?? '' ) ) . '</div>'
            . '<div><span class="hpc-gsc-log-level ' . esc_attr( $level ) . '">' . esc_html( $level ) . '</span></div>'
            . '<div><div class="hpc-gsc-log-message">' . esc_html( (string) ( $entry['message'] ?? '' ) ) . '</div>'
            . ( '' !== $context ? '<div class="hpc-gsc-log-context">' . esc_html( $context ) . '</div>' : '' )
            . '</div></div>';
    }
}
