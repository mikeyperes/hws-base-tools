<?php namespace hws_base_tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Automatically render the Website Settings footer text near the site's
 * actual footer, with a quiet fallback when theme markup is inconsistent.
 */
function enable_footer_text_auto_injection() {
    add_action( 'wp_footer', __NAMESPACE__ . '\\hws_render_footer_text_in_footer', 25 );
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
    $config = [
        'plainText'      => $plain_text,
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
            opacity: 0.9;
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
    </style>
    <div id="hws-footer-text-root" data-hws-footer-text-placement="pending"><?php echo $footer_html; ?></div>
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

    if ( $is_rendering || ! function_exists( 'get_field' ) ) {
        return '';
    }

    $footer_text = get_field( 'website_footer_text', 'option' );

    if ( empty( $footer_text ) ) {
        $website_settings = get_field( 'website', 'option' );

        if ( is_array( $website_settings ) && ! empty( $website_settings['footer_text'] ) ) {
            $footer_text = $website_settings['footer_text'];
        }
    }

    if ( ! is_string( $footer_text ) ) {
        return '';
    }

    $footer_text = trim( $footer_text );

    if ( '' === $footer_text ) {
        return '';
    }

    $is_rendering = true;
    $footer_text = apply_filters( 'the_content', $footer_text );
    $is_rendering = false;

    return trim( $footer_text );
}
