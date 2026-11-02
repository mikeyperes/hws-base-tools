# Search Console engine (Part 3)

Runtime calls Google directly. No Site Kit or Code service dependency. Signing JSON is encrypted through Core CredentialStore (OpenSSL required), never returned by any endpoint, logged, rendered or included in errors. Uploaded token_uri is ignored. Google endpoints are fixed. All endpoints require manage_options and WordPress REST authentication (cookie clients send X-WP-Nonce). AJAX requires nonce action hws_base_tools_search_console. There are no public handlers.

## REST contract

Base path `/wp-json/hws-base-tools/v1/search-console/`.

- GET `status`: no input. Output `{configured,property,console_url,registered:null|boolean,property_attempts:[],permission?,owners:[],owners_scope,owners_response?,property_response?,sitemaps:[],sitemaps_response?}`. Each sitemap retains Google's path, errors, warnings, lastDownloaded, lastSubmitted, isPending, contents and type. A missing key returns configured=false with an explanation. A 403 is unknown registration, never falsely reported unregistered. Direct verified owners are available only through Site Verification, not a complete delegated Search Console access list.
- POST `credential`: `{json:"service-account JSON"}` returns `{ok,configured}` or `{ok:false,message}`. Write-only. Do not put secrets in URLs, command arguments, debug logs or screenshots.
- POST `property`: `{property:"sc-domain:example.com"}` or exact home URL with trailing slash; returns `{ok,property}`. Rejects unrelated properties.
- POST `submit`: `{id:"index"}` from shared registry; returns `{id,url,ok,http_code,reason,message,data}`. Only this site's registered sitemap URLs are accepted.
- POST `submit-all`: empty object; returns `{results:[],count}` for currently enabled, available, recommended sitemaps. Reports every result independently, including failures.
- POST `inspect`: `{url}` on this host; returns Google's indexed-version inspection under `{ok,http_code,reason,message,data}`. This is not a live-page test.
- POST `reindex`: `{url}` on this host; returns `{ok,http_code,reason,message,data,accepted,notice}`. A 403 is explicit and preserves Google's reason. A disabled API, missing permission and unsupported page type are not silently reported as success. Accepted never means indexed.

AJAX: prefix `hws_base_tools_search_console_` plus status, submit, submit_all, inspect, reindex, credential or property. POST action, nonce and the same fields. Standard `{success,data}` wrapper; inspect inner `data.ok` for provider outcomes.

CLI: `wp hws search-console status|submit|submit-all|inspect|reindex|property`, with `--id`, `--page` or `--property` (`--url` is reserved by WP-CLI itself). `wp hws search-console credential` reads the JSON only from standard input, returns presence-only status. Trusted site operating-system account is the CLI authority. No read-key command exists.

Google uses a signed RS256 OAuth assertion with webmasters, siteverification and indexing scopes. Service tokens remain in request memory. Google API response codes and messages are surfaced; secrets are not returned.

## Official references

- https://developers.google.com/webmaster-tools/v1/sitemaps/submit
- https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect
- https://developers.google.com/site-verification/v1/webResource
- https://developers.google.com/search/apis/indexing-api/v3/using-api

## Property resolution (13.52.3)

Every service operation discovers the first accessible property in this order:
the exact host domain property, each successively shorter parent domain property
(excluding single-label suffixes), then `https://<host>/`. For example,
`podcast.michaelperes.com` tries `sc-domain:podcast.michaelperes.com`,
`sc-domain:michaelperes.com`, then `https://podcast.michaelperes.com/`.
A stored property preference does not bypass this order. The property-setting
endpoint accepts only these covering candidates; it never creates or verifies
a Google property.

Resolution is reused within a service instance, including submit-all. Status
returns the selected `property`, its `console_url`, and ordered
`property_attempts`. Owners match only the selected property, and sitemaps and
submission use that property's endpoint. Inspection passes it as `siteUrl`.
Action results also return `property`. Page and sitemap inputs must still belong
to this site's exact host, even when a parent domain is selected.

404 and 403 permit trying the next candidate; transport, authentication and
other provider errors stop discovery. When none succeeds, any unknown/error
response takes precedence over a definitive not-found response. Submission,
inspection and reindex do not proceed without an accessible property.

Google's Indexing API accepts a page URL and type, not a Search Console
property parameter. Reindex therefore checks the resolved property first,
returns it in the result, then sends the unchanged page notification payload.
Live acceptance checks use status only; they never submit or reindex a URL.
