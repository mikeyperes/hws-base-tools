# Display Conditions

Off by default per site. Enable under HWS Base Tools > Display Conditions:
`/wp-admin/admin.php?page=hws-core-tools&tab=display-conditions`.

Named definitions live in the `hws_base_tools_display_conditions` site option.
The separate module switch is `hws_base_tools_display_conditions_enabled`.
The screen supports create, edit and delete with capability and nonce checks.
Sources: `custom_field` (current post meta key), `shortcode` (shortcode text
in key), `featured_image`. Rules: `has_value`, `is_empty`, `equals` (value).
Zero is a value; empty string, false and null are empty. No HTML or whitespace
is stripped from equality comparisons. Combined definitions use source
`combined`, relation `all` or `any`, and a conditions array; edit these as JSON.

Every Elementor element has Advanced > Display condition, at the bottom of
the tab. Choose None or a named condition and Show/Hide. Sections are collapsed
by Elementor by default. Should-render filters omit failed elements entirely,
using get_the_ID() for the current loop item. The editor and its preview always
show elements. Conditional elements bypass Elementor element caching. Missing
or deleted names behave like None. Switching the module off preserves data
and restores normal Elementor rendering.

## WP-CLI

- `wp hws display-conditions list`
- `wp hws display-conditions get <slug>`
- `wp hws display-conditions set <slug> '<json>'`
- `wp hws display-conditions delete <slug>`
- `wp hws display-conditions migrate-jetengine --dry-run`
- `wp hws display-conditions migrate-jetengine`

Example definition:
`{"name":"Episode has video","source":"custom_field","key":"urls_youtube","rule":"has_value"}`

Migration scans all Elementor documents except WordPress revisions, including
drafts. Existing exists/equal rules and show/hide are preserved. Conditions
are deduplicated by semantic definition; multiple rules become an all/any
combined definition. Successfully migrated and disabled legacy keys are removed.
Unsupported sources/operators and existing HWS assignments are reported with
exact document and element IDs and left unchanged. Review the unsupported list;
a successful command alone does not mean every rule was migrated. No dry-run
writes occur. A second run has nothing to migrate for supported documents.
Elementor CSS/element cache and LiteSpeed page cache are cleared after writes.
Take a database backup before migration. Definitions and elements are editable
through the native admin/Elementor interfaces afterward.

## Assets

No visitor CSS or JavaScript. Core admin UI assets render only when this tab
opens. Elementor controls use native controls with no additional editor assets.
