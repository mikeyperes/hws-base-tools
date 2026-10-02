# Public Draft Preview

Namespace: `Hexa\PluginCore\DraftPreview`

Folder: `src/DraftPreview/`

`PublicDraftPreview` lets anyone open a recent draft at its own WordPress draft
URL without logging in. It is off until a host registers it.

```php
( new \Hexa\PluginCore\DraftPreview\PublicDraftPreview() )->register();
// Optional: window in seconds and allowed statuses.
( new PublicDraftPreview( 86400, [ 'draft' ] ) )->register();
```

## Rules

- URL: the link WordPress reports for a draft (`get_permalink()` or the REST
  `link` field): `/?p=ID` for posts, `/?page_id=ID` for pages,
  `/?post_type=<type>&p=ID` for other viewable types.
- Age: the draft's date (`post_date`, site timezone) must be within the last
  window and not in the future. WordPress moves an undated draft's date to the
  last save, so the window normally counts from the last save; a fixed date set
  by the editor or API counts from that date.
- Scope: the main singular front-end query only; admin, AJAX, REST, feeds,
  embeds, native previews, and secondary queries are untouched. Editors who can
  edit the post keep WordPress's own preview.
- Response: `no-cache` headers, LiteSpeed `litespeed_control_set_nocache`,
  `X-Robots-Tag: noindex, nofollow`, a `noindex` robots meta, and closed
  comments and pings.
- Cache safety: the visitor gets a request-local copy marked `publish`; the
  stored and object-cached post keeps its draft status.
