<?php
namespace HWS\BaseTools\Newsletter;

use Hexa\PluginCore\FrontendForms\SubmissionStore;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use Hexa\PluginCore\WpAdminComponents\{CodeDisplay,CoreUi,DynamicButton,SlugField,TemplateSelectionControl};

/** The Newsletter tab: every action (create, open, save, remove, delete a subscriber) runs over AJAX. */
final class NewsletterAdmin {
    public const NONCE = 'hws_newsletter_admin';
    private const STYLES = [ 'none' => [ 'No plugin styling', 'Plain HTML that takes the theme\'s own look.' ], 'simple' => [ 'Simple', 'Square fields and a dark button.' ], 'rounded' => [ 'Rounded', 'Pill-shaped fields and button.' ] ];

    public static function register(): void {
        AjaxActionRegistry::create( [ 'action_prefix' => 'hws_base_tools', 'nonce_action' => self::NONCE, 'nonce_field' => 'nonce' ] )->register( [
            'newsletter_create'          => [ 'callback' => [ self::class, 'ajax_create' ] ],
            'newsletter_open'            => [ 'callback' => [ self::class, 'ajax_open' ] ],
            'newsletter_update'          => [ 'callback' => [ self::class, 'ajax_update' ] ],
            'newsletter_remove'          => [ 'callback' => [ self::class, 'ajax_remove' ] ],
            'newsletter_defaults'        => [ 'callback' => [ self::class, 'ajax_defaults' ] ],
            'newsletter_delete_subscriber' => [ 'callback' => [ self::class, 'ajax_delete_subscriber' ] ],
        ] );
    }

    private static function fields( AjaxRequest $r ): array {
        $out = [];
        foreach ( Newsletters::FIELDS as $key ) {
            $value = $r->raw( $key, '', 'post' );
            $out[ $key ] = is_string( $value ) ? wp_unslash( $value ) : '';
        }
        $out['style'] = 'inherit' === $out['style'] ? '' : $out['style'];
        return $out;
    }

    public static function ajax_create( AjaxRequest $r ) {
        $made = Newsletters::create( [ 'id' => (string) $r->raw( 'id', '', 'post' ) ] + self::fields( $r ) );
        if ( is_wp_error( $made ) ) {
            return $made;
        }
        return [ 'message' => 'Created "' . $made['name'] . '".', 'id' => $made['id'], 'reset' => true, 'list_html' => self::list_card( $made['id'] ), 'panel_html' => self::panel( $made['id'] ) ];
    }

    public static function ajax_open( AjaxRequest $r ) {
        $id = $r->key( 'id', '', 'post' );
        return [ 'id' => $id, 'list_html' => self::list_card( $id ), 'panel_html' => '' === $id ? '' : self::panel( $id, $r->int( 'before', 0, 'post' ) ) ];
    }

    public static function ajax_update( AjaxRequest $r ) {
        $saved = Newsletters::update( $r->key( 'id', '', 'post' ), self::fields( $r ) );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }
        return [ 'message' => 'Saved.', 'id' => $saved['id'], 'list_html' => self::list_card( $saved['id'] ) ];
    }

    public static function ajax_remove( AjaxRequest $r ) {
        $id = $r->key( 'id', '', 'post' );
        if ( ! Newsletters::remove( $id ) ) {
            return new \WP_Error( 'not_found', 'That newsletter is already gone.' );
        }
        return [ 'message' => 'Removed. Its subscribers are kept.', 'id' => '', 'list_html' => self::list_card( '' ), 'panel_html' => '' ];
    }

    public static function ajax_defaults( AjaxRequest $r ) {
        $email = strtolower( trim( (string) $r->raw( 'notification_email', '', 'post' ) ) );
        if ( '' !== $email && ! is_email( $email ) ) {
            return new \WP_Error( 'notification_email', 'Enter a valid notice email, or leave it blank to send no notices.' );
        }
        $style = $r->key( 'style', 'none', 'post' );
        $saved = [ 'enabled' => $r->bool( 'enabled', false, 'post' ), 'notification_email' => $email, 'style' => isset( self::STYLES[ $style ] ) ? $style : 'none' ];
        foreach ( [ 'success', 'duplicate', 'error' ] as $key ) {
            $value = wp_unslash( (string) $r->raw( $key, '', 'post' ) );
            if ( strlen( $value ) > NewsletterModule::MESSAGE_LIMIT ) {
                return new \WP_Error( $key, 'Messages must be text of at most 2000 characters.' );
            }
            $saved[ $key ] = $value;
        }
        update_option( NewsletterModule::OPTION, $saved, false );
        if ( $saved['enabled'] ) {
            NewsletterModule::store()->install();
            update_option( 'hws_base_tools_newsletter_schema', '2', false );
        }
        do_action( 'litespeed_purge_all' );
        return [ 'message' => 'Site defaults saved.', 'list_html' => self::list_card( $r->key( 'current', '', 'post' ) ) ];
    }

    public static function ajax_delete_subscriber( AjaxRequest $r ) {
        $list = $r->key( 'list', '', 'post' );
        try {
            $deleted = NewsletterModule::store()->delete( $list, $r->int( 'subscriber_id', 0, 'post' ) );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 'list', 'Invalid newsletter.' );
        }
        if ( ! $deleted ) {
            return new \WP_Error( 'not_found', 'That subscriber is already gone.' );
        }
        return [ 'message' => 'Subscriber deleted.', 'total' => NewsletterModule::store()->total( $list ) ];
    }

    private static function ready(): bool {
        return in_array( get_option( 'hws_base_tools_newsletter_schema' ), [ '1', '2' ], true );
    }

    /** @return array<string,int> */
    private static function counts(): array {
        $counts = [];
        if ( self::ready() ) {
            try {
                foreach ( NewsletterModule::store()->lists() as $row ) {
                    $counts[ $row['list'] ] = (int) $row['total'];
                }
            } catch ( \Throwable $e ) {
                return [];
            }
        }
        return $counts;
    }

    /** @return string[] IDs of saved newsletters plus lists that only exist in storage */
    private static function ids( array $counts ): array {
        $ids = array_unique( array_merge( array_keys( Newsletters::all() ), array_keys( $counts ) ) );
        sort( $ids );
        return $ids;
    }

    private static function tab_url( string $id = '' ): string {
        return admin_url( 'admin.php?page=hws-core-tools&tab=newsletter' . ( '' !== $id ? '&list=' . rawurlencode( $id ) : '' ) );
    }

    private static function status(): string {
        return ' <span class="hws-nl-status" aria-live="polite"></span>';
    }

    private static function button( string $label ): string {
        return '<button type="submit" class="hpc-button button button-primary">' . esc_html( $label ) . '</button>';
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $counts = self::counts();
        $ids    = self::ids( $counts );
        $list   = sanitize_key( $_GET['list'] ?? '' );
        $list   = in_array( $list, $ids, true ) ? $list : '';
        CoreUi::render_assets();
        DynamicButton::render_assets();
        echo '<div class="hpc-ui hws-nl-tab" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" data-tab-url="' . esc_url( self::tab_url() ) . '">';
        echo '<style>' . self::css() . '</style>';
        echo '<div id="hws-nl-list">' . self::list_card( $list, $counts ) . '</div>';
        echo '<div id="hws-nl-panel">' . ( '' !== $list ? self::panel( $list ) : '' ) . '</div>';
        echo self::defaults_form();
        echo '<script>' . self::js() . '</script></div>';
    }

    /** Runs a renderer and keeps anything Core components echo (their one-time assets) inside the returned HTML, so AJAX JSON stays clean. */
    private static function capture( callable $render ): string {
        ob_start();
        $html = (string) $render();
        return (string) ob_get_clean() . $html;
    }

    public static function list_card( string $current, ?array $counts = null ): string {
        return self::capture( static function () use ( $current, $counts ) { return self::build_list_card( $current, $counts ); } );
    }

    private static function build_list_card( string $current, ?array $counts ): string {
        $counts = $counts ?? self::counts();
        $ids    = self::ids( $counts );
        $s      = NewsletterModule::settings();
        $rows   = '';
        foreach ( $ids as $id ) {
            $n     = NewsletterModule::newsletter( $id );
            $rows .= '<tr class="' . ( $id === $current ? 'is-current' : '' ) . '"><td><strong><a href="' . esc_url( self::tab_url( $id ) ) . '" data-nl-open="' . esc_attr( $id ) . '">' . esc_html( $n['name'] ) . '</a></strong><br><code>' . esc_html( $id ) . '</code></td><td>' . (int) ( $counts[ $id ] ?? 0 ) . '</td><td>' . esc_html( '' !== $n['notification_email'] ? $n['notification_email'] : 'Off' ) . '</td><td><code>[hws_newsletter list="' . esc_html( $id ) . '"]</code></td><td><a class="button" href="' . esc_url( self::tab_url( $id ) ) . '" data-nl-open="' . esc_attr( $id ) . '">Manage</a></td></tr>';
        }
        $table  = $ids ? '<table class="widefat striped"><thead><tr><th>Newsletter</th><th>Subscribers</th><th>Notices go to</th><th>Shortcode</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<div class="hws-nl-empty">No newsletters yet. Create the first one below.</div>';
        $create = '<form class="hws-nl-create" data-nl-action="newsletter_create"><h4>Create a newsletter</h4>'
            . SlugField::render( [ 'id' => 'hws-nl-new', 'name_field' => 'name', 'slug_field' => 'id', 'name_label' => 'Name', 'slug_label' => 'ID', 'name_placeholder' => 'Weekly digest', 'help' => 'Fills in as you type the name. Lowercase letters, numbers and dashes; it cannot change later.', 'preview' => '[hws_newsletter list="{slug}"]' ] )
            . '<p>' . self::button( 'Create newsletter' ) . self::status() . '</p></form>';
        $stats  = '<div class="hws-nl-stats"><div class="hws-nl-stat"><b>' . ( $s['enabled'] ? 'On' : 'Off' ) . '</b><span>Collecting</span></div><div class="hws-nl-stat"><b>' . count( $ids ) . '</b><span>Newsletters</span></div><div class="hws-nl-stat"><b>' . array_sum( $counts ) . '</b><span>Signups</span></div></div>';

        return CoreUi::card( [ 'title' => 'Newsletters', 'body_html' => '<p>Each newsletter is its own signup list with its own form, notice email, design and messages. Signups are stored on this site; no marketing emails are sent.</p>' . $stats . $table . $create ] );
    }

    public static function panel( string $id, int $before = 0 ): string {
        return self::capture( static function () use ( $id, $before ) { return self::build_panel( $id, $before ); } );
    }

    private static function build_panel( string $id, int $before ): string {
        $own  = Newsletters::get( $id ) ?? [];
        $n    = NewsletterModule::newsletter( $id );
        $s    = NewsletterModule::settings();
        $html = '<h2 class="hws-nl-section">' . esc_html( $n['name'] ) . ' <a href="#" class="hws-nl-close" data-nl-open="">Close</a></h2>';
        if ( self::ready() ) {
            $subs  = NewsletterModule::store()->recent( $id, 21, $before );
            $older = count( $subs ) > 20;
            $subs  = array_slice( $subs, 0, 20 );
            $body  = '<div class="hws-nl-bar"><strong><span data-nl-total>' . NewsletterModule::store()->total( $id ) . '</span> subscribers</strong><a class="hpc-button secondary button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hws_base_tools_newsletter_export&list=' . $id ), 'hws_newsletter_export' ) ) . '">Export CSV</a></div>';
            if ( ! $subs ) {
                $body .= '<div class="hws-nl-empty">No signups on this newsletter yet.</div>';
            } else {
                $body .= '<table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Signed up (EST)</th><th>IP</th><th>Source</th><th></th></tr></thead><tbody>';
                foreach ( $subs as $row ) {
                    $body .= '<tr><td><strong>' . esc_html( $row['email'] ) . '</strong></td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( SubmissionStore::est( $row['created_at'] ) ) . '</td><td>' . esc_html( $row['ip'] ) . '</td><td>' . esc_html( $row['source'] ) . '</td><td><button type="button" class="button-link button-link-delete" data-nl-delete-subscriber="' . (int) $row['id'] . '" data-list="' . esc_attr( $id ) . '">Delete</button></td></tr>';
                }
                $body .= '</tbody></table>';
            }
            $nav = ( $before ? '<a href="#" data-nl-open="' . esc_attr( $id ) . '">&larr; Newest</a> ' : '' ) . ( $older ? '<a href="#" class="hws-newsletter-older" data-nl-open="' . esc_attr( $id ) . '" data-before="' . (int) end( $subs )['id'] . '">Older &rarr;</a>' : '' );
            $body .= '' !== $nav ? '<p>' . $nav . '</p>' : '';
        } else {
            $body = '<div class="hws-nl-empty">Turn collecting on in the site defaults below to start storing signups.</div>';
        }
        $html .= CoreUi::card( [ 'title' => 'Subscribers', 'body_html' => $body ] );
        $html .= CoreUi::card( [ 'title' => 'Add this form to a page', 'body_html' => '<p>Paste into a page or an Elementor Shortcode widget. Every option is in the <a href="https://github.com/mikeyperes/hws-base-tools/blob/main/docs/newsletter.md" target="_blank" rel="noopener">newsletter guide</a>.</p><div class="hws-nl-bar"><code>[hws_newsletter list="' . esc_html( $id ) . '"]</code>' . CoreUi::copy_button( '[hws_newsletter list="' . $id . '"]' ) . '</div>' ] );
        $html .= '<form data-nl-action="newsletter_update"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
        $html .= CoreUi::card( [ 'title' => 'Name and notices', 'body_html' => '<div class="hws-nl-field"><label for="hws-nl-name">Name</label><input type="text" id="hws-nl-name" name="name" maxlength="120" value="' . esc_attr( (string) ( $own['name'] ?? '' ) ) . '" placeholder="' . esc_attr( $id ) . '"></div><div class="hws-nl-field"><label for="hws-nl-own-notify">Send each new signup to</label><input type="email" id="hws-nl-own-notify" name="notification_email" value="' . esc_attr( (string) ( $own['notification_email'] ?? '' ) ) . '" placeholder="' . esc_attr( '' !== $s['notification_email'] ? $s['notification_email'] . ' (site default)' : 'Off (site default)' ) . '"><p class="hws-nl-help">Leave blank to use the site default.</p></div>' ] );
        $html .= CoreUi::card( [ 'title' => 'Design', 'body_html' => self::design( 'hws-nl-style-' . $id, (string) ( $own['style'] ?? '' ), true ) ] );
        $html .= CoreUi::card( [ 'title' => 'Messages visitors see', 'body_html' => self::messages( [ 'success' => $own['success'] ?? '', 'duplicate' => $own['duplicate'] ?? '', 'error' => $own['error'] ?? '' ], [ 'success' => $s['success'], 'duplicate' => $s['duplicate'], 'error' => '' !== $s['error'] ? $s['error'] : NewsletterModule::ERROR ], 'Leave a box blank to use the site default shown in grey. Plain text, up to 2000 characters.' ) ] );
        $html .= '<div class="hws-nl-save">' . self::button( 'Save ' . $n['name'] ) . self::status() . '</div></form>';
        if ( null !== Newsletters::get( $id ) ) {
            $html .= '<p><button type="button" class="button-link button-link-delete" data-nl-remove="' . esc_attr( $id ) . '">Remove this newsletter</button> <span class="hws-nl-help">Subscribers stay stored and exportable.</span></p>';
        }
        return '<div class="hws-nl-panel" data-id="' . esc_attr( $id ) . '">' . $html . '</div>';
    }

    private static function defaults_form(): string {
        $s    = NewsletterModule::settings();
        $html = '<h2 class="hws-nl-section">Site defaults</h2><form data-nl-action="newsletter_defaults">';
        $html .= CoreUi::card( [ 'title' => 'Collecting', 'body_html' => CoreUi::toggle( 'enabled', (bool) $s['enabled'], 'Collect newsletter signups on this site', [ 'id' => 'hws-nl-enabled' ] ) . '<p class="hws-nl-help">While off, every newsletter form is hidden and nothing is collected. Stored signups are kept.</p>' ] );
        $html .= CoreUi::card( [ 'title' => 'Default notice email', 'body_html' => '<div class="hws-nl-field"><label for="hws-nl-notify">Send new signups to</label><input type="email" id="hws-nl-notify" name="notification_email" value="' . esc_attr( $s['notification_email'] ) . '" placeholder="' . esc_attr( (string) get_option( 'admin_email' ) ) . '"><p class="hws-nl-help">Used by every newsletter without its own address. Blank sends no notices. Notices come from no-reply at this domain, and replying goes to the subscriber.</p></div>' ] );
        $html .= CoreUi::card( [ 'title' => 'Default design', 'body_html' => self::design( 'hws-nl-style-default', (string) $s['style'], false ) . CoreUi::inline_details( 'Show the CSS for the default design', CodeDisplay::render( NewsletterModule::css( $s['style'] ) ?: '/* No plugin CSS: the theme styles the form. */', [ 'language' => 'css', 'title' => 'Newsletter CSS' ] ) ) ] );
        $html .= CoreUi::card( [ 'title' => 'Default messages', 'body_html' => self::messages( $s, [ 'success' => '', 'duplicate' => '', 'error' => NewsletterModule::ERROR ], 'Leave failure blank for the standard message with the specific email or consent hint. Plain text, up to 2000 characters.' ) ] );
        return $html . '<div class="hws-nl-save">' . self::button( 'Save site defaults' ) . self::status() . '</div></form>';
    }

    private static function design( string $id, string $value, bool $inherit ): string {
        $templates = [];
        $default   = NewsletterModule::settings()['style'];
        if ( $inherit ) {
            $templates['inherit'] = [ 'label' => 'Use the site default', 'description' => 'Follows the default design under Site defaults.', 'preview_html' => '<style>' . NewsletterModule::css( $default ) . '</style>' . NewsletterModule::render( [ 'style' => $default, 'id' => $id . '-inherit' ], true ) ];
        }
        foreach ( self::STYLES as $key => $label ) {
            $templates[ $key ] = [ 'label' => $label[0], 'description' => $label[1], 'preview_html' => '<style>' . NewsletterModule::css( $key ) . '</style>' . NewsletterModule::render( [ 'style' => $key, 'id' => $id . '-' . $key ], true ) ];
        }
        return TemplateSelectionControl::render( [ 'id' => $id, 'name' => 'style', 'value' => '' !== $value ? $value : ( $inherit ? 'inherit' : 'none' ), 'title' => 'Form design', 'description' => 'Each sample is the real form with the same CSS the site uses.', 'templates' => $templates, 'columns' => 1, 'preview_height' => 140 ] );
    }

    private static function messages( array $values, array $placeholders, string $note ): string {
        $html = '';
        foreach ( [ 'success' => 'After a signup', 'duplicate' => 'When the email is already on the list', 'error' => 'When the signup fails' ] as $key => $label ) {
            $html .= '<div class="hws-nl-field"><label>' . esc_html( $label ) . '<textarea name="' . $key . '" rows="2" class="large-text" maxlength="2000" placeholder="' . esc_attr( (string) $placeholders[ $key ] ) . '">' . esc_textarea( (string) $values[ $key ] ) . '</textarea></label></div>';
        }
        return $html . '<p class="hws-nl-help">' . esc_html( $note ) . '</p>';
    }

    private static function css(): string {
        return '.hws-nl-tab{max-width:960px}.hws-nl-tab .hpc-card{margin:0 0 18px}.hws-nl-tab .hws-nl-field{margin:0 0 18px}.hws-nl-tab .hws-nl-field label{display:block;font-weight:600;margin:0 0 6px}.hws-nl-tab .hws-nl-field textarea{display:block;margin-top:6px;font-weight:400}.hws-nl-tab .hws-nl-help{color:#50575e;margin:6px 0 0;font-size:13px}.hws-nl-tab .hws-nl-field input[type=email],.hws-nl-tab .hws-nl-field input[type=text]{width:100%;max-width:420px}'
            . '.hws-nl-tab .hws-nl-stats{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 16px}.hws-nl-tab .hws-nl-stat{flex:1 1 140px;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;background:#f6f7f7}.hws-nl-tab .hws-nl-stat b{display:block;font-size:22px;line-height:1.2}.hws-nl-tab .hws-nl-stat span{color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em}'
            . '.hws-nl-tab table.widefat td{vertical-align:middle}.hws-nl-tab table.widefat td code{white-space:nowrap}.hws-nl-tab tr.is-current td{background:#f0f6fc}.hws-nl-tab .hws-nl-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:0 0 12px}.hws-nl-tab .hws-nl-create{margin-top:18px;padding-top:16px;border-top:1px solid #dcdcde}.hws-nl-tab .hws-nl-create h4{margin:0 0 12px}'
            . '.hws-nl-tab .hws-nl-empty{padding:24px;text-align:center;color:#50575e;border:1px dashed #c3c4c7;border-radius:8px}.hws-nl-tab .hws-nl-save{position:sticky;bottom:0;z-index:5;background:#fff;border-top:1px solid #dcdcde;padding:12px 0;margin:0 0 18px}.hws-nl-tab h2.hws-nl-section{margin:28px 0 12px}.hws-nl-tab .hws-nl-close{font-size:13px;font-weight:400;margin-left:8px}'
            . '.hws-nl-tab .hws-nl-status{margin-left:10px}.hws-nl-tab .hws-nl-status.is-error{color:#b32d2e}.hws-nl-tab .hws-nl-status.is-ok{color:#007017}.hws-nl-tab.is-busy{cursor:progress}';
    }

    private static function js(): string {
        return <<<'JS'
(function(){
var root=document.querySelector('.hws-nl-tab');if(!root)return;
var core=window.HexaWpCoreDynamicButton;
function post(action,data){data.append('action','hws_base_tools_'+action);data.append('nonce',root.getAttribute('data-nonce'));
 root.classList.add('is-busy');
 return fetch(root.getAttribute('data-ajax-url'),{method:'POST',credentials:'same-origin',body:data}).then(function(r){return r.json().catch(function(){return {success:false,data:{message:'The server sent an unexpected reply.'}};});})
 .then(function(j){root.classList.remove('is-busy');if(!j||!j.success)throw new Error(j&&j.data&&j.data.message?j.data.message:'That did not work. Try again.');return j.data||{};},function(e){root.classList.remove('is-busy');throw e;});}
function apply(d,push){
 if(typeof d.list_html==='string')document.getElementById('hws-nl-list').innerHTML=d.list_html;
 if(typeof d.panel_html==='string'){var p=document.getElementById('hws-nl-panel');p.innerHTML=d.panel_html;document.dispatchEvent(new CustomEvent('hexa-core-host-tab-loaded',{detail:{panel:p}}));if(d.panel_html&&push!==false)p.scrollIntoView({behavior:'smooth',block:'start'});}
 if(typeof d.id==='string'&&push!==false){var u=root.getAttribute('data-tab-url')+(d.id?'&list='+encodeURIComponent(d.id):'');history.pushState({nl:d.id},'',u);}
}
function say(el,text,ok){if(!el)return;el.textContent=text;el.classList.toggle('is-error',!ok);el.classList.toggle('is-ok',!!ok);}
function open(id,before,push){var f=new FormData();f.append('id',id||'');if(before)f.append('before',before);return post('newsletter_open',f).then(function(d){apply(d,push);}).catch(function(e){alert(e.message);});}
root.addEventListener('submit',function(e){var form=e.target.closest('form[data-nl-action]');if(!form)return;e.preventDefault();
 var btn=form.querySelector('button[type=submit]'),st=form.querySelector('.hws-nl-status'),f=new FormData(form);
 var cur=document.querySelector('#hws-nl-panel .hws-nl-panel');if(cur)f.append('current',cur.getAttribute('data-id'));
 if(core&&btn)core.start(btn,'Saving...');say(st,'',true);
 post(form.getAttribute('data-nl-action'),f).then(function(d){if(core&&btn)core.success(btn,'Done');var keep=form.isConnected;apply(d);if(keep)say(st,d.message||'Saved.',true);})
 .catch(function(err){if(core&&btn)core.error(btn,'Not saved');say(st,err.message,false);});});
root.addEventListener('click',function(e){
 var o=e.target.closest('[data-nl-open]');if(o){e.preventDefault();open(o.getAttribute('data-nl-open'),o.getAttribute('data-before'));return;}
 var d=e.target.closest('[data-nl-delete-subscriber]');if(d){if(!confirm('Delete this subscriber?'))return;var f=new FormData();f.append('list',d.getAttribute('data-list'));f.append('subscriber_id',d.getAttribute('data-nl-delete-subscriber'));
  post('newsletter_delete_subscriber',f).then(function(r){var row=d.closest('tr');if(row)row.remove();var t=document.querySelector('[data-nl-total]');if(t)t.textContent=r.total;open(document.querySelector('#hws-nl-panel .hws-nl-panel').getAttribute('data-id'),0,false);}).catch(function(err){alert(err.message);});return;}
 var x=e.target.closest('[data-nl-remove]');if(x){if(!confirm('Remove this newsletter? Its subscribers are kept.'))return;var g=new FormData();g.append('id',x.getAttribute('data-nl-remove'));post('newsletter_remove',g).then(function(r){apply(r);}).catch(function(err){alert(err.message);});}
});
window.addEventListener('popstate',function(){var m=location.search.match(/[?&]list=([^&]+)/);open(m?decodeURIComponent(m[1]):'',0,false);});
})();
JS;
    }
}
