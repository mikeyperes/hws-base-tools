# Instant Indexing engine (Part 4)

No module, key or automatic-submission setting is changed by this engine. Rank Math's own API handles the manual test and writes its normal submission history. The Google Instant Indexing plugin is inventoried without reading or changing its signing key.

- GET `/wp-json/hws-base-tools/v1/instant-indexing`: no input. Returns `{module_on,key,key_present,key_url,key_at_root,key_http_code,key_matches,automatic_post_types,last_submissions:[{url,code,time,manual,ok,meaning}],google_instant_indexing_plugins:[{name,installed,active,version}],warnings}`. `key` is IndexNow's public verification key, not a Google signing key. Expose only to admins. Root file is fetched from the server with redirects disabled and its body is compared with the effective Rank Math key. Foreign or non-root locations are warned about and not fetched. History is the last 20 Rank Math entries, newest first; query strings are removed from displayed history URLs.
- POST `/wp-json/hws-base-tools/v1/instant-indexing/test`: `{url}`. Returns `{url,http_code,message,ok,meaning}`, or `{ok:false,message}` for invalid state/input. Only a published page on this site or its homepage is accepted; query strings and foreign hosts are rejected. Calls Rank Math's official IndexNow submit method once. Missing module/key fails explicitly.

Both routes require `manage_options` and WordPress REST authentication; browser clients send `X-WP-Nonce`.

AJAX: `hws_base_tools_instant_indexing_status` and `hws_base_tools_instant_indexing_test`, field `nonce`, nonce action `hws_base_tools_instant_indexing`. Same inputs, wrapped in `{success,data}`.

CLI: `wp hws instant-indexing status`, `wp hws instant-indexing test --page=https://example.com/page/`. JSON output. CLI uses the trusted site operating-system account. Never use WP-CLI's reserved `--url` argument as the test-page parameter.

Answer meanings: 200 accepted; 202 accepted pending key verification; 204 accepted by Rank Math; 403 bad/inaccessible key; 422 host/key-location mismatch; 429 rate limited; 0 transport failure. HTTP acceptance is not evidence of indexing. Settings warnings include disabled module, missing key, inaccessible/mismatched root key file, empty automatic types, and removed post types.

Assets: no CSS or JavaScript was added. Only one plain placeholder container is registered for Claude's subsequent screen design.
