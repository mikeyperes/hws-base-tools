<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class ArticleMediaCleanupRenderer {
    private ArticleMediaCleanupConfig $config;

    public function __construct( ArticleMediaCleanupConfig|array $config ) {
        $this->config = is_array( $config ) ? new ArticleMediaCleanupConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id  = $this->config->root_id();
        $defaults = $this->config->default_criteria();
        $nonce    = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-cleanup-module hpc-article-media-cleanup" data-hpc-article-media-cleanup data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-scan-action="<?php echo esc_attr( $this->config->scan_action() ); ?>" data-delete-action="<?php echo esc_attr( $this->config->delete_action() ); ?>" data-empty-message="<?php echo esc_attr( (string) $this->config->get( 'empty_message' ) ); ?>">
            <?php $this->styles( $root_id ); ?>
            <div class="hpc-hero">
                <div>
                    <h2><?php echo esc_html( (string) $this->config->get( 'title' ) ); ?></h2>
                    <p><?php echo esc_html( (string) $this->config->get( 'description' ) ); ?></p>
                </div>
                <div class="hpc-cleanup-count"><?php echo CoreUi::pill( 'Articles: 0', 'dark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </div>
            <?php echo CoreUi::collapsible( [ 'title' => 'Article Filters', 'open' => true, 'persist_key' => $root_id . '-filters', 'body_html' => $this->filters_html( $defaults ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="hpc-cleanup-table-wrap">
                <table class="hpc-cleanup-table">
                    <thead><tr><th><label><input type="checkbox" data-article-select-all> Select</label></th><th>Article</th><th>Status</th><th>Published</th><th>Author</th><th>Associated Media</th><th>Edit</th><th>Row Action</th></tr></thead>
                    <tbody data-article-results><tr><td colspan="8" class="hpc-cleanup-muted">Loading article cleanup candidates...</td></tr></tbody>
                </table>
            </div>
            <?php $this->log_html(); ?>
            <?php $this->script( $root_id ); ?>
        </div>
        <?php
    }

    private function filters_html( array $defaults ): string {
        ob_start();
        ?>
        <form class="hpc-article-filters" data-article-filters>
            <label class="hpc-field"><span>Content Type</span><?php echo $this->select_html( 'post_type', $this->config->post_types(), (string) $defaults['post_type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
            <label class="hpc-field"><span>Status</span><?php echo $this->select_html( 'status', $this->config->statuses(), (string) $defaults['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
            <label class="hpc-field"><span>Keep Most Recent</span><input type="number" min="0" max="5000" name="keep_recent" value="<?php echo esc_attr( (string) $defaults['keep_recent'] ); ?>"><div class="hpc-small">Newest matching posts skipped from cleanup.</div></label>
            <label class="hpc-field hpc-article-filter-wide"><span>Search Title / Content</span><input type="search" name="search" value="" placeholder="Optional keyword"></label>
            <label class="hpc-field"><span>Limit</span><input type="number" min="1" max="<?php echo esc_attr( (string) $this->config->max_limit() ); ?>" name="limit" value="<?php echo esc_attr( (string) $defaults['limit'] ); ?>"></label>
            <label class="hpc-field hpc-article-toggle"><span>Media Cleanup</span><?php echo CoreUi::toggle( 'delete_media', false, 'Delete associated media', [ 'tooltip' => 'When enabled, featured image plus inline/gallery image attachments detected in the post content are deleted after the post is deleted.' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div class="hpc-small">Off by default. Leave off to delete posts only.</div></label>
            <div class="hpc-actions hpc-article-actions">
                <?php echo DynamicButton::render( [ 'label' => 'Scan Articles', 'working_label' => 'Scanning...', 'success_label' => 'Scanned', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-article-scan' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo DynamicButton::render( [ 'label' => 'Delete Selected', 'working_label' => 'Deleting...', 'success_label' => 'Deleted', 'class' => 'hpc-button danger', 'attrs' => [ 'data-article-delete-selected' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private function select_html( string $name, array $options, string $selected ): string {
        $html = '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $value => $label ) {
            $html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $selected, (string) $value, false ) . '>' . esc_html( (string) $label ) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function styles( string $root_id ): void {
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{margin-top:18px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{display:grid;gap:12px;grid-template-columns:repeat(6,minmax(0,1fr));margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide{grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle{grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{align-self:end;grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table-wrap{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table{border-collapse:collapse;min-width:1180px;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table td{border-bottom:1px solid var(--hpc-line);padding:12px;text-align:left;vertical-align:middle}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th{background:#f8fafc;color:#314056;font-size:12px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-title{font-weight:800;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-slug{color:var(--hpc-muted);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px;margin-top:4px;word-break:break-all}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-muted{color:var(--hpc-muted);font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-working{opacity:.58}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-list{display:grid;gap:5px;max-width:280px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-item{background:#f8fafc;border:1px solid #e0e6ef;border-radius:6px;font-size:12px;padding:6px 8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log{border-radius:8px;overflow:hidden;border:1px solid #263241;background:#0f1720;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;margin-top:16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-head{align-items:center;background:#111c2a;border-bottom:1px solid #263241;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-title{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;font-weight:800;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-pill{background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#b9c7d8;font-size:12px;padding:4px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-body{max-height:360px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:92px 82px 1fr;padding:12px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level{border-radius:5px;font-size:11px;font-weight:900;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-message{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px;font-weight:650;margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-context{color:#9fb1c6;font-size:12px;white-space:pre-wrap}
            @media(max-width:1100px){#<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{grid-template-columns:repeat(2,minmax(0,1fr))}#<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide,#<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle,#<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{grid-column:span 2}}
            @media(max-width:700px){#<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{grid-template-columns:1fr}#<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide,#<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle,#<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{grid-column:auto}#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private function log_html(): void {
        ?>
        <section class="hpc-cleanup-log">
            <div class="hpc-cleanup-log-head">
                <div><h3 class="hpc-cleanup-log-title">Article Cleanup Activity Log</h3><span class="hpc-cleanup-log-pill">Hexa Core Log Type 1</span></div>
                <button type="button" class="hpc-button secondary" data-article-clear-log>Clear</button>
            </div>
            <div class="hpc-cleanup-log-body" data-article-log-body></div>
        </section>
        <?php
    }

    private function script( string $root_id ): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( $root_id ); ?>'); if(!root||root.dataset.articleReady==='1')return; root.dataset.articleReady='1';
            var form=root.querySelector('[data-article-filters]'), tbody=root.querySelector('[data-article-results]'), countPill=root.querySelector('.hpc-cleanup-count .hpc-pill'), logBody=root.querySelector('[data-article-log-body]');
            function text(v){return v===null||v===undefined?'':String(v)} function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML} function now(){return new Date().toTimeString().slice(0,8)}
            function dynStart(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(b,l);else if(b)b.disabled=true} function dynOk(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(b,l||'Done');else if(b)b.disabled=false} function dynFail(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(b,l||'Failed');else if(b)b.disabled=false}
            function addLog(e){if(!logBody)return;e=e||{};var level=text(e.level||'info').toLowerCase(),ctx=e.context&&Object.keys(e.context).length?JSON.stringify(e.context,null,2):'',row=document.createElement('div');row.className='hpc-cleanup-log-row';row.innerHTML='<div class="hpc-cleanup-log-time">'+esc(e.time||now())+'</div><div><span class="hpc-cleanup-log-level '+esc(level)+'">'+esc(level)+'</span></div><div><div class="hpc-cleanup-log-message">'+esc(e.message||'')+'</div>'+(ctx?'<div class="hpc-cleanup-log-context">'+esc(ctx)+'</div>':'')+'</div>';logBody.appendChild(row);logBody.scrollTop=logBody.scrollHeight}
            function addLogs(logs){(logs||[]).forEach(addLog)} function setCount(n){if(countPill)countPill.textContent='Articles: '+n}
            function criteria(){var data=new FormData(form);return{post_type:data.get('post_type')||'',status:data.get('status')||'',keep_recent:data.get('keep_recent')||'0',search:data.get('search')||'',limit:data.get('limit')||'50'}}
            function deleteMediaEnabled(){var input=form?form.querySelector('input[name="delete_media"]'):null;return !!(input&&input.checked)}
            function post(action,payload){var body=new URLSearchParams();body.set('action',action);body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');Object.keys(payload||{}).forEach(function(k){body.set(k,payload[k])});return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json()}).then(function(p){if(!p||!p.success){var m=p&&p.data&&(p.data.message||p.data.error)?(p.data.message||p.data.error):'AJAX request failed.';throw new Error(m)}return p.data||{}})}
            function mediaHtml(row){var media=row.media||[];if(!media.length)return '<span class="hpc-cleanup-muted">No associated media</span>';return '<div class="hpc-media-list">'+media.map(function(item){return '<div class="hpc-media-item"><strong>#'+esc(item.id)+'</strong> '+esc(item.title)+'<div class="hpc-cleanup-muted">'+esc(item.source)+'</div></div>'}).join('')+'</div>'}
            function rowHtml(row){var edit=row.edit_url?'<a class="hpc-button secondary hpc-external" href="'+esc(row.edit_url)+'" target="_blank" rel="noopener noreferrer">Edit</a>':'<span class="hpc-cleanup-muted">No edit link</span>';return '<tr class="hpc-cleanup-row" data-post-id="'+esc(row.id)+'"><td><input type="checkbox" data-article-select value="'+esc(row.id)+'"></td><td><div class="hpc-cleanup-title">'+esc(row.title)+'</div><div class="hpc-cleanup-slug">'+esc(row.slug)+'</div></td><td>'+esc(row.status)+'</td><td>'+esc(row.published_label)+'</td><td>'+esc(row.author)+'</td><td>'+mediaHtml(row)+'</td><td>'+edit+'</td><td><button type="button" class="hpc-button danger" data-article-delete data-post-id="'+esc(row.id)+'">Delete Post</button></td></tr>'}
            function renderRows(rows){rows=rows||[];setCount(rows.length);var all=root.querySelector('[data-article-select-all]');if(all)all.checked=false;if(!tbody)return;if(!rows.length){tbody.innerHTML='<tr><td colspan="8" class="hpc-cleanup-muted">'+esc(root.dataset.emptyMessage||'No matching articles found.')+'</td></tr>';return}tbody.innerHTML=rows.map(rowHtml).join('')}
            function scan(button){dynStart(button,'Scanning...');addLog({level:'info',message:'Starting article cleanup scan.',context:criteria()});post(root.dataset.scanAction||'',criteria()).then(function(data){addLogs(data.log);renderRows(data.rows||[]);dynOk(button,'Scanned')}).catch(function(error){addLog({level:'error',message:error.message||'Scan failed.'});dynFail(button,'Failed')})}
            function removeRow(postId){var row=root.querySelector('.hpc-cleanup-row[data-post-id="'+postId+'"]');if(row)row.remove();var remaining=root.querySelectorAll('.hpc-cleanup-row').length;setCount(remaining);if(!remaining&&tbody)tbody.innerHTML='<tr><td colspan="8" class="hpc-cleanup-muted">'+esc(root.dataset.emptyMessage||'No matching articles found.')+'</td></tr>'}
            function deleteOne(postId,button){var row=root.querySelector('.hpc-cleanup-row[data-post-id="'+postId+'"]');if(row)row.classList.add('is-working');dynStart(button,'Deleting...');addLog({level:'warning',message:'Sending article delete AJAX request.',context:{post_id:postId,delete_media:deleteMediaEnabled()?'yes':'no'}});return post(root.dataset.deleteAction||'',{post_id:postId,delete_media:deleteMediaEnabled()?'1':'0'}).then(function(data){addLogs(data.log);removeRow(postId);dynOk(button,'Deleted');return data}).catch(function(error){if(row)row.classList.remove('is-working');addLog({level:'error',message:error.message||'Delete failed.',context:{post_id:postId}});dynFail(button,'Failed');throw error})}
            root.addEventListener('click',function(event){var scanButton=event.target.closest('[data-article-scan]');if(scanButton){event.preventDefault();scan(scanButton);return}var clear=event.target.closest('[data-article-clear-log]');if(clear){event.preventDefault();if(logBody)logBody.innerHTML='';addLog({level:'info',message:'Article cleanup activity log cleared.'});return}var selectAll=event.target.closest('[data-article-select-all]');if(selectAll){root.querySelectorAll('[data-article-select]').forEach(function(box){box.checked=selectAll.checked});return}var rowDelete=event.target.closest('[data-article-delete]');if(rowDelete){event.preventDefault();var id=rowDelete.getAttribute('data-post-id')||'';var msg='Permanently delete this post?';if(deleteMediaEnabled())msg+=' Associated featured/inline media will also be deleted.';if(!window.confirm(msg))return;deleteOne(id,rowDelete).catch(function(){}) ;return}var bulk=event.target.closest('[data-article-delete-selected]');if(bulk){event.preventDefault();var ids=Array.from(root.querySelectorAll('[data-article-select]:checked')).map(function(box){return box.value});if(!ids.length){addLog({level:'warning',message:'No articles selected for deletion.'});dynFail(bulk,'Select rows');return}var msg='Permanently delete '+ids.length+' selected post(s)?';if(deleteMediaEnabled())msg+=' Associated featured/inline media will also be deleted.';if(!window.confirm(msg))return;dynStart(bulk,'Deleting...');var chain=Promise.resolve();ids.forEach(function(id){chain=chain.then(function(){var b=root.querySelector('.hpc-cleanup-row[data-post-id="'+id+'"] [data-article-delete]')||bulk;return deleteOne(id,b).catch(function(){})})});chain.then(function(){dynOk(bulk,'Deleted')})}});
            if(form){form.addEventListener('submit',function(event){event.preventDefault();scan(root.querySelector('[data-article-scan]'))})}
            addLog({level:'info',message:'Article cleanup UI loaded. Auto-running article scan.'});scan(root.querySelector('[data-article-scan]'));
        })();
        </script>
        <?php
    }
}
