# Shared heading options

Native Elementor Template widgets expose these optional settings:
- hws_heading_eyebrow: plain text, empty by default.
- hws_heading_intro: plain text, empty by default.
- hws_heading_variant: ui (default), display, newsroom.
- hws_heading_padding: default or newsroom.
- hws_heading_spacing: default or contact.
- hws_heading_role_field: executive_role by default; blank or missing values print nothing.

Native Heading widgets bind the Shortcode dynamic tag to [hws_heading_field name="title"], eyebrow, intro, or role. Hide empty widget wrappers with scoped template CSS. Placement context is nested and restored after each Template widget; the template document is never edited for individual pages.

Wrapper classes are hws-heading-variant-VARIANT, hws-heading-padding-PADDING and hws-heading-spacing-SPACING. Typography and spacing belong to the saved template's Elementor controls and scoped CSS, not the plugin. No front-end assets load.

Title resolution: search returns the query; taxonomy returns the queried term name; author returns the queried user's display name; singular returns the queried post's stored plain title. This bypasses the current loop post and SEO/document-title filters. The existing Rank Math breadcrumb shortcode remains query aware.

The optional role reads only on singular requests from the main queried post. No output is added until a template opts into the field shortcode.
Intro text falls back to the main queried singular page's saved excerpt when the per-placement value is empty. Both empty produce no line; archives and search never borrow a result's excerpt. hws_heading_intro_width is an optional pixel maximum width; empty follows the saved heading's profile. The native control exposes --hws-heading-intro-width-override.

## Optional breadcrumb middle crumbs

hws_heading_breadcrumb_middle is a native Template-widget textarea, empty by default. Enter one Label|absolute-HTTP(S)-URL pair per line. The displayed trail becomes Home, those middle crumbs in order, and the current plain context title. Home and the last crumb are automatic; do not enter them.

The Rank Math breadcrumb widget/shortcode and markup remain native. The filter is active only while the opted-in Template placement renders. Empty input, malformed rows, invalid URLs or an unavailable context title preserve the original trail in full. Other placements and the site's real page hierarchy are unchanged. It adds no frontend CSS or JS and does not modify the head's normal breadcrumb schema.

Example:
Executives|https://example.com/about/executives/

The following placement can instead use:
Team|https://example.com/team/