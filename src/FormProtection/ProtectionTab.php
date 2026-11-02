<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
final class ProtectionTab {
    public static function render():void {
        if(!current_user_can('manage_options'))return;
        CoreUi::render_assets();$s=Settings::state();
        $body='<p>Checks reject only the current submission through Elementor Pro, Contact Form 7, Gravity Forms or WPForms. No firewall or site-wide blocking.</p>';
        foreach(['enabled'=>'Enable form protection','honeypots_enabled'=>'Honeypots: two stable hidden fields per form','too_fast_enabled'=>'Too-fast: require a signed minimum fill time','header_check_enabled'=>'Header check: require same-site browser headers']as$key=>$label){
            $body.='<h3>'.esc_html($label).'</h3>'.CoreUi::toggle($key,$s[$key],$label,['id'=>'hws-form-'.$key]);
        }
        $body.='<p>Missing Accept-Language is recorded as a signal only. Header checks never block page views.</p>';
        foreach(['min_fill_seconds'=>['Minimum fill time (seconds)',1,30,1],'retention_days'=>['Log retention (days, maximum 5,000 records)',1,90,1],'recaptcha_score_threshold'=>['reCAPTCHA v3 minimum score',0,1,0.05]]as$key=>$field){
            $body.='<h3><label for="hws-form-'.esc_attr($key).'">'.esc_html($field[0]).'</label></h3><input id="hws-form-'.esc_attr($key).'" type="number" min="'.$field[1].'" max="'.$field[2].'" step="'.$field[3].'" value="'.esc_attr((string)$s[$key]).'">';
        }
        $body.='<h3>Bot check</h3><p>Choose one provider. Selecting it turns the other off. Turnstile is the default at first key setup.</p><select id="hws-form-bot_check" aria-label="Bot check">';
        foreach(['none'=>'None','turnstile'=>'Cloudflare Turnstile','recaptcha'=>'reCAPTCHA v3 (invisible)']as$value=>$label)$body.='<option value="'.$value.'"'.($s['bot_check']===$value?' selected':'').'>'.esc_html($label).'</option>';
        $body.='</select>';
        foreach(['turnstile'=>['site_key','secret_key','secret_present','Cloudflare Turnstile'],'recaptcha'=>['recaptcha_site_key','recaptcha_secret_key','recaptcha_secret_present','reCAPTCHA v3']]as$provider=>$fields){
            [$site,$secret,$present,$label]=$fields;
            $body.='<h3>'.esc_html($label).' keys</h3><p><label for="hws-form-'.$site.'">Site key</label></p><input class="regular-text" id="hws-form-'.$site.'" value="'.esc_attr($s[$site]).'">'
                .'<p>Secret key: '.($s[$present]?'saved (hidden)':'not saved').'</p><p><label for="hws-form-'.$secret.'">New secret (leave blank to keep saved key)</label></p><input type="password" class="regular-text" id="hws-form-'.$secret.'" value="" autocomplete="new-password">';
        }
        $body.='<p><button type="button" class="button button-primary" id="hws-form-save">Save form protection</button></p><p id="hws-form-status" role="status"></p>';
        echo '<div class="hpc-ui" id="hws-form-protection-settings">'.CoreUi::card(['title'=>'Form protection','body_html'=>$body]).'</div>';
        ?>
        <script>
        (() => {
            const root=document.getElementById('hws-form-protection-settings'),button=root.querySelector('#hws-form-save');
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
                    if(j.success){root.querySelector('#hws-form-secret_key').value='';root.querySelector('#hws-form-recaptcha_secret_key').value='';}
                }catch{status.textContent='Settings were not saved. Try again when the connection returns.';}
                finally{button.disabled=false;}
            });
        })();
        </script>
        <?php
    }
}
