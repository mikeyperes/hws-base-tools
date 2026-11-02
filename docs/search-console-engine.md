# Search Console engine (Part 3)

Runtime calls Google directly. No Site Kit or Code service dependency. Signing JSON is encrypted through Core CredentialStore (OpenSSL required), never returned by any endpoint, logged, rendered or included in errors. Uploaded token_uri is ignored. Google endpoints are fixed. All endpoints require manage_options and WordPress REST authentication (cookie clients send X-WP-Nonce). AJAX requires nonce action hws_base_tools_search_console. There are no public handlers.

## REST contract

Base path `/wp-json/hws-base-tools/v1/search-console/`.

- GET `status`: no input. Output `{configured,property,console_url,registered:null|boolean,permission?,owners:[],owners_scope,owners_response?,property_response?,sitemaps:[],sitemaps_response?}`. Each sitemap retains Google's path, errors, warnings, lastDownloaded, lastSubmitted, isPending, contents and type. A missing key returns configured=false with an explanation. A 403 is unknown registration, never falsely reported unregistered. Direct verified owners are available only through Site Verification, not a complete delegated Search Console access list.
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
