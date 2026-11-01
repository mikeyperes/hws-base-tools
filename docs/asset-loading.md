# Asset loading

The rule for every HWS and Hexa plugin, and how HWS Base Tools follows it.

## Rule

- Admin CSS and JS load only in the admin (`admin_enqueue_scripts`, `admin_head`, `admin_footer`), and only on the screens that use them. Check `$_GET['page']`/`tab` or `get_current_screen()` before enqueuing or printing.
- Front-end CSS and JS load only on the front end (`wp_enqueue_scripts`, `wp_head`, `wp_footer`), and only where the feature is used: behind its on/off switch, and on the pages where its shortcode, widget class or template actually appears.
- A shortcode's styles print with its first output on the page (once), not in `wp_head` on every page.
- Nothing admin-only ever loads for visitors.
- Admin-wide is allowed only for things that act on every admin screen (the admin menu, admin bar, a cleanup that hides another plugin's UI everywhere).

## How Base Tools scopes each asset

- Dashboard CSS and script: Base Tools' settings page only (`DashboardAssets`, `LegacyEventBridge`); editor and media scripts only on the tabs that need them.
- Website Settings colour-coding CSS and company/founder card scripts: the Website Settings screen only.
- Featured image requirement script: post editors of required post types only.
- Native field script and media library (Hexa WP Core, sites without ACF): post, user and term edit screens only.
- Rank Math Content AI cleanup and Comments menu style: every admin screen (they hide or mark admin-wide UI).
- Reading progress bar: the front end, behind its switch and its own page scope setting (posts, or the entire site).
- Brand colour variables and font smoothing: every front-end page while on (site-wide styling by design).
- Breadcrumbs CSS, script and bar template: the front end only, only while the Breadcrumbs switch is on and the current page is not hidden by its settings.
- 404 page CSS (`404-<design>.css`) and its colour variables: the front end only, only while the 404 Page switch is on and only on a missing page.
- Team grid CSS: with the first `[hws_team_members]` on a page.
- Auto load-more script: only when the page's Elementor output carries `hws-auto-load-more` or `smpi-auto-load-more`.
- Brand template and page layout CSS: only on pages using a brand template or a chosen layout style.
- View As user picker and banner: signed-in staff while the toolbar is showing or a view-as session is active.

## How to check

List the plugin's assets on a page and compare before and after a change:

```sh
curl -s "https://<site>/?nocache=1" | grep -oE '(href|src)="[^"]*(hws-base-tools|hexa-wordpress-plugin-core)[^"]*"|<(style|script)[^>]*id="[^"]*(hws|hexa|hpc)[^"]*"'
```

Check at least the home page, an article, an archive that uses the feature, and for the admin a Base Tools tab plus Dashboard and Posts (signed in, with the browser's cookie). An asset that appears where its feature is not used is a bug.

## Page collections

Page Collections adds no CSS, JavaScript or front-end markup on the homepage, articles, collection loops or admin screens. Native WordPress taxonomy controls provide the admin interface.

## People directory rows

People search and pagination emit Core search CSS/JavaScript only with the opted-in shortcode. SMP row CSS is emitted with the first styled row per block; `style="none"` omits SMP CSS. No new assets are loaded on home pages, articles or admin tabs without these shortcodes; existing assets remain unchanged.

## People chips and team paging

Opt-in controls reuse Core SearchBox inline assets only at rendered search forms. Chip decoration is scoped to `.hls` and omitted with `none`. Team cards retain their shortcode-scoped styles; no new global files are enqueued. Home, ordinary article and unrelated admin screens gain no assets.

## AJAX Navigation

Off: no navigation assets on the homepage, article, feature page or admin tab. On: public eligible homepage, article and feature pages load exactly `assets/ajax-navigation/navigation.js` and local Turbo `assets/ajax-navigation/vendor/turbo.js`, plus a small settings object. No CSS is added. Excluded requests, logged-in visitors and pages blocked by competing podcast-player navigation load neither. The admin tab loads existing Core UI components and one tab-local save handler, never Turbo. Script exclusions needed for LiteSpeed are in [AJAX Navigation](ajax-navigation.md).

## Form protection

Off by default. A protected form render enqueues only assets/form-protection/protection.js; pages without a supported form get no asset. No frontend CSS. Turnstile adds its provider script only when configured and a protected form renders. Form protection admin UI uses Core assets only when its tab opens. The protection script is excluded from LiteSpeed combine/delay/defer while enabled, so challenges are ready for native form submission.

## AJAX navigation 13.48.0
No new asset handles. Home, article and feature pages load the same Turbo/navigation pair only when eligible navigation is enabled; admin AJAX Navigation retains its existing settings assets. The adapter now morphs public bodies with connected permanent nodes and lets Turbo own safe GET forms. POST/admin exclusions are unchanged.
