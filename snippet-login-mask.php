<?php namespace hws_base_tools;

if (!defined('ABSPATH')) { exit; }

/**
 * Final class for predictable behavior (no subclassing).
 */
final class Login_Masking {

    /* ============================================================
     *                         CONSTANTS
     * ============================================================ */

/** Default masked login slug (can be changed in Settings). */
const DEFAULT_SLUG = 'hexa-admin';
    /** Options key (single array). */
    const OPT_KEY = 'hws_login_mask_options';

    /** Defaults. */
    const DEFAULT_ENABLED          = true;
    const DEFAULT_HIDE_WP_ADMIN    = true;    // 404 /wp-admin/ for logged-out users (ajax & upload allowed)
    const DEFAULT_COMPAT_WP_TOOL   = true;    // allow "WP Toolkit" UA to follow redirect from /wp-login.php
    const DEFAULT_ALLOWLIST_IPS    = '';      // CSV: "127.0.0.1, 51.81.93.236, 10.0.0.0/8"
    const DEFAULT_WELL_KNOWN       = true;    // serve /.well-known/hws-login.json

    /** Emergency query string: /?hws=bypass or /?hws=repair */
    const QP_EMERGENCY             = 'hws';


    /* ============================================================
     *                           BOOTSTRAP
     * ============================================================ */

    public static function bootstrap() {
        // Global kill switch (wp-config.php): define('HWS_DISABLE_LOGIN_MASKING', true);
        if (defined('HWS_DISABLE_LOGIN_MASKING') && HWS_DISABLE_LOGIN_MASKING) {
            return;
        }

        // Activation/deactivation for normal plugin installs.
        if (function_exists('register_activation_hook')) {
            \register_activation_hook(__FILE__, [__CLASS__, 'activate']);
            \register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        }

        // Emergency actions first.
        add_action('init', [__CLASS__, 'maybe_emergency'], 0);

        // Early, rewrite-free fallback so /hexa-admin works even if rules are missing.
        add_action('init', [__CLASS__, 'serve_masked_login_early_fallback'], 1);

        // Rewrites + routing.
        add_action('init', [__CLASS__, 'add_rewrites'], 5);
        add_filter('query_vars', [__CLASS__, 'register_qv']);
        add_action('template_redirect', [__CLASS__, 'serve_masked_login'], 0);
        add_action('template_redirect', [__CLASS__, 'serve_well_known'], 0);

        // Stealth/blocks (wp-login.php & wp-admin/).
        add_action('init', [__CLASS__, 'maybe_block_default_endpoints'], 2);


        if (!empty(self::opts()['enabled'])) {
            add_filter('login_url', [__CLASS__, 'filter_login_url'], 10, 3);
            add_filter('lostpassword_url', [__CLASS__, 'filter_lostpassword_url'], 10, 2);
            add_filter('register_url', [__CLASS__, 'filter_register_url'], 10);
            add_filter('site_url', [__CLASS__, 'filter_site_url'], 10, 4);
            add_filter('network_site_url', [__CLASS__, 'filter_site_url'], 10, 4);
            add_filter('wp_redirect', [__CLASS__, 'filter_redirect'], 10, 2);
        }

   
        // Simple admin UI (Tools → Login Masking).
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Ensure options exist.
        self::seed_options();
    }


    /* ============================================================
     *                     OPTIONS & HELPERS
     * ============================================================ */

     public static function defaults(): array {
        return [
            'legacy_slugs'     => [],
            'enabled'          => self::DEFAULT_ENABLED,
            'slug'             => self::DEFAULT_SLUG, // user-editable
            'hide_wp_admin'    => self::DEFAULT_HIDE_WP_ADMIN,
            'compat_wptoolkit' => self::DEFAULT_COMPAT_WP_TOOL,
            'allowlist_ips'    => self::DEFAULT_ALLOWLIST_IPS,
            'well_known'       => self::DEFAULT_WELL_KNOWN,
        ];
    }

    public static function opts(): array {
        $o = get_option(self::OPT_KEY, []);
        return \wp_parse_args(is_array($o) ? $o : [], self::defaults());
    }

    public static function slug(): string {
        $o = self::opts();
        $s = isset($o['slug']) ? sanitize_title_with_dashes($o['slug']) : self::FIXED_SLUG;
        return $s ?: self::FIXED_SLUG;
    }

    public static function login_url(string $redirect = '', bool $reauth = false): string {
        $url  = home_url('/' . self::slug() . '/');
        $args = [];
        if ($redirect !== '') $args['redirect_to'] = $redirect;
        if ($reauth)         $args['reauth'] = '1';
        return $args ? add_query_arg($args, $url) : $url;
    }

    private static function seed_options(): void {
        $existing = get_option(self::OPT_KEY, null);
        if (!is_array($existing)) add_option(self::OPT_KEY, self::defaults(), '', 'no');
    }

    /** Best-effort purge across common stacks. */
    private static function purge_cache_best_effort(): void {
        if (function_exists('wp_cache_flush'))       { @wp_cache_flush(); }
        if (function_exists('wp_cache_clear_cache')) { @wp_cache_clear_cache(); } // WP Super Cache
        if (function_exists('w3tc_flush_all'))       { @w3tc_flush_all(); }       // W3TC
        if (function_exists('rocket_clean_domain'))  { @rocket_clean_domain(); }  // WP Rocket
        if (function_exists('rocket_clean_minify'))  { @rocket_clean_minify(); }
        if (function_exists('do_action'))            { @do_action('litespeed_purge_all'); } // LiteSpeed
        if (function_exists('do_action'))            { @do_action('hws_base_tools_purge_all'); } // custom hook
    }

    /** CSV IP or CIDR allowlist check. */
    private static function ip_allowed(string $ip): bool {
        $csv  = self::opts()['allowlist_ips'] ?? '';
        $csv  = apply_filters('hws_base_tools/login_mask_allowlist', $csv);
        $list = array_filter(array_map('trim', explode(',', $csv)));
        if (!$list) return false;

        foreach ($list as $item) {
            if ($item === $ip) return true;
            if (strpos($item, '/') !== false && self::cidr_match($ip, $item)) return true;
        }
        return false;
    }

    /** IPv4/IPv6 CIDR match. */
    private static function cidr_match(string $ip, string $cidr): bool {
        [$subnet, $mask] = explode('/', $cidr, 2);

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mask     = (int)$mask;
            $ip_dec   = ip2long($ip);
            $sub_dec  = ip2long($subnet);
            $mask_dec = ~((1 << (32 - $mask)) - 1);
            return ($ip_dec & $mask_dec) === ($sub_dec & $mask_dec);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $mask   = (int)$mask;
            $ip_bin  = inet_pton($ip);
            $sub_bin = inet_pton($subnet);
            $bytes = intdiv($mask, 8);
            $bits  = $mask % 8;

            if ($bytes && substr($ip_bin, 0, $bytes) !== substr($sub_bin, 0, $bytes)) return false;
            if ($bits) {
                $mask_byte = chr((~(0xff >> $bits)) & 0xff);
                return (($ip_bin[$bytes] & $mask_byte) === ($sub_bin[$bytes] & $mask_byte));
            }
            return true;
        }

        return false;
    }

    /** True when the request looks like WP Toolkit (Plesk) automation. */
    private static function is_tool_request(): bool {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($ua, 'WP Toolkit') !== false
            || stripos($ua, 'WordPress Toolkit') !== false
            || (stripos($ua, 'Plesk') !== false && stripos($ua, 'wp-toolkit') !== false);
    }


    /* ============================================================
     *                  LIFECYCLE / REWRITES
     * ============================================================ */

    public static function activate()  { self::flush_rewrites(); }
    public static function deactivate(){ self::flush_rewrites(); }

    private static function flush_rewrites(): void {
        self::add_rewrites();
        flush_rewrite_rules(false);
    }


    /* ============================================================
     *                     EMERGENCY HANDLERS
     * ============================================================ */

    /**
     * /?hws=bypass  → serve native wp-login.php immediately
     * /?hws=repair  → register + flush rewrites, purge caches, redirect to masked login
     */
    public static function maybe_emergency(): void {
        if (empty($_GET[self::QP_EMERGENCY])) return;

        $action = strtolower(sanitize_text_field((string) $_GET[self::QP_EMERGENCY]));

        if ($action === 'bypass') {
            self::serve_core_login_now();
        }

        if ($action === 'repair') {
            self::flush_rewrites();
            self::purge_cache_best_effort();
            wp_safe_redirect(self::login_url());
            exit;
        }
    }


    /** Keep slug safe: lowercase, dashes, not reserved, no slashes. */
private static function sanitize_slug_value(string $slug): string {
    if (!function_exists('sanitize_title')) {
        // very early edge case; be conservative
        $slug = strtolower(preg_replace('~[^a-z0-9\-]+~', '-', $slug));
    } else {
        $slug = sanitize_title($slug);
    }
    $slug = trim($slug, '/');
    if ($slug === '') { $slug = self::DEFAULT_SLUG; }

    // Disallow reserved/problematic paths
    $reserved = ['wp-admin','wp-login','wp-login.php','.well-known'];
    if (in_array($slug, $reserved, true)) {
        $slug = self::DEFAULT_SLUG;
    }
    return $slug;
}

    /* ============================================================
     *                     REWRITES & ROUTING
     * ============================================================ */

    public static function register_qv(array $vars): array {
        $vars[] = 'hws_login';
        $vars[] = 'hws_login_meta';
        return $vars;
    }

    public static function add_rewrites(): void {
        $o = self::opts();
        if (empty($o['enabled'])) return;

        $slug = self::slug();

        // /{slug}/ → index.php?hws_login=1
        add_rewrite_rule('^' . preg_quote($slug, '~') . '/?$', 'index.php?hws_login=1', 'top');

        // /.well-known/hws-login.json → index.php?hws_login_meta=1
        if (!empty($o['well_known'])) {
            add_rewrite_rule('^\.well-known/hws-login\.json$', 'index.php?hws_login_meta=1', 'top');
        }
    }

    /** Serve native login at the masked route (rewrite path). */
    public static function serve_masked_login(): void {
        if (!get_query_var('hws_login')) return;

        // Disable caches for login page.
        if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);

        self::serve_core_login_now();
    }

    /** Serve native login if request path equals /hexa-admin, even without rewrites. */
    public static function serve_masked_login_early_fallback(): void {
        $o = self::opts();
        if (empty($o['enabled'])) return;

        $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $req_path = rtrim($req_path ?? '/', '/');
        if (strcasecmp(trim($req_path, '/'), self::slug()) === 0) {
            self::serve_core_login_now();
        }
    }

    /** Discovery JSON. */
    public static function serve_well_known(): void {
        if (!get_query_var('hws_login_meta')) return;
        $o = self::opts();
        if (empty($o['well_known'])) return;

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([
            'login_url'        => self::login_url(),
            'slug'             => self::slug(),
            'plugin'           => 'hws_base_tools',
            'compat_wptoolkit' => !empty($o['compat_wptoolkit']),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
/** Include the native login file safely (PHP8/Xdebug friendly) and exit. */
private static function serve_core_login_now(): void {
    // Make sure debug output never leaks onto the login page in production.
    if (!headers_sent()) {
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @ini_set('html_errors', '0');
    }

    // PHP 8: preseed variables wp-login.php may read before setting.
    // (Because we're including it early in our own function scope.)
    $user_login = '';
    $user_pass  = '';
    $error      = '';
    $errors     = class_exists('\WP_Error') ? new \WP_Error() : null;

    // Also mark as a non-cacheable response.
    if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
    if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
    if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);

    require_once ABSPATH . 'wp-login.php';
    exit;
}


    /* ============================================================
     *                   STEALTH / BLOCKING
     * ============================================================ */

     public static function maybe_block_default_endpoints(): void {
        $o = self::opts();
        if (empty($o['enabled'])) return;
    
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rtrim($path ?? '/', '/');
    
        // Kill/redirect old slugs so they stop working once slug changes.
$legacy = isset(self::opts()['legacy_slugs']) ? (array) self::opts()['legacy_slugs'] : [];
$req    = ltrim($path, '/');
if (in_array($req, $legacy, true) || in_array(rtrim($req,'/').'/', $legacy, true)) {
    $is_tool  = self::is_tool_request();
    $is_allow = self::ip_allowed($_SERVER['REMOTE_ADDR'] ?? '');

    if ($is_tool || $is_allow) {
        // Let tooling discover the new URL
        header('X-Redirect-By: hws_base_tools');
        wp_safe_redirect(self::login_url());
        exit;
    }
    status_header(404);
    nocache_headers();
    exit;
}


        // Always allow admin-ajax.php and async-upload.php
        if (preg_match('~^/wp-admin/(admin-ajax\.php|async-upload\.php)$~i', $path)) {
            return;
        }
    
        // Respect emergency bypass anywhere
        if (!empty($_GET[self::QP_EMERGENCY]) && strtolower($_GET[self::QP_EMERGENCY]) === 'bypass') {
            return;
        }
    
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        $is_tool  = self::is_tool_request();
        $is_allow = self::ip_allowed($ip);
    
        // ── Root /wp-admin (cheapest path) ───────────────────────────────────────────
        if ($path === '/wp-admin') {
            // Tools / allowlisted IPs:
            // - if not logged in → send to masked login
            // - if logged in     → let /wp-admin load normally
            if ($is_tool || $is_allow) {
                if (!is_user_logged_in()) {
                    header('X-Redirect-By: hws_base_tools');
                    wp_safe_redirect(self::login_url());
                    exit;
                }
                return;
            }
    
           // Everyone else (guests) → ultra-light static HTML (no PHP parser work)
if (!is_user_logged_in()) {
    header('Content-Type: text/html; charset=utf-8');
    status_header(200);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Access Restricted</title></head><body>'
       . '<p>WordPress URL has been changed by Hexa Cloud Services (Hexa Web System) for security and performance.</p>'
       . '<p>Contact support for the revised URL and emergency access.</p>'
       . '<p>You can also find the current URL under <strong>Dashboard &gt; Settings &gt; Masked Login</strong>.</p>'
       . '</body></html>';
    exit;
}
            // Logged-in users: allow normal load
            return;
        }
    
        // ── /wp-admin subtree (anything under /wp-admin/...) ────────────────────────
        if (preg_match('~^/wp-admin(?:/.*)?$~i', $path)) {
            // Tools / allowlisted IPs: if not logged in, send to masked login; else allow
            if ($is_tool || $is_allow) {
                if (!is_user_logged_in()) {
                    header('X-Redirect-By: hws_base_tools');
                    wp_safe_redirect(self::login_url());
                    exit;
                }
                return;
            }
    
            // Guests: optionally hide subtree with hard 404
            if (!empty($o['hide_wp_admin']) && !is_user_logged_in()) {
                status_header(404);
                nocache_headers();
                if (function_exists('get_404_template') && ($template = get_404_template())) {
                    include $template;
                } else {
                    wp_die(__('Not Found'), '', ['response' => 404]);
                }
                exit;
            }
    
            // Logged-in, non-tool traffic: allow normal /wp-admin
            return;
        }
    
        // ── /wp-login.php handling ──────────────────────────────────────────────────
        if (strcasecmp($path, '/wp-login.php') === 0) {
            // Tools / allowlisted IPs → discovery-friendly redirect to masked login
            if ($is_tool || $is_allow) {
                header('X-Redirect-By: hws_base_tools');
                wp_safe_redirect(self::login_url());
                exit;
            }
    
            // Everyone else → pretend it doesn't exist
            status_header(404);
            nocache_headers();
            if (function_exists('get_404_template') && ($template = get_404_template())) {
                include $template;
            } else {
                wp_die(__('Not Found'), '', ['response' => 404]);
            }
            exit;
        }
    }
    


    /* ============================================================
     *                     URL FILTERS → MASK
     * ============================================================ */

     public static function filter_login_url($login_url, $redirect, $force_reauth) {
        if (empty(self::opts()['enabled'])) return $login_url;
        return self::login_url($redirect, $force_reauth);
    }
    
    public static function filter_lostpassword_url($url, $redirect) {
        if (empty(self::opts()['enabled'])) return $url;
        return add_query_arg(['action'=>'lostpassword'], self::login_url($redirect));
    }
    
    public static function filter_register_url($url) {
        if (empty(self::opts()['enabled'])) return $url;
        return add_query_arg(['action'=>'register'], self::login_url());
    }
    
    public static function filter_site_url($url, $path, $scheme, $blog_id) {
        if (empty(self::opts()['enabled'])) return $url;
        if (is_string($path) && strpos($path, 'wp-login.php') !== false) {
            $parts = wp_parse_url($url); $q = [];
            if (!empty($parts['query'])) parse_str($parts['query'], $q);
            $url = self::login_url(); if ($q) $url = add_query_arg($q, $url);
        }
        return $url;
    }
    
    public static function filter_redirect($location, $status) {
        if (empty(self::opts()['enabled'])) return $location;
        if (strpos($location, 'wp-login.php') !== false) {
            $parts = wp_parse_url($location); $q = [];
            if (!empty($parts['query'])) parse_str($parts['query'], $q);
            $location = self::login_url(); if ($q) $location = add_query_arg($q, $location);
        }
        return $location;
    }
    


    /* ============================================================
     *                          ADMIN UI
     * ============================================================ */

    public static function admin_menu() {
        add_options_page(
            'HWS Login Masking',
            'Masked Login - Hexa Cloud Services',
            'manage_options',
            'hws-login-masking',
            [__CLASS__, 'render_settings']
        );
        /*
        add_management_page(
            'HWS Login Masking',
            'Login Masking',
            'manage_options',
            'hws-login-masking',
            [__CLASS__, 'render_settings']
        );*/
    }

    public static function register_settings() {
        register_setting('hws_login_mask_group', self::OPT_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default'           => self::defaults(),
        ]);
    }

    public static function sanitize($input) {
        $d   = self::defaults();
        $prev = self::opts();
    
        // Sanitize slug from settings (fallback to default)
        $raw  = isset($input['slug']) ? (string)$input['slug'] : $d['slug'];
        $slug = sanitize_title_with_dashes($raw);
        if ($slug === '' || in_array($slug, ['wp-admin','wp-login','wp-login.php'], true)) {
            $slug = $d['slug']; // safety
        }
    
        $out = [];
        $out['enabled']          = !empty($input['enabled']);
        $out['slug']             = $slug;                       // <— allow changing
        $out['hide_wp_admin']    = !empty($input['hide_wp_admin']);
        $out['compat_wptoolkit'] = !empty($input['compat_wptoolkit']);
        $out['allowlist_ips']    = sanitize_text_field($input['allowlist_ips'] ?? $d['allowlist_ips']);
        $out['well_known']       = !empty($input['well_known']);
    
        // Track legacy slugs so we can kill/redirect them
        $legacy = isset($prev['legacy_slugs']) && is_array($prev['legacy_slugs']) ? $prev['legacy_slugs'] : [];
        if (!empty($prev['slug']) && $prev['slug'] !== $slug) {
            $legacy[] = $prev['slug'];
            $legacy = array_values(array_unique(array_filter($legacy)));
        }
        $out['legacy_slugs'] = $legacy;
    
        // If routing-affecting toggles OR the slug changed → flush & purge
        if ($prev['enabled'] !== $out['enabled']
            || $prev['well_known'] !== $out['well_known']
            || $prev['slug'] !== $out['slug']) {
    
            add_action('updated_option', function($opt) {
                if ($opt === self::OPT_KEY) {
                    self::flush_rewrites();
                    self::purge_cache_best_effort();
                }
            }, 10, 1);
        }
    
        return $out;
    }
    

    private static function login_help_text(): string {
        $o     = self::opts();
        $url   = esc_url(self::login_url());
        $slug  = esc_html(self::slug());
        $json  = esc_url(home_url('/.well-known/hws-login.json'));
        $admin = esc_url(home_url('/wp-admin/'));
        $toolkit = !empty($o['compat_wptoolkit']) ? 'Enabled' : 'Disabled';
        $hide    = !empty($o['hide_wp_admin']) ? 'Enabled' : 'Disabled';
        $wk      = !empty($o['well_known']) ? 'Enabled' : 'Disabled';
    
        return '
            <p><strong>Masked login is active.</strong></p>
            <ul style="list-style: disc; padding-left: 20px;">
                <li><strong>Login URL:</strong> <a href="'.$url.'" target="_blank">'.$url.'</a> (slug: <code>'.$slug.'</code>)</li>
                <li><strong>WP Toolkit & Allowlisted IPs:</strong> '.$toolkit.' — may follow redirect from <code><a href="'.home_url('/wp-login.php').'" target="_blank">'.home_url('/wp-login.php').'</a></code>.</li>
                <li><strong>/wp-admin/ visibility:</strong> '.$hide.' — guests get 404 at <a href="'.$admin.'" target="_blank">'.$admin.'</a> (ajax/upload allowed).</li>
                <li><strong>.well-known discovery:</strong> '.$wk.' — <a href="'.$json.'" target="_blank">'.$json.'</a>.</li>
                <li><strong>Emergency:</strong> <code><a href="'.home_url('/?hws=bypass').'" target="_blank">/?hws=bypass</a></code> (native login), <code><a href="'.home_url('/?hws=repair').'" target="_blank">/?hws=repair</a></code> (fix rewrites & purge caches).</li>
                <li><strong>Disable masking:</strong> <code>define(\'HWS_DISABLE_LOGIN_MASKING\', true);</code> in <code>wp-config.php</code>.</li>
            </ul>';
    }
    

    /**
     * Render the admin settings page with a help box and a few toggles.
     */
    public static function render_settings() {
        if (!current_user_can('manage_options')) return;
        $o = self::opts();
        ?>
        <div class="wrap">
            <h1>HWS Login Masking</h1>

            <!-- Help/explanation panel -->
            <div class="notice notice-info" style="padding:12px;margin-top:10px;">
                <?php echo self::login_help_text(); ?>
            </div>

            <!-- Settings form (slug is read-only because it's fixed in code) -->
            <form method="post" action="options.php" style="margin-top: 12px;">
                <?php settings_fields('hws_login_mask_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Enable</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(self::OPT_KEY); ?>[enabled]"
                                       value="1"
                                       <?php checked(!empty($o['enabled'])); ?>>
                                Turn on masked login
                            </label>
                        </td>
                    </tr>

                    <tr>
    <th scope="row">Masked Slug</th>
    <td>
        <input type="text"
               name="<?php echo esc_attr(self::OPT_KEY); ?>[slug]"
               class="regular-text"
               value="<?php echo esc_attr(self::slug()); ?>"
               pattern="[a-z0-9\-]+"
               title="Lowercase letters, numbers, and dashes only">
        <p class="description">
            The URL segment used for login (default <code>hexa-admin</code>). Example:
            <code><?php echo esc_html( home_url('/') ); ?><span id="hws-slug-preview"><?php echo esc_html(self::slug()); ?></span>/</code>
        </p>
    </td>
</tr>

                    <tr>
                        <th scope="row">Hide /wp-admin/ for guests</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(self::OPT_KEY); ?>[hide_wp_admin]"
                                       value="1"
                                       <?php checked(!empty($o['hide_wp_admin'])); ?>>
                                Return 404 for /wp-admin/ when not logged in (admin-ajax.php & async-upload.php remain allowed)
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Compatibility: WP Toolkit</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(self::OPT_KEY); ?>[compat_wptoolkit]"
                                       value="1"
                                       <?php checked(!empty($o['compat_wptoolkit'])); ?>>
                                Allow /wp-login.php → masked URL (302) for “WP Toolkit” user agent
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Allowlist IPs</th>
                        <td>
                            <input type="text"
                                   name="<?php echo esc_attr(self::OPT_KEY); ?>[allowlist_ips]"
                                   value="<?php echo esc_attr($o['allowlist_ips']); ?>"
                                   class="regular-text"
                                   placeholder="127.0.0.1, 51.81.93.236, 10.0.0.0/8">
                            <p class="description">IPs/CIDR ranges that may follow a redirect from /wp-login.php to the masked URL.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">.well-known discovery</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(self::OPT_KEY); ?>[well_known]"
                                       value="1"
                                       <?php checked(!empty($o['well_known'])); ?>>
                                Serve <code>/.well-known/hws-login.json</code> with the current masked login URL and metadata
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <!-- Convenience: show the current login URL -->
            <p><strong>Current login URL:</strong>
                <a href="<?php echo esc_url(self::login_url()); ?>">
                    <?php echo esc_html(self::login_url()); ?>
                </a>
            </p>
        </div>
        <?php
    }
}

/* Initialize immediately on plugin load (NOT admin_init). */
Login_Masking::bootstrap();
