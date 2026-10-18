<?php

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Shared admin pieces for long jobs that run as a series of AJAX steps:
 * stat cards, a progress meter, row status badges and a live log, plus the
 * window.HexaWpCoreTaskRunner client that posts each step, runs queues one
 * item at a time, repeats batched steps until they report done, and can be
 * cancelled between steps.
 */
final class TaskRunner {
    private const STATES = [ 'pending', 'running', 'done', 'error', 'skipped' ];

    /**
     * @param array<int,array{key:string,label:string,value?:string,hint?:string}> $cards
     */
    public static function stats( array $cards ): string {
        self::render_assets();
        $html = '';
        foreach ( $cards as $card ) {
            $key   = sanitize_key( (string) ( $card['key'] ?? '' ) );
            $html .= '<div class="hpc-runner-stat">'
                . '<strong>' . esc_html( (string) ( $card['label'] ?? '' ) ) . '</strong>'
                . '<span data-hpc-stat="' . esc_attr( $key ) . '">' . esc_html( (string) ( $card['value'] ?? '-' ) ) . '</span>'
                . ( isset( $card['hint'] ) ? '<em data-hpc-stat-hint="' . esc_attr( $key ) . '">' . esc_html( (string) $card['hint'] ) . '</em>' : '' )
                . '</div>';
        }

        return '<div class="hpc-runner-stats">' . $html . '</div>';
    }

    /**
     * @param array{title?:string,text?:string} $args
     */
    public static function progress( array $args = [] ): string {
        self::render_assets();

        return '<div class="hpc-runner-progress" data-hpc-runner-progress hidden>'
            . '<div class="hpc-runner-progress-copy"><strong data-hpc-runner-progress-title>' . esc_html( (string) ( $args['title'] ?? 'Preparing' ) ) . '</strong>'
            . '<span data-hpc-runner-progress-text>' . esc_html( (string) ( $args['text'] ?? '' ) ) . '</span></div>'
            . '<div class="hpc-runner-meter"><span data-hpc-runner-progress-bar></span></div>'
            . '</div>';
    }

    /**
     * @param array{title?:string,open?:bool} $args
     */
    public static function log( array $args = [] ): string {
        self::render_assets();

        return '<details class="hpc-runner-log"' . ( ! empty( $args['open'] ) ? ' open' : '' ) . '>'
            . '<summary><span>' . esc_html( (string) ( $args['title'] ?? 'Live Log' ) ) . '</span>'
            . '<button type="button" class="hpc-button secondary" data-hpc-runner-clear-log>Clear</button></summary>'
            . '<div class="hpc-runner-log-body" data-hpc-runner-log></div>'
            . '</details>';
    }

    public static function state( string $status, string $label = '' ): string {
        self::render_assets();
        $status = in_array( $status, self::STATES, true ) ? $status : 'pending';
        $icons  = [ 'pending' => '-', 'running' => '', 'done' => "\u{2713}", 'error' => "\u{00d7}", 'skipped' => "\u{2013}" ];

        return '<span class="hpc-runner-state"><span class="hpc-runner-state-icon ' . esc_attr( $status ) . '">' . esc_html( $icons[ $status ] ) . '</span><span>' . esc_html( '' !== $label ? $label : ucfirst( $status ) ) . '</span></span>';
    }

    public static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        CoreUi::render_assets();
        DynamicButton::render_assets();
        ?>
        <style>
            .hpc-runner-stats{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin:0 0 14px}
            .hpc-runner-stat{background:#fbfcfe;border:1px solid var(--hpc-line,#dde3ec);border-radius:8px;display:grid;gap:4px;padding:12px}
            .hpc-runner-stat strong{color:#314056;font-size:11px;letter-spacing:.02em;text-transform:uppercase}
            .hpc-runner-stat span{color:#0f1720;font-size:20px;font-weight:800;line-height:1.2}
            .hpc-runner-stat em{color:var(--hpc-muted,#65758b);font-size:12px;font-style:normal}
            .hpc-runner-progress{background:#f8fbff;border:1px solid #cfe0ff;border-radius:8px;display:grid;gap:10px;margin:0 0 14px;padding:12px}
            .hpc-runner-progress[hidden]{display:none}
            .hpc-runner-progress-copy strong{display:block;font-size:13px;margin:0 0 4px}
            .hpc-runner-progress-copy span{color:#3f4d63;font-size:12px}
            .hpc-runner-meter{background:#d9e5ff;border-radius:999px;height:10px;overflow:hidden}
            .hpc-runner-meter span{background:var(--hpc-blue,#3157d5);display:block;height:100%;transition:width .2s;width:0}
            .hpc-runner-state{align-items:center;display:inline-flex;font-weight:800;gap:7px;white-space:nowrap}
            .hpc-runner-state-icon{align-items:center;border-radius:999px;display:inline-flex;font-size:12px;height:22px;justify-content:center;width:22px}
            .hpc-runner-state-icon.pending,.hpc-runner-state-icon.skipped{background:#eef2f7;color:#65758b}
            .hpc-runner-state-icon.running{animation:hpc-runner-spin .8s linear infinite;border:3px solid #3157d5;border-right-color:transparent;box-sizing:border-box}
            .hpc-runner-state-icon.done{background:#eaf8ef;color:#16803c}
            .hpc-runner-state-icon.error{background:#fff0f2;color:#b42336}
            .hpc-runner-table-wrap{border:1px solid var(--hpc-line,#dde3ec);border-radius:8px;max-width:100%;overflow-x:auto}
            .hpc-runner-table{border-collapse:collapse;min-width:640px;width:100%}
            .hpc-runner-table th,.hpc-runner-table td{border-top:1px solid var(--hpc-line,#dde3ec);font-size:12px;line-height:1.45;padding:9px 10px;text-align:left;vertical-align:middle}
            .hpc-runner-table thead th{background:#f8fafc;border-top:0;color:#314056;font-size:11px;font-weight:800;text-transform:uppercase;white-space:nowrap}
            .hpc-runner-table td.num,.hpc-runner-table th.num{font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
            .hpc-runner-table tr.hpc-runner-group td{background:#f3f6fa;color:#314056;font-size:11px;font-weight:800;text-transform:uppercase}
            .hpc-runner-table .hpc-runner-muted{color:var(--hpc-muted,#65758b)}
            .hpc-runner-table .hpc-runner-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;word-break:break-all}
            .hpc-runner-table .hpc-button{font-size:11px;padding:6px 8px}
            tr.hpc-runner-row-running td{background:#f8fbff}
            tr.hpc-runner-row-done td{background:#fbfffc}
            tr.hpc-runner-row-error td{background:#fff7f8}
            .hpc-runner-log{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;margin-top:14px;overflow:hidden}
            .hpc-runner-log summary{align-items:center;background:#111c2a;cursor:pointer;display:flex;font-size:13px;font-weight:800;justify-content:space-between;list-style:none;padding:10px 14px}
            .hpc-runner-log summary::-webkit-details-marker{display:none}
            .hpc-runner-log-body{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;max-height:300px;overflow:auto}
            .hpc-runner-log-row{border-top:1px solid #263241;display:grid;gap:10px;grid-template-columns:72px 74px minmax(0,1fr);padding:8px 14px}
            .hpc-runner-log-time{color:#8ca1b8}
            .hpc-runner-log-level{border-radius:5px;font-size:11px;font-weight:900;padding:3px 7px;text-align:center;text-transform:uppercase}
            .hpc-runner-log-level.info{background:#16324f;color:#9bd0ff}
            .hpc-runner-log-level.success{background:#14391f;color:#9cf0b0}
            .hpc-runner-log-level.warning{background:#493813;color:#ffd37a}
            .hpc-runner-log-level.error{background:#4c1720;color:#ff9cac}
            .hpc-runner-log-message{white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-runner-spin{to{transform:rotate(360deg)}}
            @media(max-width:782px){.hpc-runner-log-row{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            if(window.HexaWpCoreTaskRunner)return;
            function text(v){return v===null||v===undefined?'':String(v)}
            function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML}
            function now(){return new Date().toTimeString().slice(0,8)}
            function bytes(n){n=Number(n)||0;var u=['B','KB','MB','GB','TB'],i=0;while(n>=1024&&i<u.length-1){n/=1024;i++}return (i?n.toFixed(1):n)+' '+u[i]}
            function number(n){return (Number(n)||0).toLocaleString()}
            var icons={pending:'-',running:'',done:'✓',error:'×',skipped:'–'};
            function state(status,label){status=icons.hasOwnProperty(status)?status:'pending';return '<span class="hpc-runner-state"><span class="hpc-runner-state-icon '+status+'">'+esc(icons[status])+'</span><span>'+esc(label||status.charAt(0).toUpperCase()+status.slice(1))+'</span></span>'}
            function append(body,key,value){if(Array.isArray(value)){value.forEach(function(v){body.append(key+'[]',v)});return}if(value&&typeof value==='object'){Object.keys(value).forEach(function(k){append(body,key+'['+k+']',value[k])});return}body.append(key,value===undefined||value===null?'':value)}
            function create(root,opts){
                root=typeof root==='string'?document.querySelector(root):root;opts=opts||{};
                var ds=root?root.dataset:{},cancelled=false;
                var ui={
                    progress:root.querySelector('[data-hpc-runner-progress]'),
                    title:root.querySelector('[data-hpc-runner-progress-title]'),
                    text:root.querySelector('[data-hpc-runner-progress-text]'),
                    bar:root.querySelector('[data-hpc-runner-progress-bar]'),
                    log:root.querySelector('[data-hpc-runner-log]')
                };
                var runner={
                    busy:false,
                    esc:esc,bytes:bytes,number:number,state:state,
                    post:function(action,payload){
                        var body=new URLSearchParams();body.set('action',action||'');body.set(opts.nonceField||ds.nonceField||'nonce',opts.nonce||ds.nonce||'');
                        Object.keys(payload||{}).forEach(function(k){append(body,k,payload[k])});
                        return fetch(opts.ajaxUrl||ds.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                            .then(function(r){return r.text().then(function(t){var p;try{p=JSON.parse(t)}catch(e){throw new Error('Server returned an invalid response (HTTP '+r.status+').')}return p})})
                            .then(function(p){if(!p||!p.success){var d=p&&p.data||{};throw new Error(d.message||d.error||'Request failed.')}return p.data||{}});
                    },
                    progress:function(done,total,title,msg){if(!ui.progress)return;ui.progress.hidden=false;if(ui.title)ui.title.textContent=title||'';if(ui.text)ui.text.textContent=msg||'';if(ui.bar)ui.bar.style.width=(total?Math.min(100,Math.round(done/total*100)):0)+'%'},
                    hideProgress:function(){if(ui.progress)ui.progress.hidden=true},
                    log:function(level,message,time){if(!ui.log)return;level=text(level||'info').toLowerCase();var row=document.createElement('div');row.className='hpc-runner-log-row';row.innerHTML='<div class="hpc-runner-log-time">'+esc(time||now())+'</div><div><span class="hpc-runner-log-level '+esc(level)+'">'+esc(level)+'</span></div><div class="hpc-runner-log-message">'+esc(message)+'</div>';ui.log.appendChild(row);ui.log.scrollTop=ui.log.scrollHeight},
                    logs:function(entries){(entries||[]).forEach(function(e){runner.log(e.level,e.message,e.time)})},
                    clearLog:function(){if(ui.log)ui.log.innerHTML=''},
                    setRow:function(row,status,label,cell){if(!row)return;['pending','running','done','error','skipped'].forEach(function(s){row.classList.remove('hpc-runner-row-'+s)});row.classList.add('hpc-runner-row-'+status);var c=row.querySelector(cell||'[data-hpc-runner-state]');if(c)c.innerHTML=state(status,label)},
                    cancel:function(){cancelled=true},
                    cancelled:function(){return cancelled},
                    begin:function(){cancelled=false;runner.busy=true},
                    end:function(){runner.busy=false},
                    /* Run worker(item,index) for each item, one at a time. Stops early after cancel(). */
                    queue:async function(items,worker,hooks){hooks=hooks||{};var out={done:0,failed:0,cancelled:false};for(var i=0;i<items.length;i++){if(cancelled){out.cancelled=true;break}try{await worker(items[i],i);out.done++}catch(e){out.failed++;runner.log('error',e.message||'Step failed.');if(hooks.onError)hooks.onError(items[i],e)}if(hooks.onProgress)hooks.onProgress(i+1,items.length)}return out},
                    /* Repeat one batched action until the server returns done:true. */
                    loop:async function(action,payload,hooks){hooks=hooks||{};var max=hooks.maxSteps||500,data={};for(var i=0;i<max;i++){if(cancelled){data.cancelled=true;return data}data=await runner.post(action,payload);if(hooks.onStep)hooks.onStep(data,i);if(data.done)return data}throw new Error('Stopped after '+max+' batches.')}
                };
                if(root){root.addEventListener('click',function(e){if(e.target.closest('[data-hpc-runner-clear-log]')){e.preventDefault();runner.clearLog()}})}
                return runner;
            }
            window.HexaWpCoreTaskRunner={create:create,esc:esc,bytes:bytes,number:number,state:state};
        })();
        </script>
        <?php
    }
}
