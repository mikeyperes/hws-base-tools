<?php

declare( strict_types=1 );

namespace HWS\BaseTools\PageLayoutStyling;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class PageLayoutAdmin {
    private const NOTICE_PREFIX = 'hws_page_layout_notice_';

    public static function render(): void {
        CoreUi::render_assets();
        $selected = PageLayoutSettings::selected();
        $definitions = PageLayoutSettings::definitions();
        $discovery = PageLayoutDiscovery::current();
        $notice = self::consume_notice();
        $selected_label = $definitions[ $selected ]['label'];
        $selected_scope = PageLayoutSettings::NO_STYLE === $selected
            ? $discovery['scoped_selector']
            : 'body.hws-page-layout-style-' . $selected . ' ' . $discovery['parent_selector'];
        $template_structure = self::template_structure_code( $discovery );
        $override_css = self::override_css_code( $selected_scope, $discovery );
        ?>
        <div class="hpc-ui hws-page-layout-admin">
            <style>
                .hws-page-layout-admin{--hws-pla-accent:<?php echo esc_attr( PageLayoutFeature::elementor_primary_color() ); ?>;color:#172033}
                .hws-page-layout-admin *{box-sizing:border-box}
                .hws-pla-hero{align-items:center;background:linear-gradient(135deg,#111827 0%,#24324a 58%,var(--hws-pla-accent) 145%);border-radius:18px;color:#fff;display:flex;gap:24px;justify-content:space-between;margin-bottom:18px;overflow:hidden;padding:28px 30px;position:relative}
                .hws-pla-hero:after{background:radial-gradient(circle,rgba(255,255,255,.2) 0 2px,transparent 3px);background-size:18px 18px;content:"";inset:0 0 0 62%;opacity:.35;position:absolute}
                .hws-pla-hero>div{position:relative;z-index:1}.hws-pla-kicker{color:#cbd5e1;font-size:11px;font-weight:800;letter-spacing:.14em;margin:0 0 8px;text-transform:uppercase}.hws-pla-hero h2{color:#fff;font-size:28px;line-height:1.15;margin:0 0 8px}.hws-pla-hero p{color:#e2e8f0;font-size:14px;margin:0;max-width:690px}.hws-pla-current{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.26);border-radius:999px;font-weight:800;padding:8px 13px;white-space:nowrap}
                .hws-pla-panel{background:#fff;border:1px solid #d8dee8;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.055);margin-bottom:18px;padding:22px}.hws-pla-panel-head{align-items:flex-start;border-bottom:1px solid #edf0f4;display:flex;gap:16px;justify-content:space-between;margin-bottom:18px;padding-bottom:15px}.hws-pla-panel h3{font-size:19px;margin:0 0 5px}.hws-pla-panel-head p{color:#64748b;margin:0;max-width:860px}
                .hws-pla-grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(min(100%,390px),1fr))}.hws-pla-card{background:#fff;border:1px solid #d8dee8;border-radius:15px;cursor:pointer;display:flex;flex-direction:column;min-width:0;overflow:hidden;position:relative;transition:border-color .18s,box-shadow .18s,transform .18s}.hws-pla-card:hover{border-color:#94a3b8;box-shadow:0 12px 24px rgba(15,23,42,.08);transform:translateY(-1px)}.hws-pla-card:has(input:checked){border-color:var(--hws-pla-accent);box-shadow:0 0 0 2px color-mix(in srgb,var(--hws-pla-accent) 30%,transparent),0 13px 28px rgba(15,23,42,.1)}.hws-pla-card input{height:1px;opacity:0;position:absolute;width:1px}.hws-pla-card-copy{align-items:flex-start;display:flex;gap:10px;padding:15px 16px 14px}.hws-pla-radio{border:2px solid #94a3b8;border-radius:50%;height:18px;margin-top:1px;min-width:18px;position:relative}.hws-pla-card:has(input:checked) .hws-pla-radio{border-color:var(--hws-pla-accent)}.hws-pla-card:has(input:checked) .hws-pla-radio:after{background:var(--hws-pla-accent);border-radius:50%;content:"";inset:3px;position:absolute}.hws-pla-card strong{color:#1e293b;display:block;font-size:14px}.hws-pla-card small{color:#64748b;display:block;line-height:1.45;margin-top:4px}.hws-pla-selected{background:var(--hws-pla-accent);border-radius:999px;color:#fff;display:none;font-size:10px;font-weight:800;letter-spacing:.07em;margin-left:auto;padding:3px 8px;text-transform:uppercase}.hws-pla-card:has(input:checked) .hws-pla-selected{display:inline-flex}
                .hws-pla-preview{background:#edf1f5;border-bottom:1px solid #e5e9ef;min-height:390px;padding:24px}.hws-pla-sample{background:#fff;color:#283142;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;line-height:1.55;margin:auto;max-width:410px;min-height:342px;padding:26px 28px}.hws-pla-sample *{box-sizing:border-box}.hws-pla-sample-kicker{color:var(--hws-pla-accent);font-size:9px;font-weight:800;letter-spacing:.12em;margin:0 0 7px;text-transform:uppercase}.hws-pla-sample h4{color:#172033;font-size:24px;letter-spacing:-.035em;line-height:1.08;margin:0 0 14px}.hws-pla-sample h5{color:#172033;font-size:14px;line-height:1.25;margin:18px 0 7px}.hws-pla-sample p{margin:0 0 11px}.hws-pla-sample a{color:var(--hws-pla-accent);font-weight:700;text-decoration:underline;text-underline-offset:2px}.hws-pla-sample ul{margin:0 0 13px;padding-left:18px}.hws-pla-sample li{margin:3px 0}.hws-pla-sample blockquote{border-left:3px solid var(--hws-pla-accent);font-size:12px;font-style:italic;margin:14px 0;padding:5px 0 5px 12px}.hws-pla-sample-button{background:var(--hws-pla-accent);border-radius:5px;color:#fff;display:inline-block;font-size:10px;font-weight:800;padding:8px 12px}.hws-pla-preview--minimalist .hws-pla-sample{box-shadow:0 8px 22px rgba(15,23,42,.08)}.hws-pla-preview--editorial{background:#e9e2d5}.hws-pla-preview--editorial .hws-pla-sample{background:#fffdf8;border-bottom:4px double #5f5548;border-top:4px double #5f5548;font-family:Georgia,"Times New Roman",serif}.hws-pla-preview--editorial .hws-pla-sample h4,.hws-pla-preview--editorial .hws-pla-sample h5{font-family:Georgia,"Times New Roman",serif;letter-spacing:-.015em}.hws-pla-preview--editorial .hws-pla-sample p:first-of-type:first-letter{color:var(--hws-pla-accent);float:left;font-size:34px;font-weight:700;line-height:.8;padding:4px 5px 0 0}.hws-pla-preview--modern-card{background:linear-gradient(135deg,#e3eaf4,#f7f9fc)}.hws-pla-preview--modern-card .hws-pla-sample{border:1px solid #dbe2ec;border-radius:18px;box-shadow:0 18px 32px rgba(15,23,42,.14)}.hws-pla-preview--bold-accent{background:#edf1f5}.hws-pla-preview--bold-accent .hws-pla-sample{border-left:9px solid var(--hws-pla-accent)}.hws-pla-preview--bold-accent .hws-pla-sample h4{color:var(--hws-pla-accent);font-size:26px}.hws-pla-preview--bold-accent .hws-pla-sample h5{border-bottom:2px solid var(--hws-pla-accent);padding-bottom:5px}.hws-pla-preview--soft-canvas{background:#e8e2d9}.hws-pla-preview--soft-canvas .hws-pla-sample{background:color-mix(in srgb,var(--hws-pla-accent) 5%,#f6f2eb);border:1px solid color-mix(in srgb,var(--hws-pla-accent) 14%,#ddd6cc);border-radius:22px}.hws-pla-preview--soft-canvas .hws-pla-sample blockquote{background:rgba(255,255,255,.72);border:0;border-radius:10px;padding:9px 11px}.hws-pla-preview--classic-serif{background:#eee9df}.hws-pla-preview--classic-serif .hws-pla-sample{border-bottom:4px double #57534e;border-top:4px double #57534e;font-family:Baskerville,"Palatino Linotype",Palatino,Georgia,serif}.hws-pla-preview--classic-serif .hws-pla-sample h4,.hws-pla-preview--classic-serif .hws-pla-sample h5{font-family:Baskerville,"Palatino Linotype",Palatino,Georgia,serif;text-align:center}.hws-pla-preview--classic-serif .hws-pla-sample-kicker{text-align:center}.hws-pla-preview--none{background:repeating-linear-gradient(135deg,#f8fafc 0 10px,#edf1f5 10px 20px)}.hws-pla-preview--none .hws-pla-sample{border:1px dashed #94a3b8;box-shadow:none}.hws-pla-preview--none .hws-pla-sample-kicker{color:#64748b}.hws-pla-preview--none .hws-pla-sample a{color:#2271b1}.hws-pla-preview--none .hws-pla-sample blockquote{border-left-color:#9ca3af}.hws-pla-preview--none .hws-pla-sample-button{background:#e5e7eb;color:#374151}
                .hws-pla-actions{align-items:center;display:flex;gap:12px;margin-top:18px}.hws-pla-save{background:var(--hws-pla-accent)!important;border-color:var(--hws-pla-accent)!important;border-radius:8px!important;font-weight:700!important;min-height:38px;padding:5px 16px!important}.hws-pla-note{color:#64748b;font-size:12px}
                .hws-pla-code-grid{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr))}.hws-pla-code-view{background:#0b1220;border:1px solid #273449;border-radius:14px;min-width:0;overflow:hidden}.hws-pla-code-head{align-items:center;background:#111b2e;border-bottom:1px solid #273449;display:flex;gap:12px;justify-content:space-between;padding:12px 14px}.hws-pla-code-head strong{color:#f8fafc;font-size:13px}.hws-pla-code-head span{color:#94a3b8;display:block;font-size:11px;margin-top:2px}.hws-pla-copy{align-items:center;background:#fff!important;border:0!important;border-radius:7px!important;color:#172033!important;cursor:pointer;display:inline-flex;font-size:11px!important;font-weight:800!important;min-height:32px;padding:6px 10px!important;white-space:nowrap}.hws-pla-copy:hover,.hws-pla-copy:focus{background:#e2e8f0!important}.hws-pla-copy.is-copied{background:#dcfce7!important;color:#166534!important}.hws-pla-code-view pre{color:#d7e2f0;font:12px/1.65 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;margin:0;max-height:520px;min-height:360px;overflow:auto;padding:18px;tab-size:2;white-space:pre}.hws-pla-code-view code{background:transparent;color:inherit;padding:0}.hws-pla-template-meta{align-items:center;background:#f8fafc;border:1px solid #e5e9ef;border-radius:12px;display:flex;gap:10px;justify-content:space-between;margin-bottom:16px;padding:12px 14px}.hws-pla-template-meta p{margin:0}.hws-pla-template-meta code{word-break:break-word}.hws-pla-tech{border-top:1px solid #e5e9ef;margin-top:18px;padding-top:14px}.hws-pla-tech summary{cursor:pointer;font-weight:700}.hws-pla-facts{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin-top:12px}.hws-pla-fact{background:#f8fafc;border:1px solid #e5e9ef;border-radius:10px;padding:11px}.hws-pla-fact span{color:#64748b;display:block;font-size:10px;font-weight:800;letter-spacing:.08em;margin-bottom:4px;text-transform:uppercase}.hws-pla-fact code{background:transparent;color:#1e293b;padding:0;white-space:normal;word-break:break-word}.hws-pla-guidance{background:#f8fafc;border-left:4px solid var(--hws-pla-accent);border-radius:0 12px 12px 0;margin:16px 0 0;padding:14px 16px}.hws-pla-guidance p{margin:5px 0 0}
                @media(max-width:1050px){.hws-pla-code-grid{grid-template-columns:1fr}}
                @media(max-width:782px){.hws-pla-hero{align-items:flex-start;flex-direction:column;padding:22px}.hws-pla-current{white-space:normal}.hws-pla-panel{padding:17px}.hws-pla-panel-head{display:block}.hws-pla-grid{grid-template-columns:1fr}.hws-pla-preview{padding:15px}.hws-pla-template-meta{align-items:flex-start;flex-direction:column}.hws-pla-code-view pre{min-height:280px}.hws-pla-code-head{align-items:flex-start}}
            </style>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'danger' === $notice['tone'] ? 'error' : $notice['tone'] ); ?> inline"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
            <?php endif; ?>

            <section class="hws-pla-hero">
                <div>
                    <p class="hws-pla-kicker">Site &amp; Brand</p>
                    <h2>Default Page Content Styling</h2>
                    <p>Style the WordPress content displayed inside the site’s <strong>Default Page Template</strong>. These designs control headings, paragraphs, links, lists, quotes, tables, and images inside the template’s Post Content area. They do not replace or redesign the Elementor template itself.</p>
                </div>
                <div class="hws-pla-current">Active: <?php echo esc_html( $selected_label ); ?></div>
            </section>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hws-pla-panel">
                <input type="hidden" name="action" value="hws_page_layout_style_save">
                <?php wp_nonce_field( 'hws_page_layout_style_save' ); ?>
                <header class="hws-pla-panel-head">
                    <div><h3>Choose a Default Page content style</h3><p>Every preview below uses the same real sample content so you can compare the typography, spacing, links, lists, quote, and button treatment before selecting a style. Minimalist is the default; brand accents use the active Elementor Primary color.</p></div>
                </header>
                <div class="hws-pla-grid">
                    <?php foreach ( $definitions as $style => $definition ) : ?>
                        <label class="hws-pla-card">
                            <input type="radio" name="style" value="<?php echo esc_attr( $style ); ?>" <?php checked( $selected, $style ); ?>>
                            <span class="hws-pla-preview hws-pla-preview--<?php echo esc_attr( $style ); ?>">
                                <span class="hws-pla-sample">
                                    <span class="hws-pla-sample-kicker"><?php echo PageLayoutSettings::NO_STYLE === $style ? 'HWS styling disabled' : 'Default Page'; ?></span>
                                    <h4>Build a Stronger Digital Presence</h4>
                                    <p>A clear page helps visitors understand your work and find the <a href="#" tabindex="-1">information they need</a>.</p>
                                    <h5>What this style includes</h5>
                                    <ul><li>Readable headings and paragraphs</li><li>Consistent links, lists, and spacing</li></ul>
                                    <blockquote>Good content should feel clear, useful, and easy to explore.</blockquote>
                                    <span class="hws-pla-sample-button">Explore the guide</span>
                                </span>
                            </span>
                            <span class="hws-pla-card-copy"><span class="hws-pla-radio" aria-hidden="true"></span><span><strong><?php echo esc_html( $definition['label'] ); ?></strong><small><?php echo esc_html( $definition['description'] ); ?></small></span><span class="hws-pla-selected">Selected</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="hws-pla-actions"><button type="submit" class="button button-primary hws-pla-save">Save Content Style</button><span class="hws-pla-note">Applies to the Post Content area of Pages using the Default Page Template.</span></div>
            </form>

            <section class="hws-pla-panel">
                <header class="hws-pla-panel-head"><div><h3>Default Page Template structure &amp; CSS</h3><p>The structure is discovered from the active Default Page Template. The CSS beside it is ready to paste into a child theme or another site-owned stylesheet loaded after HWS Base Tools.</p></div></header>
                <div class="hws-pla-template-meta">
                    <p><strong><?php echo esc_html( $discovery['renderer'] ); ?></strong><br><code><?php echo esc_html( $discovery['file'] . ':' . $discovery['line'] ); ?></code></p>
                    <?php if ( $discovery['edit_url'] ) : ?><a class="button" href="<?php echo esc_url( $discovery['edit_url'] ); ?>" target="_blank" rel="noopener">Edit Default Page Template in Elementor ↗</a><?php endif; ?>
                </div>
                <div class="hws-pla-code-grid">
                    <div class="hws-pla-code-view">
                        <div class="hws-pla-code-head"><div><strong>Template structure</strong><span>Where WordPress page content is rendered</span></div><button type="button" class="button hws-pla-copy" data-hws-copy="hws-pla-template-code">Copy structure</button></div>
                        <pre><code id="hws-pla-template-code"><?php echo esc_html( $template_structure ); ?></code></pre>
                    </div>
                    <div class="hws-pla-code-view">
                        <div class="hws-pla-code-head"><div><strong>CSS override</strong><span>Scoped to the active Default Page content style</span></div><button type="button" class="button hws-pla-copy" data-hws-copy="hws-pla-css-code">Copy CSS</button></div>
                        <pre><code id="hws-pla-css-code"><?php echo esc_html( $override_css ); ?></code></pre>
                    </div>
                </div>
                <div class="hws-pla-guidance"><strong>How to override it</strong><p>Copy the CSS, paste it into a child theme or site-owned stylesheet that loads after HWS Base Tools, then change the values you need. Keep the <code>body.hws-page-layout-style-<?php echo esc_html( $selected ); ?></code> prefix to affect only the selected design. Selecting <strong>No Style</strong> disables HWS content styling.</p></div>
                <details class="hws-pla-tech"><summary>Technical selectors and renderer details</summary><div class="hws-pla-facts">
                    <div class="hws-pla-fact"><span>Active theme</span><code><?php echo esc_html( $discovery['theme'] ); ?></code></div>
                    <div class="hws-pla-fact"><span>Layout container</span><code><?php echo esc_html( $discovery['layout_selector'] ); ?></code></div>
                    <div class="hws-pla-fact"><span>Post Content widget</span><code><?php echo esc_html( $discovery['parent_selector'] ); ?></code></div>
                    <div class="hws-pla-fact"><span>HWS scope</span><code><?php echo esc_html( $discovery['scoped_selector'] ); ?></code></div>
                </div></details>
            </section>
            <script>
                (function(){
                    var root=document.currentScript.closest('.hws-page-layout-admin');
                    if(!root){return;}
                    root.addEventListener('click',function(event){
                        var button=event.target.closest('[data-hws-copy]');
                        if(!button){return;}
                        var code=document.getElementById(button.getAttribute('data-hws-copy'));
                        if(!code){return;}
                        var text=code.textContent||'';
                        var done=function(){var old=button.textContent;button.textContent='Copied';button.classList.add('is-copied');window.setTimeout(function(){button.textContent=old;button.classList.remove('is-copied');},1600);};
                        var fallback=function(){var area=document.createElement('textarea');area.value=text;area.setAttribute('readonly','');area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();var copied=false;try{copied=document.execCommand('copy');}catch(error){}area.remove();if(copied){done();}else{button.textContent='Copy failed';window.setTimeout(function(){button.textContent='Copy';},1600);}};
                        if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(done).catch(fallback);return;}
                        fallback();
                    });
                }());
            </script>
        </div>
        <?php
    }

    public static function handle_save(): void {
        if ( ! current_user_can( PluginMetadata::ADMIN_CAPABILITY ) ) {
            wp_die( esc_html__( 'You are not allowed to manage Page Layout Styling.', 'hws-base-tools' ), esc_html__( 'Forbidden', 'hws-base-tools' ), [ 'response' => 403 ] );
        }
        check_admin_referer( 'hws_page_layout_style_save' );
        $style = PageLayoutSettings::update( isset( $_POST['style'] ) ? wp_unslash( $_POST['style'] ) : '' );
        $definition = PageLayoutSettings::definitions()[ $style ];
        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            [ 'tone' => 'success', 'message' => sprintf( '%s is now the default Page layout style.', $definition['label'] ) ],
            MINUTE_IN_SECONDS
        );
        wp_safe_redirect( self::admin_url() );
        exit;
    }

    public static function admin_url(): string {
        return add_query_arg( [ 'page' => PluginMetadata::ADMIN_PAGE_SLUG, 'tab' => 'page-layout-styling' ], admin_url( 'options-general.php' ) );
    }

    /** @param array{renderer:string,file:string,line:int,layout_selector:string,parent_selector:string} $discovery */
    private static function template_structure_code( array $discovery ): string {
        if ( str_contains( $discovery['renderer'], 'Elementor Theme Builder' ) ) {
            $layout_classes = self::selector_classes( $discovery['layout_selector'] );
            $content_classes = self::selector_classes( $discovery['parent_selector'] );
            return sprintf(
                "<!-- %s -->\n<div class=\"elementor-location-single\">\n  <div class=\"%s\">\n    <div class=\"%s\">\n      <?php\n      // Elementor Pro renders the WordPress Page content here.\n      echo apply_filters( 'the_content', get_the_content() );\n      ?>\n    </div>\n  </div>\n</div>",
                str_replace( '--', '—', $discovery['renderer'] ),
                $layout_classes,
                $content_classes
            );
        }

        return sprintf(
            "<!-- Active theme template: %s:%d -->\n<main id=\"content\" class=\"site-main\">\n  <div class=\"page-content\">\n    <?php the_content(); ?>\n  </div>\n</main>",
            $discovery['file'],
            $discovery['line']
        );
    }

    /** @param array{layout_selector:string,parent_selector:string} $discovery */
    private static function override_css_code( string $selected_scope, array $discovery ): string {
        $layout_scope = 'body.hws-page-layout-styled ' . $discovery['layout_selector'];
        $accent = PageLayoutFeature::elementor_primary_color();
        return "/* Default Page Template layout container */\n{$layout_scope} {\n  width: min(100% - 40px, 1120px);\n  margin-inline: auto;\n}\n\n/* Default Page Post Content */\n{$selected_scope} {\n  --hws-page-layout-accent: {$accent};\n  max-width: 780px;\n  margin-inline: auto;\n  font-size: 18px;\n  line-height: 1.8;\n}\n\n{$selected_scope} > :is(h2, h3) {\n  letter-spacing: -0.02em;\n}\n\n{$selected_scope} a {\n  color: var(--hws-page-layout-accent);\n  text-underline-offset: 0.18em;\n}\n\n{$selected_scope} blockquote {\n  border-left: 3px solid var(--hws-page-layout-accent);\n  padding-left: 1.25em;\n}";
    }

    private static function selector_classes( string $selector ): string {
        preg_match_all( '/\.([A-Za-z0-9_-]+)/', $selector, $matches );
        $classes = array_values( array_unique( $matches[1] ?? [] ) );
        if ( array_filter( $classes, static fn( string $class ): bool => str_starts_with( $class, 'elementor-element-' ) ) ) {
            array_unshift( $classes, 'elementor-element' );
        }
        return implode( ' ', array_values( array_unique( $classes ) ) );
    }

    /** @return array{tone:string,message:string}|null */
    private static function consume_notice(): ?array {
        $key = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient( $key );
        delete_transient( $key );
        return is_array( $notice ) && isset( $notice['tone'], $notice['message'] ) ? $notice : null;
    }
}
