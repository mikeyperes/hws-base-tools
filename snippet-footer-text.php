<?php namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Auto-injects the saved Footer Text as a full-width band at the very bottom
 * of the theme footer. Each template is its own self-contained section so it
 * reads as a natural continuation of the footer, not an inset panel.
 */
function enable_footer_text_auto_injection() {
    if ( is_admin() || ! hws_is_footer_text_feature_enabled() ) {
        return;
    }

    add_action( 'wp_footer', __NAMESPACE__ . '\\hws_render_footer_text_in_footer', 25 );
}

function hws_is_footer_text_module_enabled(): bool {
    return (bool) get_option( 'enable_footer_text_auto_injection', false );
}

function hws_is_footer_text_feature_enabled(): bool {
    return (bool) get_option( 'hws_footer_text_feature_enabled', false );
}

/**
 * Map legacy template keys to the redesigned set so saved selections keep
 * working without the user touching anything.
 */
function hws_footer_text_legacy_template_map(): array {
    return [
        'quiet-inline'   => 'whisper',
        'fine-divider'   => 'hairline',
        'fine-print'     => 'colophon',
        'boxed-card'     => 'keyline',
        'accent-bar'     => 'bookend',
        'stamp'          => 'marquee',
        'italic-tagline' => 'editorial',
        'two-column'     => 'broadsheet',
        'pull-quote'     => 'spotlight',
        'soft-shadow'    => 'monolith',
    ];
}

function hws_get_footer_text_templates(): array {
    return [
        'whisper' => [
            'label'       => 'Whisper',
            'description' => 'Bare text, transparent, blends into the footer. The quietest possible sign-off.',
            'tier'        => 'minimal',
        ],
        'hairline' => [
            'label'       => 'Hairline',
            'description' => 'A single thin rule above the text. Adds the lightest touch of structure without weight.',
            'tier'        => 'minimal',
        ],
        'colophon' => [
            'label'       => 'Colophon',
            'description' => 'Small caps with wide tracking framed by short rules, like the imprint on the back of a book.',
            'tier'        => 'minimal',
        ],
        'bookend' => [
            'label'       => 'Bookend',
            'description' => 'Centered text held between two thin rules, top and bottom. Calm and balanced.',
            'tier'        => 'light',
        ],
        'keyline' => [
            'label'       => 'Keyline',
            'description' => 'Soft tinted band with a crisp accent line on top. Quietly intentional.',
            'tier'        => 'light',
        ],
        'editorial' => [
            'label'       => 'Editorial',
            'description' => 'Serif italic in the center with a single hairline above. A magazine-style closing line.',
            'tier'        => 'medium',
        ],
        'broadsheet' => [
            'label'       => 'Broadsheet',
            'description' => 'Newspaper-style band with rules above and below. Long copy breaks into columns on wide screens.',
            'tier'        => 'medium',
        ],
        'marquee' => [
            'label'       => 'Marquee',
            'description' => 'Bold dark band with confident centered type. Strong, branded sign-off.',
            'tier'        => 'heavy',
        ],
        'spotlight' => [
            'label'       => 'Spotlight',
            'description' => 'Dramatic dark band with a soft center glow. Built to draw the eye.',
            'tier'        => 'heavy',
        ],
        'monolith' => [
            'label'       => 'Monolith',
            'description' => 'Solid black slab with a precise white accent. The most decisive option.',
            'tier'        => 'heavy',
        ],
    ];
}

/**
 * Single source of truth for template CSS. Used by the live frontend, the
 * settings preview, and each compact preview tile in the picker.
 *
 * @param string $scope 'frontend' targets #hws-footer-text-root,
 *                      'admin' targets .hws-ft-preview,
 *                      'admin-mini' targets .hws-ft-item-mini.
 */
function hws_get_footer_text_template_css( string $scope = 'frontend' ): string {
    if ( 'admin-mini' === $scope ) {
        $prefix = '.hws-ft-item-mini';
        $is_admin = true;
        $is_mini  = true;
    } elseif ( 'admin' === $scope ) {
        $prefix = '.hws-ft-preview';
        $is_admin = true;
        $is_mini  = false;
    } else {
        $prefix = '#hws-footer-text-root';
        $is_admin = false;
        $is_mini  = false;
    }

    $rule = function( string $key ) use ( $prefix ): string {
        return $prefix . '.hws-footer-text--' . $key;
    };

    // Padding tunings: admin-mini keeps the previews compact, admin (live preview)
    // and frontend get the full breathing room.
    $pad_minimal  = $is_mini ? '0.7rem 0.9rem' : ( $is_admin ? '1.1rem 1.25rem' : '1.2rem 1.5rem' );
    $pad_light    = $is_mini ? '0.85rem 0.95rem' : ( $is_admin ? '1.3rem 1.4rem' : '1.5rem 1.5rem' );
    $pad_medium   = $is_mini ? '0.95rem 1rem' : ( $is_admin ? '1.45rem 1.4rem' : '1.7rem 1.5rem' );
    $pad_heavy    = $is_mini ? '1.05rem 1rem' : ( $is_admin ? '1.6rem 1.4rem' : '1.9rem 1.5rem' );
    $pad_monolith = $is_mini ? '1.1rem 1rem' : ( $is_admin ? '1.7rem 1.4rem' : '2.1rem 1.5rem' );

    $rule_short_w = $is_mini ? '28px' : '48px';
    $rule_long_w  = $is_mini ? '40px' : '64px';
    $accent_w     = $is_mini ? '54px' : '80px';

    $col_gap = $is_mini ? '1.4rem' : '2.4rem';

    $css = '';

    // ---- WHISPER ------------------------------------------------------------
    $css .= $rule( 'whisper' ) . " { display:block; padding:" . $pad_minimal . "; text-align:center; font-size:" . ( $is_mini ? '0.78em' : '0.86em' ) . "; line-height:1.7; opacity:0.72; letter-spacing:0.005em; }\n";

    // ---- HAIRLINE -----------------------------------------------------------
    $css .= $rule( 'hairline' ) . " { display:block; position:relative; padding:" . $pad_minimal . "; text-align:center; font-size:" . ( $is_mini ? '0.8em' : '0.88em' ) . "; line-height:1.7; opacity:0.85; }\n";
    $css .= $rule( 'hairline' ) . "::before { content:''; position:absolute; top:0; left:50%; transform:translateX(-50%); width:min(640px, 78%); height:1px; background:currentColor; opacity:0.18; }\n";

    // ---- COLOPHON -----------------------------------------------------------
    $css .= $rule( 'colophon' ) . " { display:block; padding:" . $pad_light . "; text-align:center; font-size:" . ( $is_mini ? '0.62em' : '0.7em' ) . "; letter-spacing:0.22em; text-transform:uppercase; line-height:2.1; opacity:0.7; font-weight:500; }\n";
    $css .= $rule( 'colophon' ) . "::before, " . $rule( 'colophon' ) . "::after { content:''; display:block; width:" . $rule_short_w . "; height:1px; background:currentColor; opacity:0.42; margin:0 auto; }\n";
    $css .= $rule( 'colophon' ) . "::before { margin-bottom:" . ( $is_mini ? '0.55rem' : '0.85rem' ) . "; }\n";
    $css .= $rule( 'colophon' ) . "::after  { margin-top:"  . ( $is_mini ? '0.55rem' : '0.85rem' ) . "; }\n";

    // ---- BOOKEND ------------------------------------------------------------
    $css .= $rule( 'bookend' ) . " { display:block; position:relative; padding:" . $pad_light . "; text-align:center; font-size:" . ( $is_mini ? '0.84em' : '0.94em' ) . "; line-height:1.75; }\n";
    $css .= $rule( 'bookend' ) . "::before, " . $rule( 'bookend' ) . "::after { content:''; position:absolute; left:0; right:0; height:1px; background:currentColor; opacity:0.2; }\n";
    $css .= $rule( 'bookend' ) . "::before { top:0; }\n";
    $css .= $rule( 'bookend' ) . "::after  { bottom:0; }\n";

    // ---- KEYLINE ------------------------------------------------------------
    $css .= $rule( 'keyline' ) . " { display:block; position:relative; padding:" . $pad_light . "; text-align:center; font-size:" . ( $is_mini ? '0.86em' : '0.96em' ) . "; line-height:1.75; background:rgba(0,0,0,0.07); }\n";
    $css .= $rule( 'keyline' ) . "::before { content:''; position:absolute; top:0; left:50%; transform:translateX(-50%); width:" . $accent_w . "; height:2px; background:currentColor; opacity:0.55; border-radius:0 0 2px 2px; }\n";

    // ---- EDITORIAL ----------------------------------------------------------
    $css .= $rule( 'editorial' ) . " { display:block; position:relative; padding:" . $pad_medium . "; text-align:center; font-family:Georgia, 'Times New Roman', 'Source Serif Pro', serif; font-style:italic; font-size:" . ( $is_mini ? '0.95em' : '1.06em' ) . "; line-height:1.8; opacity:0.94; }\n";
    $css .= $rule( 'editorial' ) . "::before { content:''; position:absolute; top:0; left:50%; transform:translateX(-50%); width:" . $rule_long_w . "; height:1px; background:currentColor; opacity:0.45; }\n";

    // ---- BROADSHEET ---------------------------------------------------------
    $css .= $rule( 'broadsheet' ) . " { display:block; position:relative; padding:" . $pad_medium . "; font-family:Georgia, 'Times New Roman', 'Source Serif Pro', serif; font-size:" . ( $is_mini ? '0.88em' : '1em' ) . "; line-height:1.85; text-align:center; letter-spacing:0.005em; hyphens:auto; }\n";
    $css .= $rule( 'broadsheet' ) . " > * { max-width:" . ( $is_mini ? 'none' : '780px' ) . "; margin-left:auto; margin-right:auto; }\n";
    $css .= $rule( 'broadsheet' ) . "::before, " . $rule( 'broadsheet' ) . "::after { content:''; position:absolute; left:" . ( $is_mini ? '8%' : '15%' ) . "; right:" . ( $is_mini ? '8%' : '15%' ) . "; height:1px; background:currentColor; opacity:0.3; }\n";
    $css .= $rule( 'broadsheet' ) . "::before { top:0; }\n";
    $css .= $rule( 'broadsheet' ) . "::after  { bottom:0; }\n";

    // ---- MARQUEE ------------------------------------------------------------
    $css .= $rule( 'marquee' ) . " { display:block; position:relative; padding:" . $pad_heavy . "; text-align:center; font-size:" . ( $is_mini ? '0.78em' : '0.94em' ) . "; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; line-height:1.7; background:#111111; color:rgba(255,255,255,0.92); }\n";
    $css .= $rule( 'marquee' ) . "::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.7) 50%, rgba(255,255,255,0) 100%); }\n";

    // ---- SPOTLIGHT ----------------------------------------------------------
    $css .= $rule( 'spotlight' ) . " { display:block; position:relative; padding:" . $pad_heavy . "; text-align:center; font-size:" . ( $is_mini ? '0.86em' : '1.04em' ) . "; font-weight:500; letter-spacing:0.02em; line-height:1.7; background:radial-gradient(ellipse at center, #2a2a2a 0%, #141414 60%, #0a0a0a 100%); color:rgba(255,255,255,0.95); overflow:hidden; }\n";
    $css .= $rule( 'spotlight' ) . "::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at center, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 60%); pointer-events:none; }\n";

    // ---- MONOLITH -----------------------------------------------------------
    $css .= $rule( 'monolith' ) . " { display:block; position:relative; padding:" . $pad_monolith . "; text-align:center; font-size:" . ( $is_mini ? '0.84em' : '1em' ) . "; font-weight:500; line-height:1.75; background:#000000; color:#f4f4f5; letter-spacing:0.01em; }\n";
    $css .= $rule( 'monolith' ) . "::before { content:''; position:absolute; top:0; left:50%; transform:translateX(-50%); width:" . $accent_w . "; height:" . ( $is_mini ? '3px' : '4px' ) . "; background:#ffffff; }\n";

    // ---- LINK STYLING (default for inheriting templates) --------------------
    // Defensive overrides — themes commonly target <a> tags with large
    // font-size, weight, transform, etc., which would distort the band.
    $css .= $prefix . " a { color:inherit !important; font-size:inherit !important; font-weight:500 !important; font-family:inherit !important; line-height:inherit !important; text-transform:inherit !important; letter-spacing:inherit !important; text-decoration:none !important; border-bottom:1px solid currentColor; padding-bottom:1px; transition:opacity .18s ease, border-color .18s ease, color .18s ease, background-size .18s ease; opacity:0.85; box-shadow:none !important; background:none; }\n";
    $css .= $prefix . " a:hover, " . $prefix . " a:focus { opacity:1; border-bottom-color:currentColor; }\n";

    // Light/medium templates: gentler underline so links don't shout.
    foreach ( [ 'whisper', 'hairline', 'colophon', 'bookend', 'keyline', 'editorial', 'broadsheet' ] as $key ) {
        $css .= $rule( $key ) . " a { border-bottom-color:transparent !important; background-image:linear-gradient(currentColor, currentColor) !important; background-repeat:no-repeat !important; background-size:100% 1px !important; background-position:0 100% !important; opacity:0.85; }\n";
        $css .= $rule( $key ) . " a:hover, " . $rule( $key ) . " a:focus { opacity:1; background-size:100% 2px !important; }\n";
    }

    // Heavy templates: white-on-dark links with refined underline.
    foreach ( [ 'marquee', 'spotlight', 'monolith' ] as $key ) {
        $css .= $rule( $key ) . " a { color:#ffffff !important; border-bottom:1px solid rgba(255,255,255,0.55) !important; background:none !important; padding-bottom:2px; opacity:0.96; }\n";
        $css .= $rule( $key ) . " a:hover, " . $rule( $key ) . " a:focus { border-bottom-color:#ffffff !important; opacity:1; }\n";
    }

    return $css;
}

function hws_get_footer_text_template(): string {
    $templates = hws_get_footer_text_templates();
    $template  = get_option( 'hws_footer_text_template', 'whisper' );

    if ( ! is_string( $template ) ) {
        return 'whisper';
    }

    $legacy = hws_footer_text_legacy_template_map();
    if ( isset( $legacy[ $template ] ) ) {
        $template = $legacy[ $template ];
    }

    if ( ! isset( $templates[ $template ] ) ) {
        return 'whisper';
    }

    return $template;
}

function hws_get_footer_text_raw(): string {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    $footer_text = get_field( 'website_footer_text', 'option' );

    if ( empty( $footer_text ) ) {
        $website_settings = get_field( 'website', 'option' );

        if ( is_array( $website_settings ) && ! empty( $website_settings['footer_text'] ) ) {
            $footer_text = $website_settings['footer_text'];
        }
    }

    return is_string( $footer_text ) ? $footer_text : '';
}

function hws_save_footer_text_raw( string $content ): bool {
    $content = trim( $content );

    if ( function_exists( 'update_field' ) ) {
        update_field( 'website_footer_text', $content, 'option' );

        if ( hws_get_footer_text_raw() === $content ) {
            return true;
        }

        update_field( 'field_68420a173f1aa', $content, 'option' );

        if ( hws_get_footer_text_raw() === $content ) {
            return true;
        }
    }

    update_option( 'options_website_footer_text', $content );

    return true;
}

function hws_render_footer_text_in_footer() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || is_preview() ) {
        return;
    }

    $footer_html = hws_get_footer_text_markup();

    if ( '' === $footer_html ) {
        return;
    }

    $plain_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $footer_html ) ) );
    $template   = hws_get_footer_text_template();
    $config = [
        'plainText'       => $plain_text,
        'footerSelectors' => [
            'footer[role="contentinfo"]',
            'footer#colophon',
            '#colophon',
            'footer.site-footer',
            '.elementor-location-footer',
            '[data-elementor-type="footer"]',
            '.site-footer',
            '[role="contentinfo"]',
            'footer',
        ],
        'template' => $template,
    ];
    ?>
    <style id="hws-footer-text-style">
        #hws-footer-text-root {
            display: block;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            margin: 0;
            color: inherit;
            font-family: inherit;
        }

        #hws-footer-text-root p {
            margin: 0 0 0.55em;
        }

        #hws-footer-text-root p:last-child {
            margin-bottom: 0;
        }

        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"] {
            margin-top: 0;
        }

        <?php echo hws_get_footer_text_template_css( 'frontend' ); ?>
    </style>
    <div id="hws-footer-text-root" class="hws-footer-text--<?php echo esc_attr( $template ); ?>" data-hws-footer-text-template="<?php echo esc_attr( $template ); ?>" data-hws-footer-text-placement="pending"><?php echo $footer_html; ?></div>
    <script id="hws-footer-text-script" data-no-optimize="1" data-cfasync="false">
    (function() {
        var root = document.getElementById('hws-footer-text-root');
        if (!root) return;

        var config = <?php echo wp_json_encode( $config ); ?> || {};
        var footerSelectors = config.footerSelectors || [];
        var plainText = normalize(config.plainText || root.textContent || '');
        var observer = null;

        function normalize(value) {
            return (value || '').replace(/\s+/g, ' ').trim();
        }

        function isUsable(node) {
            return !!(node && document.body.contains(node) && node !== root && !hasHiddenAncestor(node));
        }

        function hasHiddenAncestor(node) {
            var current = node;

            while (current && current !== document.body) {
                if (current.nodeType !== 1) {
                    current = current.parentElement;
                    continue;
                }

                if (current.hasAttribute('hidden') || current.getAttribute('aria-hidden') === 'true') {
                    return true;
                }

                var className = current.getAttribute('class') || '';
                if (/(^|\s)(elementor-hidden-desktop|elementor-hidden-tablet|elementor-hidden-mobile|screen-reader-text|sr-only|hidden)(\s|$)/.test(className)) {
                    return true;
                }

                var style = (current.getAttribute('style') || '').replace(/\s+/g, '').toLowerCase();
                if (style.indexOf('display:none') !== -1 || style.indexOf('visibility:hidden') !== -1) {
                    return true;
                }

                current = current.parentElement;
            }

            return false;
        }

        function findFooterRoot() {
            for (var i = 0; i < footerSelectors.length; i++) {
                var nodes = document.querySelectorAll(footerSelectors[i]);
                for (var j = nodes.length - 1; j >= 0; j--) {
                    if (isUsable(nodes[j])) {
                        return nodes[j];
                    }
                }
            }

            return null;
        }

        function footerAlreadyContainsText(footerRoot) {
            if (!footerRoot || !plainText) return false;
            if (footerRoot.contains(root)) return false;

            return normalize(footerRoot.textContent || '').indexOf(plainText) !== -1;
        }

        function setPlacement(value) {
            root.setAttribute('data-hws-footer-text-placement', value);
        }

        function mount() {
            if (!document.body) return false;

            var footerRoot = findFooterRoot();

            if (footerRoot) {
                if (footerAlreadyContainsText(footerRoot)) {
                    root.remove();
                    return true;
                }

                if (root.parentNode !== footerRoot) {
                    footerRoot.appendChild(root);
                } else if (footerRoot.lastChild !== root) {
                    footerRoot.appendChild(root);
                }

                setPlacement('footer');
                return true;
            }

            if (root.parentNode !== document.body) {
                document.body.appendChild(root);
            }

            setPlacement('body-fallback');
            return false;
        }

        function stopObserver() {
            if (observer) {
                observer.disconnect();
                observer = null;
            }
        }

        function startObserver() {
            if (!document.body || observer) return;

            observer = new MutationObserver(function() {
                if (mount()) {
                    stopObserver();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });
            window.setTimeout(stopObserver, 8000);
        }

        if (!mount()) {
            if (document.readyState === 'complete') {
                startObserver();
            } else {
                window.addEventListener('load', function() {
                    mount();
                    startObserver();
                }, { once: true });
            }
        }
    })();
    </script>
    <?php
}

function hws_get_footer_text_markup(): string {
    static $is_rendering = false;

    if ( $is_rendering ) {
        return '';
    }

    $footer_text = trim( hws_get_footer_text_raw() );

    if ( '' === $footer_text ) {
        return '';
    }

    $is_rendering = true;
    $footer_text = apply_filters( 'the_content', $footer_text );
    $is_rendering = false;

    return trim( $footer_text );
}
