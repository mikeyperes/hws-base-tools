# Elementor Pro custom fonts from WP-CLI

Run as the site owner, with `--user` selecting a WordPress administrator:

```sh
wp hws elementor font import "Font Family" /absolute/font.woff2 --weight=400 --style=normal --user=1
wp hws elementor font import "Font Family" https://example.com/font-italic.woff2 --weight=500 --style=italic --user=1
```

WOFF and WOFF2 only; maximum 20 MB. HTTPS downloads use WordPress safe downloading. Local source files remain untouched. The file must have a matching WOFF signature, valid declared length, supported SFNT flavor, nonzero table count and zero reserved header field. This is container validation, not a font rendering sandbox.

Both `manage_options` and `upload_files` are required. The command never enables unrestricted uploads, changes upload_mimes, or exposes a web upload endpoint. Its temporary filetype filter matches only the validated temporary path and filename and is always removed. Other formats remain governed by the site's existing policy.

Variable files are not flattened or converted. Native Elementor faces describe each requested CSS weight (1-1000) and style; all original binary axes, including optical size, remain available to browsers. Add separate faces for every weight/style the design uses. This deliberately does not mark the family as Elementor's special variable control: that generator omits style descriptors, which cannot distinguish normal and italic faces reliably. Normal browser optical sizing remains automatic. Configure typography to use the custom family rather than Google's family.

Fonts appear in Elementor > Custom Fonts (`edit.php?post_type=elementor_font`), with native file metadata, `custom` taxonomy, generated font-face CSS and refreshed Elementor font lists. No added public scripts or styles. Identical imports are a no-op; a conflicting face requires `--replace`. Other faces and previous media attachments are preserved. The command returns font ID, attachment ID, URL and SHA-256 for readback. Registration does not assign typography to any page.
