# Team member listing: HTML structure and styling

`[hws_team_members]` prints the listing grid of published Team Members. It is the only part of a team page the plugin makes; the page heading, intro, leadership band and calls to action around it are the site's own design (Elementor or an HTML draft). This page is the reference for building and styling it.

## Turning it on

WP Admin → Settings → HWS Base Tools → Team (`options-general.php?page=hws-core-tools&tab=team`).

- **One switch** turns on the Team Member post type (`team-member`, archive at `/team/`, single at `/team-member/<slug>/`), its ACF fields (`position`, `featured` and profile details) and the shortcode together. Off hides them; saved team members are kept.
- **Roles shown first** (a team page setting) puts people with those positions at the top, for example the founder first.
- **Team page settings** are the defaults for every `[hws_team_members]` on the site (option `hws_team_page_settings`).
- **Shortcode builder** builds a shortcode from options and shows its live preview and the exact HTML it prints.

## Attributes

Every attribute is optional; one left out uses the Team page settings.

- `style`: `portrait_grid` (default), `editorial_list` or `compact_directory`.
- `design`: `default` (built-in look) or `none` (same markup, no plugin styling, for a site design that styles the classes itself).
- `columns`: 1 to 4 desktop columns for the portrait grid (`data-columns` and `--hws-team-columns`).
- `limit`: people shown, 1 to 100 (default 24).
- `orderby`: `menu_order` (the Order field, then name), `title` or `date`; `order`: `ASC` or `DESC`.
- `show_excerpt`: `1` or `0`, the short bio (the post excerpt, or the first 28 words of the content).
- `link_profiles`: `1` or `0`, links the photo, name and "View profile" to the person's page.
- `featured_only`: `1` lists only people whose `featured` field is on.
- `category`: one category slug, for example `leadership`.
- `roles_first`: positions listed first, in this order, comma-separated (default `Founder, Co-Founder, CEO, President, COO, CTO`). A person whose Position field matches one exactly (capitals ignored) comes first; everyone else follows in the `orderby` order. `roles_first=""` turns it off.

Example: `[hws_team_members design="none" columns="4" show_excerpt="0"]`.

## Markup

One wrapper, one `<article>` per person, in the chosen order:

```html
<div class="hws-team-directory" data-layout="portrait_grid" data-columns="3" style="--hws-team-columns:3" role="list">
  <article class="hws-team-card" role="listitem" itemscope itemtype="https://schema.org/Person">
    <a class="hws-team-member-media" href="https://example.com/team-member/jane-doe/" aria-label="View Jane Doe">
      <img width="434" height="575" src="…/jane-doe.jpg" class="attachment-medium_large size-medium_large" alt="Jane Doe" loading="lazy" decoding="async" />
    </a>
    <div class="hws-team-member-content">
      <h3 class="hws-team-member-name" itemprop="name">
        <a href="https://example.com/team-member/jane-doe/">Jane Doe</a>
      </h3>
      <p class="hws-team-member-position" itemprop="jobTitle">Senior Software Engineer</p>
      <p class="hws-team-member-excerpt" itemprop="description">Short bio…</p>
      <a class="hws-team-member-link" itemprop="url" href="https://example.com/team-member/jane-doe/">View profile <span aria-hidden="true">&rarr;</span></a>
    </div>
  </article>
</div>
```

- With `design="default"` the wrapper also carries `hws-team-directory--styled hws-team-directory--<style>`; with `design="none"` it carries only `hws-team-directory`, and `data-layout` still names the chosen style.
- No photo: `.hws-team-member-media` holds `<span class="hws-team-member-placeholder" aria-hidden="true">JD</span>` (the person's initials) instead of the image.
- `link_profiles="0"`: `.hws-team-member-media` is a `<span>`, the name is plain text and `.hws-team-member-link` is left out.
- No position or no bio (or `show_excerpt="0"`): that `<p>` is left out entirely, never printed empty.
- The image is the featured image at the `medium_large` size, lazy-loaded, with its real width and height.
- Nothing is printed when no published team member matches.

## Built-in styling

Printed once per page as `<style id="hws-team-directory-styles">` while the switch is on. Every rule is scoped to `.hws-team-directory--styled` or a `--<style>` class, so `design="none"` output gets none of it. The portrait grid is `repeat(var(--hws-team-columns),1fr)` on desktop, 2 columns at 900 px and below, 1 at 600 px and below; photos are 4:5.

## Styling it

- **Elementor:** a Shortcode widget with `[hws_team_members design="none"]`, styled in the widget's Custom CSS with `selector .hws-team-directory…` (grid, gap, card, photo ratio, name, position, bio, link). Keep the heading, intro and any leadership band as native widgets around it.
- **HTML draft:** copy the markup above with the site's real team members (name, position, photo, profile URL), so the approved draft maps one to one onto the shortcode's output.
- **Columns per screen size:** set `grid-template-columns` on `.hws-team-directory` at each breakpoint in the widget's Custom CSS; `data-columns` holds the desktop count.

Code: `src/TeamMembers/TeamMemberDirectory.php` (output), `src/TeamMembers/TeamTab.php` (tab), `src/TeamMembers/TeamPageSettings.php` (defaults).
