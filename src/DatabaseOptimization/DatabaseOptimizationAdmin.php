<?php

namespace HWS\BaseTools\DatabaseOptimization;

use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminComponents\DynamicNotice;
use Hexa\PluginCore\WpAdminComponents\TaskRunner;

/**
 * The Database Optimization dashboard. Every number and action loads through
 * AJAX, so nothing on the page needs a reload.
 */
final class DatabaseOptimizationAdmin {
    private const ROOT = 'hws-database-optimization';

    public static function render(): void {
        TaskRunner::render_assets();
        DynamicNotice::render_assets();
        $root = self::ROOT;
        $btn  = static fn( string $label, string $working, array $attrs, string $class = 'hpc-button secondary' ): string => DynamicButton::render( [ 'label' => $label, 'working_label' => $working, 'class' => $class, 'attrs' => $attrs ] );
        $r    = '#' . $root;
        ?>
        <div id="<?php echo esc_attr( $root ); ?>" class="hpc-ui hpc-runner-app hws-dbo" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( DatabaseOptimizationModule::NONCE_ACTION ) ); ?>" data-nonce-field="nonce" data-action-prefix="<?php echo esc_attr( DatabaseOptimizationModule::ACTION ); ?>">
            <style>
                <?php echo esc_html( $r ); ?> table.hpc-runner-table{max-width:none;min-width:720px}
                <?php echo esc_html( $r ); ?> [data-hpc-runner-panel="reports"] table.hpc-runner-table{min-width:480px}
                <?php echo esc_html( $r ); ?> [data-dbo-tables] .hpc-runner-mono{white-space:nowrap}
                <?php echo esc_html( $r ); ?> .hws-dbo-group{border-bottom:1px solid #f1f5f9;padding-bottom:6px}
                <?php echo esc_html( $r ); ?> .hws-dbo-group:last-child{border-bottom:0}
                <?php echo esc_html( $r ); ?> .hws-dbo-group-title{align-items:center;color:#64748b;display:flex;font-size:11px;font-weight:700;gap:8px;letter-spacing:.05em;padding:16px 18px 6px;text-transform:uppercase}
                <?php echo esc_html( $r ); ?> .hws-dbo-item{align-items:center;border-radius:10px;cursor:pointer;display:grid;gap:14px;grid-template-columns:18px minmax(0,1fr) 90px 150px;margin:0 8px;padding:11px 10px}
                <?php echo esc_html( $r ); ?> .hws-dbo-item:hover{background:#f8fafc}
                <?php echo esc_html( $r ); ?> .hws-dbo-item-main strong{color:#0f172a;display:block;font-size:13.5px;font-weight:600}
                <?php echo esc_html( $r ); ?> .hws-dbo-item-main span{color:#64748b;display:block;font-size:12.5px;line-height:1.45;margin-top:2px}
                <?php echo esc_html( $r ); ?> .hws-dbo-item-count{color:#0f172a;font-size:15px;font-variant-numeric:tabular-nums;font-weight:700;text-align:right}
                <?php echo esc_html( $r ); ?> .hws-dbo-item-count.is-zero{color:#cbd5e1;font-weight:600}
                <?php echo esc_html( $r ); ?> .hws-dbo-item-state{text-align:right}
                <?php echo esc_html( $r ); ?> .hws-dbo-selected{color:#64748b;font-size:12.5px;margin-right:4px}
                <?php echo esc_html( $r ); ?> .hws-dbo-selected strong{color:#0f172a}
                <?php echo esc_html( $r ); ?> .hws-dbo-quick{margin-left:auto}
                <?php echo esc_html( $r ); ?> .hws-dbo-owner{color:#0f172a;font-weight:600}
                <?php echo esc_html( $r ); ?> .hws-dbo-section{padding:18px}
                <?php echo esc_html( $r ); ?> .hws-dbo-section+.hws-dbo-section{border-top:1px solid #f1f5f9}
                <?php echo esc_html( $r ); ?> .hws-dbo-section h3{color:#0f172a;font-size:14px;margin:0 0 4px}
                <?php echo esc_html( $r ); ?> .hws-dbo-section>p{color:#64748b;margin:0 0 14px}
                <?php echo esc_html( $r ); ?> .hws-dbo-bar-row{align-items:center;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) 80px;padding:7px 0}
                <?php echo esc_html( $r ); ?> .hws-dbo-bar-row b{display:block;font-weight:400}
                <?php echo esc_html( $r ); ?> .hws-dbo-bar{background:#f1f5f9;border-radius:999px;display:block;height:5px;margin-top:6px;overflow:hidden}
                <?php echo esc_html( $r ); ?> .hws-dbo-bar span{background:#93c5fd;display:block;height:100%}
                <?php echo esc_html( $r ); ?> .hws-dbo-bar-size{color:#0f172a;font-variant-numeric:tabular-nums;font-weight:600;text-align:right}
                <?php echo esc_html( $r ); ?> .hws-dbo-settings{display:grid;gap:20px 28px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));padding:18px}
                <?php echo esc_html( $r ); ?> .hws-dbo-field{display:grid;gap:6px}
                <?php echo esc_html( $r ); ?> .hws-dbo-field span{color:#0f172a;font-weight:600}
                <?php echo esc_html( $r ); ?> .hws-dbo-field small{color:#64748b;font-size:12px;line-height:1.45}
                <?php echo esc_html( $r ); ?> .hws-dbo-field input{border:1px solid #e2e8f0;border-radius:9px;box-shadow:none;font-size:14px;max-width:140px;min-height:38px;padding:0 12px}
                <?php echo esc_html( $r ); ?> .hws-dbo-field input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:0}
                <?php echo esc_html( $r ); ?> .hws-dbo-footer{border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;padding:14px 18px}
                @media(max-width:782px){<?php echo esc_html( $r ); ?> .hws-dbo-item{grid-template-columns:18px minmax(0,1fr) 70px}<?php echo esc_html( $r ); ?> .hws-dbo-item-state{display:none}<?php echo esc_html( $r ); ?> .hws-dbo-quick{margin-left:0}}
            </style>
            <?php
            echo DynamicNotice::render( [ 'id' => $root . '-notice' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo TaskRunner::header( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'        => 'Database Optimization',
                    'text'         => 'Clean out old revisions, trash, spam and leftover data, then repair, convert and compact this site\'s tables. Every step runs live, one batch or one table at a time.',
                    'actions_html' => $btn( 'Rescan', 'Scanning...', [ 'data-dbo-scan' => true ] ) . $btn( 'Optimize Everything', 'Optimizing...', [ 'data-dbo-run-all' => true ], 'hpc-button' ),
                ]
            );
            echo TaskRunner::progress( [ 'stop' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo TaskRunner::stats( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    [ 'key' => 'size', 'label' => 'Database size' ],
                    [ 'key' => 'free', 'label' => 'Space to free' ],
                    [ 'key' => 'found', 'label' => 'Items to clean' ],
                    [ 'key' => 'attention', 'label' => 'Tables needing work' ],
                ]
            );
            echo TaskRunner::segments( [ 'cleanup' => 'Cleanup', 'tables' => 'Tables', 'reports' => 'Reports', 'settings' => 'Settings' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <section class="hpc-runner-card" data-hpc-runner-panel="cleanup">
                <div class="hpc-runner-toolbar">
                    <div class="hpc-runner-toolbar-group">
                        <span class="hpc-runner-bulkbar-label">Select</span>
                        <button type="button" class="hpc-runner-link" data-dbo-pick="default">Recommended</button>
                        <button type="button" class="hpc-runner-link" data-dbo-pick="all">All</button>
                        <button type="button" class="hpc-runner-link" data-dbo-pick="none">None</button>
                    </div>
                    <div class="hpc-runner-toolbar-group">
                        <span class="hws-dbo-selected" data-dbo-selected></span>
                        <?php echo $btn( 'Clean Selected', 'Cleaning...', [ 'data-dbo-clean' => true ], 'hpc-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
                <div data-dbo-tasks><div class="hpc-runner-empty">Loading cleanup items...</div></div>
            </section>

            <section class="hpc-runner-card" data-hpc-runner-panel="tables" hidden>
                <div class="hpc-runner-toolbar">
                    <div class="hpc-runner-toolbar-group">
                        <input type="search" class="hpc-runner-search" placeholder="Search tables or plugins" data-dbo-search aria-label="Search tables">
                        <span class="hpc-runner-toolbar-group" data-dbo-chips></span>
                    </div>
                </div>
                <div class="hpc-runner-bulkbar">
                    <span class="hpc-runner-bulkbar-label" data-dbo-picked>Select tables for</span>
                    <button type="button" class="hpc-button secondary small" data-dbo-selected-op="check" disabled>Check</button>
                    <button type="button" class="hpc-button secondary small" data-dbo-selected-op="repair" disabled>Repair</button>
                    <button type="button" class="hpc-button secondary small" data-dbo-selected-op="convert" disabled>Convert to InnoDB</button>
                    <button type="button" class="hpc-button secondary small" data-dbo-selected-op="optimize" disabled>Optimize</button>
                    <span class="hpc-runner-toolbar-group hws-dbo-quick">
                        <button type="button" class="hpc-button small" data-dbo-quick="convert">Convert all MyISAM</button>
                        <button type="button" class="hpc-button secondary small" data-dbo-quick="optimize">Free all space</button>
                        <button type="button" class="hpc-button secondary small" data-dbo-quick="check">Check all</button>
                    </span>
                </div>
                <div class="hpc-runner-table-wrap">
                    <table class="hpc-runner-table">
                        <thead><tr>
                            <th class="hpc-runner-col-check"><input type="checkbox" class="hpc-runner-check" data-dbo-pick-tables aria-label="Select all shown tables"></th>
                            <th>Table</th><th>Plugin</th><th>Engine</th><th class="num">Rows</th><th class="num">Size</th><th class="num">Free space</th><th>Status</th>
                        </tr></thead>
                        <tbody data-dbo-tables><tr><td colspan="8" class="hpc-runner-empty">Loading tables...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="hpc-runner-card" data-hpc-runner-panel="reports" hidden>
                <div class="hws-dbo-section">
                    <h3>Settings loaded on every page</h3>
                    <p>WordPress loads these on every request, so large ones slow every page down. <span data-dbo-autoload-total></span></p>
                    <div data-dbo-autoload><div class="hpc-runner-muted">Loading...</div></div>
                </div>
                <div class="hws-dbo-section">
                    <h3>Tables from inactive or removed plugins</h3>
                    <p>Likely leftovers, grouped by the plugin that made them. Listed for review only and never deleted automatically.</p>
                    <div class="hpc-runner-table-wrap"><table class="hpc-runner-table"><thead><tr><th>Plugin</th><th class="num">Rows</th><th class="num">Size</th></tr></thead><tbody data-dbo-leftover><tr><td colspan="3" class="hpc-runner-muted">Loading...</td></tr></tbody></table></div>
                </div>
                <div class="hws-dbo-section">
                    <h3>Media attached to deleted posts</h3>
                    <p data-dbo-media>Loading...</p>
                </div>
            </section>

            <section class="hpc-runner-card" data-hpc-runner-panel="settings" hidden>
                <form data-dbo-settings>
                    <div class="hws-dbo-settings">
                        <?php
                        $values = OptimizationSettings::get();
                        foreach ( OptimizationSettings::fields() as $key => $field ) {
                            echo '<label class="hws-dbo-field"><span>' . esc_html( $field['label'] ) . '</span>'
                                . '<input type="number" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $values[ $key ] ) . '" min="' . esc_attr( (string) $field['min'] ) . '" max="' . esc_attr( (string) $field['max'] ) . '">'
                                . '<small>' . esc_html( $field['help'] ) . '</small></label>';
                        }
                        ?>
                    </div>
                    <div class="hws-dbo-footer"><?php echo DynamicButton::render( [ 'label' => 'Save Settings', 'working_label' => 'Saving...', 'success_label' => 'Saved', 'attrs' => [ 'data-dbo-save' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </form>
            </section>
            <?php
            echo TaskRunner::log( [ 'title' => 'Activity' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            self::script();
            ?>
        </div>
        <?php
    }

    private static function script(): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( self::ROOT ); ?>');if(!root||root.dataset.ready)return;root.dataset.ready='1';
            var T=window.HexaWpCoreTaskRunner,R=T.create(root),A=root.dataset.actionPrefix,B=window.HexaWpCoreDynamicButton,N=window.HexaWpCoreDynamicNotice;
            var esc=T.esc,num=T.number,bytes=T.bytes,badge=T.badge,notice='#<?php echo esc_js( self::ROOT ); ?>-notice';
            var tasks={},counts={},results={},tables=[],picked={},rowState={},filter='all',query='';
            var FILTERS=[['all','All',function(){return true}],['attention','Needs work',function(t){return t.needs_repair||t.convertible}],['myisam','MyISAM',function(t){return t.convertible}],['free','Space to free',function(t){return t.optimizable}],['plugin','Plugin tables',function(t){return !t.core}],['leftover','Inactive or removed plugins',function(t){return ['inactive','missing','unknown'].indexOf(t.owner_state)>=0}]];
            var OPS={check:'Checking',repair:'Repairing',convert:'Converting',optimize:'Optimizing'},DONE={check:'Check finished',repair:'Repair finished',convert:'Conversion to InnoDB finished',optimize:'Optimization finished'};
            function q(s){return root.querySelector(s)}
            function isDyn(b){return b&&b.matches('[data-hpc-dynamic-button]')}
            function kpi(key,value,hint,tone){var v=q('[data-hpc-stat="'+key+'"]');if(v)v.textContent=value;var h=q('[data-hpc-stat-hint="'+key+'"]');if(h)h.textContent=hint||'';var c=q('[data-hpc-kpi="'+key+'"]');if(c)c.setAttribute('data-tone',tone||'')}
            function filterFn(id){for(var i=0;i<FILTERS.length;i++)if(FILTERS[i][0]===id)return FILTERS[i][2];return FILTERS[0][2]}

            /* Cleanup */
            function selected(){return Array.prototype.map.call(root.querySelectorAll('[data-dbo-task]:checked'),function(c){return c.value})}
            function updateFound(){var t=0,known=0,ids=selected();ids.forEach(function(id){if(counts.hasOwnProperty(id)){t+=counts[id];known++}});var s=q('[data-dbo-selected]');if(s)s.innerHTML=ids.length?'<strong>'+num(t)+'</strong> '+(t===1?'item':'items')+' across '+ids.length+' selected':'Nothing selected';kpi('found',known?num(t):'–',ids.length+' cleanup items selected',t?'warn':'good');R.segmentCount('cleanup',t?num(t):'')}
            function taskRow(id){return root.querySelector('[data-dbo-task-row="'+id+'"]')}
            function setCount(id){var row=taskRow(id);if(!row)return;var c=row.querySelector('[data-dbo-found]'),v=counts[id];c.textContent=v===undefined?'–':num(v);c.classList.toggle('is-zero',v===0)}
            function renderTasks(defs){var keep={};root.querySelectorAll('[data-dbo-task]').forEach(function(c){keep[c.value]=c.checked});tasks=defs;var groups={},order=[];Object.keys(defs).forEach(function(id){var g=defs[id].group;if(!groups[g]){groups[g]=[];order.push(g)}groups[g].push(id)});q('[data-dbo-tasks]').innerHTML=order.map(function(g){return '<div class="hws-dbo-group"><div class="hws-dbo-group-title">'+esc(g)+(g==='Review first'?badge('Not selected by default','amber'):'')+'</div>'+groups[g].map(function(id){var t=defs[id],on=keep.hasOwnProperty(id)?keep[id]:t.default;return '<label class="hws-dbo-item" data-dbo-task-row="'+esc(id)+'"><input type="checkbox" class="hpc-runner-check" data-dbo-task value="'+esc(id)+'"'+(on?' checked':'')+'><span class="hws-dbo-item-main"><strong>'+esc(t.label)+'</strong><span>'+esc(t.description)+'</span></span><span class="hws-dbo-item-count" data-dbo-found></span><span class="hws-dbo-item-state" data-hpc-runner-state></span></label>'}).join('')+'</div>'}).join('');Object.keys(defs).forEach(function(id){setCount(id);var r=results[id];if(r)R.setRow(taskRow(id),r[0],r[1])});updateFound()}
            function mark(id,status,label){results[id]=[status,label];R.setRow(taskRow(id),status,label)}

            /* Overview */
            function renderOverview(o){kpi('size',bytes(o.size),num(o.tables)+' tables · '+o.server,'info');kpi('free',bytes(o.overhead),o.overhead?'Wasted space tables can give back':'Nothing to free',o.overhead?'warn':'good');var att=o.myisam+o.needs_repair,parts=[];if(o.myisam)parts.push(o.myisam+' MyISAM');if(o.needs_repair)parts.push(o.needs_repair+' damaged');kpi('attention',num(att),att?parts.join(' · '):'All healthy and on InnoDB',att?'bad':'good')}
            function overview(withTasks){return R.post(A+'overview').then(function(d){renderOverview(d.overview);if(withTasks)renderTasks(d.tasks);return d})}

            /* Tables */
            function ownerCell(t){if(t.owner_state==='core')return '<span class="hpc-runner-muted">WordPress</span>';if(t.owner_state==='unknown')return badge('Unknown plugin');var tone={active:'green',inactive:'amber',missing:'red'}[t.owner_state],label={active:'Active',inactive:'Inactive',missing:'Not installed'}[t.owner_state];return '<span class="hpc-runner-badges"><span class="hws-dbo-owner">'+esc(t.owner)+'</span>'+badge(label,tone)+'</span>'}
            function healthCell(t){var s=rowState[t.name];if(s)return T.state(s[0],s[1]);if(t.needs_repair)return T.state('error','Needs repair');if(t.convertible)return T.state('pending','Convert to InnoDB');if(t.optimizable)return T.state('pending','Can free space');return T.state('skipped','OK')}
            function tableRowHtml(t){return '<tr data-dbo-table="'+esc(t.name)+'"><td class="hpc-runner-col-check"><input type="checkbox" class="hpc-runner-check" data-dbo-pick-table value="'+esc(t.name)+'"'+(picked[t.name]?' checked':'')+'></td><td><span class="hpc-runner-mono">'+esc(t.name)+'</span></td><td>'+ownerCell(t)+'</td><td>'+(t.convertible?badge(t.engine,'amber'):badge(t.engine))+'</td><td class="num">'+num(t.rows)+'</td><td class="num">'+bytes(t.size)+'</td><td class="num" title="'+esc(t.optimize_note)+'">'+(t.optimizable?bytes(t.overhead):'<span class="hpc-runner-muted">–</span>')+'</td><td data-hpc-runner-state>'+healthCell(t)+'</td></tr>'}
            function visibleTables(){var f=filterFn(filter);return tables.filter(function(t){return f(t)&&(!query||(t.name+' '+t.owner+' '+t.engine).toLowerCase().indexOf(query)>=0)})}
            function renderTables(){var list=visibleTables();q('[data-dbo-tables]').innerHTML=list.length?list.map(tableRowHtml).join(''):'<tr><td colspan="8" class="hpc-runner-empty">No tables match.</td></tr>';q('[data-dbo-chips]').innerHTML=FILTERS.map(function(f){var n=tables.filter(f[2]).length;return (f[0]==='all'||n)?'<button type="button" class="hpc-runner-chip'+(filter===f[0]?' is-active':'')+'" data-dbo-filter="'+f[0]+'">'+esc(f[1])+' <span>'+n+'</span></button>':''}).join('');var all=q('[data-dbo-pick-tables]');all.checked=list.length>0&&list.every(function(t){return picked[t.name]});updatePicked();updateQuick();var att=tables.filter(filterFn('attention')).length;R.segmentCount('tables',att||'')}
            function updatePicked(){var n=Object.keys(picked).length;q('[data-dbo-picked]').textContent=n?'With '+n+' selected:':'Select tables for';root.querySelectorAll('[data-dbo-selected-op]').forEach(function(b){b.disabled=!n||R.busy})}
            function updateQuick(){var c={convert:tables.filter(filterFn('myisam')).length,optimize:tables.filter(filterFn('free')).length,check:tables.length};var L={convert:'Convert all MyISAM',optimize:'Free all space',check:'Check all'};root.querySelectorAll('[data-dbo-quick]').forEach(function(b){var op=b.getAttribute('data-dbo-quick');b.textContent=L[op]+' ('+c[op]+')';b.disabled=!c[op]||R.busy;b.title=!c[op]?(op==='convert'?'Every table already uses InnoDB.':'Nothing to free.'):''})}
            function loadTables(){return R.post(A+'tables').then(function(d){tables=d.tables||[];renderTables()})}
            function replaceTable(t){for(var i=0;i<tables.length;i++){if(tables[i].name===t.name)tables[i]=t}var row=root.querySelector('tr[data-dbo-table="'+CSS.escape(t.name)+'"]');if(row){var tmp=document.createElement('tbody');tmp.innerHTML=tableRowHtml(t);row.replaceWith(tmp.firstChild)}}
            function setTableState(name,status,label){rowState[name]=[status,label];R.setRow(root.querySelector('tr[data-dbo-table="'+CSS.escape(name)+'"]'),status,label)}

            /* Jobs */
            async function scan(){var ids=Object.keys(tasks),done=0;R.log('info','Scanning '+ids.length+' cleanup items.');var out=await R.queue(ids,async function(id){mark(id,'running','Counting');R.progress(done,ids.length,'Scanning the database',tasks[id].label);var d=await R.post(A+'count',{task:id});counts[id]=d.count;setCount(id);mark(id,d.count?'pending':'done',d.count?'Ready to clean':'Clean');done++;updateFound()},{concurrency:4,onError:function(id){mark(id,'error','Failed')}});if(out.cancelled){R.log('warning','Scan stopped after '+done+' of '+ids.length+' items.');return}var total=0;Object.keys(counts).forEach(function(k){total+=counts[k]});R.log('success','Scan finished: '+num(total)+' items found.');R.progress(1,1,'Scan finished',num(total)+' items found')}
            async function clean(){var ids=selected().filter(function(id){return !counts.hasOwnProperty(id)||counts[id]>0});if(!ids.length){R.log('info','Nothing selected needs cleaning.');return}var step=0,all=0;await R.queue(ids,async function(id){var removed=0,failed=0;mark(id,'running','Cleaning');R.progress(step,ids.length,'Cleaning',tasks[id].label);var d=await R.loop(A+'clean',{task:id},{onStep:function(s){removed+=s.removed;failed+=s.failed;mark(id,'running','Removed '+num(removed));R.progress(step,ids.length,'Cleaning '+tasks[id].label,num(removed)+' removed so far')}});if(d.cancelled){mark(id,'skipped','Stopped');return}counts[id]=d.remaining||0;setCount(id);all+=removed;mark(id,counts[id]?'error':'done',counts[id]?num(counts[id])+' left':'Removed '+num(removed));R.log(failed?'warning':'success',tasks[id].label+': removed '+num(removed)+(failed?', '+num(failed)+' could not be removed':'')+'.');step++;updateFound()},{onError:function(id){mark(id,'error','Failed')}});R.progress(1,1,'Cleanup finished',num(all)+' items removed');await overview()}
            async function tableOps(op,names){if(!names.length){R.log('info','No tables to '+op+'.');return}var done=0;await R.queue(names,async function(name){setTableState(name,'running',OPS[op]);R.progress(done,names.length,OPS[op]+' tables',name);var d=await R.post(A+'table',{table:name,operation:op});rowState[name]=[d.status,d.status==='done'?(op==='check'?'Healthy':op==='convert'?'Converted':op==='repair'?'Repaired':'Optimized'):d.status==='skipped'?'Skipped':'Problem'];replaceTable(d.table);if(d.status!=='skipped'||op!=='check')R.log(d.status==='error'?'error':d.status==='done'?'success':'info',name+': '+d.message);done++},{onError:function(name){setTableState(name,'error','Failed')}});R.progress(1,1,DONE[op],names.length+(names.length===1?' table':' tables')+' processed');renderTables()}
            function namesBy(id){return tables.filter(filterFn(id)).map(function(t){return t.name})}
            async function optimizeAll(){await tableOps('check',tables.map(function(t){return t.name}));if(R.cancelled())return;await tableOps('repair',tables.filter(function(t){return t.needs_repair}).map(function(t){return t.name}));if(R.cancelled())return;await tableOps('convert',namesBy('myisam'));if(R.cancelled())return;await tableOps('optimize',namesBy('free'));await overview()}
            function reports(){return R.post(A+'reports').then(function(d){var a=d.autoload||[],max=a.length?a[0].size:1,total=0;a.forEach(function(o){total+=o.size});q('[data-dbo-autoload]').innerHTML=a.map(function(o){return '<div class="hws-dbo-bar-row"><span><b class="hpc-runner-mono">'+esc(o.name)+'</b><span class="hws-dbo-bar"><span style="width:'+Math.max(2,Math.round(o.size/max*100))+'%"></span></span></span><span class="hws-dbo-bar-size">'+bytes(o.size)+'</span></div>'}).join('')||'<div class="hpc-runner-muted">None.</div>';q('[data-dbo-autoload-total]').textContent=a.length?'The '+a.length+' largest add up to '+bytes(total)+'.':'';var l=d.leftover||[],groups={},order=[];l.forEach(function(t){var k=t.owner||'Unknown plugin';if(!groups[k]){groups[k]={owner:t.owner,owner_state:t.owner_state,tables:[],rows:0,size:0};order.push(k)}var g=groups[k];g.tables.push(t.name);g.rows+=t.rows;g.size+=t.size});order.sort(function(x,y){return groups[y].size-groups[x].size});q('[data-dbo-leftover]').innerHTML=order.map(function(k){var g=groups[k];return '<tr><td>'+ownerCell(g)+'<details class="hpc-runner-more"><summary>'+g.tables.length+(g.tables.length===1?' table':' tables')+'</summary><div>'+g.tables.map(esc).join('<br>')+'</div></details></td><td class="num">'+num(g.rows)+'</td><td class="num">'+bytes(g.size)+'</td></tr>'}).join('')||'<tr><td colspan="3" class="hpc-runner-empty">None. Every table belongs to WordPress or an active plugin.</td></tr>';R.segmentCount('reports',order.length||'');q('[data-dbo-media]').textContent=d.detached_media?num(d.detached_media)+' media items point to a post that no longer exists. They may still be used on other pages, so they are never removed automatically.':'None.'})}

            function lock(on){root.querySelectorAll('[data-dbo-run-all],[data-dbo-scan],[data-dbo-clean]').forEach(function(b){b.disabled=on});on?R.begin():R.end();updatePicked();updateQuick()}
            function guard(button,label,fn){if(R.busy)return;lock(true);if(isDyn(button))B.start(button);fn().then(function(){if(isDyn(button))B.success(button,R.cancelled()?'Stopped':label);if(R.cancelled())N.warning(notice,'Stopped','The run stopped after the current step.')}).catch(function(e){R.log('error',e.message);if(isDyn(button))B.error(button,'Failed');N.error(notice,'Something went wrong',e.message)}).finally(function(){lock(false)})}

            root.addEventListener('input',function(e){if(e.target.matches('[data-dbo-search]')){query=e.target.value.trim().toLowerCase();renderTables()}});
            root.addEventListener('change',function(e){var t=e.target;if(t.matches('[data-dbo-task]'))updateFound();if(t.matches('[data-dbo-pick-table]')){if(t.checked)picked[t.value]=true;else delete picked[t.value];renderTables()}if(t.matches('[data-dbo-pick-tables]')){visibleTables().forEach(function(x){if(t.checked)picked[x.name]=true;else delete picked[x.name]});renderTables()}});
            root.addEventListener('click',function(e){var b=e.target.closest('button');if(!b||!root.contains(b))return;
                if(b.matches('[data-dbo-pick]')){var mode=b.getAttribute('data-dbo-pick');root.querySelectorAll('[data-dbo-task]').forEach(function(c){c.checked=mode==='all'||(mode==='default'&&tasks[c.value]&&tasks[c.value].default)});updateFound();return}
                if(b.matches('[data-dbo-filter]')){filter=b.getAttribute('data-dbo-filter');renderTables();return}
                if(b.matches('[data-dbo-scan]'))return guard(b,'Scanned',function(){return scan()});
                if(b.matches('[data-dbo-clean]'))return guard(b,'Cleaned',clean);
                if(b.matches('[data-dbo-run-all]'))return guard(b,'Done',async function(){await scan();if(!R.cancelled())await clean();if(!R.cancelled())await optimizeAll();await reports();if(!R.cancelled()){R.progress(1,1,'All done','Cleanup and table maintenance finished');N.success(notice,'Database optimized','Cleanup and table maintenance finished. The Activity log lists every step.')}});
                if(b.matches('[data-dbo-selected-op]')){var op=b.getAttribute('data-dbo-selected-op'),names=Object.keys(picked);return guard(null,'',function(){return tableOps(op,names).then(overview)})}
                if(b.matches('[data-dbo-quick]')){var qo=b.getAttribute('data-dbo-quick');return guard(null,'',function(){return tableOps(qo,qo==='convert'?namesBy('myisam'):qo==='optimize'?namesBy('free'):tables.map(function(t){return t.name})).then(overview)})}
                if(b.matches('[data-dbo-save]')){e.preventDefault();var form=b.closest('form'),data={};Array.prototype.forEach.call(form.elements,function(el){if(el.name)data[el.name]=el.value});B.start(b);R.post(A+'settings',data).then(function(d){Object.keys(d.settings).forEach(function(k){if(form.elements[k])form.elements[k].value=d.settings[k]});counts={};results={};renderTasks(d.tasks);B.success(b,'Saved');R.log('success','Settings saved. Rescan to see the new counts.')}).catch(function(err){B.error(b,'Failed');R.log('error',err.message)})}
            });

            R.log('info','Loading the database overview.');
            lock(true);
            Promise.all([overview(true),loadTables()]).then(function(){return scan()}).then(function(){return reports()}).then(function(){R.hideProgress()}).catch(function(e){R.log('error',e.message);N.error(notice,'Could not load the overview',e.message)}).finally(function(){lock(false)});
        })();
        </script>
        <?php
    }
}
