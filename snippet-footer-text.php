<?php namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Automatically render the Website Settings footer text near the site's
 * actual footer, with a quiet fallback when theme markup is inconsistent.
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

function hws_get_footer_text_templates(): array {
    return [
        'quiet-inline' => [
            'label'       => 'Plain',
            'description' => 'Bare minimum. No framing, no box, just the saved copy sitting cleanly in the footer.',
        ],
        'fine-divider' => [
            'label'       => 'Top Divider',
            'description' => 'Refined top rule with breathing room, for a quiet but finished footer edge.',
        ],
        'fine-print' => [
            'label'       => 'Legal Small',
            'description' => 'Small centered legal-copy treatment with softer contrast and tighter discipline.',
        ],
        'boxed-card' => [
            'label'       => 'Soft Panel',
            'description' => 'Rounded panel with a soft surface and subtle depth, without turning into a banner.',
        ],
        'accent-bar' => [
            'label'       => 'Signature Bar',
            'description' => 'Short accent rule above the text for a sharper, more intentional sign-off.',
        ],
        'stamp' => [
            'label'       => 'Micro Stamp',
            'description' => 'Compact uppercase capsule with tracking, useful when the footer needs to feel branded.',
        ],
        'italic-tagline' => [
            'label'       => 'Editorial Line',
            'description' => 'Centered italic line with a little poise, like a restrained editorial closing note.',
        ],
        'two-column' => [
            'label'       => 'Journal Columns',
            'description' => 'Longer copy breaks into neat columns on wide screens, with a restrained publication feel.',
        ],
        'pull-quote' => [
            'label'       => 'Side Note',
            'description' => 'Inline-note treatment with a left rail and slightly elevated voice.',
        ],
        'soft-shadow' => [
            'label'       => 'Elevated Card',
            'description' => 'The most designed option: rounded, lifted, and polished without looking loud.',
        ],
    ];
}

/**
 * Returns per-template CSS as a string. Single source of truth so the admin
 * Live Preview and the frontend renderer cannot disagree.
 *
 * @param string $scope 'frontend' for the live site (#hws-footer-text-root)
 *                      or 'admin' for the settings preview (.hws-ft-preview).
 */
function hws_get_footer_text_template_css( string $scope = 'frontend' ): string {
    if ( 'admin-mini' === $scope ) {
        $prefix = '.hws-ft-item-mini';
    } elseif ( 'admin' === $scope ) {
        $prefix = '.hws-ft-preview';
    } else {
        $prefix = '#hws-footer-text-root';
    }

    $is_admin_scope = in_array( $scope, [ 'admin', 'admin-mini' ], true );
    $is_mini_scope  = 'admin-mini' === $scope;

    $rule = function( string $key ) use ( $prefix ): string {
        return $prefix . '.hws-footer-text--' . $key;
    };

    $line_color       = $is_admin_scope ? 'rgba(15, 23, 42, 0.18)' : 'rgba(15, 23, 42, 0.14)';
    $frame_color      = $is_admin_scope ? 'rgba(15, 23, 42, 0.10)' : 'rgba(15, 23, 42, 0.13)';
    $surface_soft     = $is_admin_scope ? 'rgba(15, 23, 42, 0.04)' : 'rgba(15, 23, 42, 0.06)';
    $surface_strong   = $is_admin_scope ? 'rgba(15, 23, 42, 0.065)' : 'rgba(15, 23, 42, 0.085)';
    $shadow_soft      = $is_mini_scope ? '0 8px 18px rgba(15, 23, 42, 0.06)' : '0 16px 34px rgba(15, 23, 42, 0.08)';
    $shadow_strong    = $is_mini_scope ? '0 12px 22px rgba(15, 23, 42, 0.09)' : '0 22px 48px rgba(15, 23, 42, 0.14)';
    $panel_padding    = $is_mini_scope ? '0.8rem 1rem' : '1rem 1.2rem';
    $card_padding     = $is_mini_scope ? '0.95rem 1.05rem' : '1.15rem 1.35rem';
    $panel_radius     = $is_mini_scope ? '14px' : '18px';
    $card_radius      = $is_mini_scope ? '16px' : '22px';
    $column_gap       = $is_mini_scope ? '1.4rem' : '2.2rem';
    $bar_width        = $is_mini_scope ? '54px' : '68px';
    $center_bar_width = $is_mini_scope ? '42px' : '54px';

    $css = '';

    $css .= $rule( 'quiet-inline' ) . " { max-width: 42rem; opacity: 0.8; }\n";

    $css .= $rule( 'fine-divider' ) . " { max-width: 40rem; padding-top: 1.05rem; position: relative; }\n";
    $css .= $rule( 'fine-divider' ) . "::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: " . $line_color . "; }\n";
    $css .= $rule( 'fine-divider' ) . "::after { content: ''; position: absolute; top: 0; left: 0; width: " . $bar_width . "; height: 2px; border-radius: 999px; background: currentColor; opacity: 0.4; }\n";

    $css .= $rule( 'fine-print' ) . " { max-width: 38rem; margin-inline: auto; font-size: 0.79em; line-height: 1.9; text-align: center; letter-spacing: 0.01em; opacity: 0.68; }\n";

    $css .= $rule( 'boxed-card' ) . " { max-width: 44rem; margin-inline: auto; padding: " . $panel_padding . "; border-radius: " . $panel_radius . "; border: 1px solid " . $frame_color . "; background: linear-gradient(180deg, " . $surface_soft . " 0%, " . $surface_strong . " 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.48), " . $shadow_soft . "; }\n";

    $css .= $rule( 'accent-bar' ) . " { max-width: 36rem; padding-top: 1rem; position: relative; }\n";
    $css .= $rule( 'accent-bar' ) . "::before { content: ''; position: absolute; top: 0; left: 0; width: " . $bar_width . "; height: 3px; background: currentColor; opacity: 0.78; border-radius: 999px; }\n";

    $css .= $rule( 'stamp' ) . " { max-width: 30rem; margin-inline: auto; padding: 0.55rem 0.95rem; border-radius: 999px; border: 1px solid " . $frame_color . "; background: " . $surface_soft . "; font-size: 0.72em; letter-spacing: 0.22em; text-transform: uppercase; text-align: center; line-height: 1.8; opacity: 0.82; font-weight: 600; }\n";

    $css .= $rule( 'italic-tagline' ) . " { max-width: 34rem; margin-inline: auto; padding-top: 0.95rem; position: relative; font-size: 1.05em; font-style: italic; text-align: center; line-height: 1.8; opacity: 0.92; }\n";
    $css .= $rule( 'italic-tagline' ) . "::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: " . $center_bar_width . "; height: 1px; background: currentColor; opacity: 0.3; }\n";

    $css .= $rule( 'two-column' ) . " { max-width: 48rem; margin-inline: auto; font-size: 0.92em; line-height: 1.85; }\n";
    $css .= "@media (min-width: 720px) { " . $rule( 'two-column' ) . " { column-count: 2; column-gap: " . $column_gap . "; column-rule: 1px solid " . $line_color . "; } }\n";

    $css .= $rule( 'pull-quote' ) . " { max-width: 40rem; margin-inline: auto; padding: 0.45rem 0 0.45rem 1.05rem; border-left: 3px solid currentColor; background: linear-gradient(90deg, " . $surface_soft . " 0%, rgba(15, 23, 42, 0) 68%); font-size: 1.02em; line-height: 1.8; font-style: italic; opacity: 0.93; }\n";

    $css .= $rule( 'soft-shadow' ) . " { max-width: 44rem; margin-inline: auto; padding: " . $card_padding . "; border-radius: " . $card_radius . "; border: 1px solid rgba(255, 255, 255, 0.22); background: linear-gradient(180deg, rgba(255, 255, 255, 0.28) 0%, rgba(255, 255, 255, 0.12) 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42), " . $shadow_strong . "; backdrop-filter: blur(10px); }\n";

    return $css;
}

function hws_get_footer_text_template(): string {
    $templates = hws_get_footer_text_templates();
    $template  = get_option( 'hws_footer_text_template', 'quiet-inline' );

    if ( ! is_string( $template ) || ! isset( $templates[ $template ] ) ) {
        return 'quiet-inline';
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
        'innerSelectors' => [
            '.site-info',
            '.site-footer__inner',
            '.footer-inner',
            '.footer-content',
            '.footer-widgets-wrap',
            '.elementor-container',
            '.ast-builder-grid-row',
            '.container',
            '.wrap',
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
            flex-basis: 100%;
            grid-column: 1 / -1;
            margin: 0.9rem 0 0;
            font-size: 0.95em;
            line-height: 1.65;
            color: inherit;
            text-align: inherit;
        }

        #hws-footer-text-root p {
            margin: 0 0 0.6em;
        }

        #hws-footer-text-root p:last-child {
            margin-bottom: 0;
        }

        #hws-footer-text-root a {
            color: inherit;
            text-decoration: underline;
            text-underline-offset: 0.12em;
        }

        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"] {
            padding: 0 1rem 1rem;
        }

        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"].hws-footer-text--fine-divider,
        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"].hws-footer-text--accent-bar,
        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"].hws-footer-text--boxed-card,
        #hws-footer-text-root[data-hws-footer-text-placement="body-fallback"].hws-footer-text--soft-shadow {
            margin-top: 1rem;
        }

        <?php echo hws_get_footer_text_template_css( 'frontend' ); ?>
    </style>
    <div id="hws-footer-text-root" class="hws-footer-text--<?php echo esc_attr( $template ); ?>" data-hws-footer-text-placement="pending"><?php echo $footer_html; ?></div>
    <script id="hws-footer-text-script">
    (function() {
        var root = document.getElementById('hws-footer-text-root');
        if (!root) return;

        var config = <?php echo wp_json_encode( $config ); ?> || {};
        var footerSelectors = config.footerSelectors || [];
        var innerSelectors = config.innerSelectors || [];
        var plainText = normalize(config.plainText || root.textContent || '');
        var observer = null;

        function normalize(value) {
            return (value || '').replace(/\s+/g, ' ').trim();
        }

        function isUsable(node) {
            return !!(node && document.body.contains(node) && node !== root);
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

        function findInnerTarget(footerRoot) {
            if (!footerRoot) return null;

            for (var i = 0; i < innerSelectors.length; i++) {
                var nodes = footerRoot.querySelectorAll(innerSelectors[i]);
                for (var j = nodes.length - 1; j >= 0; j--) {
                    if (isUsable(nodes[j]) && !nodes[j].contains(root)) {
                        return nodes[j];
                    }
                }
            }

            return footerRoot;
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

                var target = findInnerTarget(footerRoot);

                if (target && root.parentNode !== target) {
                    target.appendChild(root);
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
