<?php

namespace HWS\BaseTools\DatabaseOptimization;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
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
        ?>
        <div id="<?php echo esc_attr( $root ); ?>" class="hpc-ui hws-dbo" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( DatabaseOptimizationModule::NONCE_ACTION ) ); ?>" data-nonce-field="nonce" data-action-prefix="<?php echo esc_attr( DatabaseOptimizationModule::ACTION ); ?>">
            <style>
                #<?php echo esc_attr( $root ); ?> .hws-dbo-lead{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px;max-width:880px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-bar{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-desc{color:var(--hpc-muted,#65758b);display:block;font-size:12px;margin-top:2px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-row-actions{display:flex;flex-wrap:wrap;gap:4px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-settings{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin:0 0 12px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-settings label{display:grid;font-size:12px;font-weight:700;gap:4px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-settings input{max-width:120px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-settings small{color:var(--hpc-muted,#65758b);font-weight:400}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-report{margin:0 0 16px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-report h4{font-size:13px;margin:0 0 8px}
                #<?php echo esc_attr( $root ); ?> .hws-dbo-check{text-align:center;width:34px}
            </style>
            <?php
            echo DynamicNotice::render( [ 'id' => $root . '-notice' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            echo CoreUi::collapsible( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'       => 'Database Optimization',
                    'open'        => true,
                    'persist_key' => $root . '-overview',
                    'meta_html'   => CoreUi::pill( 'Built in', 'green' ) . CoreUi::pill( 'Live', 'blue' ),
                    'body_html'   => self::overview_html(),
                ]
            );
            echo CoreUi::collapsible( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'       => 'Cleanup',
                    'open'        => true,
                    'persist_key' => $root . '-cleanup',
                    'meta_html'   => '<span class="hpc-pill" data-dbo-cleanup-pill>Not scanned</span>',
                    'body_html'   => self::cleanup_html(),
                ]
            );
            echo CoreUi::collapsible( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'       => 'Tables',
                    'open'        => true,
                    'persist_key' => $root . '-tables',
                    'meta_html'   => '<span class="hpc-pill" data-dbo-tables-pill>Loading</span>',
                    'body_html'   => self::tables_html(),
                ]
            );
            echo CoreUi::collapsible( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'       => 'Reports',
                    'open'        => false,
                    'persist_key' => $root . '-reports',
                    'meta_html'   => CoreUi::pill( 'Read only' ),
                    'body_html'   => self::reports_html(),
                ]
            );
            echo CoreUi::collapsible( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                [
                    'title'       => 'Cleanup Settings',
                    'open'        => false,
                    'persist_key' => $root . '-settings',
                    'body_html'   => self::settings_html(),
                ]
            );
            echo TaskRunner::log( [ 'title' => 'Live Log', 'open' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            self::script();
            ?>
        </div>
        <?php
    }

    private static function overview_html(): string {
        $button = static fn( string $label, string $working, string $attr, string $class = 'hpc-button' ): string => DynamicButton::render( [ 'label' => $label, 'working_label' => $working, 'class' => $class, 'attrs' => [ $attr => true ] ] );

        return '<p class="hws-dbo-lead">Removes old revisions, drafts, trash, spam and leftover rows, then checks, repairs and compacts this site\'s tables and converts old MyISAM tables to InnoDB. Every step runs live, one batch or one table at a time. No other optimization plugin is needed.</p>'
            . TaskRunner::stats(
                [
                    [ 'key' => 'size', 'label' => 'Database size' ],
                    [ 'key' => 'tables', 'label' => 'Tables' ],
                    [ 'key' => 'overhead', 'label' => 'Space to reclaim' ],
                    [ 'key' => 'found', 'label' => 'Items to clean', 'hint' => 'Selected items' ],
                    [ 'key' => 'myisam', 'label' => 'MyISAM tables' ],
                    [ 'key' => 'repair', 'label' => 'Tables to repair' ],
                    [ 'key' => 'autoload', 'label' => 'Loaded on every page', 'hint' => '-' ],
                ]
            )
            . '<div class="hws-dbo-bar">'
            . $button( 'Run Everything', 'Running...', 'data-dbo-run-all' )
            . $button( 'Scan Database', 'Scanning...', 'data-dbo-scan', 'hpc-button secondary' )
            . $button( 'Clean Selected', 'Cleaning...', 'data-dbo-clean', 'hpc-button secondary' )
            . $button( 'Optimize Tables', 'Optimizing...', 'data-dbo-optimize', 'hpc-button secondary' )
            . '<button type="button" class="hpc-button secondary" data-dbo-stop disabled>Stop</button>'
            . '</div>'
            . TaskRunner::progress();
    }

    private static function cleanup_html(): string {
        return '<p class="hws-dbo-lead">Items under "Review first" are not selected by default because they remove things someone may still want.</p>'
            . '<div class="hpc-runner-table-wrap"><table class="hpc-runner-table"><thead><tr>'
            . '<th class="hws-dbo-check"><input type="checkbox" data-dbo-check-all aria-label="Select all"></th><th>Item</th><th class="num">Found</th><th class="num">Removed</th><th>Status</th>'
            . '</tr></thead><tbody data-dbo-tasks><tr><td colspan="5" class="hpc-runner-muted">Loading...</td></tr></tbody></table></div>';
    }

    private static function tables_html(): string {
        return '<p class="hws-dbo-lead">Only tables that belong to this site are shown. Check finds damaged tables, Repair fixes them, Convert moves MyISAM tables to InnoDB, and Optimize gives wasted space back.</p>'
            . '<div class="hws-dbo-bar">'
            . DynamicButton::render( [ 'label' => 'Check All', 'working_label' => 'Checking...', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-dbo-bulk' => 'check' ] ] )
            . DynamicButton::render( [ 'label' => 'Repair Damaged', 'working_label' => 'Repairing...', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-dbo-bulk' => 'repair' ] ] )
            . DynamicButton::render( [ 'label' => 'Convert to InnoDB', 'working_label' => 'Converting...', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-dbo-bulk' => 'convert' ] ] )
            . DynamicButton::render( [ 'label' => 'Optimize With Overhead', 'working_label' => 'Optimizing...', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-dbo-bulk' => 'optimize' ] ] )
            . '</div>'
            . CoreUi::collection_filter( [ 'target_id' => self::ROOT . '-table-list', 'item_selector' => 'tr[data-dbo-table]', 'label' => 'Search tables', 'placeholder' => 'Search by name, engine or status (MyISAM, Needs repair, Can optimize)', 'item_label_singular' => 'table', 'empty_message' => 'No matching tables.' ] )
            . '<div class="hpc-runner-table-wrap"><table class="hpc-runner-table" id="' . self::ROOT . '-table-list" style="min-width:860px"><thead><tr>'
            . '<th>Table</th><th>Engine</th><th class="num">Rows</th><th class="num">Size</th><th class="num">Reclaimable</th><th>Status</th><th>Actions</th>'
            . '</tr></thead><tbody data-dbo-tables><tr><td colspan="7" class="hpc-runner-muted">Loading...</td></tr></tbody></table></div>';
    }

    private static function reports_html(): string {
        return '<p class="hws-dbo-lead">These are shown for review only. Nothing here is deleted automatically.</p>'
            . '<div class="hws-dbo-bar">' . DynamicButton::render( [ 'label' => 'Refresh Reports', 'working_label' => 'Loading...', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-dbo-reports' => true ] ] ) . '</div>'
            . '<div class="hws-dbo-report"><h4>Largest settings loaded on every page</h4><div class="hpc-runner-table-wrap"><table class="hpc-runner-table"><thead><tr><th>Setting</th><th class="num">Size</th></tr></thead><tbody data-dbo-autoload><tr><td colspan="2" class="hpc-runner-muted">Not loaded yet.</td></tr></tbody></table></div></div>'
            . '<div class="hws-dbo-report"><h4>Tables not created by WordPress</h4><div class="hpc-runner-table-wrap"><table class="hpc-runner-table"><thead><tr><th>Table</th><th>Likely plugin</th><th class="num">Rows</th><th class="num">Size</th></tr></thead><tbody data-dbo-extra><tr><td colspan="4" class="hpc-runner-muted">Not loaded yet.</td></tr></tbody></table></div></div>'
            . '<div class="hws-dbo-report"><h4>Media attached to deleted posts</h4><p class="hws-dbo-lead" data-dbo-media>Not loaded yet.</p></div>';
    }

    private static function settings_html(): string {
        $values = OptimizationSettings::get();
        $html   = '<form data-dbo-settings><div class="hws-dbo-settings">';
        foreach ( OptimizationSettings::fields() as $key => $field ) {
            $html .= '<label>' . esc_html( $field['label'] )
                . '<input type="number" class="small-text" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $values[ $key ] ) . '" min="' . esc_attr( (string) $field['min'] ) . '" max="' . esc_attr( (string) $field['max'] ) . '">'
                . '<small>' . esc_html( $field['help'] ) . '</small></label>';
        }

        return $html . '</div>' . DynamicButton::render( [ 'label' => 'Save Settings', 'working_label' => 'Saving...', 'success_label' => 'Saved', 'attrs' => [ 'data-dbo-save' => true ] ] ) . '</form>';
    }

    private static function script(): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( self::ROOT ); ?>');if(!root||root.dataset.ready)return;root.dataset.ready='1';
            var T=window.HexaWpCoreTaskRunner,R=T.create(root),A=root.dataset.actionPrefix,B=window.HexaWpCoreDynamicButton,N=window.HexaWpCoreDynamicNotice,esc=T.esc,num=T.number,bytes=T.bytes;
            var notice='#<?php echo esc_js( self::ROOT ); ?>-notice',tasks={},counts={},tables=[],stop=root.querySelector('[data-dbo-stop]');
            function q(s){return root.querySelector(s)}
            function stat(k,v,hint){var e=q('[data-hpc-stat="'+k+'"]');if(e)e.textContent=v;if(hint!==undefined){var h=q('[data-hpc-stat-hint="'+k+'"]');if(h)h.textContent=hint}}
            function selected(){return Array.prototype.map.call(root.querySelectorAll('[data-dbo-task]:checked'),function(c){return c.value})}
            function foundTotal(){var t=0;selected().forEach(function(id){t+=counts[id]||0});stat('found',num(t),selected().length+' items selected');var p=q('[data-dbo-cleanup-pill]');if(p)p.textContent=Object.keys(counts).length?num(t)+' to clean':'Not scanned'}
            function taskRow(id){return root.querySelector('tr[data-dbo-task-row="'+id+'"]')}
            function renderTasks(defs){var keep={};root.querySelectorAll('[data-dbo-task]').forEach(function(c){keep[c.value]=c.checked});tasks=defs;var html='',group='';Object.keys(defs).forEach(function(id){var t=defs[id],on=keep.hasOwnProperty(id)?keep[id]:t.default;if(t.group!==group){group=t.group;html+='<tr class="hpc-runner-group"><td colspan="5">'+esc(group)+'</td></tr>'}html+='<tr data-dbo-task-row="'+esc(id)+'"><td class="hws-dbo-check"><input type="checkbox" data-dbo-task value="'+esc(id)+'"'+(on?' checked':'')+' aria-label="'+esc(t.label)+'"></td><td><strong>'+esc(t.label)+'</strong><span class="hws-dbo-desc">'+esc(t.description)+'</span></td><td class="num" data-dbo-found>'+(counts.hasOwnProperty(id)?num(counts[id]):'-')+'</td><td class="num" data-dbo-removed>-</td><td data-hpc-runner-state>'+T.state('pending','Waiting')+'</td></tr>'});q('[data-dbo-tasks]').innerHTML=html;foundTotal()}
            function renderOverview(o){stat('size',bytes(o.size));stat('tables',num(o.tables));stat('overhead',bytes(o.overhead));stat('myisam',num(o.myisam));stat('repair',num(o.needs_repair));stat('autoload',bytes(o.autoload_size),num(o.autoload_count)+' settings')}
            function tableRow(t){var health=t.needs_repair?T.state('error','Needs repair'):T.state('pending',t.optimizable?'Can optimize':'OK');return '<td><span class="hpc-runner-mono">'+esc(t.name)+'</span>'+(t.core?'':' <span class="hpc-pill">plugin</span>')+'</td><td>'+esc(t.engine)+'</td><td class="num">'+num(t.rows)+'</td><td class="num">'+bytes(t.size)+'</td><td class="num" title="'+esc(t.optimize_note)+'">'+(t.optimizable?bytes(t.overhead):'-')+'</td><td data-hpc-runner-state>'+health+'</td><td><div class="hws-dbo-row-actions"><button type="button" class="hpc-button secondary" data-dbo-op="check">Check</button><button type="button" class="hpc-button secondary" data-dbo-op="repair">Repair</button>'+(t.convertible?'<button type="button" class="hpc-button secondary" data-dbo-op="convert">Convert</button>':'')+(t.rebuildable?'<button type="button" class="hpc-button secondary" data-dbo-op="optimize" title="'+esc(t.optimize_note)+'">Optimize</button>':'')+'</div></td>'}
            function renderTables(list){tables=list;q('[data-dbo-tables]').innerHTML=list.length?list.map(function(t){return '<tr data-dbo-table="'+esc(t.name)+'">'+tableRow(t)+'</tr>'}).join(''):'<tr><td colspan="7" class="hpc-runner-muted">No tables found.</td></tr>';var p=q('[data-dbo-tables-pill]');if(p)p.textContent=list.length+' tables'}
            function updateTable(t){for(var i=0;i<tables.length;i++){if(tables[i].name===t.name)tables[i]=t}var row=root.querySelector('tr[data-dbo-table="'+CSS.escape(t.name)+'"]');if(row)row.innerHTML=tableRow(t);return row}
            function overview(withTasks){return R.post(A+'overview').then(function(d){renderOverview(d.overview);if(withTasks)renderTasks(d.tasks);return d})}
            function loadTables(){return R.post(A+'tables').then(function(d){renderTables(d.tables||[])})}
            function lock(on){root.querySelectorAll('[data-dbo-run-all],[data-dbo-scan],[data-dbo-clean],[data-dbo-optimize],[data-dbo-bulk],[data-dbo-op]').forEach(function(b){b.disabled=on});if(stop)stop.disabled=!on;on?R.begin():R.end()}
            async function scan(){var ids=Object.keys(tasks),done=0;R.log('info','Scanning '+ids.length+' cleanup items.');var out=await R.queue(ids,async function(id){var row=taskRow(id);R.setRow(row,'running','Counting');R.progress(done,ids.length,'Scanning database',tasks[id].label);var d=await R.post(A+'count',{task:id});counts[id]=d.count;row.querySelector('[data-dbo-found]').textContent=num(d.count);R.setRow(row,d.count?'pending':'done',d.count?'Found':'Clean');done++;foundTotal()},{onError:function(id){R.setRow(taskRow(id),'error','Failed')}});if(out.cancelled){R.log('warning','Scan stopped after '+done+' of '+ids.length+' items.');R.progress(done,ids.length,'Scan stopped','');return}var total=0;Object.keys(counts).forEach(function(k){total+=counts[k]});R.log('success','Scan finished: '+num(total)+' items found across all cleanup items.');R.progress(1,1,'Scan finished',num(total)+' items found.')}
            async function clean(){var ids=selected().filter(function(id){return !counts.hasOwnProperty(id)||counts[id]>0});if(!ids.length){R.log('info','Nothing selected needs cleaning.');return}var step=0,all=0;await R.queue(ids,async function(id){var row=taskRow(id),removed=0,failed=0;R.setRow(row,'running','Cleaning');R.progress(step,ids.length,'Cleaning',tasks[id].label);var d=await R.loop(A+'clean',{task:id},{onStep:function(s){removed+=s.removed;failed+=s.failed;row.querySelector('[data-dbo-removed]').textContent=num(removed);R.progress(step,ids.length,'Cleaning '+tasks[id].label,num(removed)+' removed so far')}});if(d.cancelled){R.setRow(row,'skipped','Stopped');return}counts[id]=d.remaining||0;row.querySelector('[data-dbo-found]').textContent=num(counts[id]);all+=removed;R.setRow(row,counts[id]?'error':'done',counts[id]?num(counts[id])+' left':'Clean');R.log(failed?'warning':'success',tasks[id].label+': removed '+num(removed)+(failed?', '+num(failed)+' could not be removed':'')+'.');step++;foundTotal()},{onError:function(id){R.setRow(taskRow(id),'error','Failed')}});R.progress(1,1,'Cleanup finished',num(all)+' items removed.');await overview()}
            async function tableOps(op,names){var labels={check:'Checking',repair:'Repairing',convert:'Converting',optimize:'Optimizing'},done=0;if(!names.length){R.log('info','No tables need '+op+'.');return}await R.queue(names,async function(name){var row=root.querySelector('tr[data-dbo-table="'+CSS.escape(name)+'"]');R.setRow(row,'running',labels[op]);R.progress(done,names.length,labels[op]+' tables',name);var d=await R.post(A+'table',{table:name,operation:op});row=updateTable(d.table);R.setRow(row,d.status,d.status==='done'?(op==='check'?'Healthy':'Done'):d.status==='skipped'?'Skipped':'Problem');if(d.status!=='skipped'||op!=='check')R.log(d.status==='error'?'error':(d.status==='done'?'success':'info'),name+' '+op+': '+d.message);done++},{onError:function(name){R.setRow(root.querySelector('tr[data-dbo-table="'+CSS.escape(name)+'"]'),'error','Failed')}});R.progress(1,1,labels[op]+' finished',names.length+' tables processed.')}
            function names(filter){return tables.filter(filter).map(function(t){return t.name})}
            async function optimizeAll(){await tableOps('check',names(function(){return true}));if(R.cancelled())return;await tableOps('repair',names(function(t){return t.needs_repair}));if(R.cancelled())return;await tableOps('convert',names(function(t){return t.convertible}));if(R.cancelled())return;await tableOps('optimize',names(function(t){return t.optimizable}));await overview()}
            function reports(){return R.post(A+'reports').then(function(d){q('[data-dbo-autoload]').innerHTML=(d.autoload||[]).map(function(o){return '<tr><td class="hpc-runner-mono">'+esc(o.name)+'</td><td class="num">'+bytes(o.size)+'</td></tr>'}).join('')||'<tr><td colspan="2" class="hpc-runner-muted">None.</td></tr>';q('[data-dbo-extra]').innerHTML=(d.extra_tables||[]).map(function(t){return '<tr><td class="hpc-runner-mono">'+esc(t.name)+'</td><td>'+(t.owner?esc(t.owner)+(t.active?'':' (inactive)'):'<span class="hpc-runner-muted">No installed plugin matched</span>')+'</td><td class="num">'+num(t.rows)+'</td><td class="num">'+bytes(t.size)+'</td></tr>'}).join('')||'<tr><td colspan="4" class="hpc-runner-muted">None.</td></tr>';q('[data-dbo-media]').textContent=num(d.detached_media)+' media items point to a post that no longer exists. They may still be used on other pages, so they are not removed.'})}
            function guard(button,label,fn){if(R.busy)return;lock(true);B.start(button);fn().then(function(){B.success(button,R.cancelled()?'Stopped':label);if(R.cancelled())N.warning(notice,'Stopped','The run stopped after the current step.')}).catch(function(e){R.log('error',e.message);B.error(button,'Failed');N.error(notice,'Something went wrong',e.message)}).finally(function(){lock(false)})}
            root.addEventListener('change',function(e){if(e.target.matches('[data-dbo-check-all]')){root.querySelectorAll('[data-dbo-task]').forEach(function(c){c.checked=e.target.checked})}if(e.target.matches('[data-dbo-task],[data-dbo-check-all]'))foundTotal()});
            root.addEventListener('click',function(e){var b=e.target.closest('button');if(!b||!root.contains(b))return;
                if(b.matches('[data-dbo-stop]')){R.cancel();R.log('warning','Stopping after the current step.');stop.disabled=true;return}
                if(b.matches('[data-dbo-scan]'))return guard(b,'Scanned',scan);
                if(b.matches('[data-dbo-clean]'))return guard(b,'Cleaned',clean);
                if(b.matches('[data-dbo-optimize]'))return guard(b,'Optimized',optimizeAll);
                if(b.matches('[data-dbo-run-all]'))return guard(b,'Finished',async function(){await scan();if(!R.cancelled())await clean();if(!R.cancelled())await optimizeAll();await reports();N.success(notice,'Database optimized','Cleanup and table maintenance finished. See the live log for every step.')});
                if(b.matches('[data-dbo-bulk]')){var op=b.getAttribute('data-dbo-bulk');return guard(b,'Done',function(){var f={check:function(){return true},repair:function(t){return t.needs_repair},convert:function(t){return t.convertible},optimize:function(t){return t.optimizable}}[op];return tableOps(op,names(f)).then(overview)})}
                if(b.matches('[data-dbo-op]')){var name=b.closest('tr').getAttribute('data-dbo-table');if(R.busy)return;lock(true);tableOps(b.getAttribute('data-dbo-op'),[name]).then(overview).catch(function(err){R.log('error',err.message)}).finally(function(){lock(false)});return}
                if(b.matches('[data-dbo-reports]')){B.start(b);reports().then(function(){B.success(b,'Updated')}).catch(function(err){R.log('error',err.message);B.error(b,'Failed')});return}
                if(b.matches('[data-dbo-save]')){e.preventDefault();var form=b.closest('form'),data={};Array.prototype.forEach.call(form.elements,function(el){if(el.name)data[el.name]=el.value});B.start(b);R.post(A+'settings',data).then(function(d){Object.keys(d.settings).forEach(function(k){if(form.elements[k])form.elements[k].value=d.settings[k]});counts={};renderTasks(d.tasks);B.success(b,'Saved');R.log('success','Cleanup settings saved. Scan again to see the new counts.')}).catch(function(err){B.error(b,'Failed');R.log('error',err.message)})}
            });
            R.log('info','Loading database overview.');
            Promise.all([overview(true),loadTables()]).then(function(){R.log('success','Overview loaded. Counting cleanup items.');lock(true);return scan()}).then(function(){return reports()}).catch(function(e){R.log('error',e.message);N.error(notice,'Could not load the overview',e.message)}).finally(function(){lock(false)});
        })();
        </script>
        <?php
    }
}
