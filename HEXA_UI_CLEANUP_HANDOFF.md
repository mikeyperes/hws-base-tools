# Hexa UI Cleanup Handoff

Status: HWS Base Tools has a working local implementation in settings-dashboard-ui-cleanup.php as of 10.18.49. This handoff defines exactly what must be extracted into Hexa WordPress Plugin Core so other plugins can use the same UI cleanup behavior without copying HWS code.

## Current Production Owner

- Site: MashViral
- Plugin: hws-base-tools
- Settings URL: wp-admin/options-general.php?page=hws-core-tools&tab=ui-cleanup
- Current implementation file: settings-dashboard-ui-cleanup.php
- Loader: initialization.php, inside the admin init block before the HWS dashboard-only include gate
- Exact editor proof URL: wp-admin/post.php?post=559904&action=edit&classic-editor&classic-editor__forget
- Passing proof: /root/codex-browser/artifacts/mashviral/hws-ui-cleanup-10-18-49-1782096362335/proof.json

## Root Cause That Was Fixed

The cleanup file was previously loaded only when hws_is_dashboard_request was true. The settings tab showed toggles as enabled, but post.php, post-new.php, profile.php, and user-edit.php did not load the CSS, JS, or filters. A reusable core version must load behavior on the target admin screens, not only inside the settings screen.

## Proposed Core Namespace

Do not put this in WpAdminComponents. Components are visual render helpers. This is admin behavior and screen mutation.

```text
src/WpAdminUiCleanup/        Hexa\PluginCore\WpAdminUiCleanup
```

Recommended classes:

```text
CleanupOptionDefinition      Option config: key, label, description, section, default, admin pages, selectors, callbacks.
CleanupRegistry              Registers definitions, resolves defaults, reads and saves option state, applies active behavior.
CleanupRenderer              Renders grouped toggle UI using WpAdminComponents CoreUi.
CleanupAjaxController        Generic AJAX save endpoint for one option and bulk actions.
AdminScreenCss               Emits CSS selectors only on allowed admin pages.
AdminScreenJs                Emits text, input, and postbox cleanup JS only on allowed admin pages.
EditorPostboxController      Hide or force-collapse classic editor postboxes.
AdminFooterController        Suppress admin footer text and update footer through filters.
PluginSpecificCleanup        Small adapters for plugins like Rank Math and LiteSpeed.
```

## Definition Shape

Each host plugin should pass definitions into the core registry using this structure:

```text
key: hide_post_editor_comments
label: Post Editor Comments
description: Hides the Comments metabox on post and page editor screens.
section: wordpress_editor
default: false
admin_pages: post.php, post-new.php
mode: css_hide
selectors: #commentsdiv, #commentsdiv-hide, label[for="commentsdiv-hide"]
```

Supported modes needed from HWS:

```text
css_hide            Output CSS selectors with display none important.
js_header_hide      Find H2 text and hide that header plus related form table.
js_input_row_hide   Find input ID and hide closest table row.
postbox_hide        Hide a classic editor metabox and its screen-option checkbox.
postbox_collapse    Keep a metabox visible but force closed class and hide inside content.
callback            Run a plugin-specific PHP callback or filter.
footer_filter       Suppress admin_footer_text and/or update_footer.
```

## HWS Options To Preserve

```text
hide_admin_color_scheme                Hide .user-admin-color-wrap on profile and user-edit.
hide_language_selector                 Hide .user-language-wrap on profile and user-edit.
hide_keyboard_shortcuts                Hide .user-comment-shortcuts-wrap on profile and user-edit.
hide_syntax_highlighting               Hide .user-syntax-highlighting-wrap on profile and user-edit.
hide_elementor_ai                      Hide Elementor AI section by header and input matching.
hide_elementor_notes                   Hide Elementor Notes section by header and input matching.
hide_wordfence_app_passwords           Hide Application Passwords section.
hide_classic_editor_default_editor     Hide .classic-editor-user-options.
hide_post_editor_comments              Hide #commentsdiv and its screen option.
hide_litespeed_editor_box              Hide #litespeed_meta_boxes and its screen option.
collapse_litespeed_editor_box          Force #litespeed_meta_boxes closed.
collapse_post_attributes_box           Force #pageparentdiv closed.
hide_wordfence_2fa                     Hide Wordfence Login Security profile section.
hide_rankmath_content_ai               Remove and hide Rank Math Content AI module, metabox, promo, admin-bar item, and menu item.
hide_rankmath_admin_footer             Suppress Rank Math admin footer text and WP update footer text.
```

## Selectors Added In HWS 10.18.49

```css
#commentsdiv,
#commentsdiv-hide,
label[for="commentsdiv-hide"],
#litespeed_meta_boxes,
#litespeed_meta_boxes-hide,
label[for="litespeed_meta_boxes-hide"],
.postbox[id*="litespeed"],
#pageparentdiv
```

Rank Math Content AI requires these extra selectors because it appears in multiple admin surfaces:

```css
.rank-math-content-ai-tab,
.rank-math-content-ai-score,
#rank-math-content-ai-metabox,
#rank_math_metabox_content_ai,
#rank_math_metabox_content_ai-hide,
label[for="rank_math_metabox_content_ai-hide"],
.rank-math-toolbar .content-ai,
[data-module="content-ai"],
.rank-math-content-ai-wrapper,
.rank-math-tab-content-content-ai,
.rank-math-content-ai-data,
.rank-math-content-ai-warning-wrapper,
.rank-math-ca-credits,
#rank-math-ca-wrap,
#rank-math-pro-cta,
[id$="-content-ai-view"],
button[id$="contentAI"],
#wp-admin-bar-rank-math-content-ai-page,
#adminmenu a[href*="rank-math-content-ai"],
#adminmenu a[href*="content-ai"]
```

## Required Load Behavior

Core must apply active cleanup behavior on these pages when definitions target them:

```text
profile.php
user-edit.php
post.php
post-new.php
```

The host plugin settings screen may render toggles in one tab, but the actual cleanup hooks must be registered admin-wide. Do not attach behavior only during the settings tab request.

## AJAX Contract

Core should expose a generic endpoint that host plugins can namespace:

```text
action: plugin_slug_ui_cleanup_toggle
nonce: plugin admin nonce
option: cleanup option key
enabled: 1 or 0
```

Response shape:

```json
{
  "success": true,
  "data": {
    "option": "hide_post_editor_comments",
    "enabled": true
  }
}
```

The renderer must update the toggle and status badge without a page refresh.

## Test Protocol

Every implementation must be tested through the visible admin UI with Puppeteer or Playwright. Backend option writes are not proof.

Required path:

1. Open the host plugin UI cleanup tab.
2. Click the visible toggles.
3. Wait for AJAX completion and badge text change.
4. Open the exact target admin screen.
5. Inspect the affected DOM nodes for display and class state.
6. Capture JSON proof and screenshots.

For HWS 10.18.49, the passing assertions were:

```text
UI section title exists: WordPress User & Editor Screens
Post Editor Comments option exists
LiteSpeed Post Editor Box option exists
LiteSpeed Collapsed By Default option exists
Post Attributes Collapsed By Default option exists
Cleanup CSS loaded on editor screen
Rank Math Content AI cleanup CSS loaded on editor screen
Comments metabox hidden
LiteSpeed metabox hidden when hide is enabled
Post Attributes metabox collapsed when collapse is enabled
Rank Math Content AI hidden
Rank Math footer suppressed
LiteSpeed metabox visible but closed when hide is off and collapse is on
No console errors
No page errors
```

## Migration Steps For Other Plugins

1. Add the core dependency or vendored core path.
2. Register a plugin-specific option namespace, for example smp_vp_ui_cleanup_ prefix.
3. Define cleanup sections and options in arrays, not scattered ad hoc CSS.
4. Render the tab with the core renderer.
5. Register the core AJAX controller with the plugin nonce and capability.
6. Register admin-wide behavior on admin_init and admin_head so target screens load the cleanup.
7. Add task-specific Puppeteer proof for each target screen.
8. Add the proof path to the plugin README or handoff notes.

## Do Not Do

- Do not load cleanup behavior only inside the settings tab.
- Do not copy selectors into multiple plugins without a core definition layer.
- Do not use backend option writes as proof.
- Do not call this section WordPress Core. The correct label is WordPress User & Editor Screens.
- Do not mix visual component rendering with behavior mutation classes.
