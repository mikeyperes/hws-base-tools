# Query Hook Compatibility

`HWS\BaseTools\QueryCompatibility\QueryHookCompatibility` is the single owner
of HWS adaptations for third-party callbacks that mutate public WordPress
queries. It registers through Core and reconciles at `wp_loaded` priority
`PHP_INT_MAX`, after the supported vendor versions have registered their
hooks.

## Shared Eligibility

Every HWS `pre_get_posts` or `parse_query` mutation uses the selected Hexa WP
Core 1.2.0 `QuerySafety\QueryEligibility` contract. A query must be the exact
filtered frontend main query. Admin, WP-CLI, AJAX, cron, REST, XML-RPC,
suppressed-filter, and secondary queries are rejected before settings, cache,
or database work.

## Post Type Transfer 1.6

The adapter requires one `PTT_Post_Visibility` object and validates its source
path, public one-argument method signatures, priorities, and object identity.
Every documented callback must exist exactly once across all hook priorities;
an additional callback at an unexpected priority is treated as vendor drift.
It removes only:

- `parse_query` priority 10: `fix_queried_object`
- `pre_get_posts` priority 99: `filter_queries`

It then installs one guarded delegate for each method. The vendor's dedicated
`widget_posts_args::filter_recent_posts_widget` and
`query_loop_block_query_vars::filter_query_loop_block` filters remain on their
original object.

Signature, version, source, or callback-count drift fails closed. Every exact
`PTT_Post_Visibility::fix_queried_object` and `::filter_queries` callback is
quarantined across priorities, no delegate is installed, and HWS emits
`hws_query_hook_compatibility_drift` plus
`hws_query_hook_compatibility_critical`. Dedicated secondary filters are not
removed.

The guarded delegates first consult a cached exact-prefix existence check for
`_ptt_hide_` post metadata. A site with no visibility rules does not add
pointless `NOT EXISTS` joins. The cold check is `SELECT 1 ... LIMIT 1`; its
boolean result is stored as a non-autoloaded option and in request memory.
`added_post_meta`, `updated_post_meta`, and `deleted_post_meta` immediately
invalidate the cache for matching keys, so adding the first rule restores
vendor behavior on the next eligible query.

## Echo RSS 5.5.1.2

The adapter identifies the private post-source taxonomy closure by exact
plugin version, reflected source path, one-argument signature, priority, and a
whitespace-independent token fingerprint. It removes only that closure and
installs one named callback. The callback calls
`$query->is_tax( 'coderevolution_post_source' )` and can set 404 only on the
eligible main query.

If the anonymous function drifts, HWS reports the drift and does not modify the
unmatched vendor callback.

## Elementor Pro Enhanced Search

Elementor Pro 4.2.1 constructs enhanced-search requests as
`<element-id>-<post-id>`. Read-only production inspection found values such as
`70ae8ff-270490`, `6e50f87-270529`, and `ce89a01-271825`. The adapter accepts a
1-64 character alphanumeric or underscore element ID and a positive WordPress
post ID, and requires a scalar `s` request value.

A late `pre_get_posts` callback changes `posts_per_page` only when the validated
enhanced-search main query requests zero, a negative/unlimited value, or more
than the configured maximum. The default maximum is 100 and is filterable with
`hws_query_hook_compatibility_elementor_search_max_results`. Ordinary searches,
smaller limits, malformed requests, suppressed filters, secondary queries, and
background requests are unchanged.

## HWS Query Inventory

- The syndication feed limit uses the same Core eligibility guard and remains
  bounded from 1 to 500.
- `[hws_team_members]` defaults to 24 and caps explicit limits at 100. Its
  featured-count diagnostic fetches one row and reads `found_posts`.
- Elementor Theme Builder conflict fallback scans complete template IDs in
  deterministic 100-row, no-count batches.
- User-profile migration scans complete user IDs in deterministic 100-row
  batches.

## MichaelPeres.com Active-Hook Audit

The read-only production inventory on 2026-07-30 covered every active
`parse_query` and `pre_get_posts` callback:

- Core static-front-page capture/protection uses exact main-query object state.
- Asset Delivery Portal status sorting is admin-only, main-query-only, exact-CPT,
  and requires the explicit `portal_status` order key before adding one meta
  sort.
- Elementor Pro enhanced search is covered by the bounded HWS cap above.
- Echo and Post Type Transfer are covered by the source-validated replacements
  above.
- SMP Publication Integration's author, featured-image, and visibility paths
  use selected-Core eligibility and exact host markers where secondary loops
  are supported.
- Hexa Core Search Query uses exact-query SQL-filter ownership and removes its
  filters after the owning query.

No other active callback mutates public post queries. The inventory is repeated
at deployment because a plugin update can introduce a new callback after this
release is built.

`QueryHookCompatibility::audit()` exposes state and exact callback counts. The
Core integration test `hws.query-hook-compatibility` fails on vendor drift,
missing guards, remaining broad PTT callbacks, or duplicate callbacks.
