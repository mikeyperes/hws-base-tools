# External featured image import

Run `wp hws media import-external-featured --dry-run` before the real command. Optional `--post-type=post` restricts the scan; `--meta-key=external_image_url` selects another provider. Default source is `fifu_image_url`. Every source must return HTTP 200 and decode as an image. The command uses WordPress safe HTTP downloads and media sideloads with registered thumbnail generation. Existing local attachments are reused by original URL or SHA-256 URL identity. Errors are reported per post and cause a nonzero exit status. No source meta or old attachments are deleted. Run removal only after an independent content-reference inventory and rendered comparison.

Assets: none. Registration is CLI-only; no admin or front-end assets or rendering.

The default provider also detects FIFU placeholder thumbnail attachments by their provider author marker and HTTP source in post_content_filtered. This covers records with no fifu_image_url field. An explicitly selected custom meta key does not enable that provider fallback.

Passive SVG artwork is accepted through a strict element/attribute validation pass that rejects scripts, foreign objects, entities, event attributes, inline styles, and external references. A scoped upload MIME filter applies only to the validated sideload and is removed immediately. Native vector dimensions are stored; SVGs scale without raster crop files. Unsupported SVGs preserve the current thumbnail and report an error.
