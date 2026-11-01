<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
final class ProtectionTab {
    public static function render():void {
        if(!current_user_can('manage_options'))return;
        CoreUi::render_assets();$s=Settings::state();
        $body='<p>Protect Elementor Pro, Contact Form 7, Gravity Forms and WPForms on this site. Off by default. Existing plugin validation and spam checks remain active.</p>'
            .CoreUi::toggle('enabled',$s['enabled'],'Enable form protection on this site',['id'=>'hws-form-enabled'])
            .'<h3>Minimum fill time</h3><p>Submissions must wait this many seconds after their signed challenge is issued. Choose 1 to 30 seconds.</p><input id="hws-form-minimum" type="number" min="1" max="30" value="'.esc_attr((string)$s['min_fill_seconds']).'">'
            .'<h3>Blocked attempt retention</h3><p>Keep only time, form, IP and reason for 1 to 90 days, with a maximum of 5,000 attempts. Submission contents are never logged.</p><input id="hws-form-retention" type="number" min="1" max="90" value="'.esc_attr((string)$s['retention_days']).'">'
            .'<h3>Cloudflare Turnstile</h3><p>Use when spam continues. Create this domain in <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare Turnstile</a> and copy its site and secret keys.</p>'
            .CoreUi::toggle('turnstile_enabled',$s['turnstile_enabled'],'Require Turnstile on protected forms',['id'=>'hws-form-turnstile'])
            .'<p><label for="hws-form-site-key">Site key</label></p><input class="regular-text" id="hws-form-site-key" value="'.esc_attr($s['site_key']).'">'
            .'<p>Secret key: '.($s['secret_present']?'saved (hidden)':'not saved').'</p><button type="button" class="button" id="hws-form-change-secret">Change secret key</button><p id="hws-form-secret-row" hidden><label for="hws-form-secret">New secret key</label><br><input type="password" class="regular-text" id="hws-form-secret" value="" autocomplete="new-password"></p>'
            .'<p><button type="button" class="button button-primary" id="hws-form-save">Save form protection</button></p><p id="hws-form-status" role="status"></p>';
        echo '<div class="hpc-ui" id="hws-form-protection-settings">'.CoreUi::card(['title'=>'Form protection','body_html'=>$body]).'</div>';
        ?>
        <script>
        (() => {
            const root=document.getElementById('hws-form-protection-settings');
            root.querySelector('#hws-form-change-secret').addEventListener('click',()=>{root.querySelector('#hws-form-secret-row').hidden=false;root.querySelector('#hws-form-secret').focus();});
            root.querySelector('#hws-form-save').addEventListener('click',async()=>{
                const data=new FormData(),status=root.querySelector('#hws-form-status');
                data.set('action','hws_base_tools_form_protection_save');
                data.set('nonce',<?php echo wp_json_encode(wp_create_nonce('hws_base_tools_form_protection')); ?>);
                for(const [key,id]of Object.entries({enabled:'enabled',turnstile_enabled:'turnstile'}))data.set(key,root.querySelector('#hws-form-'+id).checked?'1':'0');
                for(const [key,id]of Object.entries({min_fill_seconds:'minimum',retention_days:'retention',site_key:'site-key',secret_key:'secret'}))data.set(key,root.querySelector('#hws-form-'+id).value);
                status.textContent='Saving...';
                try {const r=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',credentials:'same-origin',body:data});const j=await r.json();status.textContent=j.data?.message||'Settings were not saved.';if(j.success)root.querySelector('#hws-form-secret').value='';}
                catch{status.textContent='Settings were not saved. Try again when the connection returns.';}
            });
        })();
        </script>
        <?php
    }
}
