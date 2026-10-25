# HWS Base Tools

Shared WordPress site configuration, administration, and operational tooling for Hexa-managed websites.

> Feature base for HWS Skills. Read before building on this plugin; use or
> extend these features instead of rebuilding them.

**Purpose:** site-wide building blocks for Hexa-managed sites: brand assets, site values, team and testimonial content, reading progress, footer text and admin cleanup. **Admin:** WP Admin → HWS Base Tools (`admin.php?page=hws-base-tools`); feature switches are on its Features tab, each stored as an option of the same name. **Depends on:** ACF Pro for the Website Settings and profile fields; Elementor optional.

## Features

### Hidden admin access message
- **Does:** logged-out visitors to `/wp-admin/` see a Hexa Cloud Services notice linking to `https://hexacloudservices.com/` and `https://hexatiger.com/`, plus email and WhatsApp support. Every link opens in a new tab, and the closing sentence links both HexaTiger and Hexa Cloud Services. The email goes to `support@hexacloudservices.com`; its subject starts with the site's canonical domain, followed by ` - Login URL Request`. "Message 24/7 support. Click here." links to `https://wa.me/14152129449` (+1 415-212-9449). The page keeps HTTP 404, disables caching, and never exposes the masked login URL.
- **Switch:** Settings → HWS Base Tools → Security → Masked Login; masking and "Hide /wp-admin/ from guests" enabled (`hws_login_mask_options`).
- **Use:** automatic; logged-in access and existing management-tool exceptions are preserved.
- **Example:** [Message HTML and link behavior](docs/hidden-admin-access.md).
- **Code:** `src/Security/legacy-login-mask.php` (`Login_Masking::render_admin_access_notice`).

### Expired login link message
- **Does:** when a one-click login link from Code's WP Toolkit has expired or was already used, shows "This login link has expired or was already used. Ask for a new one." with a link to the login page (HTTP 410) instead of WordPress's bare "0"; a logged-in user goes to the dashboard.
- **Switch:** always on; it answers only the `hws_auto_login` admin-ajax action, which a valid link's temporary file handles first.
- **Code:** `src/Security/LoginLinkFallback.php`.

### Font smoothing
- **Does:** draws text smoothly (antialiased) on every front-end page, so light text on dark backgrounds no longer looks bolder than the design on Mac browsers.
- **Switch:** on by default; option `hws_font_smoothing` set to `0` turns it off.
- **Use:** automatic, one small style in the page head (`#hws-font-smoothing`). Never add the same rule in Elementor or the theme.
- **Code:** `src/FrontendContent/FontSmoothing.php`

### Reading progress bar
- **Does:** a progress line at the top of the page that fills as the visitor scrolls.
- **Switch:** Features → Reading Progress Bar (`enable_reading_progress_bar`). Style `hws_reading_progress_style`: `thin`, `track`, `glow`, `floating`, `segmented`. Colour `hws_reading_progress_color`. Where it shows `hws_reading_progress_scope`: `posts`, `posts_front_page`, `sitewide`, `selected` (with `hws_reading_progress_post_types`, `hws_reading_progress_front_page`).
- **Use:** automatic, printed right after `<body>`. Never build a second progress bar on a page; configure this one.
- **Use inline:** `[hws_reading_progress label="Read" class=""]` adds a themeable track and percentage, synchronized by the same scroll listener as the top bar. An empty label omits it. It respects the existing feature switch and page scope.
- **Style:** `#hws-reading-progress.hws-reading-progress--<style>`, `.hws-reading-progress__track`, `.hws-reading-progress__fill`; variables `--hws-reading-progress-color`, `--hws-reading-progress-soft`, `--hws-reading-progress-glow`.
- **Code:** `src/FrontendContent/ReadingProgress.php`

### Site logo and brand assets
- **Does:** outputs a saved Brand Assets image (logo, logo with text, icon) without distortion, or its URL.
- **Switch:** HWS Base Tools → Brand Assets (ACF Assets group).
- **Use:** `[site_logo key="logo" size="full" output="img" width="" height="" class="" alt="" loading="lazy" style=""]` (alias `[hws_brand_asset]`, `type=""` also accepted); `output="url"` returns the URL. Pass `loading="eager"` in headers.
- **Code:** `src/BrandAssets/legacy-brand-functions.php`

### Brand asset gallery
- **Does:** the saved Brand gallery as a responsive grid, URL list or ID list.
- **Use:** `[brand_asset_gallery size="medium" output="grid|urls|ids" columns="4" class="" loading="lazy"]` (alias `[site_gallery]`).
- **Code:** `src/BrandAssets/legacy-brand-functions.php`

### Site identity (company or person basics)
- **Does:** holds the website's basic identity, generic for any site: on Website & Primary Entity, "This website represents: a company / a person / neither", each with its own short form. Company (founding date and city show only when the site represents a company, never on a person and never inside founders): name, legal name, other names, short description, logo, website, founding date, founding city (and link), founders (read from the Founders tab; no picker here), headquarters (city, address, link), parent company (name, link), contact email, phone, mission, online profiles. Person: name, user account, job title, short bio, photo, online profiles. Fields are defined in code through Hexa WP Core's field layer (works with or without ACF) and stored as options `options_hws_site_represents`, `options_hws_company_*`, `options_hws_person_*`.
- **Use (PHP):** `hws_site_identity()` returns `[ 'type' => 'company'|'person'|'none'|'', 'company' => [...], 'person' => [...] ]`; `hws_site_identity( 'company.name' )`, `hws_site_identity( 'person.job_title' )`, or a bare key (`'name'`, `'email'`) for the type the site represents. Logo and photo are attachment IDs, founders and user are user IDs, other names and profiles are lists. `hws_site_identity_edit_url()` is the edit screen. Check `function_exists( 'hws_site_identity' )` first.
- **Use (shortcode):** `[hws_site_identity field="company.name"]`, `[hws_site_identity field="mission"]`, `[hws_site_identity field="profiles" sep=" | "]`, `[hws_site_identity field="logo" size="medium"]` (image), `[hws_site_identity field="founders"]` (names).
- **Showing it in another plugin:** read it with `hws_site_identity()` and put Hexa WP Core's `SourceBanner::render( [ 'owner' => 'HWS Base Tools', 'edit_url' => hws_site_identity_edit_url() ] )` above it ("Original content at HWS Base Tools. Click here to edit."). Never copy the values into the other plugin.
- **Routing for skills:** basic website identity (who the site represents, company or person basics, contact email, mission, social profiles, headquarters) is read and edited here. Founders are picked only on the Founders tab (see Founders). Publication-specific information (DMCA, policies, editorial pages, founding-date page, publication standard pages) belongs to SMP Publication Integration. The older Website Settings fields (founder, website group) are kept for old shortcodes but are superseded; write new values to Site identity.
- **Safe during startup:** read during plugin startup (before ACF has started), the lookup returns the stored option values instead of reading fields, so an early caller can never start ACF too early and crash the site.
- **Code:** `src/SiteProfile/SiteIdentity.php`, `src/SiteProfile/site-identity-functions.php`

### Founders
- **Does:** the website's founders, its own definition in HWS Base Tools, separate from the Primary Entity and from Website Identity (which reads it). Founders tab (`options-general.php?page=hws-core-tools&tab=founders`): on/off, section title ("Founders" by default), the people (user search, drag to order, an optional title each, one-column editor), style (Cards, List, No styling) with a live example, the HTML structure, a plugin breakdown and the shortcode breakdown. When the Primary Entity is a person marked as a founder, that person is included automatically, first; more founders are added by hand. A company Primary Entity is never on the list, so its founders (any number) are picked separately. Founding date and city are not part of founders (they are company basics). Stored in option `hws_publication_founders`; on first read it is seeded from SMP's `smpi_founders`, else Website Identity's old founders field.
- **Use (shortcode):** `[hws_publication_founders]` with `style`, `columns`, `ids`, `limit`, `hide="photo,title,bio,link"`, `link`, `bio_words`, `photo_size`, `heading` (tag for each name), `section_title` (`none` hides it), `section_tag`. Prints only the founders, never the founding date; prints nothing while off. `[smpi_founders]` is an alias for pages built with SMP's shortcode and keeps the `smpi-founders` / `smpi-founder` class names.
- **Use (PHP, the read API):** `hws_publication_founders()` returns every founder in order, whether or not the section is on: `[ [ 'user_id', 'name', 'title', 'url', 'bio', 'photo' ], ... ]`; `hws_publication_founders_enabled()`; `hws_publication_founders_edit_url()`. Check `function_exists( 'hws_publication_founders' )` first.
- **Extend:** filter `hws_publication_founders_photo_url( $url, $user_id, $size )` supplies a profile photo (default: the user's avatar).
- **Routing for skills:** founders are read and edited only here. SMP Publication Integration reads `hws_publication_founders()` for its search-engine data and shows the list read-only under Core's SourceBanner linking to the Founders tab; it stores no founders.
- **HTML structure and styling:** [docs/founders.md](docs/founders.md)
- **Code:** `src/Founders/Founders.php`, `src/Founders/FounderCards.php`, `src/Founders/FoundersTab.php`, `src/Founders/founders-functions.php`

### Site values and Website Settings content
- **Does:** prints a core site value or a Website Settings field anywhere, including inside Elementor text widgets.
- **Use:** `[hws_site_value field="site_name|home_url|contact_email|..." format="text|url|email|html" fallback=""]`; `[website_content field="website_mission_statement"]` for any Website Settings ACF field; `[website_url social="linkedin"]` for the Website profile's social links; `[company id="title"]` for the primary company.
- **Code:** `src/BrandAssets/legacy-brand-functions.php`

### Founder and profile photos
- **Does:** the founder's (or any user's) profile fields and public photo gallery.
- **Use:** `[founder id="title" size="medium" output="grid" columns="4" class="" loading="lazy"]`; `[hws_profile_photos user_id="0" size="medium" output="grid" columns="4" class="" loading="lazy"]`.
- **Code:** `src/BrandAssets/legacy-brand-functions.php`, fields in `src/AcfFields/user-profile-2025.php`

### Company pages
- **Does:** the Pages tab (`options-general.php?page=hws-core-tools&tab=pages`) owns the pages any website has: About, with Mission Statement, Founders (plus one profile page per founder), Founding Date, Headquarters, Logo, Parent Network (optional) and Team under it; Contact, Privacy Policy, Terms of Use, Accessibility, FAQs and RSS Feed at the top level. Each page sits under its parent; a page already at its usual address (or an alternative such as `her-forward-mission-statement`) is linked with its content kept, and a moved page's old address redirects (Hexa WP Core). Wording prints only the Website Identity facts that are filled in. Publication pages (newsroom people, editorial policies, DMCA, press releases) belong to SMP Publication Integration, whose pages can sit under these.
- **Use:** `wp hws-pages ensure` sets every page up (existing optional pages only). `[site_page_template type="<key>"]` (alias `[hws_site_page_template]`) prints a page's wording from `templates/company-pages/<key>.html`; `[hws_company field="name"]`, `[hws_if field|page|shortcode="..."]...[/hws_if]`, `[hws_page_link key="contact"]` (a key page from any plugin) and `[hws_founder_profile user="ID"]` build it.
- **Use (PHP):** `CompanyPages::ensure_pages()`; `CompanyPages::page_id( '<key>' )` finds a key page from any plugin; `hws_base_tools_linked_page( '<key>' )` returns Base Tools' linked page ID or 0; `hws_base_tools_pages_url()` is the Pages tab. Links are stored in `hws_site_page_assignment_<key>`; founder pages carry `_hws_founder_user`.
- **Code:** `src/CompanyPages/CompanyPages.php`, `src/CompanyPages/CompanyFacts.php`, `src/CompanyPages/company-pages-functions.php`, `src/SiteStructure/legacy-pages.php`, `templates/company-pages/`

### Missing company facts notice
- **Does:** when a linked page (Headquarters, Founding Date) or another plugin's fact page lacks what it needs from Website Identity, Base Tools shows a red notice at the top of its admin pages naming the page and the missing facts, with a button to Website Identity. Headquarters needs a headquarters city or address; Founding Date (company sites only) needs the founding date and the founding city.
- **Extend:** filter `hws_base_tools_pages_in_use` (`key => label`); a page definition's `required_facts` is checked by Hexa WP Core's `missing_facts()`.
- **Code:** `src/CompanyPages/CompanyPages.php`

### Team
- **Does:** the Team Member post type (`team-member`, archive `/team/`), its ACF fields and the `[hws_team_members]` listing grid. The plugin prints only the grid; the rest of the team page is the site's own design around it.
- **Switch:** Settings → HWS Base Tools → Team (`options-general.php?page=hws-core-tools&tab=team`): one switch turns the post type, its fields and the shortcode on or off together (`enable_team_member_directory_templates` plus the Team Member content type settings). Team page defaults in `hws_team_page_settings`.
- **Use:** `[hws_team_members style="portrait_grid|editorial_list|compact_directory" design="default|none" columns="3" limit="24" orderby="menu_order|title|date" order="ASC|DESC" show_excerpt="1" link_profiles="1" featured_only="0" category="" roles_first="Founder, Co-Founder, CEO, President, COO, CTO"]` (people whose position is in `roles_first` come first, in that order); the Team tab's shortcode builder writes it and shows its preview and HTML. Elementor query ID `featured_team_members` for Posts/Loop widgets.
- **HTML structure and styling:** [docs/team-members.md](docs/team-members.md): the grid's markup, every attribute, the classes (`.hws-team-directory`, `.hws-team-card`, `.hws-team-member-media`, `-name`, `-position`, `-excerpt`, `-link`), the built-in CSS and how to style it in Elementor and HTML drafts.
- **Use (PHP, the read API):** `hws_team_members( [ 'featured_only' => false, 'category' => '', 'limit' => 0 ] )` returns published team members in the team page order (roles first), as data: `[ [ 'id', 'name', 'position', 'featured', 'excerpt', 'url', 'photo', 'photo_id', 'order', 'edit_url' ], ... ]`; `hws_team_enabled()`; `hws_team_page_url()` (the `/team/` archive); `hws_team_edit_url()` (the Team tab). Check `function_exists( 'hws_team_members' )` first.
- **Routing for skills:** HWS Base Tools is the one source for team work: the post type, its fields, the listing and the team page. Other plugins (SMP's Team page first) read `hws_team_members()` or place `[hws_team_members]`, report it and link to `hws_team_edit_url()` under Core's SourceBanner; they never store team members.
- **Shortcode breakdown:** the Team tab shows Core's shortcode breakdown for `[hws_team_members]` (every attribute, its default from the team page settings, examples). The grid's CSS prints only with the first list on a page.
- **Code:** `src/TeamMembers/TeamTab.php`, `src/TeamMembers/TeamMemberDirectory.php`, `src/TeamMembers/TeamPageSettings.php`, `src/TeamMembers/team-functions.php`

### Testimonials
- **Does:** one quote from a Testimonial (`testimonial` post type).
- **Use:** `[hws_testimonial_quote id="" number="1"]` (current Testimonial when `id` is empty); Elementor query ID `featured_testimonials`.
- **Code:** `src/Testimonials/TestimonialQuoteShortcode.php`

### Search (Hexa WP Core's one search)
- **Does:** one search for the whole site. `[hws_search]` is a search box with Type, Category, Tag and Author filters above any list of posts: on a category, tag, author, search or post type page it searches inside that page; on any other page (a homepage section) it searches what its `post_type`, `category`, `tag` and `author` attributes say. The list, the filter counts and the address refresh in place as visitors type or tick, without reloading, and the list dims under one spinner while it loads. `[hws_search_box]` is the header search: a magnifying glass that opens a field in place, or an always-open field; it goes to the search page and never opens a popup. Site search covers pages, posts and custom post types (what the Search settings cover once switched on).
- **Use:** `[hws_search]` directly above the list. The list is the Elementor Loop Grid, Posts or Archive Posts widget with the CSS class `hexa-listing-results`; a loop showing the page's own list (Source: Current Query) is found by itself. `[hws_search_box style="icon-reveal|field" placeholder="" accent="" label=""]` in the header.
- **Attributes:** every one optional and per box, listed with examples on Settings, Search: `style`, `filters`, `post_type`, `category`, `tag`, `author`, `placeholder`, `results_one`, `results_many`, `empty`, `clear`, `accent`, `border`, `radius`, `panel_bg`, `field_width`, `field_height`, `font_size`, `target`, `class`.
- **Style:** Settings, Search picks No styling, Default 1 (compact) or Default 2 (editorial) for the box and the header look; classes `hls-*`, custom properties `--hls-*` including the loader's.
- **Code:** `src/FrontendContent/SearchBoxFeature.php`, engine in `lib/hexa-wordpress-plugin-core/src/Search/`.
- **HTML structure and styling:** [docs/hws-search.md](docs/hws-search.md): the markup, states, classes, custom properties, loader and how to style it in Elementor and HTML drafts.

### Infinite scroll with a Load more fallback
- **Does:** a Loop Grid or Posts widget set to "Load on click" loads its next batch as its button comes into view; the button stays as the fallback. A page with `window.hexaFreezeAutoLoad = true` keeps its first batch.
- **Use:** give the widget the CSS class `hws-auto-load-more` (the older `smpi-auto-load-more` still works). The script prints only on pages whose Elementor output carries one of these classes.
- **Code:** `src/FrontendContent/SearchBoxFeature.php`

### Current year
- **Use:** `[current_year]` (legacy `[display_year]`). **Switch:** `enable_current_year_shortcode`.

### Footer text band
- **Does:** adds the saved footer text as a full-width band at the bottom of the theme footer, or at a chosen selector.
- **Switch:** `enable_footer_text_auto_injection`; template `hws_footer_text_template`, alignment `hws_footer_text_alignment`, targeted placement `hws_footer_text_targeted_*`; text from Website Settings `website_footer_text`.
- **Style:** `.hws-footer-inline-injection`, `.hws-footer-text--<template>`.
- **Code:** `src/FrontendContent/legacy-footer-text.php`

### Breadcrumbs
- **Does:** a breadcrumb bar ("Home » Section » Article") right under the site header, built from Rank Math breadcrumbs (a plain trail when Rank Math is off). Moved here from SMP Publication Integration 2.5.25.
- **Switch:** WP Admin → Settings → HWS Base Tools → Breadcrumbs (`options-general.php?page=hws-core-tools&tab=breadcrumbs`), option `hws_base_tools_breadcrumbs`. Off by default. Design `style` `bc-b1`…`bc-b6`, accent/background/text colour, font size, hide on the home page, hide on category archives, hide per post type, custom CSS.
- **Carry-over:** a site that had SMP's breadcrumbs switched on gets its SMP look (style, colours, size, hidden types and posts, CSS) copied once; a site that had them off stays off.
- **Use:** automatic under the header while on. `[hws_breadcrumbs style=""]` (and the old `[smp_breadcrumbs]`) places it manually; a visible manual bar stops the automatic one.
- **Style:** `.hws-breadcrumbs-band`, `.hws-breadcrumbs.smpi-bc-<style>`, `.smpi-breadcrumb-link`, `.smpi-breadcrumb-separator`, `.smpi-breadcrumb-current` (the old SMP classes stay so existing custom CSS keeps working); variables `--smpi-bc-accent`, `--smpi-bc-background`, `--smpi-bc-text`, `--smpi-bc-font-size`.
- **Code:** `src/Breadcrumbs/`

### 404 page
- **Does:** replaces the site's "page not found" page with a plain one ("Page not found", one sentence, a button to the homepage) between the site's own header and footer, with a real 404 status. Colours and fonts come from the site's Elementor global colours (primary, text) and fonts (primary, text).
- **Switch:** WP Admin → Settings → HWS Base Tools → 404 Page (`options-general.php?page=hws-core-tools&tab=404-page`), option `hws_base_tools_404_page`. Off by default. Design `plinth`, `divider` or `headline`; optional brand, text and background colour. The tab warns when Rank Math's redirect fallback sends missing pages elsewhere.
- **Style:** `.hws-404.hws-404--<design>`; variables `--brand-primary`, `--brand-ink`, `--brand-bg`, `--brand-muted`, `--font-heading`, `--font-body`. HTML in [docs/404-page.md](docs/404-page.md).
- **Code:** `src/NotFoundPage/`, `templates/404.php`, `assets/frontend/404-<design>.css`

### Page layout styling
- **Does:** a site-wide page layout style applied through body classes.
- **Switch:** `hws_page_layout_style`.
- **Style:** `body.hws-page-layout-styled.hws-page-layout-style-<style>`.
- **Code:** `src/PageLayoutStyling/PageLayoutFeature.php`

### Elementor helpers
- **Does:** a dynamic tag for a word-limited ACF value; cleanup of empty Social Icons (hides empty icons, opens external links in a new tab); the brand shortcodes above render inside any Elementor widget.
- **Use:** dynamic tag `hws-trimmed-acf-text`; Social Icons cleanup switch `enable_elementor_social_icon_cleanup`; custom query `query_featured_posts` (switch `enable_elementor_queries`).
- **Code:** `src/Elementor/TrimmedAcfTextTag.php`, `src/FrontendContent/legacy-elementor-social-icons.php`

### Maintenance mode
- **Does:** shows a maintenance page to visitors while logged-in admins see the site.
- **Switch:** `hws_maintenance_mode_enabled`; template `hws_maintenance_mode_template`.
- **Code:** `src/MaintenanceMode/`

### Editorial guards
- **Does:** requires a featured image of a minimum size before publishing, and allows public previews of drafts.
- **Switch:** `enable_required_featured_image` (`hws_required_featured_image_min_width`, `_min_height`, `_post_types`); `enable_public_draft_preview`.
- **Code:** `src/Editorial/`

### Rank Math schema switch
- **Does:** decides whether Rank Math prints its own schema block next to the Hexa plugins' shared `hexa-schema` block. Where it is off, Featured Image From URL's duplicate image block is removed too. Rank Math stays active for titles, descriptions and sitemaps.
- **Switch:** WP Admin → Settings → HWS Base Tools → SEO & Rank Math → Rank Math schema output (`hws_rank_math_schema_output`: `keep`, `hexa` default, `off`).
- **Code:** `src/SeoSettings/RankMathSchemaSetting.php` (Core: `Hexa\PluginCore\SchemaTools\RankMathSchemaControl`)

### Database optimization
- **Does:** cleans the database and maintains its tables without WP-Optimize or WP Sweep: revisions (keeps the newest N per post), auto-drafts, old trash, spam and trashed comments, pingbacks, trackbacks, expired transients, embed caches, leftover post/comment/user/term data and category links; optional review-first items (unapproved comments, all transients, duplicate custom fields, unused terms). Tables: each shows its owning plugin and that plugin's state; check, repair damaged, convert MyISAM to InnoDB and optimize per selection or in one click for all. Read-only reports: autoloaded options, leftover tables grouped by inactive or removed plugin, media attached to deleted posts. Rows are removed through WordPress delete functions; only this site's prefixed tables are touched.
- **Switch:** WP Admin → Settings → HWS Base Tools → Database Optimization (`options-general.php?page=hws-core-tools&tab=database-optimization`); retention ages in `hws_database_optimization_settings`.
- **Use:** `wp hws db-optimize scan [--format=json]`, `wp hws db-optimize clean [--items=revisions,autodrafts]`, `wp hws db-optimize tables [--operation=check|repair|convert|optimize|all] [--tables=...]`, `wp hws db-optimize run`. AJAX actions `hws_database_optimization_{overview,count,clean,tables,table,reports,settings}` (nonce `hws_database_optimization`).
- **Code:** `src/DatabaseOptimization/` (`CleanupTasks`, `TableMaintenance`, `TableOwners` with `data/table-owners.json.gz`, `DatabaseReport`, `DatabaseOptimizationModule`); UI on Core's `WpAdminComponents\TaskRunner`.

### Sitemaps and RSS feeds
- **Does:** lists every RSS feed (all posts, comments, each browsable content type, category, tag and author feeds, Rank Math Pro's podcast feed) and every XML sitemap (index, Rank Math Pro news and video sitemaps, each content type). Each one shows the server response in plain view (green ✓ for 200 OK, red ✗ for anything else, with the response time), a working ✓ or broken ✗, item count, published total, newest date and response time. Validate requests the address from the server and checks the HTTP status, content type, XML and required fields. Server response shows the headers and the start of the body. Advanced shows the feed's general (channel) fields and each item's fields, plus which fields each item has. Each feed has an Active switch: off answers 404 "This feed is turned off." and removes it from the page head. While comments are turned off on the site, the comments feeds (site-wide and each post's) are off automatically, their head links are removed, and the Comments row links to the Comments tab to turn comments back on. Settings: items per feed (site default, 5, 10, 20, 50, 100 or unlimited) and addresses per sitemap page (Rank Math's Links per sitemap). Caching: how long LiteSpeed keeps each feed or sitemap (its own setting, don't cache, 15 minutes to 1 week) and clearing them when a post is published, updated or unpublished (on by default). A plugin that controls feed caching itself (Hexa PR Wire Distributor's "Disable local RSS caching") is shown and the length choice is locked; others declare it with `hws_base_tools_feed_cache_owner`. Tools: clear cached sitemaps or feeds, refresh permalinks.
- **Switch:** two tabs: WP Admin → Settings → HWS Base Tools → Sitemaps (`options-general.php?page=hws-core-tools&tab=sitemaps`) and RSS Feeds (`options-general.php?page=hws-core-tools&tab=rss-feeds`). Turned-off feed keys in `hws_feeds_disabled`; settings in `hws_feed_sitemap_settings`; last check results in `hws_sitemap_feed_checks`.
- **Shortcode:** `[hws_rss_feeds]` lists the active feeds (icon, name, item count, address, copy button). Each feed has an ID shown on the RSS Feeds tab (`posts`, `comments`, a content type's name such as `press-release`, `categories`, `tags`, `authors`, `podcast`). Attributes: `only="posts,press-release"` (just these, in order; the only way to list categories, tags or authors), `exclude="comments"`, `hide="icon,title,url,count,copy"`, `style="none"`. A feed turned off drops out by itself.
- **HTML structure and styling:** [docs/rss-feeds.md](docs/rss-feeds.md): the `[hws_rss_feeds]` markup, classes (`.hws-rss-feeds`, `.hws-rss-feed--<id>`, `__icon`, `__title`, `__count`, `__url`, `__copy`), built-in CSS and how to style it in Elementor and HTML drafts.
- **Extend:** filters `hws_base_tools_feeds` and `hws_base_tools_sitemaps` add entries (`label`, `group`, `url`, optional `slug`, `example`, `toggle`, `match`, `total`, `latest`, `available`, `note`, `settings`); see `SitemapFeedRegistry`. AJAX `hws_sitemap_check`, `hws_sitemap_feed_toggle`.
- **Code:** `src/SitemapTools/` (`SitemapFeedRegistry`, `XmlInspector`, `FeedSwitches`, `FeedSitemapSettings`, `sitemaps-feeds-admin.php`).

### Code and shortcode displays
- **Does:** every code block and shortcode explanation in the admin uses Hexa WP Core's shared components: `CodeDisplay` (colour-coded HTML, CSS, PHP, JSON and shortcodes with a copy button) on the Founders, Team, Default Page Content, Maintenance Mode, Features and Configuration tabs and the login-masking debug page; `ShortcodeBreakdown` (shortcode, copy, every attribute with default and explanation) on the Founders, Team and RSS Feeds tabs.
- **Code:** Hexa WP Core `src/WpAdminComponents/CodeDisplay.php`, `src/ShortcodeRegistry/ShortcodeBreakdown.php`

### Extension points
- `hws_base_tools_feeds` / `hws_base_tools_sitemaps` (entries on the RSS Feeds and Sitemaps tabs), `hws_base_tools_shared_content_type_definitions` (add or change shared post types), `hws_base_tools_dashboard_tabs` / `hws_base_tools_dashboard_tab_groups` / `hws_base_tools_render_dashboard_tab` (dashboard tabs), `hws_base_tools_quick_start_profiles`, `hws_base_tools_litespeed_profiles`, `hws_base_tools_article_image_post_types`, `hws_acf_group_meta`, `hws_base_tools/login_mask_allowlist`.

## Asset loading

Rule: admin CSS and JS load only in the admin and only on the screens that use them; front-end CSS and JS load only on the front end and only where the feature is used. Nothing admin-only loads for visitors. How each feature is scoped, and how to check it, is in [docs/asset-loading.md](docs/asset-loading.md).

## Internals

Admin-only switches on the Features tab: admin bar for non-admins (`disable_non_admin_admin_bar`), SVG uploads, lowercase upload names, custom RSS feeds and tag feed limit, noindex for AMP and feeds, LiteSpeed JS combine, Rank Math sitemap cache, ACF source tracker, login masking (Settings → Masked Login), View As user impersonation (`enable_hws_user_impersonation`).

Auto-Update All Plugins (`enable_auto_update_plugins`) and Auto-Update All Themes (`enable_auto_update_themes`) load in every context, including WordPress background update runs and WP-CLI, so every plugin and theme updates automatically; plugins on GPL Vault's do-not-update list (`gplvault_blocked_plugins`) are skipped.

## Ownership

HWS Base Tools is the canonical owner of:

- The website's basic identity (company, person or neither, and its basics); see Site identity.
- The website's founders (one list; see Founders) and the team (see Team).
- Generic site pages (Contact). Publication standard pages belong to SMP.
- Website classification: News Outlet, Podcast Website, Personal Website, Company Website, e-Commerce Website, or Other.
- The optional primary entity selection consumed by profile and publication plugins.
- Shared custom post types: `team-member`, `testimonial`, and `services`.
- Brand assets, common shortcodes, site checks, maintenance tools, and WordPress admin cleanup.
- The Assets ACF group provides separate Brand and Banners galleries under HWS Website & Primary Entity settings.

The primary entity is optional. Sites that do not need a canonical person, organization, publication, or verified profile continue to work without one.

HWS does not own the `organization` post type or its fields; SFPF Person Profile Integration owns those structures. HWS also leaves publication-specific Knowledge Base and Resources post types to SMP Publication Integration.

## Architecture

The canonical entry point is `hws-base-tools.php`. `initialization.php` remains a compatibility loader for older active-plugin records.

Namespaced implementation code lives under `src/`. Historical root files are compatibility adapters and are not destinations for new behavior.

Reusable cross-plugin behavior comes from the bundled Hexa WordPress Plugin Core:

- Dashboard tabs, collapsible cards, buttons, activity logs, and guarded AJAX.
- Custom post type, taxonomy, and ACF structure registration and settings UI.
- Canonical entity resolution and attached-user field inspection.
- FAQ normalization/rendering and schema document utilities.
- Plugin/Core update reporting, provisioning, cleanup, search, and system utilities.
- Persistent real-time checklists, WordPress operations, LiteSpeed setting
  profiles, and reusable field-value normalization.

See [docs/architecture.md](docs/architecture.md) and [HEXA_PLUGIN_CORE_LIBRARY.md](HEXA_PLUGIN_CORE_LIBRARY.md).

## Dashboard

The HWS dashboard includes:

- A profile-driven Quick Start master checklist with individual and safe batch
  execution, resume/retry state, rollback snapshots, and before/after reporting.
- A separate Review Center for deletion candidates that must be approved one at
  a time, including complete comment removal.
- A dedicated LiteSpeed checklist with Compatibility, Safe Baseline, Editorial,
  and Aggressive Test-First profiles.
- Website and Primary Entity settings.
- Custom Post Types and ACF structure controls.
- Plugins, themes, updates, backups, cleanup, and system checks.
- Brand Assets, Footer Text, UI Cleanup, and Site Structure tools.
- Sitemaps and RSS Feeds tabs (every sitemap and feed with health checks; feeds have on/off switches), Search Display, Search Behavior, Features, and Shortcodes. Every Search tab section saves on its own as you change it (text after you stop typing) and reports Saved or Not saved with the reason in the shared Core notice.
- Featured image publication requirements for selected public content types, with optional minimum dimensions and one-time migration from Require Featured Image.
- A generic reading-progress feature with an entire-site override, front-page and public post-type targeting, five visual designs, live previews, and the shared Hexa WP Core color picker.
- Elementor query IDs and a configurable trimmed ACF text dynamic tag for native Loop Grid documents.
- An Elementor Pro display condition for safe GET parameter comparisons, including the `hws_request_parameter` condition used by URL-driven elements.
- Brand Templates for safe WordPress fallbacks and native Elementor Theme Builder imports.
- Default Page Content styling with six exact visual designs, an explicit No Style option, and an inspectable template/CSS view.
- Full Site Maintenance Mode with five exact response templates, administrator bypass, proper public 503/Retry-After/noindex handling, REST/XML-RPC protection, and a live enable/disable checklist.
- A default-off administrator-only View As User feature that opens an isolated virtual user session in a separate tab, keeps the administrator login intact, and displays a persistent red exit banner. The toolbar search starts it from any page, and the session survives refusal pages until it is ended.

Dashboard navigation and asynchronous tab loading use Hexa WP Core. Operational actions report progress without requiring full page refreshes.

See [Quick Start and Review Center](docs/quick-start.md), [LiteSpeed Profiles](docs/litespeed.md), and the [HWS Bootstrap URL](docs/bootstrap-installer.md).

## Brand Templates

Open **Settings > HWS Core Tools > Brand Templates** to opt into default Author, Page, Single Post, Category, or Tag behavior. All switches are disabled by default.

- `author.php`, `page.php`, `category.php`, and `tag.php` are plugin-owned fallbacks.
- Page and Single Post content-style switches are separately scoped and optional.
- Elementor imports use native containers, dynamic tags, current-query archive widgets, global Kit tokens, responsive controls, and Rank Math breadcrumbs.
- The default Page import excludes the front page.
- Matching Theme Builder conditions are treated as conflicts. HWS never overwrites or deactivates another template automatically.
- Managed imports are idempotent, backed up before replacement or activation changes, and expose a restore action.
- If a matching Elementor document is active, the PHP fallback yields to Elementor.

## Primary Entity

HWS stores `hws_site_type` and the optional `hws_primary_entity` record. New sites remain unclassified until a website type is deliberately selected. The optional primary entity source is:

- A WordPress user.

The entity panel shows the selected author, semantic type, public/edit links, WordPress identity values, and available profile data. SFPF, SMC, SMP Publication, and Verified Profiles consume this canonical selection while retaining plugin-owned entity relationships and read-only legacy fallbacks for migration.

## Custom Post Types

Every HWS-owned type is registered through `Hexa\PluginCore\ContentTypes`. The WordPress post-type key is immutable to protect existing content. Administrators may edit:

- Public rewrite slug.
- Singular label.
- Plural label.
- Related ACF structure toggles.

Each type and field structure is closed by default in the dashboard and includes an exact ownership and field breakdown.

## Key Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[hws_search]` | Search box with filters above any list of posts. |
| `[hws_search_box]` | Header search box: magnifying glass that opens in place, or a field. |
| `[site_logo key="logo" size="medium"]` | Render a configured brand image. |
| `[site_logo key="logo_text" width="180"]` | Constrain a logo without changing its aspect ratio. |
| `[hws_team_members]` | Render the HWS Team Member directory. |
| `[hws_publication_founders]` | The founders section (alias `[smpi_founders]`). |
| `[hws_testimonial_quote]` | Return a quote from the current or specified Testimonial. |
| `[website_content id="..."]` | Read a configured Website Settings value. |
| `[website_url]` | Return the site URL. |
| `[current_year]` | Return the current year. |

Legacy `[founder]` and `[company]` compatibility remains available, but profile-specific behavior belongs to the corresponding profile plugin.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.1 |
| Hexa WP Core bundle | 3.28.1 |

ACF or ACF Pro is optional and is required only for ACF-backed structures and values. Individual operational panels may require the plugin they inspect, such as LiteSpeed Cache or Rank Math.

## Installation

Install the repository as `wp-content/plugins/hws-base-tools`, activate `hws-base-tools.php`, and open **Settings > HWS Core Tools**. Existing installs activated through `initialization.php` are migrated by the compatibility bootstrap. Prepared sites can also use the secure administrator bootstrap URL documented above to download the canonical GitHub package without a manual ZIP workflow.

## Development

Run the complete static, unit, architecture, Core-integrity, and PHP-lint suite with:

```bash
php tests/run.php
```

The bundled Core `VERSION`, `PACKAGE_HASH`, executable source, and root `HEXA_PLUGIN_CORE_LIBRARY.md` must match the canonical Core repository exactly.

## Changelog

### 13.26.1

- Pages tab (Hexa WP Core 3.38.1): the template box is labelled "What goes into the page" and explains that its line prints the Default page content.

### 13.26.0

- Pages tab redesigned (Hexa WP Core 3.38.0): one row per company page, pages under About indented under it, and one Page details expander with the page's facts, default content and template. The menu section is only on the Menu Tools tab now.

### 13.25.0

- Bundles Hexa WP Core 3.37.0: a page in the trash shows as not set, with Create.

### 13.24.2

- Pages tab: describes all company pages Base Tools now owns and that pages under About are placed automatically.

### 13.24.1

- Company pages link the live page when a site has both a live and a draft copy (a live `privacy-policy-2` over a draft `privacy-policy`), and also find Contact at `contact-us` (Hexa WP Core 3.36.1).

### 13.24.0

- The company pages any website has now live here, each under its parent: About with Mission Statement, Founders and a profile page per founder, Founding Date, Headquarters, Logo, Parent Network and Team; plus Contact, Privacy Policy, Terms of Use, Accessibility, FAQs and RSS Feed. Existing pages are kept and moved, with their old addresses redirecting (Hexa WP Core 3.36.0).

### 13.23.0

- New 404 Page section in the sidebar (Site & Brand): a plain "Page not found" page in the site's colours and fonts, three designs, switched off by default.

### 13.22.1

- Open every hidden admin access message link in a new tab, and link both HexaTiger and Hexa Cloud Services in the closing sentence.

### 13.22.0

- New Breadcrumbs section in the sidebar (Site & Brand): the breadcrumb bar under the header moved here from SMP Publication Integration, switched off by default. A site that had SMP breadcrumbs on keeps its look; every other site shows nothing until it is switched on.

### 13.21.2

- Bundles Hexa WP Core 3.34.2: after Core copies itself into every Hexa plugin on a site, it loads the home page once and puts every copy back if the site stopped loading.

### 13.21.1

- Add Hexa Cloud Services and HexaTiger website links to the hidden admin access message, plus WhatsApp support at +1 415-212-9449. Keep the domain-specific email subject, HTTP 404 and no-cache behavior. Bundles Hexa WP Core 3.34.1.

### 13.21.0

- Safe startup: Website Identity and the Primary Entity lookup (used by Founders) return stored values before ACF has started, so an early caller cannot crash the admin (cause of the 2026-10-22 17:16 UTC crash).
- Asset loading: the auto load-more script prints only on pages with a marked list; the Team grid CSS only with a team list. Rule and checks in docs/asset-loading.md.
- Headquarters and Logo are Base Tools company pages like Contact (page type, smart template, page link); `hws_base_tools_linked_page()`.
- Missing company facts notice, fed by `hws_base_tools_pages_in_use`.
- Team tab: shortcode breakdown.
- Hexa WP Core 3.33.0.

### 13.20.0

- Founders: own definition and Founders tab (moved from SMP's Founders tab); `[hws_publication_founders]` with alias `[smpi_founders]`; read API `hws_publication_founders()`; Primary Entity person marked as a founder is included automatically, a company never is. Website Identity's founders picker folded into it. Her Forward carried over: Michael Peres, "Founder & Publisher".
- Team read API: `hws_team_members()`, `hws_team_enabled()`, `hws_team_page_url()`, `hws_team_edit_url()`.
- Every admin code and shortcode display now uses Hexa WP Core's CodeDisplay and ShortcodeBreakdown.
- Hexa WP Core 3.32.0.

### 13.19.0

- Website & Primary Entity: new Website Identity form, "This website represents: a company / a person / neither", each with its own basics; read API `hws_site_identity()` and `[hws_site_identity]`. The older Website Settings panel is relabeled as superseded and kept.
- Pages tab keeps only Contact; Terms of Use, Privacy Policy, Brand Assets, Headquarters and FAQs moved to SMP Publication Integration 2.5.17.
- Hexa WP Core 3.31.0: SourceBanner, and native fields honor wrapper classes.

### 13.18.2

- Hexa WP Core 3.30.4: an Elementor loop that shows the page's own results (Current Query loops and archive post lists) is narrowed by the search without the `hexa-listing-results` class; filter controls (checkboxes, ranges, menus) are styled in every look; the browser's own clear button no longer doubles the search field's clear button.

### 13.18.1

- Team tab: every Layout and Design choice shows a real sample of the team list with the plugin's own front-end styling; the HTML structure shows the wrapper with one person.

### 13.18.0

- Team: "Roles shown first" setting and `roles_first` attribute on `[hws_team_members]`: people whose position is listed (default Founder, Co-Founder, CEO, President, COO, CTO) come first in that order, everyone else after.

### 13.17.1

- One search for the whole site, built on Hexa WP Core 3.30.2's new single search: `[hws_search]` (box above any list, now also on ordinary pages with `post_type`, `category`, `tag`, `author`) and `[hws_search_box]` (header: magnifying glass that opens a field in place, or a field; never a popup). Every search refreshes in place with one loader: the list dims and a spinner shows. The old separate search code is gone; the old shortcode names `[hws_listing_search]` and `[hexa_search]` (header) are retired: pages using them switch to `[hws_search]` and `[hws_search_box]`.

### 13.17.0

- New Team tab: one switch for the Team Member post type, its fields and the `[hws_team_members]` listing grid, team page defaults, and a shortcode builder with live preview and the exact HTML. The team directory moved here from the Features tab.
- `[hws_team_members]` gains `design="none"` (same markup, no plugin styling) and `data-layout` / `data-columns` on the wrapper; attributes left out use the team page settings. Markup reference: `docs/team-members.md`.

### 13.16.9

- New `[hws_rss_feeds]` shortcode listing the active RSS feeds, with `only`, `exclude`, `hide` and `style` attributes. Every feed has an ID, shown next to its name on the RSS Feeds tab, which also explains the shortcode.

### 13.16.8

- Masked Login: settings and status are one column instead of two side by side.

### 13.16.7

- RSS Feeds and Sitemaps: new Settings section (items per feed, from site default to unlimited; addresses per sitemap page) and Caching section (how long LiteSpeed keeps them; clear when a post is published, updated or unpublished). Saves as you change it. Replaces the Never cache sitemaps switch with a Don't cache choice.

### 13.16.6

- Settings, Search: the `[hws_search]` attribute list is now compact rows grouped into Look, Content and Advanced; click an example to copy it.

### 13.16.5

- `[hws_search]` takes every setting as an attribute: style (none, default-1, default-2), filters, placeholder, results_one, results_many, empty, clear, accent, border, radius, panel_bg, field_width, field_height, font_size, target, class. Look attributes apply to that one box only. Settings, Search lists them all at the top of the search box section.

### 13.16.4

- Sitemaps and RSS Feeds: each row shows the server response in plain view, a green check for 200 OK or a red X for anything else, with the response time. The Server response dropdown keeps the full details.

### 13.16.3

- RSS Feeds: when comments are turned off on the site, the comments feeds (site-wide and each post's) are off automatically and drop out of the page head. The Comments row says why and links to the Comments tab to turn comments back on.

### 13.16.2

- Settings, Search: removed the leftover "Five-Plugin Search Criteria Audit" research panel.

### 13.16.1

- Sitemaps & Feeds is now two tabs: Sitemaps and RSS Feeds. Each has its own checks and tools; Never cache sitemaps stays on Sitemaps.

### 13.16.0

- New Sitemaps & Feeds tab (replaces Sitemaps): every RSS feed and sitemap with a working or broken mark, Validate, server response, advanced field view, item counts and newest date. Feeds have an Active switch. Includes Rank Math Pro's news and video sitemaps and podcast feed. Other plugins add their own through `hws_base_tools_feeds` and `hws_base_tools_sitemaps`.

### 13.15.4

- Settings, Search: no Save buttons. Every section saves as you change it and shows Saved or Not saved (with the reason) in the shared notice bar. Fixes the search box look choice, which never saved.

### 13.15.3

- Search settings: plain Save buttons that read Saving… while they work (no icon), and plain save messages.

### 13.15.2

- Settings, Search: each look (No styling, Default 1, Default 2) shows a live preview of the real search box under it. Bundles Core 3.29.8.

### 13.15.1

- The number of ticked options now shows on each filter button (it was drawn white on white). Bundles Core 3.29.7.

### 13.15.0

- The search box with filters is now `[hws_search]` and the plain header search box `[hws_search_box]`; the former names `[hws_listing_search]` and `[hexa_search]` keep working.

### 13.14.2

- The Search settings screens are one plain column: options stacked under short headings, no side-by-side card grids.

### 13.14.1

- The search box keeps its look on themes whose own field and checkbox styles used to reshape it; restyle it with the --hls-* settings. Bundles Core 3.29.6.

### 13.14.0

- Settings, Search: choose the search box's look above lists (No styling, Default 1 compact, Default 2 editorial); site CSS always wins. Shortcode attribute `style` overrides it per box. Bundles Core 3.29.5.

### 13.13.2

- A filter list opened near the right edge of the screen opens leftward so it stays on screen. Bundles Core 3.29.4.

### 13.13.1

- The listing search box keeps its look and live updates on sites where another plugin renders page content in the background first. Bundles Core 3.29.3.

### 13.13.0

- `[hws_listing_search]`: search and filters inside category, tag, author, search and any listing page, refreshed in place with counts.
- Infinite scroll with a Load more fallback moves here from Scale My Publication (`hws-auto-load-more`, `smpi-auto-load-more`).
- The JetEngine search bridge is removed. Bundles Core 3.29.2.

### 13.12.3

- Restore the public Hexa contact message on the hidden admin address, retaining HTTP 404 and uncached responses. Add a support email link with the site's canonical domain and "Login URL Request" in the subject.

### 13.12.2

- Add a reusable inline reading-progress shortcode for article facts and contents cards, synchronized with the existing top bar and its page scope.

### 13.12.1

- Bundles Hexa WordPress Plugin Core 3.28.1: the Profile page for non-administrators also drops staff plugins' contact fields and their footer scripts (Rank Math's Twitter, Facebook and extra-link fields and its "Rank Math SEO" heading).

### 13.12.0

- Staff business never reaches users below administrator. Two new UI Cleanup switches, on by default: "Plugin Notices for Non-Administrators" (only WordPress, Hexa, HWS, ACF and avatar notices reach them; Rank Math notices and donation banners never do) and "Profile Page for Non-Administrators" (no Personal Options, Application Passwords, or staff plugins' sections such as Rank Math SEO and Elementor AI). Both remove the other plugins' hooks at the source through Core's client-facing plugin list.
- "Additional User Profile Fields" is staff-only: users below administrator do not get the group. Changeable on its ACF Structures card.
- Bundles Hexa WordPress Plugin Core 3.28.0.

### 13.11.0

- User Profile Fields (2025) and Additional fields are client-friendly: shortcodes and technical notes are developer notes, shown only to administrators. Everyone below administrator sees the short help only (for example "Social media and web URLs." and no shortcode under each link). Change who gets the client view per field group on the ACF structures screen (everyone below administrator, or chosen roles).
- Bundles Hexa WordPress Plugin Core 3.27.0 (client-friendly field view; ImageZoom hover delay, hint and detail panel from 3.26.0).

### 13.10.0

- View As stays on until you end it. A page the viewed user may not open now shows WordPress's refusal with the View As banner, a Back link and the session kept, instead of a bare page that dropped the session on the next click.
- Links carry the View As session from page load, so opening them in a new tab (middle-click, right-click, copied links) stays in the session.
- The toolbar's "View as user" search is available on every page, not only users, editors and single content. Choosing a user opens View As on the page you are on.

### 13.9.1

- An expired or already-used one-click login link now shows "This login link has expired or was already used. Ask for a new one." with a link to the login page, instead of a blank page reading "0". Someone already logged in is taken to the dashboard.

### 13.9.0

- Database Optimization redesigned: summary tiles, Cleanup / Tables / Reports / Settings panels, a cleaner cleanup list with live per-item results, and a slim progress bar with Stop.
- Every table shows the plugin that created it and whether that plugin is active, inactive or not installed, from WP-Optimize's list of 27,000+ known plugin tables (GPLv2+, bundled compressed) plus name matching. Action Scheduler tables are no longer counted as WordPress's own.
- Tables: search, filter chips (Needs work, MyISAM, Space to free, Plugin tables, Inactive or removed plugins), checkboxes with bulk Check / Repair / Convert to InnoDB / Optimize, and one-click Convert all MyISAM, Free all space and Check all.
- Reports: leftover tables grouped by plugin with table count, rows and size; autoloaded settings with size bars.
- The page-load scan counts four items at a time, so the page is ready in about half the time.
- Bundles Hexa WordPress Plugin Core 3.25.0.

### 13.8.1

- Database Optimization: the cleanup and table lists scroll sideways on phones instead of squeezing words letter by letter.

### 13.8.0

- New Database Optimization tab, our own replacement for WP-Optimize and WP Sweep. Live, with no page reloads: scan, clean selected items in batches, and check, repair, convert (MyISAM to InnoDB) and optimize each table one at a time, with a live log and a Stop button.
- Cleans revisions (keeps the newest 5 per post), auto-drafts, old trash, spam and trashed comments, pingbacks, trackbacks, expired transients, embed caches, and leftover post, comment, user and term data and category links, always through WordPress's own delete functions. Optional review-first items: unapproved comments, all transients, duplicate custom fields, unused categories and tags. Retention ages are set on the tab.
- Read-only reports: largest settings loaded on every page, tables not created by WordPress with their likely plugin, and media attached to deleted posts.
- `wp hws db-optimize scan|clean|tables|run` runs the same engine from the command line.
- The Cleanup tab no longer switches WP-Optimize on for a run; it links to the new tab.
- Bundles Hexa WordPress Plugin Core 3.24.1 (shared `TaskRunner` live-progress component).

### 13.7.2

- Font smoothing on every site: text is drawn antialiased on every front-end page (on by default, option `hws_font_smoothing`), matching approved HTML drafts on Mac browsers.

### 13.7.1

- The plugin check lists Simple Local Avatars as the essential avatar plugin instead of WP User Avatars, matching SMP Publication Integration and the author photo code in Hexa WP Core.

### 13.7.0

- New SEO & Rank Math tab with the site's one Rank Math schema switch: On, Off where Hexa plugins print schema (default), or Off on every page. Rank Math stays active for titles, descriptions and sitemaps; only its schema block is removed. Where it is removed, the duplicate image schema of Featured Image From URL is removed too. The SEO / Rank Math report moved from Advanced to this tab.
- Bundles Hexa WordPress Plugin Core 3.22.0, the shared schema library every Hexa plugin now uses.

### 13.6.3

- The auto-update switch code now loads on every request (new `GenericLibrary/generic-auto-updates.php`). In 13.6.2 the switches were activated in every context but their code was still only loaded in wp-admin, so background runs still skipped them.

### 13.6.2

- Auto-Update All Plugins and Auto-Update All Themes now work. They were only switched on during wp-admin page views, so WordPress's background update run never saw them. They now load in every context and cover every plugin, including ones installed later. Plugins on GPL Vault's do-not-update list are skipped.

### 13.6.1

- View As keeps WooCommerce AJAX requests (checkout totals, place order) in the virtual session by appending its token to `wc-ajax` endpoints. Previously checkout opened as the viewed user but its background requests ran without the session.

### 13.6.0

- Bundles Hexa WordPress Plugin Core 3.16.0. New UI Cleanup option "LiteSpeed for Non-Administrators" removes the LiteSpeed post editor box, Media Library columns and admin-bar menu for users who are not administrators, using Core's shared `litespeed_ui()` preset and audience scoping.

### 13.5.0

- Bundles Hexa WordPress Plugin Core 3.15.0. UI Cleanup's Post Editor Comments option now removes the Comments box in PHP (WordPress `remove_meta_box`) instead of hiding it with CSS, and turns on automatically while comments are disabled. New FIFU Post Editor Box option removes the FIFU box. Both come from Core's shared `CleanupPresets`.

### 13.4.6

- Bundles Hexa WordPress Plugin Core 3.14.4, synchronizing the native Elementor Search input's expanded state on reopening and its collapsed state on outside clicks. Preserves inside clicks, unregistered widgets, and the existing natural search and map features.

### 13.4.5

- Bundles Hexa WordPress Plugin Core 3.14.2 with generic natural search time windows for duration-only and keyword-plus-window event searches, while preserving native WordPress and Elementor behavior.

### 13.4.4

- Bundles Hexa WordPress Plugin Core 3.13.4 with nested-search Escape handling and input-height icon alignment for native Elementor Search widgets.

### 13.4.2

- Bundles Hexa WP Plugin Core 3.13.2 with bounded correlated meta constraints for efficient host-owned upcoming-event search eligibility.

### 13.4.1

- Bundles Hexa WP Plugin Core 3.13.1 with corrected live-search status and error-grid presentation.

### 13.4.0

- Bundles Hexa WP Plugin Core 3.13.0, including the generic native Elementor Search adapter for bounded live AJAX search and Loop Item listing grids.

### 13.3.11

- Makes enabled public draft and pending-post links permanent publication-domain URLs, exposes the protected `hws_public_draft_url` REST field to editors, and keeps private or password-protected posts inaccessible.

### 13.3.10
- Moved the generic View As toolbar and account picker into Base Tools with configurable user, editor and single-content locations and optional owner metadata fields. Default disabled.
- Fixed nested destination URL encoding so editor links keep the session action and open correctly in a separate tab.


### 13.3.9

- Exposed the existing administrator-only View As session URL builder for plugin integrations, with an optional same-origin destination so a new isolated tab can open the page being inspected. Administrator authentication cookies remain unchanged.

### 13.3.8

- **Public Draft Links (48 hours)** replaces the 24-hour draft preview and now lives in Base Tools itself (Hexa WP Core 3.10.0 drops its module). A draft or pending post opens for anyone only at its own URL plus the site's secret key, `/?p=ID&draft_key=<key>`, for 48 hours after the post was created. The key is a human-friendly phrase such as `amber-falcon-river-42`, created on first use and stored in the `hws_public_draft_key` option (delete it to rotate). Each draft and pending post gets a **Public link (48h)** action in the Posts list. Without the key, or after 48 hours, the post stays private.

### 13.3.7

- Added **Public Draft Preview (24 hours)** under Features → New / Requested Features, default off. When on, anyone can open a draft dated within the last 24 hours at its own draft URL (`/?p=ID`) without logging in; older drafts return 404. The view is uncached, noindexed, and closed to comments. Bundles Hexa WP Core 3.9.0, which also brings 3.8.0 Calendar/Map link behavior and lightbox and the 3.8.1 lightbox close fix.

### 13.3.6

- The login-mask emergency URLs now require the static key: `/?hws=bypass&hws_key=hexarescue` serves the native login and `/?hws=repair&hws_key=hexarescue` flushes rewrites and purges caches. Requests without the key load normally, and the Masked Login tab shows the full keyed URLs.

### 13.3.5

- Bundled Hexa WP Core 3.7.3, which safely serializes administrator-supplied `wp-config.php` INI and constant values as PHP literals and prevents quote-bearing input from escaping generated assignments.

### 13.3.4

- Fixed a fatal `Class "Hexa\PluginCore\Fields\Field" not found` on sites whose legacy plugins (for example SMP Core Podcast Integration) include the generic library while plugins load: the ACF form-head check now waits for `init`, after Hexa WP Core resolves.

### 13.3.3

- Bundles Hexa WP Core 3.6.1 and adds the signed `external-publishing/users/{id}/profile` route (GET reads, POST writes name, bio, fields and photo through Core's user profile route), so Publish's journalist registry manages author profiles over the HWS Base Tools connection. Role, login and password are never changed.

### 13.3.2

- Bundles Hexa WP Core 3.4.10: without ACF, custom fields now behave exactly as with ACF, including groups built in the ACF admin screen, ACF's location filters, `include_fields` registration and load-safe hooks (verified value-for-value against ACF on hexaprwire.com, herforward.com and jpnmiami.com).

### 13.3.1

- Bundles Hexa WP Core 3.4.4: without ACF, field reads resolve exactly as ACF resolves them and option field references use ACF's `_options_<name>` storage name.

### 13.3.0

- ACF Pro is no longer required. Every custom field, options page, form and ACF lifecycle hook goes through Hexa WP Core 3.4.2 `Fields`: with ACF active nothing changes; without ACF, Base Tools' fields are stored, formatted and edited natively in ACF's storage layout.
- Bundles Hexa WP Core 3.4.2.

### 13.2.22

- Bundles Hexa WP Core 3.2.1: `[hexa_directory]` live search and pagination now work behind web firewalls that block the `dir` URL parameter.

### 13.2.21

- Bundles Hexa WP Core 3.2.0 and enables its shared public components on every HWS site: `[hexa_calendar]` (lightweight month-grid calendar, `docs/calendar.md` in Core), `[hexa_directory]` (live directory search), and the shared `QueryFilter` structure (taxonomy, custom field/ACF, date range, and callback filters). Site plugins or snippets register profiles; sites without profiles are unaffected.

### 13.2.20

- Returns the capability-gated WordPress login alongside each author ID, display name, nicename, email and role so bridge publications preserve the same author identity selected through WP Toolkit.

### 13.2.19

- Adds a dedicated HMAC-authenticated author-directory route so security layers that protect core WordPress users paths cannot intercept campaign author resolution before the bridge callback.

### 13.2.18

- Makes the signed External Publishing bridge resolve its bounded author directory directly after capability-gated HMAC authentication, avoiding nested WordPress user-route rejection by security layers.

### 13.2.17

- Expands the default-off External Publishing API into a complete article-delivery bridge with an allowlisted WordPress REST proxy, body-bound HMAC media uploads, owned article metadata, cache purge and article-audio routes, replay-safe mutations, and native Application Password access to the same bounded plugin capabilities.

### 13.2.16

- Adds a default-off administrator-only View As User feature with isolated request-bound sessions, unchanged administrator authentication, user-list and profile actions, a persistent red identity banner, and immediate session revocation.

### 13.2.15

- Makes `[founder id="gallery"]` the documented alias for the canonical HWS user-profile Photos gallery while retaining `[founder id="photos"]` and `[hws_profile_photos]` for compatibility.

### 13.2.14

- Replaces WordPress Application Password authentication on the default-off External Publishing bridge with a dedicated, encrypted, rotatable HMAC credential bound to timestamped, nonce-protected, body-hashed requests.

### 13.2.13

- Adds the collision-free `[hws_profile_photos]` shortcode for the canonical HWS user-profile Photos gallery.

### 13.2.12

- Adds the `[founder id="photos"]` shortcode for rendering the canonical HWS user-profile Photos gallery, with grid, URL, and attachment-ID output modes.

### 13.2.11

- Adds a default-off External Publishing dashboard control and Application Password-authenticated API for idempotent post creation, readback, updates, and deletion.

### 13.2.10

- Adds the `hws_request_parameter` Elementor Pro display condition with parameter, comparator, and value controls for native URL-driven element visibility.

### 13.2.9

- Moves Require Featured Image enforcement and its existing settings into the HWS Features panel, covering editor controls, REST publication, and server-side publication attempts.
- Adds the HexaPR Wire query IDs and HWS Trimmed ACF Text dynamic tag needed to replace JetEngine listing grids and the JetEngine trim callback with native Elementor Loop Grid documents.

### 13.2.8

- Preserves an existing WordPress Site Icon or readable root `favicon.ico` during Quick Start, returning `action=preserved` without mutation, and generates the HWS fallback only when neither asset exists.
- Bundles Hexa WordPress Plugin Core 3.0.6 with native updater activation-scope preservation, cache invalidation, canonical-folder normalization, and explicit restoration verification.

### 13.2.7

- Renames the shared ACF group to Assets, keeps the existing Brand gallery key and field name stable, and adds the Banners gallery.
- Updates the shared Hexa WP Core registry display and adds automated ACF structure coverage.

### 13.2.6

- Adds a canonical `quotes` repeater with quote text, source URL, and attribution/tagline fields to Testimonial and User - Additional ACF groups.
- Migrates Testimonial `notable_quotes` rows non-destructively, keeps legacy metadata available, and makes the testimonial quote shortcode prefer canonical rows with a legacy fallback.

### 13.2.5

- Reverses the automatic article-image policy introduced in 13.2.0: HWS Base Tools no longer generates fixed-ratio featured-image crops, backfills existing media, rewrites front-end image sources or dimensions, or overrides Rank Math schema, social, robots, and sitemap image output.
- Restores full control of featured-image presentation and SEO image selection to WordPress, the active theme or builder, and the configured SEO provider.

### 13.2.4

- Uses the attachment's descriptive alt text on the crawlable hero and verifies Google News sitemaps only when the saved module and active Rank Math Pro runtime both make that endpoint applicable.
- Adds Default Page Content styling with six exact visual designs, No Style, and code views for the active default renderer.
- Adds Full Site Maintenance Mode with five exact HTML/CSS templates, an administrator bypass, 503/Retry-After/noindex responses, REST/XML-RPC protection, and persisted permalink, cache, and verification steps.

### 13.2.3

- Rewrites a theme-authored raw featured image in the initial singular article HTML so it uses the same 1200px landscape family as metadata and carries crawlable `src`, `srcset`, `sizes`, dimensions, descriptive alt text, eager loading, and high fetch priority.

### 13.2.2

- Generates exact 1200px article crops from landscape sources whose shorter edge needs controlled upscaling, rejects undersized derivative metadata instead of marking an incomplete image family as complete, and reruns backfill with targeted article and sitemap cache refreshes.

### 13.2.1

- Adds the `[hws_testimonial_quote]` shortcode for rendering numbered notable quotes from the current or specified Testimonial, with ACF and raw-meta fallback support.

### 13.2.0

- Adds automatic 1200px 16:9, 4:3, and 1:1 crops for new and existing published article featured images across every installation.
- Extends Rank Math's existing Article graph, Open Graph, Twitter, robots, and image-sitemap output without creating a competing schema provider or overriding a deliberately selected social image.
- Keeps native featured images out of LiteSpeed lazy replacement and adds high fetch priority while preserving responsive WordPress markup.
- Completes targeted Rank Math sitemap invalidation in persistent Redis/object cache, then primes and verifies the affected sitemap endpoints with bounded retries.

### 13.1.1

- Adds reusable notable quote entries to the Testimonial ACF fields as a textarea repeater.

### 13.1.0

- Transfers Organization CPT and field ownership to SFPF Person Profile Integration while retaining the immutable post-type key for compatibility and preventing duplicate registration.

### 13.0.4

- Completes required-plugin provisioning before enabling automatic updates, verifies both WordPress memory constants at `4096M`, and captures `WP_MAX_MEMORY_LIMIT` in rollback snapshots.

### 13.0.3

- Separates LiteSpeed Redis configuration from next-request verification, fails closed for foreign object-cache drop-ins, and accepts an explicit UTC offset of zero during Quick Start identity checks. Synchronized the bundled Hexa WP Core to 3.0.1.

### 13.0.2

- Makes the HWS discussion policy authoritative over importers and other content writers: closed comment or ping defaults are enforced on every post insert/update at final priority and on frontend availability checks, while explicitly enabled discussion remains untouched.

### 13.0.1

- Invalidates stale WordPress plugin discovery before activating a fresh bootstrap install and after rollback, preventing `no_plugin_header` errors when `get_plugins()` was populated earlier in the request. The MU bootstrap loader is now version 2.0.1.

### 13.0.0

- Coordinated major release for the expanded HWS operations, provisioning, review, security, and Quick Start infrastructure, synchronized with Hexa WordPress Plugin Core 3.0.0.

### 12.1.8

- Added an entire-site reading-progress toggle that disables narrower targeting while active, plus independent front-page and dynamically discovered public post-type choices for every single item in selected types.

### 12.1.7

- Kept full Quick Start runs available when later child tasks need input, ensuring the first task can automatically install and activate missing required plugins before dependent actions continue.
- Removed the launch-email prefill workaround; email-dependent tasks validate only themselves and no longer block plugin provisioning or other runnable setup tasks.

### 12.1.6

- Prefilled Quick Start's required Wordfence and SMTP email fields from their configured values or the current administrator so Quick Run is not disabled by blank launch-contact inputs.

### 12.1.5

- Updated the bundled Hexa WP Core package to 2.1.3 so Quick Start distinguishes a selected template from a completed load and gives the Load Template button visible loading, success, and failure feedback.

### 12.1.4

- Moved the generic reading-progress bar from SMP Publication Integration into the HWS Features architecture, including enablement, scope, five shared frontend/admin designs, and the Hexa WP Core color picker.
- Added a one-time, non-destructive migration of existing SMP enablement, scope, Thin style, and color settings while preventing duplicate renderers during staggered plugin updates.

### 12.1.3

- Made Quick Start load the plugin policy it consumes and install or activate missing required plugins through the shared Hexa WP Core provisioner before plugin-dependent setup actions run.

### 12.1.2

- Preserved the canonical 2025 Threads field key while making it an explicit `Threads URL` field with URL validation, legacy-value migration coverage, and shortcode compatibility.
- Updated the bundled Hexa WP Core package to 2.1.2.

### 12.1.1

- Treat duplicate Post Type Transfer callbacks at unexpected hook priorities as critical drift and quarantine every broad query callback.

### 12.1.0

- Added fail-closed, source-validated query-hook adapters for Post Type Transfer 1.6 and Echo RSS Feed Post Generator 5.5.1.2 without editing vendor plugins.
- Capped validated Elementor Pro enhanced-search main queries at a filterable default of 100 while preserving ordinary, smaller, secondary, suppressed, and background queries.
- Skipped Post Type Transfer meta joins when no visibility rules exist, with a bounded cached presence check and complete metadata invalidation.
- Removed HWS-owned unlimited query defaults and changed complete administrative scans to fixed-size batches.
- Updated the bundled Hexa WP Core package to 1.2.0 and its shared frontend query eligibility contract.

### 12.0.0

- Scope WordPress media and editor dependencies to the HWS tabs that use them.
- Preserve asset-heavy tab behavior with explicit full-page navigation.
- Stop loading Cleanup and log-maintenance controllers on Overview requests.

### 11.2.13

- Added Site Kit by Google to the required and recommended WordPress.org plugin policy with direct AJAX install-and-activate support.

### 11.2.12

- Rebuilt the Photos gallery Details panel through the generic Hexa WP Core ACF gallery module.
- Added immediate Details refresh after native ACF add, remove, and reorder changes, including unsaved selections.
- Added larger previews, separate image-data and URL clipboard actions, and gallery-only deletion that preserves Media Library attachments.
- Updated the bundled Hexa WP Core package to 1.1.9.

### 11.2.11

- Scoped podcast plugin recommendations to Podcast Website installs.

### 11.2.10

- Updated the Core gallery clipboard action to fall back cleanly when a browser exposes but rejects the modern Clipboard API.

### 11.2.9

- Added Podcast Website to the canonical website types and mapped it to the publication entity contract.
- Added JetEngine to the explicit red-flag plugin policy.
- Added a selectable Core-powered Details panel below the HWS Photos gallery with full and generated-size URLs, new-tab links, and dynamic clipboard buttons.
- Updated the bundled Hexa WP Core package to 1.1.8.

### 11.2.8

- Added a dedicated Wikidata URL to the canonical HWS user-profile URL group, including legacy metadata migration and shortcode support.

### 11.2.7

- Removed synchronous GitHub version requests from the dashboard sidebar and kept it on local/cached version data.
- Moved the Git updater panels from the default Overview into Update Center so the initial HWS dashboard request stays local.

### 11.2.6

- Added direct new-tab links from the Brand Assets primary-author image card to the WordPress profile editor and public author archive.
- Restored all lazy-loaded plugin inventory AJAX actions so install, activate, refresh, deactivate, and delete controls reach their registered controllers.

### 11.2.5

- Added a dedicated Mail Authentication tab with an ordered SMTP2GO settings, API-key, and test-delivery workflow.
- Added the same full SMTP2GO authentication test to Quick Start and made legacy SMTP health reporting use its authoritative result.

### 11.2.4

- Suppressed the local Brand Template breadcrumb when the active Elementor header already renders a Rank Math breadcrumb.

### 11.2.3

- Made the Rank Math dependency check admin-safe because Rank Math registers its breadcrumb shortcode only for frontend rendering requests.

### 11.2.2

- Replaced Elementor Pro's Yoast-only breadcrumb widget with Elementor's native Shortcode widget and the Rank Math `[rank_math_breadcrumb]` shortcode.
- Added an import dependency check so Brand Templates cannot claim Rank Math breadcrumb support when the shortcode is unavailable.

### 11.2.1

- Preserved escaped Elementor dynamic-tag JSON in versioned managed-template backups.
- Added strict backup decoding and JSON validation so a corrupt snapshot cannot be applied.

### 11.2.0

- Added the Brand Templates dashboard for Author, Page, Single Post, Category, and Tag defaults.
- Added conflict-aware, reversible native Elementor Theme Builder imports with exact preview contexts and conditions.
- Added plugin-owned Author, Page, Category, and Tag fallbacks plus optional scoped Page and Single Post content styles.
- Added Rank Math breadcrumbs, dynamic archive data, responsive current-query grids, duplicate-breadcrumb protection, and focused regression coverage.

### 11.1.7

- Left new sites unclassified until an administrator deliberately selects a website type, without changing existing saved selections.
- Cleaned up the optional primary-author empty state and removed the blank Smart Search selection strip.
- Updated the bundled Hexa WP Core to 1.1.4.

### 11.1.6

- Added a UI Cleanup option that completely removes WooCommerce customer billing and shipping sections from profile and user-edit screens.
- Added the primary WordPress author profile image as a visible Site Icon and favicon source, using the existing PNG and ICO workflow.

### 11.1.5

- Moved the selected primary author’s complete WordPress/ACF field inventory from Website & Primary Entity to the Custom Post Types tab after ACF Structures.
- Updated the bundled Hexa WP Core to 1.1.3.

### 11.1.4

- Displayed primary-author social links as labeled rows with their complete clickable URLs.
- Updated the bundled Hexa WP Core to 1.1.2.

### 11.0.0

- Established HWS as the stable source of truth for website classification and optional primary entities.
- Standardized shared CPT, ACF, entity, schema, AJAX, updater, and admin UI integrations on Hexa WP Core 1.0.0.
- Preserved optional entity configuration and existing content, field, slug, and label settings across the major upgrade.

### 10.18.154

- Made HWS the canonical source for website classification and optional primary entities.
- Consolidated shared custom post types and ACF structures behind Hexa WP Core with editable labels and rewrite slugs.
- Removed duplicate legacy CPT/ACF registration paths while preserving existing option state.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Consolidated this README; full historical release details remain in Git history.

## Support

Report issues at <https://github.com/mikeyperes/hws-base-tools/issues>.

## License

Proprietary Hexa Web Systems software unless a source file states otherwise.
