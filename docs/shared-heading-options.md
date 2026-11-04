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