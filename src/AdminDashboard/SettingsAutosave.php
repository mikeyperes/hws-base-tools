<?php

namespace HWS\BaseTools\AdminDashboard;

use Hexa\PluginCore\WpAdminComponents\DynamicNotice;

/**
 * Saves a settings section as soon as a control changes and reports the result
 * through Core's DynamicNotice. Text fields save after typing pauses.
 *
 * PHP: echo SettingsAutosave::notice( 'my-section-notice' ); inside the section.
 * JS:  HWSSettingsAutosave.bind( root, { notice: '#my-section-notice', save: function(){ return promise; } } );
 *      save() returns a Promise resolving to { message, title? } or rejecting with an Error.
 *      Return false from validate() (after calling HWSSettingsAutosave.fail) to skip a save.
 */
final class SettingsAutosave {
    public static function notice( string $id ): string {
        self::render_assets();
        return DynamicNotice::render(
            [
                'id'          => $id,
                'tone'        => 'info',
                'hidden'      => true,
                'dismissible' => true,
            ]
        );
    }

    public static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        DynamicNotice::render_assets();
        ?>
        <style>
            .hws-settings-autosave .hpc-dynamic-notice{position:sticky;bottom:16px;z-index:20;margin:12px 0 0;box-shadow:0 4px 14px rgba(15,23,42,.12)}
        </style>
        <script>
        (function(){
            if(window.HWSSettingsAutosave)return;
            var N=function(){return window.HexaWpCoreDynamicNotice;};
            function post(action,nonce,fields){
                var body=new URLSearchParams();body.set('action',action);body.set('nonce',nonce);
                Object.keys(fields||{}).forEach(function(key){var value=fields[key];if(Array.isArray(value)){value.forEach(function(item){body.append(key+'[]',item);});}else{body.set(key,value==null?'':value);}});
                return fetch(window.ajaxurl||'/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                    .then(function(response){return response.json().catch(function(){throw new Error('The server returned an unreadable answer (HTTP '+response.status+').');});})
                    .then(function(payload){if(!payload||!payload.success){throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:(payload&&typeof payload.data==='string'?payload.data:'Save failed.'));}return payload.data||{};});
            }
            function bind(root,options){
                if(!root||root.dataset.hwsAutosave==='1')return;
                root.dataset.hwsAutosave='1';root.classList.add('hws-settings-autosave');
                var notice=options.notice,timer=null,running=false,again=false,hideTimer=null;
                function show(tone,title,message){clearTimeout(hideTimer);N()[tone](notice,title,message);if(tone==='success'){hideTimer=setTimeout(function(){N().hide(notice);},5000);}}
                function run(){
                    clearTimeout(timer);timer=null;
                    if(running){again=true;return;}
                    if(options.validate&&options.validate()===false)return;
                    running=true;show('info','Saving…','');
                    Promise.resolve().then(options.save)
                        .then(function(result){result=result||{};show('success',result.title||'Saved',result.message||'');})
                        .catch(function(error){show('error','Not saved',error&&error.message?error.message:'Save failed.');})
                        .then(function(){running=false;if(again){again=false;run();}});
                }
                function typed(target){return target.matches('input[type="text"],input[type="number"],input[type="search"],input[type="url"],input[type="email"],input:not([type]),textarea');}
                root.addEventListener('input',function(event){if(!typed(event.target)||(options.ignore&&event.target.matches(options.ignore)))return;clearTimeout(timer);timer=setTimeout(function(){timer=null;run();},options.delay||800);});
                root.addEventListener('change',function(event){if(!event.target.name||(options.ignore&&event.target.matches(options.ignore)))return;if(typed(event.target)&&!timer)return;run();});
            }
            window.HWSSettingsAutosave={
                bind:bind,
                post:post,
                fail:function(notice,message){N().error(notice,'Not saved',message);},
                done:function(notice,title,message){N().success(notice,title,message||'');}
            };
        })();
        </script>
        <?php
    }
}
