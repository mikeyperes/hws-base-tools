# External featured image import

Run `wp hws media import-external-featured --dry-run` before the real command. Optional `--post-type=post` restricts the scan; `--meta-key=external_image_url` selects another provider. Default source is `fifu_image_url`. Every source must return HTTP 200 and decode as an image. The command uses WordPress safe HTTP downloads and media sideloads with registered thumbnail generation. Existing local attachments are reused by original URL or SHA-256 URL identity. Errors are reported per post and cause a nonzero exit status. No source meta or old attachments are deleted. Run removal only after an independent content-reference inventory and rendered comparison.

Assets: none. Registration is CLI-only; no admin or front-end assets or rendering.
