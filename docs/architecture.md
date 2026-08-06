# HWS Base Tools Architecture

This document is the implementation contract for HWS Base Tools. Read it before
adding a feature, moving a file, or copying behavior into another plugin.

## Package Ownership

HWS Base Tools owns site policy, HWS-specific feature definitions, WordPress
hooks, and adapters that connect those policies to reusable Hexa WordPress
Plugin Core services.

Hexa WordPress Plugin Core owns reusable behavior and reusable admin UI. Its
fixed identity is:

```text
Repository: hexa-wordpress-plugin-core
Composer package: hexa/plugin-core
Namespace: Hexa\PluginCore\
Vendored path: lib/hexa-wordpress-plugin-core/
```

Do not add HWS option names, HWS AJAX action names, HWS plugin catalogs, or HWS
business rules to Core. Do not duplicate a Core renderer, updater, AJAX guard,
cleanup service, system probe, tab shell, or activity log inside HWS.

## Entry Points

`hws-base-tools.php` is the only canonical WordPress plugin entry. It:

1. Defines the canonical path constants.
2. registers the `HWS\BaseTools\` autoloader.
3. registers the vendored Core package candidate before any Core class loads.
4. schedules `HWS\BaseTools\PluginRuntime\Bootstrap` on `plugins_loaded`.

`initialization.php` is a compatibility shim for sites whose stored active
plugin basename still points to the historical file. It must remain thin and
must not regain feature code.

Root PHP files such as `settings-dashboard.php`, `generic-functions.php`, and
`snippet-comments.php` are compatibility shims. They load their implementation
from `src/`. New implementation code must not be added to a root shim.

## HWS Namespace And Folder Map

```text
src/PluginRuntime/       HWS\BaseTools\PluginRuntime
src/AdminDashboard/      HWS\BaseTools\AdminDashboard
src/FeatureCatalog/      HWS\BaseTools\FeatureCatalog
src/FrontendContent/     HWS\BaseTools\FrontendContent
src/ContentTypes/        HWS-owned shared WordPress content types
src/AcfFields/           HWS\BaseTools\AcfFields
src/BrandAssets/         HWS site identity and legacy brand adapters
src/PluginPolicy/        HWS required/optional plugin policy
src/QuickStart/          HWS launch profiles and task adapters
src/ReviewCenter/        explicitly reviewed, individual cleanup policy
src/LiteSpeed/           HWS LiteSpeed profiles and checklist/task orchestration
src/BootstrapInstaller/  deployment of the secure GitHub bootstrap loader
src/SystemHealth/        HWS environment and health presentation
src/Maintenance/         HWS schedules and cleanup adapters
src/SitemapTools/        HWS sitemap policy and controls
src/SiteProfile/         HWS website-type policy
src/SiteStructure/       HWS page and menu policy
src/UiCleanup/           HWS wp-admin cleanup definitions and adapters
src/Security/            HWS secret storage and remote-action policy
src/QueryCompatibility/  guarded third-party query-hook adapters
src/LegacyCompatibility/ temporary procedural adapters only
```

PSR-4 classes use `HWS\BaseTools\...`. Historical procedural callbacks remain
in `hws_base_tools` while their public hook names are supported. Global fallback
functions are allowed only in `src/LegacyCompatibility/legacy-helper.php`.

## Runtime Sequence

The normal load sequence is:

1. WordPress loads `hws-base-tools.php`.
2. Core package selection resolves one compatible Core root for all plugins.
3. `PluginRuntime\Bootstrap` loads the legacy compatibility runtime once.
4. `PluginRuntime\CoreIntegration` creates one `PluginContext` and boots Core
   modules.
5. Request-specific loaders include only the HWS implementations needed for the
   current frontend, admin, AJAX, cron, or CLI request.

Never reference a `Hexa\PluginCore\...` class before the root Core bootstrap has
registered the HWS package candidate.

## Request-Specific Loading

`PluginRuntime\RequestContext` determines HWS dashboard and HWS AJAX requests.
`AdminDashboard\DashboardRegistry` owns tab IDs, labels, compatibility aliases,
render callbacks, and implementation files. A tab implementation loads when
that tab is rendered or when one of its mapped AJAX actions runs.

`FrontendContent\FeatureLoader` maps enabled HWS options to frontend behavior.
Disabled feature files must not load on ordinary frontend requests.

`Maintenance\ScheduledTaskLoader` loads scheduled maintenance implementations
only for cron and WP-CLI requests. Dashboard requests load a maintenance module
only when its tab or AJAX action needs it.

`AcfFields\AcfModule` loads field definitions on `acf/init`. Legacy SMP field
groups are disabled unless their explicit compatibility option is enabled.

`ContentTypes\SharedContentTypes` is the canonical owner of the
`organization`, `testimonial`, and `team-member` post types. It registers
enabled types on WordPress `init` without depending on ACF. Product plugins may
enable and consume these types, but must not register competing definitions.
Historical `enable_*_cpt_*` functions remain thin compatibility adapters to
this registry.

## Dashboard Contract

The tab shell comes from `Hexa\PluginCore\WpAdminTabs`. HWS owns only its tab
registry and tab-specific data. Quick Start is the second tab. Legacy Snippets
remains visible and marked deprecated until its stored options are retired.

Reusable controls must come from the matching Core namespace:

```text
Tabs and AJAX tab navigation       Hexa\PluginCore\WpAdminTabs
Buttons, toggles, cards, details   Hexa\PluginCore\WpAdminComponents
AJAX guards and request parsing    Hexa\PluginCore\WpAdminAjax
Activity logs                      Hexa\PluginCore\ActivityLog
Plugin inventory and provisioning Hexa\PluginCore\PluginChecks
GitHub plugin updater              Hexa\PluginCore\PluginUpdates
Vendored Core updater              Hexa\PluginCore\CorePackageUpdates
Shortcode catalog and testing      Hexa\PluginCore\ShortcodeRegistry
Front-end search-form templates    Hexa\PluginCore\SearchDisplay
Native search-result behavior      Hexa\PluginCore\SearchQuery
UI cleanup behavior                Hexa\PluginCore\WpAdminUiCleanup
Content and backup cleanup         Hexa\PluginCore\ContentCleanup
Environment probes                Hexa\PluginCore\SystemEnvironment
wp-config access                   Hexa\PluginCore\WpConfigFile
Cron task mechanics               Hexa\PluginCore\WpCronTasks
Persistent real-time checklists   Hexa\PluginCore\GettingStartedChecklist
WordPress update/baseline actions Hexa\PluginCore\WordPressOperations
LiteSpeed profiles and auditing   Hexa\PluginCore\LiteSpeedCache
Field value normalization         Hexa\PluginCore\DataNormalization
```

HWS callbacks supply labels, option keys, capabilities, nonces, selectors,
plugin lists, and business rules through Core configuration objects.

Third-party query-hook compatibility follows
[`docs/query-hook-compatibility.md`](query-hook-compatibility.md). Host adapters
must use selected-Core `QuerySafety\QueryEligibility`; vendor code is never
edited in place.

## Generic-Code Decision Rule

Before adding a function, apply this order:

1. If the behavior already exists in Core, call Core.
2. If the behavior is reusable by two or more plugins, implement it in the
   standalone Core repository, release Core, then update the vendored package.
3. If only HWS reuses it, add a focused class in the owning HWS domain.
4. If it must preserve a historical callback, keep a thin procedural adapter
   that delegates to the class.
5. Site-specific policy and option names stay in HWS even when Core renders or
   executes the generic mechanism.

Do not create another generic utility dump. A class belongs in the narrowest
domain that describes what it does.

## Compatibility Rules

- Keep canonical root shims until all deployed sites use `hws-base-tools.php`.
- Keep historical WordPress hook and AJAX action names unless a migration is
  released and tested.
- Never load a root shim by a relative path from a relocated implementation.
  Use `PluginMetadata::root_path()` or `HWS_BASE_TOOLS_DIR`.
- Deactivation hooks must point to the canonical plugin file.
- A compatibility adapter may delegate, normalize old input, or preserve an old
  callback. It must not become the owner of new behavior.

## Security Rules

- Every mutation requires an explicit capability and nonce check.
- Public GET mutation routes remain disabled by default.
- The bootstrap installer accepts either a logged-in administrator nonce or a
  short-lived, one-use HMAC URL whose secret is injected by the server.
- Bootstrap and Wordfence fleet secrets are never committed, logged, returned
  by AJAX, or copied from another WordPress site.
- Legacy remote actions require `HWS_ALLOW_LEGACY_REMOTE_ACTIONS`.
- Secret comparisons use `hash_equals`.
- Secrets are stored through `Security\SecretStore` and never rendered into the
  admin DOM.
- Sanitization is input-specific; escaping happens at output.

## Test Requirements

Before release:

1. Run `git diff --check`.
2. Run `composer test`.
3. Verify the canonical and legacy plugin entry paths.
4. Verify an ordinary frontend request, wp-admin, HWS dashboard, dashboard AJAX,
   cron callbacks, and the native updater.
5. Exercise AJAX tabs through visible browser controls.
6. Exercise media/editor tabs, especially Brand Assets and Footer Text.
7. Verify the Shortcodes tab lists definitions and produces test output.
8. Verify every Search preview, AJAX settings persistence, shortcode output,
   overlay controls, native `/?s=` submission, term mode, word-matching mode,
   content source, and shortcode-only query scope.
9. Confirm no PHP notices, page errors, or browser console errors.
10. Verify Quick Start, Review Center, and LiteSpeed state after a reload and
    confirm destructive or mail-sending tasks never participate in a batch.

Browser proof must use the exact visible UI path. A direct helper invocation is
not proof that an operator workflow works.

## Current Legacy Boundaries

Files prefixed `legacy-` preserve stable procedural APIs while migration
continues. Their folder is their ownership boundary; the prefix is not
permission to put unrelated functions together. When changing one, extract
new reusable logic into a class first and leave the procedural function as an
adapter.

`LegacyCompatibility/legacy-generic-functions.php` is only the compatibility
facade for the historical `hws_base_tools\*` utility surface. Its adapters are
grouped by responsibility in `LegacyCompatibility/GenericLibrary/generic-*.php`;
PSR-4 implementations in that folder use
`HWS\BaseTools\LegacyCompatibility\GenericLibrary`. Cache diagnostics read
effective LiteSpeed values through LiteSpeed's `Conf` API and never inspect
`litespeed.conf.*` option rows directly.

The remaining large procedural surfaces are the dashboard workspace, brand
asset compatibility shortcodes, Quick Start callbacks, Footer Text settings,
and historical system probes. They are intentionally isolated from the
canonical bootstrap and request loaders so they can be replaced incrementally
without changing public hooks.
