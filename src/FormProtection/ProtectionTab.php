<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
final class ProtectionTab {
    public static function render():void {
        if(!current_user_can('manage_options'))return;
        CoreUi::render_assets();$s=Settings::state();
        $checks=['honeypots_enabled'=>['Hidden trap fields','Adds two invisible fields to every form. People never see them; bots fill them in and are rejected.'],'too_fast_enabled'=>['Too-fast check','Rejects a form sent faster than a person can type it.'],'header_check_enabled'=>['Header check','Rejects a form sent without the browser details a real visitor\'s browser always sends.']];
        $keysReady=['none'=>true,'turnstile'=>$s['site_key']!==''&&$s['secret_present'],'recaptcha'=>$s['recaptcha_site_key']!==''&&$s['recaptcha_secret_present']];
        $bot=['none'=>'Off','turnstile'=>'Cloudflare Turnstile','recaptcha'=>'reCAPTCHA v3 (invisible)'];
        $row=fn(bool $ok,string $label,string $note='')=>'<li style="display:flex;gap:8px;align-items:baseline;margin:4px 0">'.($ok?'✅':'❌').' <span>'.esc_html($label).'</span>'.($note!==''?' <span style="color:#6b7280">'.esc_html($note).'</span>':'').'</li>';
        $summary='<p style="margin:0 0 8px">'.($s['enabled']?CoreUi::pill('Protection is on','success'):CoreUi::pill('Protection is off','danger')).'</p><ul style="margin:0;padding:0;list-style:none">';
        foreach($checks as $key=>[$label])$summary.=$row($s['enabled']&&$s[$key],$label);
        $summary.=$row($s['enabled']&&$s['bot_check']!=='none'&&$keysReady[$s['bot_check']],'Bot check: '.$bot[$s['bot_check']],$s['bot_check']!=='none'&&!$keysReady[$s['bot_check']]?'(keys missing)':'').'</ul>';
        $summary.='<p style="color:#6b7280;margin-top:10px">Each check rejects only the one suspicious submission (Elementor Pro, Contact Form 7, Gravity Forms, WPForms). Nobody is banned and pages are never blocked.</p>';
        $main='<div style="margin:16px 0">'.CoreUi::toggle('enabled',$s['enabled'],'Form protection',['id'=>'hws-form-enabled']).'<p style="color:#6b7280;margin:4px 0 0">The master switch. While off, none of the checks below run.</p></div>';
        $list='';
        foreach($checks as $key=>[$label,$help])$list.=CoreUi::subcard(['body_html'=>CoreUi::toggle($key,$s[$key],$label,['id'=>'hws-form-'.$key]).'<p style="color:#6b7280">'.esc_html($help).'</p>']);
        $botHtml='<p style="color:#6b7280">Use one at a time. Turnstile is the default; switch to reCAPTCHA only if Turnstile fails on this site.</p><input type="hidden" id="hws-form-bot_check" value="'.esc_attr($s['bot_check']).'"><div style="display:flex;flex-wrap:wrap;gap:12px;margin:8px 0 12px">';
        foreach(['turnstile'=>'Recommended','recaptcha'=>'Fallback only','none'=>'No bot check']as$value=>$tag){
            $botHtml.='<label style="flex:1 1 180px;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;cursor:pointer"><input type="radio" name="hws-form-bot" value="'.$value.'"'.($s['bot_check']===$value?' checked':'').'> <strong>'.esc_html($bot[$value]).'</strong><br><span style="color:#6b7280">'.esc_html($tag).'</span></label>';
        }
        $botHtml.='</div>';
        foreach(['turnstile'=>['site_key','secret_key','secret_present','Turnstile keys'],'recaptcha'=>['recaptcha_site_key','recaptcha_secret_key','recaptcha_secret_present','reCAPTCHA keys']]as$provider=>[$site,$secret,$present,$label]){
            $botHtml.='<div data-hws-provider="'.$provider.'"'.($s['bot_check']===$provider?'':' hidden').'>'.CoreUi::subcard(['title'=>$label,'body_html'=>'<p><label for="hws-form-'.$site.'">Site key</label><br><input class="regular-text" id="hws-form-'.$site.'" value="'.esc_attr($s[$site]).'"></p><p>Secret key: '.($s[$present]?CoreUi::pill('saved','success'):CoreUi::pill('not saved','warning')).'</p><p><label for="hws-form-'.$secret.'">New secret (leave blank to keep the saved one)</label><br><input type="password" class="regular-text" id="hws-form-'.$secret.'" value="" autocomplete="new-password"></p>'.($provider==='recaptcha'?'<p><label for="hws-form-recaptcha_score_threshold">Minimum score (0 to 1, higher is stricter)</label><br><input id="hws-form-recaptcha_score_threshold" type="number" min="0" max="1" step="0.05" value="'.esc_attr((string)$s['recaptcha_score_threshold']).'"></p>':'')]).'</div>';
        }
        $adv='<p><label for="hws-form-min_fill_seconds">Too-fast limit: minimum seconds to fill a form</label><br><input id="hws-form-min_fill_seconds" type="number" min="1" max="30" step="1" value="'.esc_attr((string)$s['min_fill_seconds']).'"></p><p><label for="hws-form-retention_days">Keep the rejected-submission log for (days, up to 5,000 records)</label><br><input id="hws-form-retention_days" type="number" min="1" max="90" step="1" value="'.esc_attr((string)$s['retention_days']).'"></p>';
        $body=CoreUi::subcard(['title'=>'Status','body_html'=>$summary]).$main.'<h3>Checks</h3>'.$list.'<h3>Bot check</h3>'.$botHtml.'<details class="hpc-inline-details" style="margin:12px 0"><summary>Advanced</summary><div class="hpc-inline-details-body">'.$adv.'</div></details>'.'<p><button type="button" class="button button-primary" id="hws-form-save">Save form protection</button> <span id="hws-form-status" role="status" style="margin-left:8px"></span></p>';
        echo '<div class="hpc-ui" id="hws-form-protection-settings">'.CoreUi::card(['title'=>'Form protection','body_html'=>$body]).'</div>';
        ?>
        <script>
        (() => {
            const root=document.getElementById('hws-form-protection-settings'),button=root.querySelector('#hws-form-save');
            root.querySelectorAll('input[name="hws-form-bot"]').forEach(r=>r.addEventListener('change',()=>{root.querySelector('#hws-form-bot_check').value=r.value;root.querySelectorAll('[data-hws-provider]').forEach(d=>d.hidden=d.dataset.hwsProvider!==r.value);}));
            button.addEventListener('click',async()=>{
                const data=new FormData(),status=root.querySelector('#hws-form-status');
                data.set('action','hws_base_tools_form_protection_save');
                data.set('nonce',<?php echo wp_json_encode(wp_create_nonce('hws_base_tools_form_protection')); ?>);
                for(const key of ['enabled','honeypots_enabled','too_fast_enabled','header_check_enabled'])data.set(key,root.querySelector('#hws-form-'+key).checked?'1':'0');
                for(const key of ['min_fill_seconds','retention_days','bot_check','site_key','secret_key','recaptcha_site_key','recaptcha_secret_key','recaptcha_score_threshold'])data.set(key,root.querySelector('#hws-form-'+key).value);
                status.textContent='Saving...';button.disabled=true;
                try {
                    const r=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',credentials:'same-origin',body:data}),j=await r.json();
                    status.textContent=j.data?.message||'Settings were not saved.';
                    if(j.success)setTimeout(()=>location.reload(),700);
                    if(j.success){root.querySelector('#hws-form-secret_key').value='';root.querySelector('#hws-form-recaptcha_secret_key').value='';}
                }catch{status.textContent='Settings were not saved. Try again when the connection returns.';}
                finally{button.disabled=false;}
            });
        })();
        </script>
        <?php
    }
}
