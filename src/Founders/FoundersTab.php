<?php

declare( strict_types=1 );

namespace HWS\BaseTools\Founders;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeBreakdown;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\WpAdminComponents\CodeDisplay;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

defined( 'ABSPATH' ) || exit;

/**
 * HWS Base Tools → Founders: on/off, section title, the people (user search,
 * drag to order, optional title each), style with a live example, the HTML
 * structure, what reads the list, and the shortcode with every attribute.
 */
final class FoundersTab implements ModuleInterface {
    private const NONCE  = 'hws_founders_tab';
    private const PREFIX = 'hws_founders_';
    private const ROOT   = 'hws-founders-tab';

    public function register(): void {
        foreach ( [ 'toggle', 'heading', 'save_people', 'style', 'entity' ] as $action ) {
            add_action( 'wp_ajax_' . self::PREFIX . $action, [ self::class, 'ajax_' . $action ] );
        }
    }

    private static function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ], 403 );
        }
        if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'This page expired. Reload it and try again.' ], 403 );
        }
    }

    private static function saved( string $message ): void {
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => $message, 'example' => self::example_html() ] );
    }

    public static function ajax_toggle(): void {
        self::guard();
        $on = '1' === (string) ( $_POST['on'] ?? '' );
        Founders::update_settings( [ 'enabled' => $on ] );
        self::saved( $on ? 'Founders is on.' : 'Founders is off; the shortcode prints nothing. People and titles are kept.' );
    }

    public static function ajax_entity(): void {
        self::guard();
        $changes = [ 'entity_is_founder' => '1' === (string) ( $_POST['on'] ?? '' ) ];
        $id      = Founders::primary_entity()['user_id'] ?? 0;
        if ( $id && isset( $_POST['title'] ) ) {
            $titles        = Founders::settings()['titles'];
            $titles[ $id ] = sanitize_text_field( (string) wp_unslash( $_POST['title'] ) );
            $changes['titles'] = $titles;
        }
        Founders::update_settings( $changes );
        self::saved( $changes['entity_is_founder'] ? 'The Primary Entity is included as a founder.' : 'The Primary Entity is not a founder.' );
    }

    public static function ajax_heading(): void {
        self::guard();
        Founders::update_settings( [ 'heading' => sanitize_text_field( (string) wp_unslash( $_POST['heading'] ?? '' ) ) ] );
        self::saved( 'Title saved.' );
    }

    public static function ajax_save_people(): void {
        self::guard();
        $people = json_decode( (string) wp_unslash( $_POST['people'] ?? '[]' ), true );
        $ids    = [];
        $titles = [];
        foreach ( is_array( $people ) ? $people : [] as $person ) {
            $id = absint( $person['id'] ?? 0 );
            if ( $id && get_userdata( $id ) && ! in_array( $id, $ids, true ) ) {
                $ids[]         = $id;
                $titles[ $id ] = sanitize_text_field( (string) ( $person['title'] ?? '' ) );
            }
        }
        $entity = Founders::primary_entity()['user_id'] ?? 0;
        if ( $entity && ! isset( $titles[ $entity ] ) ) {
            $titles[ $entity ] = Founders::title( $entity );
        }
        Founders::update_settings( [ 'ids' => $ids, 'titles' => $titles ] );
        self::saved( count( $ids ) . ( 1 === count( $ids ) ? ' founder is' : ' founders are' ) . ' in the section.' );
    }

    public static function ajax_style(): void {
        self::guard();
        $style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
        if ( ! isset( FounderCards::STYLES[ $style ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown style.' ] );
        }
        Founders::update_settings( [ 'style' => $style ] );
        self::saved( 'Style saved.' );
    }

    private static function example_html(): string {
        $html = Founders::render();
        if ( '' !== $html ) {
            return $html;
        }
        return Founders::settings()['enabled'] ? '<p class="description">Add someone above to see the example.</p>' : '<p class="description">Founders is off, so the shortcode prints nothing.</p>';
    }

    /** @return array<string,array{default:string,description:string,options?:string[]}> */
    public static function attributes(): array {
        return [
            'style'         => [ 'default' => 'the style chosen above', 'description' => 'Uses another look for this one list.', 'options' => [ 'cards', 'list', 'none' ] ],
            'columns'       => [ 'default' => '3', 'description' => 'Founders per row in Cards, 1 to 6 (fewer on tablets and phones).' ],
            'ids'           => [ 'default' => '', 'description' => 'Shows only these users, in this order (user IDs, for example 12,7).' ],
            'limit'         => [ 'default' => '0', 'description' => 'Shows only the first founders; 0 shows everyone.' ],
            'hide'          => [ 'default' => '', 'description' => 'Leaves those parts out, comma separated.', 'options' => [ 'photo', 'title', 'bio', 'link' ] ],
            'link'          => [ 'default' => 'profile', 'description' => 'none stops names and photos linking to the person\'s author page.', 'options' => [ 'profile', 'none' ] ],
            'bio_words'     => [ 'default' => '30', 'description' => 'Length of the short bio from the profile\'s Biographical Info; 0 hides it.' ],
            'photo_size'    => [ 'default' => 'medium', 'description' => 'Image size.', 'options' => [ 'thumbnail', 'medium', 'medium_large', 'large' ] ],
            'heading'       => [ 'default' => 'h3', 'description' => 'Tag for each founder\'s name.', 'options' => [ 'h2', 'h3', 'h4', 'p' ] ],
            'section_title' => [ 'default' => 'the section title above', 'description' => 'Another title for this one list; none leaves it out.' ],
            'section_tag'   => [ 'default' => 'h2', 'description' => 'Tag for the section title.', 'options' => [ 'h2', 'h3', 'h4', 'p' ] ],
        ];
    }

    public static function render(): void {
        CoreUi::render_assets();
        $settings = Founders::settings();
        $members  = Founders::manual_ids();
        $entity   = Founders::primary_entity();
        $count    = count( $members );
        ?>
        <div id="<?php echo esc_attr( self::ROOT ); ?>" class="hpc-ui hws-fo" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>">
            <?php self::styles(); ?>
            <section>
                <h3>Founders</h3>
                <p class="lead">The people who founded or run this website. This is the one place founders are picked: Website Identity, SMP Publication Integration and the search-engine data all read this list. Each person's photo, name and short bio come from their user profile; the title is set here.</p>
                <?php echo CoreUi::toggle( 'hws_founders_enabled', $settings['enabled'], 'Show the founders section', [ 'id' => 'hws-fo-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <p class="hws-fo-hint">Off: the shortcode prints nothing on pages. The list is kept and other plugins still read it.</p>
                <div class="hws-fo-notice" data-role="toggle-notice" role="status" aria-live="polite"></div>
            </section>

            <section>
                <h3>Primary Entity</h3>
                <?php if ( ! $entity ) : ?>
                    <p class="lead">No Primary Entity is set (Website &amp; Primary Entity tab), so the founders are only the people added below.</p>
                <?php elseif ( 'person' !== $entity['type'] ) : ?>
                    <p class="lead">The Primary Entity is a company (<?php echo esc_html( (string) get_userdata( $entity['user_id'] )->display_name ); ?>), so it is never on the founders list. Add the founders below; there can be several.</p>
                <?php else : ?>
                    <p class="lead">The Primary Entity is a person, <strong><?php echo esc_html( (string) get_userdata( $entity['user_id'] )->display_name ); ?></strong>. When marked as a founder they are included automatically, first; add any other founders below.</p>
                    <?php echo CoreUi::toggle( 'hws_founders_entity', $settings['entity_is_founder'], 'This person is a founder', [ 'id' => 'hws-fo-entity' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <label class="hws-fo-title"><span>Founder title</span><input type="text" id="hws-fo-entity-title" value="<?php echo esc_attr( Founders::title( $entity['user_id'] ) ); ?>" placeholder="For example: Founder &amp; CEO"></label>
                    <div class="hws-fo-notice" data-role="entity-notice" role="status" aria-live="polite"></div>
                <?php endif; ?>
            </section>

            <section>
                <h3>Section title</h3>
                <p class="lead">Shown above the founders. "Founders" by default; it can be "Our founders", "Executives" or anything else. Leave it empty for no title.</p>
                <input type="text" class="regular-text" id="hws-fo-heading" value="<?php echo esc_attr( $settings['heading'] ); ?>" placeholder="Founders">
                <div class="hws-fo-notice" data-role="heading-notice" role="status" aria-live="polite"></div>
            </section>

            <section>
                <h3>People</h3>
                <p class="lead">Founders added by hand. Search for a user account to add it. Drag to set the order. The title is optional, for example "Founder &amp; Publisher".</p>
                <div class="hws-fo-team">
                    <div class="hws-fo-head"><strong><?php echo esc_html( '' !== $settings['heading'] ? $settings['heading'] : 'Founders' ); ?></strong><span class="hws-fo-count" data-role="count"><?php echo esc_html( $count . ( 1 === $count ? ' person' : ' people' ) ); ?></span></div>
                    <div class="hws-fo-search">
                        <?php
                        ( new SmartSearchRenderer() )->render(
                            [ 'id' => self::ROOT . '-search', 'label' => 'Add a founder', 'placeholder' => 'Type a name or email', 'source' => 'users', 'min_chars' => 2, 'limit' => 10 ]
                        );
                        ?>
                    </div>
                    <ul class="hws-fo-people" data-role="people">
                        <?php foreach ( $members as $id ) : ?>
                            <?php echo self::person_html( $id, Founders::title( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="hws-fo-empty" data-role="empty"<?php echo $count ? ' hidden' : ''; ?>>No founders yet. Search above to add the first one.</div>
                    <div class="hws-fo-notice" data-role="people-notice" role="status" aria-live="polite"></div>
                </div>
            </section>

            <section>
                <h3>Style</h3>
                <ul class="hws-fo-styles">
                    <?php foreach ( FounderCards::STYLES as $value => $label ) : ?>
                        <li><label><input type="radio" name="hws_founders_style" data-role="style" value="<?php echo esc_attr( $value ); ?>"<?php checked( $settings['style'], $value ); ?>> <?php echo esc_html( $label ); ?></label></li>
                    <?php endforeach; ?>
                </ul>
                <div class="hws-fo-notice" data-role="style-notice" role="status" aria-live="polite"></div>
                <p class="lead hws-fo-example-label">Live example, with the founders above:</p>
                <div class="hws-fo-example" data-role="example"><?php echo self::example_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </section>

            <section>
                <h3>HTML structure</h3>
                <p class="lead">One founder shown. Style it with these classes in the site's CSS or an Elementor widget's Custom CSS. "No styling" prints the same markup without the built-in CSS. Pages that still use <code>[smpi_founders]</code> print the same markup with <code>smpi-founders</code> / <code>smpi-founder</code> class names.</p>
                <?php echo CodeDisplay::render( FounderCards::structure(), [ 'language' => 'html', 'title' => 'Founders section' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <details class="hws-fo-details"><summary>Built-in CSS</summary><?php echo CodeDisplay::render( str_replace( '}', "}\n", FounderCards::css( Founders::BLOCK, Founders::ITEM ) ), [ 'language' => 'css', 'title' => 'Cards and List looks' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></details>
            </section>

            <section>
                <h3>Plugin breakdown</h3>
                <ul class="hws-fo-breakdown">
                    <li><strong>Owner:</strong> HWS Base Tools stores the founders (option <code><?php echo esc_html( Founders::OPTION ); ?></code>), its own definition, separate from the Primary Entity.</li>
                    <li><strong>Primary Entity:</strong> a person marked as a founder is included automatically, first. A company is never a founder; its founders are added by hand.</li>
                    <li><strong>Website Identity:</strong> its company "founders" value reads this list; it has no picker of its own.</li>
                    <li><strong>SMP Publication Integration:</strong> reads this list for its search-engine data and its own founders displays, and shows it read-only with a link here.</li>
                    <li><strong>On pages:</strong> <code>[<?php echo esc_html( Founders::SHORTCODE ); ?>]</code>, below. Only the founders, never the founding date.</li>
                    <li><strong>Docs:</strong> <a href="https://github.com/mikeyperes/hws-base-tools/blob/main/docs/founders.md" target="_blank" rel="noopener">https://github.com/mikeyperes/hws-base-tools/blob/main/docs/founders.md</a></li>
                </ul>
                <?php echo CodeDisplay::render( "// Every founder, in order, whether or not the section is on.\nforeach ( hws_publication_founders() as \$founder ) {\n    // user_id, name, title, url, bio, photo\n}\n\nhws_publication_founders_enabled();  // is the on-page section on?\nhws_publication_founders_edit_url(); // this tab, for a SourceBanner link", [ 'language' => 'php', 'title' => 'Read API for other plugins' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

            <section>
                <h3>Shortcode</h3>
                <?php
                echo ShortcodeBreakdown::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    [
                        'tag'         => Founders::SHORTCODE,
                        'title'       => 'Founders section',
                        'description' => 'Place it on any page. With no attributes it shows every founder above, in order, in the chosen style, under the section title. While Founders is off it prints nothing.',
                        'aliases'     => [ Founders::ALIAS ],
                        'attributes'  => self::attributes(),
                        'examples'    => [
                            [ 'label' => 'List look, no section title', 'shortcode' => '[' . Founders::SHORTCODE . ' style="list" section_title="none"]' ],
                            [ 'label' => 'Names and titles only', 'shortcode' => '[' . Founders::SHORTCODE . ' hide="photo,bio,link"]' ],
                        ],
                    ]
                );
                ?>
            </section>
        </div>
        <?php
        self::script();
    }

    /** One founder row; with $id 0 it is the template the script fills when someone is added. */
    private static function person_html( int $id, string $title ): string {
        $user = $id ? get_userdata( $id ) : null;
        $name = $user instanceof \WP_User ? (string) $user->display_name : '';
        $meta = $user instanceof \WP_User ? implode( ' · ', array_filter( [ implode( ', ', array_map( 'ucfirst', (array) $user->roles ) ), (string) $user->user_email ] ) ) : '';

        return '<li class="hws-fo-person" draggable="true" data-id="' . esc_attr( (string) $id ) . '">'
            . '<span class="hws-fo-handle dashicons dashicons-menu" title="Drag to reorder" aria-hidden="true"></span>'
            . '<img class="hws-fo-avatar" src="' . esc_url( $id ? (string) get_avatar_url( $id, [ 'size' => 104 ] ) : '' ) . '" alt="">'
            . '<div class="hws-fo-who"><strong class="hws-fo-name">' . esc_html( $name ) . '</strong><div class="hws-fo-meta">' . esc_html( $meta ) . '</div>'
            . '<label class="hws-fo-title"><span>Founder title</span><input type="text" data-role="title" value="' . esc_attr( $title ) . '" placeholder="For example: Founder &amp; Publisher"></label>'
            . '</div><div class="hws-fo-actions">'
            . '<button type="button" class="hws-fo-icon" data-action="up" title="Move up" aria-label="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>'
            . '<button type="button" class="hws-fo-icon" data-action="down" title="Move down" aria-label="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>'
            . '<a class="hws-fo-icon" data-role="profile" href="' . esc_url( $id ? (string) get_edit_user_link( $id ) : '#' ) . '" target="_blank" rel="noopener" title="Open profile" aria-label="Open profile"><span class="dashicons dashicons-admin-users"></span></a>'
            . '<button type="button" class="hws-fo-icon is-remove" data-action="remove" title="Remove" aria-label="Remove"><span class="dashicons dashicons-no-alt"></span></button>'
            . '</div></li>';
    }

    private static function styles(): void {
        ?>
        <style>
            .hws-fo{max-width:900px}.hws-fo section{margin:0 0 30px}.hws-fo h3{font-size:16px;margin:0 0 6px}
            .hws-fo p.lead{color:#646970;margin:0 0 12px}.hws-fo .hws-fo-hint{color:#646970;font-size:12px;margin:8px 0 0}
            .hws-fo .hws-fo-notice{font-size:13px;margin-top:8px;color:#00701a}.hws-fo .hws-fo-notice.error{color:#b32d2e}
            .hws-fo #hws-fo-heading{max-width:420px;height:36px}
            .hws-fo .hws-fo-team{max-width:760px;background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden}
            .hws-fo .hws-fo-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#f9fafb}
            .hws-fo .hws-fo-count{font-size:12px;color:#646970;background:#fff;border:1px solid #dcdcde;border-radius:999px;padding:2px 10px}
            .hws-fo .hws-fo-search{padding:14px 18px;border-bottom:1px solid #f0f0f1}
            .hws-fo .hws-fo-search .hpc-smart-search-input{width:100%;height:40px;font-size:14px}.hws-fo .hws-fo-search .hpc-smart-search-selected{display:none!important}
            .hws-fo .hws-fo-people{list-style:none;margin:0;padding:0}
            .hws-fo .hws-fo-person{display:grid;grid-template-columns:20px 52px minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#fff;margin:0}
            .hws-fo .hws-fo-person:last-child{border-bottom:0}.hws-fo .hws-fo-person.is-dragging{opacity:.45}
            .hws-fo .hws-fo-handle{cursor:grab;color:#a7aaad}.hws-fo .hws-fo-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;background:#f0f0f1}
            .hws-fo .hws-fo-who{min-width:0}.hws-fo .hws-fo-name{font-size:14px}
            .hws-fo .hws-fo-meta{font-size:12px;color:#787c82;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hws-fo .hws-fo-title{display:block;margin-top:8px}.hws-fo .hws-fo-title span{display:block;font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;color:#646970;margin-bottom:4px}
            .hws-fo .hws-fo-title input{width:100%;max-width:420px;height:34px}
            .hws-fo .hws-fo-actions{display:flex;gap:4px}
            .hws-fo .hws-fo-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #dcdcde;border-radius:6px;background:#fff;color:#50575e;cursor:pointer;text-decoration:none;padding:0}
            .hws-fo .hws-fo-icon:hover{border-color:#2271b1;color:#2271b1}.hws-fo .hws-fo-icon.is-remove:hover{border-color:#d63638;color:#d63638}
            .hws-fo .hws-fo-icon .dashicons{font-size:16px;width:16px;height:16px;pointer-events:none}
            .hws-fo .hws-fo-empty{padding:28px 18px;text-align:center;color:#646970}.hws-fo .hws-fo-team .hws-fo-notice{padding:0 18px 12px;margin:0}
            .hws-fo .hws-fo-styles{list-style:none;margin:0;padding:0}.hws-fo .hws-fo-styles li{margin:6px 0}
            .hws-fo .hws-fo-example-label{margin-top:14px}
            .hws-fo .hws-fo-example{border:1px solid #dcdcde;border-radius:6px;padding:24px;background:#fff;max-height:720px;overflow:auto}
            .hws-fo .hws-fo-details summary{cursor:pointer;margin:0 0 8px;color:#2271b1}
            .hws-fo .hws-fo-breakdown{margin:0 0 14px;padding-left:18px;list-style:disc}.hws-fo .hws-fo-breakdown li{margin:0 0 6px}
        </style>
        <?php
    }

    private static function script(): void {
        ?>
        <script>
        (function(){
            var root = document.getElementById(<?php echo wp_json_encode( self::ROOT ); ?>);
            if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var prefix = <?php echo wp_json_encode( self::PREFIX ); ?>, tpl = <?php echo wp_json_encode( self::person_html( 0, '' ) ); ?>;
            var list = root.querySelector('[data-role="people"]'), timer = null, queue = Promise.resolve();
            function post(action, fields){
                var body = new URLSearchParams(); body.set('action', prefix + action); body.set('nonce', root.dataset.nonce);
                Object.keys(fields || {}).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); })
                    .then(function(p){ if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.'); return p.data; });
            }
            function say(role, text, error){ var n = root.querySelector('[data-role="' + role + '"]'); if (!n) return; n.className = 'hws-fo-notice' + (error ? ' error' : ''); n.textContent = text; }
            function send(action, fields, role){ say(role, 'Saving…'); return post(action, fields).then(function(d){ say(role, d.message); var e = root.querySelector('[data-role="example"]'); if (e && typeof d.example === 'string') e.innerHTML = d.example; }).catch(function(err){ say(role, err.message, true); }); }
            function refresh(){ var n = list.children.length; root.querySelector('[data-role="count"]').textContent = n + (n === 1 ? ' person' : ' people'); root.querySelector('[data-role="empty"]').hidden = n > 0; }
            function people(){ return Array.prototype.map.call(list.children, function(li){ var t = li.querySelector('[data-role="title"]'); return { id: li.dataset.id, title: t ? t.value : '' }; }); }
            // One save at a time, each sending the list as it is when it starts, so a slow save never overwrites a newer one.
            function save(){ clearTimeout(timer); queue = queue.then(function(){ return send('save_people', { people: JSON.stringify(people()) }, 'people-notice'); }); return queue; }
            var toggle = document.getElementById('hws-fo-enabled');
            if (toggle) toggle.addEventListener('change', function(){ send('toggle', { on: toggle.checked ? '1' : '0' }, 'toggle-notice'); });
            var ent = document.getElementById('hws-fo-entity'), entTitle = document.getElementById('hws-fo-entity-title'), et = null;
            function sendEntity(){ send('entity', { on: ent.checked ? '1' : '0', title: entTitle ? entTitle.value : '' }, 'entity-notice'); }
            if (ent) ent.addEventListener('change', sendEntity);
            if (entTitle) entTitle.addEventListener('input', function(e){ e.stopPropagation(); clearTimeout(et); et = setTimeout(sendEntity, 700); });
            var heading = document.getElementById('hws-fo-heading'), ht = null;
            heading.addEventListener('input', function(){ clearTimeout(ht); ht = setTimeout(function(){ send('heading', { heading: heading.value }, 'heading-notice'); }, 700); });
            document.addEventListener('hexa-search-selected', function(ev){
                if (!ev.detail || ev.detail.component_id !== root.id + '-search') return;
                var item = ev.detail.item || {}, search = document.getElementById(root.id + '-search');
                var input = search && search.querySelector('.hpc-smart-search-input'); if (input) input.value = '';
                if (!item.id) return;
                if (list.querySelector('.hws-fo-person[data-id="' + item.id + '"]')) { say('people-notice', (item.name || 'This person') + ' is already a founder.', true); return; }
                var holder = document.createElement('ul'); holder.innerHTML = tpl; var row = holder.firstElementChild;
                row.dataset.id = String(item.id); row.querySelector('.hws-fo-avatar').src = item.thumbnail || '';
                row.querySelector('.hws-fo-name').textContent = item.name || '';
                row.querySelector('.hws-fo-meta').textContent = [String(item.status || ''), item.email].filter(Boolean).join(' · ');
                row.querySelector('[data-role="profile"]').href = item.edit_url || '#';
                list.appendChild(row); refresh(); save(); row.querySelector('[data-role="title"]').focus();
            });
            var dragged = null;
            list.addEventListener('dragstart', function(e){ var li = e.target.closest('.hws-fo-person'); if (!li) return; dragged = li; li.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
            list.addEventListener('dragover', function(e){ var li = e.target.closest('.hws-fo-person'); if (!dragged || !li || li === dragged) return; e.preventDefault(); var r = li.getBoundingClientRect(); list.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? li.nextSibling : li); });
            list.addEventListener('dragend', function(){ if (!dragged) return; dragged.classList.remove('is-dragging'); dragged = null; save(); });
            root.addEventListener('input', function(e){ if (e.target.dataset.role === 'title') { clearTimeout(timer); timer = setTimeout(save, 800); } });
            root.addEventListener('change', function(e){ if (e.target.dataset.role === 'style') send('style', { style: e.target.value }, 'style-notice'); });
            root.addEventListener('click', function(e){
                var btn = e.target.closest('[data-action]'); if (!btn) return; var li = btn.closest('.hws-fo-person'); if (!li) return;
                var a = btn.getAttribute('data-action');
                if (a === 'remove') { li.remove(); refresh(); save(); }
                if (a === 'up' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); save(); }
                if (a === 'down' && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); save(); }
            });
        })();
        </script>
        <?php
    }
}
