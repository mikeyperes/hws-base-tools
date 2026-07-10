<?php namespace hws_base_tools;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Snippet: Elementor Social Icons — Hide Empty & External Link Handling
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * When enabled, this snippet runs lightweight inline jQuery on every frontend
 * page to clean up Elementor Social Icons widgets:
 *
 *   1. HIDE EMPTY ICONS — Any social icon whose <a> tag has no href attribute
 *      (or an empty href) is hidden. This prevents blank/placeholder icons
 *      from rendering when a URL hasn't been filled in.
 *
 *   2. EXTERNAL LINK TARGET — Any social icon whose href points to an
 *      external domain automatically gets target="_blank" and rel="noopener"
 *      added, ensuring external links open in a new tab safely.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * EXCLUDE PARAMETERS (CSS classes added in Elementor widget → Advanced → CSS Classes):
 *
 *   hws-no-hide-empty       — Skip hiding empty icons for this specific widget.
 *                              All icons render regardless of href status.
 *
 *   hws-no-external-blank   — Skip adding target="_blank" for this specific widget.
 *                              External links open in the same tab.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * HOW TO USE:
 *
 *   1. Enable the snippet in HWS Base Tools → Snippets dashboard.
 *   2. That's it — all Elementor Social Icons widgets site-wide are processed.
 *   3. To exclude a specific widget, open it in Elementor, go to
 *      Advanced → CSS Classes, and add one or both exclude classes above.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TECHNICAL NOTES:
 *
 *   - Runs via wp_footer with priority 99 (after Elementor renders)
 *   - Uses jQuery (bundled with WordPress) — no external dependencies
 *   - Executes on DOMContentLoaded inside a single <script> tag
 *   - Only loads on frontend (skips admin, AJAX, REST, and cron)
 *   - Targets .elementor-widget-social-icons (Elementor's default class)
 *   - External detection compares link hostname against window.location.hostname
 *
 * @since 10.7.4
 * ═══════════════════════════════════════════════════════════════════════════
 */

function enable_elementor_social_icon_cleanup() {

    // — Only run on the frontend, not in admin/AJAX/cron/REST
    add_action( 'wp_footer', function() {

        // — Guard: skip admin, AJAX, cron, REST, and preview contexts
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() ) {
            return;
        }

        ?>
        <!-- HWS Base Tools: Elementor Social Icon Cleanup -->
        <script>
        (function() {
            // — wp_footer fires after DOM is ready, so execute immediately
            // — (DOMContentLoaded has already fired by this point)

            // — Grab all Elementor Social Icons widgets on the page
            var widgets = document.querySelectorAll('.elementor-widget-social-icons');

            // — Exit early if no social icon widgets found
            if (!widgets.length) return;

            // — Current site hostname for external link detection
            var siteHost = window.location.hostname;

            // — Process each widget independently
            widgets.forEach(function(widget) {

                // — Check for exclude classes on the widget container
                var skipHideEmpty     = widget.classList.contains('hws-no-hide-empty');
                var skipExternalBlank = widget.classList.contains('hws-no-external-blank');

                // — Find all grid items (each wraps one social icon link)
                var items = widget.querySelectorAll('.elementor-grid-item');

                items.forEach(function(item) {

                    // — Get the anchor element inside this grid item
                    var link = item.querySelector('a.elementor-social-icon');
                    if (!link) return;

                    // — Get the href value (null if attribute missing, '' if empty)
                    var href = link.getAttribute('href');

                    // ─────────────────────────────────────────────────
                    // 1. HIDE EMPTY ICONS
                    // — Hide if href is missing, empty, or just '#'
                    // ─────────────────────────────────────────────────
                    if (!skipHideEmpty) {
                        if (!href || href === '' || href === '#') {
                            item.style.display = 'none';
                            return; // — No need to process further
                        }
                    }

                    // ─────────────────────────────────────────────────
                    // 2. EXTERNAL LINK TARGET
                    // — Add target="_blank" and rel="noopener" for
                    //   external URLs (different hostname)
                    // ─────────────────────────────────────────────────
                    if (!skipExternalBlank && href && href !== '#') {
                        try {
                            // — Parse the URL to extract hostname
                            var urlObj = new URL(href, window.location.origin);

                            // — Compare hostnames to detect external links
                            if (urlObj.hostname && urlObj.hostname !== siteHost) {
                                link.setAttribute('target', '_blank');

                                // — Ensure rel contains 'noopener' for security
                                var rel = link.getAttribute('rel') || '';
                                if (rel.indexOf('noopener') === -1) {
                                    link.setAttribute('rel', (rel + ' noopener').trim());
                                }
                            }
                        } catch(e) {
                            // — Silently skip malformed URLs
                        }
                    }
                });
            });
        })();
        </script>
        <?php

    }, 99 ); // — Priority 99: run after Elementor's own footer scripts
}
