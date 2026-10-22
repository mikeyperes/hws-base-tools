<?php

declare( strict_types=1 );

namespace HWS\BaseTools\TeamMembers;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * HWS Base Tools → Team: one switch for the Team Member post type, its ACF
 * fields and the [hws_team_members] listing grid, the team page defaults, and
 * a shortcode builder that shows the exact HTML the shortcode prints.
 */
final class TeamTab implements ModuleInterface {
    private const NONCE_ACTION = 'hws_team_tab';
    private const FIELD_GROUP  = 'team-member-fields';
    public const DOCS_URL      = 'https://github.com/mikeyperes/hws-base-tools/blob/main/docs/team-members.md';

    public function register(): void {
        if ( get_option( TeamMemberDirectory::FEATURE_OPTION, false ) ) {
            ( new TeamMemberDirectory() )->register();
        }
        add_action( 'wp_ajax_hws_team_tab_toggle', [ self::class, 'handle_toggle' ] );
        add_action( 'wp_ajax_hws_team_tab_save', [ self::class, 'handle_save' ] );
        add_action( 'wp_ajax_hws_team_tab_preview', [ self::class, 'handle_preview' ] );
    }

    /** @return array{post_type:bool,fields:bool,listing:bool} */
    public static function state(): array {
        $registry   = SharedContentTypes::registry();
        $definition = $registry->definition( SharedContentTypes::TEAM_MEMBER );
        $resolved   = $definition ? $registry->store()->resolve( $definition ) : [ 'enabled' => false, 'field_groups' => [] ];
        $fields     = false;
        foreach ( (array) $resolved['field_groups'] as $group ) {
            if ( self::FIELD_GROUP === ( $group['id'] ?? '' ) ) {
                $fields = ! empty( $group['enabled'] );
            }
        }

        return [
            'post_type' => ! empty( $resolved['enabled'] ),
            'fields'    => $fields,
            'listing'   => (bool) get_option( TeamMemberDirectory::FEATURE_OPTION, false ),
        ];
    }

    public static function enabled(): bool {
        return ! in_array( false, self::state(), true );
    }

    /** Turns the post type, its fields and the listing grid on or off together. */
    public static function set_enabled( bool $enabled ): array {
        $registry   = SharedContentTypes::registry();
        $definition = $registry->definition( SharedContentTypes::TEAM_MEMBER );
        if ( ! $definition ) {
            throw new \RuntimeException( 'The Team Member content type is not registered.' );
        }

        $current = $registry->store()->resolve( $definition );
        $groups  = [];
        foreach ( (array) $current['field_groups'] as $group ) {
            $on = self::FIELD_GROUP === $group['id'] ? $enabled : ! empty( $group['enabled'] );
            if ( $on ) {
                $groups[] = (string) $group['id'];
            }
        }

        $registry->store()->save(
            $definition,
            [
                'enabled'              => $enabled,
                'singular'             => $current['post_type']['singular'],
                'plural'               => $current['post_type']['plural'],
                'rewrite_slug'         => $current['post_type']['rewrite_slug'],
                'enabled_field_groups' => $groups,
            ]
        );
        update_option( TeamMemberDirectory::FEATURE_OPTION, $enabled ? 1 : 0 );

        return self::state();
    }

    public static function render(): void {
        CoreUi::render_assets();
        $state    = self::state();
        $enabled  = self::enabled();
        $settings = TeamPageSettings::all();
        $count    = post_type_exists( TeamMemberDirectory::POST_TYPE ) ? (int) ( wp_count_posts( TeamMemberDirectory::POST_TYPE )->publish ?? 0 ) : 0;
        $archive  = post_type_exists( TeamMemberDirectory::POST_TYPE ) ? get_post_type_archive_link( TeamMemberDirectory::POST_TYPE ) : '';
        ?>
        <div class="hpc-ui hws-team-tab" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
            <style>
                .hws-team-tab{color:#182238;max-width:860px}.hws-team-tab *{box-sizing:border-box}
                .hws-team-tab section{background:#fff;border:1px solid #d9dfe8;border-radius:12px;margin:0 0 18px;padding:20px 22px}
                .hws-team-tab h3{font-size:17px;margin:0 0 4px}.hws-team-tab .hws-team-note{color:#64748b;margin:0 0 14px}
                .hws-team-tab h4{font-size:13px;margin:16px 0 6px}
                .hws-team-tab ul.hws-team-options{list-style:none;margin:0;padding:0}.hws-team-tab ul.hws-team-options li{margin:0 0 6px}
                .hws-team-tab ul.hws-team-options small{color:#64748b;display:block;margin-left:24px}
                .hws-team-tab input[type=number],.hws-team-tab input[type=text]{max-width:220px;width:100%}
                .hws-team-switch{align-items:center;display:flex;gap:10px;font-weight:700}
                .hws-team-status{list-style:none;margin:12px 0 0;padding:0}.hws-team-status li{margin:0 0 4px}
                .hws-team-status .is-on{color:#15803d}.hws-team-status .is-off{color:#b42336}
                .hws-team-msg{color:#3157d5;font-size:12px;font-weight:700;margin-top:10px;min-height:18px}
                .hws-team-code{align-items:center;background:#f8fafc;border:1px solid #d8dee8;border-radius:8px;display:flex;gap:10px;margin:14px 0 0;padding:10px 12px}
                .hws-team-code code{flex:1;overflow-wrap:anywhere;white-space:normal}
                .hws-team-tab pre{background:#0b1120;border-radius:8px;color:#cbd5e1;font:11px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;margin:8px 0 0;max-height:360px;overflow:auto;padding:14px;white-space:pre-wrap}
                .hws-team-preview{border:1px solid #edf0f4;border-radius:8px;margin-top:8px;padding:14px}
            </style>
            <style><?php echo TeamMemberDirectory::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>

            <section>
                <h3>Team members</h3>
                <p class="hws-team-note">One switch turns on the Team Member post type, its fields (position, featured and profile details) and the <code>[hws_team_members]</code> listing grid. Turning it off hides them; saved team members are kept.</p>
                <label class="hws-team-switch"><input type="checkbox" role="switch" data-team-toggle <?php checked( $enabled ); ?>> <span data-team-toggle-label><?php echo $enabled ? 'On' : 'Off'; ?></span></label>
                <ul class="hws-team-status" data-team-status>
                    <?php self::status_rows( $state, $count ); ?>
                </ul>
                <?php if ( $state['post_type'] ) : ?>
                    <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TeamMemberDirectory::POST_TYPE ) ); ?>">Manage team members</a><?php if ( $archive ) : ?> · Team archive: <a href="<?php echo esc_url( $archive ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $archive ); ?></a><?php endif; ?></p>
                <?php endif; ?>
                <div class="hws-team-msg" data-team-toggle-msg aria-live="polite"></div>
            </section>

            <section data-team-settings>
                <h3>Team page settings</h3>
                <p class="hws-team-note">Defaults for every <code>[hws_team_members]</code> on the site. The plugin prints only the listing grid; the rest of the team page is the site's own design around it.</p>
                <?php self::fields( 'settings', $settings, false ); ?>
                <p><button type="button" class="button button-primary" data-team-save>Save team page settings</button></p>
                <div class="hws-team-msg" data-team-save-msg aria-live="polite"></div>
            </section>

            <section data-team-builder>
                <h3>Shortcode builder</h3>
                <p class="hws-team-note">Choose options to build a shortcode. Only options that differ from the team page settings are added. The preview and HTML below are the shortcode's real output. Full markup reference: <a href="<?php echo esc_url( self::DOCS_URL ); ?>" target="_blank" rel="noopener">docs/team-members.md</a>.</p>
                <?php self::fields( 'builder', $settings, true ); ?>
                <div class="hws-team-code"><code data-team-shortcode>[hws_team_members]</code><button type="button" class="button" data-team-copy>Copy</button></div>
                <h4>Preview</h4>
                <div class="hws-team-preview" data-team-preview></div>
                <h4>HTML structure</h4>
                <pre data-team-html></pre>
            </section>

            <script>
            (function(){
                var script=document.currentScript,root=script&&script.closest('.hws-team-tab');if(!root||root.dataset.bound==='1'){return;}root.dataset.bound='1';
                var defaults=<?php echo wp_json_encode( $settings ); ?>;
                function request(action,data){var body=new URLSearchParams(Object.assign({action:action,nonce:root.dataset.nonce},data||{}));return fetch(root.dataset.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json();}).then(function(p){if(!p||!p.success){throw new Error(p&&p.data&&p.data.message?p.data.message:'The request failed.');}return p.data;});}
                function values(scope){var box=root.querySelector('[data-team-'+scope+']'),out={};box.querySelectorAll('[data-field]').forEach(function(el){var k=el.dataset.field;if(el.type==='radio'){if(el.checked){out[k]=el.value;}}else if(el.type==='checkbox'){out[k]=el.checked?'1':'0';}else{out[k]=el.value.trim();}});return out;}
                function shortcode(v){var parts=['hws_team_members'];Object.keys(v).forEach(function(k){var val=v[k];if(k==='featured_only'){if(val==='1'){parts.push('featured_only="1"');}return;}if(k==='category'){if(val){parts.push('category="'+val.replace(/"/g,'')+'"');}return;}if(String(defaults[k])!==String(val)){parts.push(k+'="'+val+'"');}});return '['+parts.join(' ')+']';}
                var timer;function build(){var v=values('builder'),code=shortcode(v);root.querySelector('[data-team-shortcode]').textContent=code;clearTimeout(timer);timer=setTimeout(function(){request('hws_team_tab_preview',v).then(function(d){root.querySelector('[data-team-preview]').innerHTML=d.html||'<p>No published team members match these options.</p>';root.querySelector('[data-team-html]').textContent=d.pretty||'';}).catch(function(e){root.querySelector('[data-team-html]').textContent=e.message;});},250);}
                root.querySelector('[data-team-builder]').addEventListener('input',build);root.querySelector('[data-team-builder]').addEventListener('change',build);build();
                root.querySelector('[data-team-copy]').addEventListener('click',function(){var b=this,t=root.querySelector('[data-team-shortcode]').textContent;(navigator.clipboard&&window.isSecureContext?navigator.clipboard.writeText(t):Promise.reject()).catch(function(){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove();}).then(function(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy';},1500);});});
                root.querySelector('[data-team-save]').addEventListener('click',function(){var msg=root.querySelector('[data-team-save-msg]');msg.textContent='Saving…';request('hws_team_tab_save',values('settings')).then(function(d){defaults=d.settings;msg.textContent=d.message;build();}).catch(function(e){msg.textContent='Not saved: '+e.message;});});
                var toggle=root.querySelector('[data-team-toggle]');toggle.addEventListener('change',function(){var msg=root.querySelector('[data-team-toggle-msg]');toggle.disabled=true;msg.textContent='Saving…';request('hws_team_tab_toggle',{enabled:toggle.checked?'1':'0'}).then(function(d){root.querySelector('[data-team-toggle-label]').textContent=d.enabled?'On':'Off';root.querySelector('[data-team-status]').innerHTML=d.status_html;msg.textContent=d.message;}).catch(function(e){toggle.checked=!toggle.checked;msg.textContent='Not saved: '+e.message;}).finally(function(){toggle.disabled=false;});});
            }());
            </script>
        </div>
        <?php
    }

    /** @param array<string,mixed> $settings */
    private static function fields( string $scope, array $settings, bool $builder ): void {
        $name = static fn( string $field ): string => 'hws_team_' . $scope . '_' . $field;
        ?>
        <h4>Layout</h4>
        <ul class="hws-team-options">
            <?php foreach ( TeamMemberDirectory::template_options() as $style => $template ) : ?>
                <li><label><input type="radio" data-field="style" name="<?php echo esc_attr( $name( 'style' ) ); ?>" value="<?php echo esc_attr( $style ); ?>" <?php checked( $settings['style'], $style ); ?>> <?php echo esc_html( $template['label'] ); ?></label><small><?php echo esc_html( $template['description'] ); ?></small></li>
            <?php endforeach; ?>
        </ul>
        <h4>Design</h4>
        <ul class="hws-team-options">
            <li><label><input type="radio" data-field="design" name="<?php echo esc_attr( $name( 'design' ) ); ?>" value="default" <?php checked( $settings['design'], 'default' ); ?>> Built-in look</label><small>The plugin's own clean styling.</small></li>
            <li><label><input type="radio" data-field="design" name="<?php echo esc_attr( $name( 'design' ) ); ?>" value="none" <?php checked( $settings['design'], 'none' ); ?>> No plugin styling</label><small>Same markup with no plugin CSS, for an Elementor or HTML design that styles the classes itself.</small></li>
        </ul>
        <h4>Columns</h4>
        <ul class="hws-team-options"><li><input type="number" min="1" max="4" data-field="columns" value="<?php echo esc_attr( (string) $settings['columns'] ); ?>"><small>1 to 4 on desktop, for the portrait grid.</small></li></ul>
        <h4>People shown</h4>
        <ul class="hws-team-options"><li><input type="number" min="1" max="<?php echo esc_attr( (string) TeamMemberDirectory::MAX_LIMIT ); ?>" data-field="limit" value="<?php echo esc_attr( (string) $settings['limit'] ); ?>"></li></ul>
        <h4>Order</h4>
        <ul class="hws-team-options">
            <?php foreach ( [ 'menu_order' => 'Manual order (Order field, then name)', 'title' => 'Name', 'date' => 'Date added' ] as $value => $label ) : ?>
                <li><label><input type="radio" data-field="orderby" name="<?php echo esc_attr( $name( 'orderby' ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['orderby'], $value ); ?>> <?php echo esc_html( $label ); ?></label></li>
            <?php endforeach; ?>
            <?php foreach ( [ 'ASC' => 'Ascending', 'DESC' => 'Descending' ] as $value => $label ) : ?>
                <li><label><input type="radio" data-field="order" name="<?php echo esc_attr( $name( 'order' ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['order'], $value ); ?>> <?php echo esc_html( $label ); ?></label></li>
            <?php endforeach; ?>
        </ul>
        <h4>Show</h4>
        <ul class="hws-team-options">
            <li><label><input type="checkbox" data-field="show_excerpt" <?php checked( ! empty( $settings['show_excerpt'] ) ); ?>> Short bio</label></li>
            <li><label><input type="checkbox" data-field="link_profiles" <?php checked( ! empty( $settings['link_profiles'] ) ); ?>> Links to each person's profile page</label></li>
            <?php if ( $builder ) : ?>
                <li><label><input type="checkbox" data-field="featured_only"> Featured people only</label></li>
            <?php endif; ?>
        </ul>
        <?php if ( $builder ) : ?>
            <h4>Category</h4>
            <ul class="hws-team-options"><li><input type="text" data-field="category" placeholder="leadership"><small>Optional category slug, for example a leadership group.</small></li></ul>
        <?php endif; ?>
        <?php
    }

    /** @param array{post_type:bool,fields:bool,listing:bool} $state */
    private static function status_rows( array $state, int $count ): void {
        foreach ( [ 'post_type' => 'Team Member post type', 'fields' => 'Team Member fields', 'listing' => 'Listing grid shortcode' ] as $key => $label ) {
            $on = ! empty( $state[ $key ] );
            echo '<li class="' . ( $on ? 'is-on' : 'is-off' ) . '">' . ( $on ? '✓ ' : '✕ ' ) . esc_html( $label ) . ': ' . ( $on ? 'on' : 'off' ) . '</li>';
        }
        echo '<li>Published team members: ' . (int) $count . '</li>';
    }

    public static function handle_toggle(): void {
        self::authorize();
        try {
            $state = self::set_enabled( isset( $_POST['enabled'] ) && '1' === (string) wp_unslash( $_POST['enabled'] ) );
            $count = post_type_exists( TeamMemberDirectory::POST_TYPE ) ? (int) ( wp_count_posts( TeamMemberDirectory::POST_TYPE )->publish ?? 0 ) : 0;
            ob_start();
            self::status_rows( $state, $count );
            $enabled = ! in_array( false, $state, true );
            wp_send_json_success( [
                'enabled'     => $enabled,
                'state'       => $state,
                'status_html' => (string) ob_get_clean(),
                'message'     => $enabled ? 'Team members are on. Reload the page to see the Team Members menu.' : 'Team members are off. Saved team members are kept.',
            ] );
        } catch ( \Throwable $exception ) {
            wp_send_json_error( [ 'message' => $exception->getMessage() ], 500 );
        }
    }

    public static function handle_save(): void {
        self::authorize();
        $settings = TeamPageSettings::save( self::posted_values() );
        wp_send_json_success( [ 'settings' => $settings, 'message' => 'Team page settings saved.' ] );
    }

    public static function handle_preview(): void {
        self::authorize();
        $atts = array_filter( self::posted_values(), static fn( $value ): bool => '' !== $value );
        $atts['limit'] = (string) min( 6, TeamMemberDirectory::normalize_limit( $atts['limit'] ?? TeamPageSettings::all()['limit'] ) );
        $html = TeamMemberDirectory::render( $atts );
        if ( '' === $html ) {
            $html = TeamMemberDirectory::preview_html( (string) ( $atts['style'] ?? '' ), (string) ( $atts['design'] ?? 'default' ) );
        }

        wp_send_json_success( [ 'html' => $html, 'pretty' => self::pretty( $html ) ] );
    }

    /** @return array<string,string> */
    private static function posted_values(): array {
        $values = [];
        foreach ( [ 'style', 'design', 'columns', 'limit', 'orderby', 'order', 'show_excerpt', 'link_profiles', 'featured_only', 'category' ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $values[ $key ] = sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
            }
        }

        return $values;
    }

    /** Indents the grid markup one element per line for reading. */
    public static function pretty( string $html ): string {
        $html  = (string) preg_replace( '/\s*srcset="[^"]*"|\s*sizes="[^"]*"/', '', $html );
        $parts = array_values( array_filter( preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [], static fn( string $part ): bool => '' !== trim( $part ) ) );
        $out   = '';
        $depth = 0;
        $count = count( $parts );
        for ( $i = 0; $i < $count; $i++ ) {
            $part = $parts[ $i ];
            if ( str_starts_with( $part, '</' ) ) {
                $depth = max( 0, $depth - 1 );
                $out  .= str_repeat( '  ', $depth ) . $part . "\n";
                continue;
            }
            $opens = str_starts_with( $part, '<' ) && ! str_ends_with( $part, '/>' ) && ! preg_match( '/^<(img|br|hr|input|meta|link)\b/i', $part );
            // An element holding only text stays on one line: <p class="x">Text</p>.
            if ( $opens && isset( $parts[ $i + 2 ] ) && ! str_starts_with( $parts[ $i + 1 ], '<' ) && str_starts_with( $parts[ $i + 2 ], '</' ) ) {
                $out .= str_repeat( '  ', $depth ) . $part . trim( $parts[ $i + 1 ] ) . $parts[ $i + 2 ] . "\n";
                $i  += 2;
                continue;
            }
            $out .= str_repeat( '  ', $depth ) . ( $opens || str_starts_with( $part, '<' ) ? $part : trim( $part ) ) . "\n";
            if ( $opens ) {
                ++$depth;
            }
        }

        return rtrim( $out );
    }

    private static function authorize(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to manage team settings.' ], 403 );
        }
        $nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_send_json_error( [ 'message' => 'This page expired. Reload it and try again.' ], 403 );
        }
    }
}
