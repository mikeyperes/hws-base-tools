<?php namespace hws_base_tools;

/**
 * Echo a clean “Plugin Info / Shortcode Tests” panel (no return).
 * - Uses semantic tables (no style="" attributes).
 * - Emits a <style> block ONCE per request via a static flag.
 * - Each row shows: ✅/❌ status • shortcode snippet • rendered output.
 *
 * Call it directly where you want HTML printed:
 *   \hws_base_tools\display_shortcode_tests();
 */
function display_shortcode_tests(): void
{
    // helper: echo one row
    $row = function (string $label, string $shortcode): void {
        $raw       = do_shortcode($shortcode);                 // may include HTML
        $stripped  = trim(wp_strip_all_tags($raw));
        $has_value = ($stripped !== '');
        $status    = $has_value ? '✅' : '❌';

        if ($has_value && filter_var($stripped, FILTER_VALIDATE_URL)) {
            $output = '<a class="hws-sc-link" href="' . esc_url($stripped) . '" target="_blank" rel="noopener">' . esc_html($stripped) . '</a>';
        } else {
            $output = $has_value ? $raw : '<em class="hws-sc-empty">(empty)</em>';
        }

        echo '<tr class="hws-sc-row">
            <td class="hws-sc-col hws-sc-label"><span class="hws-sc-status" aria-hidden="true">'.$status.'</span> '.esc_html($label).'</td>
            <td class="hws-sc-col hws-sc-code"><code class="hws-sc-badge">'.esc_html($shortcode).'</code></td>
            <td class="hws-sc-col hws-sc-output">'.$output.'</td>
        </tr>';
    };

    // print CSS once per request
    static $css_printed = false;
    if (!$css_printed) {
        $css_printed = true;
        echo '
        <style id="hws-sc-styles">
            .hws-sc-title{margin:0 0 .5rem;font-size:20px;font-weight:700}
            .hws-sc-section{margin:20px 0}
            .hws-sc-section-head{display:flex;align-items:center;gap:.75rem;margin-bottom:.25rem}
            .hws-sc-section-title{margin:0;font-size:18px;font-weight:700}
            .hws-sc-hint{color:#6b7280;font-size:12px}
            .hws-sc-table{width:100%;border-collapse:collapse}
            .hws-sc-table th,.hws-sc-table td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top}
            .hws-sc-th-test{width:220px}.hws-sc-th-code{width:360px}
            .hws-sc-row:last-child td{border-bottom:none}
            .hws-sc-badge{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:2px 6px;font-size:12px}
            .hws-sc-link{color:#2563eb;text-decoration:none}.hws-sc-link:hover{text-decoration:underline}
            .hws-sc-empty{color:#9ca3af}.hws-sc-status{display:inline-block;margin-right:.4rem}
        </style>';
    }

    echo '<div class="panel hws-sc-panel">
        <h2 class="panel-title hws-sc-title">HWS - Base Tools Plugin Info</h2>
        <div class="panel-content hws-sc-content">';

    // ---------- Podcast Host Links ----------
    echo '<section class="hws-sc-section hws-sc-host">
        <header class="hws-sc-section-head">
            <h3 class="hws-sc-section-title">Podcast Host Links</h3>
        </header>
        <div class="hws-sc-section-body">
            <table class="hws-sc-table" role="table">
                <thead><tr>
                    <th class="hws-sc-th hws-sc-th-test">Test</th>
                    <th class="hws-sc-th hws-sc-th-code">Shortcode</th>
                    <th class="hws-sc-th hws-sc-th-output">Output</th>
                </tr></thead>
                <tbody>';
                    $row('Facebook',  '[podcast_host id="url_facebook"]');
                    $row('Instagram', '[podcast_host id="url_instagram"]');
                    $row('LinkedIn',  '[podcast_host id="url_linkedin"]');
                    $row('Website',   '[podcast_host id="url_website"]');
    echo '      </tbody>
            </table>
        </div>
    </section>';

    // ---------- Company Shortcodes ----------
    echo '<section class="hws-sc-section hws-sc-company">
        <header class="hws-sc-section-head">
            <h3 class="hws-sc-section-title">Company Shortcodes</h3>
        </header>
        <div class="hws-sc-section-body">
            <table class="hws-sc-table" role="table">
                <thead><tr>
                    <th class="hws-sc-th hws-sc-th-test">Test</th>
                    <th class="hws-sc-th hws-sc-th-code">Shortcode</th>
                    <th class="hws-sc-th hws-sc-th-output">Output</th>
                </tr></thead>
                <tbody>';
                    $row('Title',     '[company id="title"]');
                    $row('Biography', '[company id="biography"]');
                    $row('Website',   '[company id="website"]');
                    $row('Facebook',  '[company id="url_facebook"]');
                    $row('Additional Info Public Email',  '[company id="additional_public_email"]');
                    $row('Additional Title',  '[company id="additional_title"]');
                    $row('Additional Title',  '[company id="additional_public_phone"]');
    echo '      </tbody>
            </table>
        </div>
    </section>';

    // ---------- Podcast Platform URLs ----------
    echo '<section class="hws-sc-section hws-sc-podcast-urls">
        <header class="hws-sc-section-head">
            <h3 class="hws-sc-section-title">Podcast Platform URLs</h3>
            <div class="hws-sc-hint">Usage: [podcast_url social="..."]</div>
        </header>
        <div class="hws-sc-section-body">
            <table class="hws-sc-table" role="table">
                <thead><tr>
                    <th class="hws-sc-th hws-sc-th-test">Test</th>
                    <th class="hws-sc-th hws-sc-th-code">Shortcode</th>
                    <th class="hws-sc-th hws-sc-th-output">Output</th>
                </tr></thead>
                <tbody>';
                    $row('Spotify',              '[podcast_url social="spotify"]');
                    $row('SoundCloud',           '[podcast_url social="soundcloud"]');
                    $row('Google Podcast',       '[podcast_url social="google_podcast"]');
                    $row('Apple Podcast',        '[podcast_url social="apple_podcast"]');
                    $row('Amazon',               '[podcast_url social="amazon"]');
                    $row('Pandora',              '[podcast_url social="pandora"]');
                    $row('iHeart',               '[podcast_url social="iheart"]');
                    $row('Stitcher',             '[podcast_url social="stitcher"]');
                    $row('Blubrry',              '[podcast_url social="blubrry"]');
                    $row('Podchaser',            '[podcast_url social="podchaser"]');
                    $row('Deezer',               '[podcast_url social="deezer"]');
                    $row('TuneIn',               '[podcast_url social="tunein"]');
                    $row('Anghami',              '[podcast_url social="anghami"]');
                    $row('JioSaavn',             '[podcast_url social="jiosaavn"]');
                    $row('RSS',                  '[podcast_url social="rss"]');
                    $row('INC Verified Profile', '[podcast_url social="inc_verified_profile"]');
                    $row('Listen Notes',         '[podcast_url social="listen_notes"]');
                    $row('Audible',              '[podcast_url social="audible"]');
                    $row('IMDB',                 '[podcast_url social="imdb"]');
                    $row('Gaana',                '[podcast_url social="gaana"]');
    echo '      </tbody>
            </table>
        </div>
    </section>';

    // ---------- Website Content & URLs ----------
    echo '<section class="hws-sc-section hws-sc-website">
        <header class="hws-sc-section-head">
            <h3 class="hws-sc-section-title">Website Content & URLs</h3>
        </header>
        <div class="hws-sc-section-body">
            <table class="hws-sc-table" role="table">
                <thead><tr>
                    <th class="hws-sc-th hws-sc-th-test">Test</th>
                    <th class="hws-sc-th hws-sc-th-code">Shortcode</th>
                    <th class="hws-sc-th hws-sc-th-output">Output</th>
                </tr></thead>
                <tbody>';
                    $row('Email (website_url)',  '[website_url social="email"]');
                    $row('DMCA',                 '[website_content field="website_dmca"]');
                    $row('Mission Statement',    '[website_content field="website_mission_statement"]');
                    $row('Biography',            '[website_content field="website_biography"]');
                    $row('Biography Short',      '[website_content field="website_biography_short"]');
                    $row('Footer Text',          '[website_content field="website_footer_text"]');
    echo '      </tbody>
            </table>
        </div>
    </section>';

    // ---------- Founder Shortcodes ----------
    echo '<section class="hws-sc-section hws-sc-founder">
        <header class="hws-sc-section-head">
            <h3 class="hws-sc-section-title">Founder Shortcodes</h3>
        </header>
        <div class="hws-sc-section-body">
            <table class="hws-sc-table" role="table">
                <thead><tr>
                    <th class="hws-sc-th hws-sc-th-test">Test</th>
                    <th class="hws-sc-th hws-sc-th-code">Shortcode</th>
                    <th class="hws-sc-th hws-sc-th-output">Output</th>
                </tr></thead>
                <tbody>';
                    $row('Founder (website_url)', '[website_url social="founder"]');
                    $row('Title',                  '[founder id="title"]');
                    $row('Biography',              '[founder id="biography"]');
                    $row('Website',                '[founder id="website"]');
                    $row('Facebook',               '[founder id="url_facebook"]');
                    $row('Instagram',              '[founder id="url_instagram"]');
    echo '      </tbody>
            </table>
        </div>
    </section>';

    echo '</div></div>';
}
