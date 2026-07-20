# HWS Search Query Audit

## Scope

This audit defines the technical behavior behind HWS Base Tools `[hexa_search]`. The public form remains a native WordPress GET search. This work does not add AJAX suggestions or replace the separate Hexa WP Core `SmartSearch` content picker.

Audit date: 2026-07-20. The official WordPress.org stable ZIP for each plugin was downloaded and its shipped settings and query implementation inspected. The version numbers below come from each plugin's main header.

## Plugins Reviewed

### AJAX Search Lite 4.14.4

- Source: <https://wordpress.org/plugins/ajax-search-lite/>
- Primary implementation inspected: `src/server/Search/SearchQueryArgs.php` and `src/server/Search/SearchQuery.php`.
- Matching: AND, OR, exact-word variants, secondary logic, exact matches, minimum word length, and regular or indexed engines.
- Sources: selected post types; title, content, excerpt, IDs, terms, and permalink; selected or all custom fields; taxonomy, user, comment, attachment, and other source types.
- Ordering: relevance, title, dates, ID, menu order, random, custom fields, and author.
- HWS decision: separate term relation from word location, keep field selection explicit, and avoid making expensive sources automatic.

### Ivory Search / Add Search to Menu 5.5.16

- Source: <https://wordpress.org/plugins/add-search-to-menu/>
- Primary implementation inspected: `admin/class-is-editor.php`, `public/class-is-public.php`, and `public/class-is-index-search.php`.
- Matching: AND/OR, whole word, begins with, ends with, partial, indexed fuzzy matching, stemming, synonyms, and per-form behavior.
- Sources: selected post types; title, content, excerpt, author, terms, custom fields, attachments, comments, TablePress, media, and WooCommerce data.
- HWS decision: retain per-form scope and the useful whole/prefix/contains distinction. Do not put fuzzy, stemming, or synonym expansion into a live non-indexed query.

### Relevanssi 4.27.2

- Source: <https://wordpress.org/plugins/relevanssi/>
- Primary implementation inspected: `lib/search.php`, `lib/indexing.php`, `lib/phrases.php`, and the option panels under `lib/tabs/`.
- Matching: AND/OR operators, quoted phrases, fuzzy partial matching, stop words, synonyms, and TF-IDF-style relevance.
- Sources: indexed posts and public post types, taxonomy terms, custom fields, comments, authors, excerpts, shortcode output, and other indexed material.
- Operational characteristic: advanced ranking depends on a maintained index and Relevanssi documents substantial index storage requirements.
- HWS decision: preserve quoted phrases and simple relevance ordering. Indexing, weighted ranking, stop words, and synonyms require a separate indexed search product rather than hidden complexity in HWS Base Tools.

### Advanced Woo Search 3.67

- Source: <https://wordpress.org/plugins/advanced-woo-search/>
- Primary implementation inspected: `includes/admin/class-aws-admin-options.php`, `includes/class-aws-search.php`, and `includes/class-aws-index.php`.
- Matching: OR/AND, partial/exact terms, fuzzy fallback, stemming, synonyms, stop words, and weighted source relevance.
- Sources: product title, content, excerpt, SKU, ID, category, tag, variations, brands, attributes, taxonomies, and selected custom fields.
- HWS decision: expose general WordPress fields and explicit custom-field keys. Product indexing, SKU weighting, variations, and fuzzy commerce correction remain commerce-specific and out of scope.

### WP Extended Search 2.2.1

- Source: <https://wordpress.org/plugins/wp-extended-search/>
- Primary implementation inspected: `includes/class-wpes-core.php` and `includes/admin/class-wpes-admin.php`.
- Matching: AND/OR term relation and exact versus partial matching.
- Sources: title, content, excerpt, public post types, selected meta keys, selected taxonomies, and author display names.
- HWS decision: this is the closest lightweight feature model. HWS adopts its clear source controls but applies stricter context checks, exact `WP_Query` object identity, bounded terms, and opt-in `EXISTS` subqueries.

## Resulting HWS Contract

The HWS interface intentionally separates concepts that search plugins often mix together:

| Question | HWS options |
| --- | --- |
| How do multiple terms relate? | All words, any word, exact phrase |
| Where can each term match inside a word? | Whole word, prefix wildcard, contains wildcard |
| Which content is eligible? | Dynamic public searchable post types, defaulting to posts and pages |
| Which native fields are searched? | Title, content, excerpt, slug |
| Which advanced sources are searched? | Selected taxonomy names, author display names, up to 20 selected custom-field keys |
| How many results? | Keep WordPress or 1 through 100 |
| How are results ordered? | Relevance, newest, oldest, title A-Z |
| Which searches are affected? | Marked `[hexa_search]` requests only, or every public native search |

Default behavior is compatibility-safe: the enhanced engine is disabled, scope is shortcode-only, terms use all-words plus contains matching, posts and pages are eligible, title/content/excerpt are searched, WordPress controls result count, and ordering is relevance.

## Deliberately Excluded

The following features were observed but are not appropriate for this lightweight native-query layer:

- AJAX suggestions and live results: already owned by `Hexa\PluginCore\SmartSearch`.
- Indexes, TF-IDF, and weighted fields: require index lifecycle, storage reporting, and rebuild tools.
- Typo correction, fuzzy edit distance, stemming, synonyms, and stop-word dictionaries: language-specific and unpredictable without an index.
- Product variation, SKU, attribute, and stock logic: belongs to a WooCommerce-specific search adapter.
- Comment and attachment-content searching: expensive and not a safe general default.
- Negative operators and arbitrary wildcard syntax: easy to misuse and harder to explain than the explicit controls.

## Ownership

Hexa WordPress Plugin Core owns:

- `SearchQueryConfiguration`
- `SearchTermParser`
- `SearchQueryEngine`
- accepted modes and hard limits
- exact-query request guards and SQL construction

HWS Base Tools owns:

- options `hws_search_display` and `hws_search_behavior`
- `[hexa_search]`
- dynamic post-type/taxonomy discovery
- the `hexa_search=1` request marker
- capability and nonce enforcement
- the Search tab, AJAX save action, labels, defaults, and this audit

The display and behavior options must remain separate. A visual template save cannot silently enable or alter query behavior.

## Performance And Safety

The engine's `pre_get_posts` callback rejects non-search, non-main, admin, AJAX, REST, cron, feed, XML-RPC, suppressed, disabled, and empty queries before it calls the HWS settings provider. This prevents ordinary loops from performing option or object discovery.

For one eligible query, Core attaches a temporary `posts_search` filter. The filter compares the exact `WP_Query` object, ignores every other query, and removes itself immediately after the target reaches it. Taxonomy, author, and custom-field matching use opt-in correlated `EXISTS` clauses rather than broad joins.

Search parsing is limited to eight unique terms and 80 characters per term. Results are capped at 100 per page, and custom-field selection is capped at 20 explicit keys.

Do not replace this with a global permanent `posts_search` filter or a less specific `pre_get_posts` callback.

## Release Proof

Before release, run the Core query test and the complete HWS suite. Live browser proof must then use the visible HWS Search tab and a public fixture page to verify AJAX persistence, all/any/exact behavior, whole/prefix/contains behavior, field and post-type filtering, native form submission, and isolation from unmarked searches. Restore the original option and delete all fixtures afterward.
