# Sitemap engine (Parts 1 and 2)

Core owns `hexa-core/v1/sitemaps`, `/check`, `/change`, `/conditions`; see the bundled [JSON contract](../lib/hexa-wordpress-plugin-core/docs/sitemaps.md) for every input and output, permissions, AJAX and CLI equivalents.

Base Tools owns `hws_base_tools_sitemap_recommendations` (rows, site_type), bridged to Core. Supply complete registered row fields when adding a provider. Recommendations never apply settings automatically. Each lookup refreshes its persisted snapshot. A nonempty site_type selects host recommendation policy; live counts and current settings still refer to this site.

POST `/wp-json/hws-base-tools/v1/sitemaps/google-news-approved` requires `manage_options`, normal REST authentication and `{approved:boolean}`; returns `{approved:boolean}`. Default false. This is Base Tools' Google News approval switch, not a claim of Google's approval. Only an administrator who has confirmed approval should enable it.

Base Tools supplies feature switches for its three content types, active-plugin recommendations, WooCommerce taxonomy rules, approved publication news and business-address local recommendations. Core live checks reuse XmlInspector, including the actual response, parsed checks and stored result. Existing Sitemaps markup and feed switches remain intact. Claude will design the additional controls against these endpoints.

Local policy uses the primary `hws_site_type` (or explicitly requested lookup type). Only `company_website` and `ecommerce_website` qualify; news, podcast, personal, unset and all other types remain off even with an address. Rank Math `rank-math-options-titles.local_address` must contain a nonblank, non-PO-box `streetAddress`, `addressLocality` and `addressCountry`. `local_business_eligibility` reports the policy and field-presence evidence without changing any settings.
