# `[hws_search]`: the site's search box

The one reference for styling every search box on our sites: the box with
filters above a list of posts (category, tag, author, search and post type
pages, or a homepage section), a directory that shows its own result cards,
and the header search box. All of them are Hexa WP Core's one search, so they
share this markup, these classes and one loading look. HTML drafts copy this
markup; Elementor builds style the real box with these classes.

- Shortcodes: `[hws_search]` and `[hws_search_box]` (HWS Base Tools, `src/FrontendContent/SearchBoxFeature.php`); `[hexa_search id="…"]` for any other registered search (Hexa WP Core).
- Markup, default looks, loader and script: Hexa WP Core, `src/Search/SearchBox.php` (API: Core `docs/hexa-search.md`).
- Site default look: WP Admin, Settings, HWS Base Tools, Search tab
  (`/wp-admin/options-general.php?page=hws-core-tools&tab=search`), which also
  lists every attribute with a copyable example.

## What it searches

The page decides the scope: a category page searches that category (with its
subcategories), a tag page that tag, an author page that author, the search
page every searchable type. Typed words and ticked filters narrow the page's
own list. The list that refreshes is the Loop Grid, Posts or Archive Posts
widget with the CSS class `hexa-listing-results`; a loop showing the page's own
posts (Source: Current Query) is found without it.

On any other page (a homepage section, a landing page) the box searches what
its attributes say: `post_type`, `category`, `tag`, `author`. Give the loop
in that section the CSS class `hexa-listing-results` and put the box directly
above it; only that loop refreshes. Example for a press release section:
`[hws_search post_type="press_release" filters="category" placeholder="Search press releases"]`.

## Attributes

All optional, per box.

- Look: `style` (`none`, `default-1`, `default-2`), `accent`, `border`,
  `panel_bg`, `radius`, `field_width`, `field_height`, `font_size`.
- Content: `filters` (any of `type,category,tag,author`; order is fixed),
  `placeholder`, `results_one`, `results_many` (`%d` is the number),
  `empty`, `clear`.
- Scope on an ordinary page: `post_type`, `category`, `tag`, `author`
  (comma-separated slugs; author IDs).
- Advanced: `target` (CSS selector of the list), `class` (extra class).

Example: `[hws_search style="none" filters="category,author" placeholder="Search stories"]`

## HTML structure

```html
<form class="hls hls--default-1" id="hls-listing" role="search" method="get"
      data-hls-target=".hexa-listing-results" data-hls-min="2">
  <div class="hls-bar">
    <label class="hls-field hls-field--text">
      <span class="hls-visually-hidden">Search</span>
      <svg class="hls-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/>
        <path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input class="hls-input" type="search" name="hq" placeholder="Search Business">  <!-- the search page uses name="s" -->
    </label>
    <div class="hls-filters">
      <!-- One per filter: category, tag, author (type on search pages). -->
      <details class="hls-filter hls-field--category is-active" data-hls-key="category">
        <summary class="hls-filter-toggle">
          <span class="hls-filter-label">Category</span>
          <span class="hls-filter-badge">1</span>        <!-- only when ticked -->
        </summary>
        <div class="hls-filter-panel" role="group" aria-label="Category">
          <label class="hls-option">
            <input type="checkbox" name="hf[category][]" value="business" checked>
            <span class="hls-option-label">Business</span>
            <span class="hls-count">6</span>
          </label>
          <!-- A subcategory: style="--hls-depth:1" indents it. -->
          <label class="hls-option" style="--hls-depth:1">…</label>
        </div>
      </details>
      <details class="hls-filter hls-field--tag" data-hls-key="tag">…</details>
      <details class="hls-filter hls-field--author" data-hls-key="author">…</details>
    </div>
    <a class="hls-clear" href="…">Clear</a>          <!-- hidden until a search is active -->
    <button class="hls-submit hls-visually-hidden" type="submit">Search</button>
  </div>
  <p class="hls-status" role="status">12 stories</p> <!-- hidden until a search is active -->
</form>
```

## States

- Ticked filter: `.hls-filter.is-active`, with `.hls-filter-badge` showing how many.
- Open filter list: `.hls-filter[open]`.
- Loading (every search, one look): the form and the results element have
  `aria-busy="true"` and the results get `.hls-loading`. Their content dims to
  `--hls-loading-opacity` and a round spinner shows near the top of the
  results until the new ones arrive. Restyle it only through the loader
  properties below.
- Nothing active: `.hls-clear` and `.hls-status` carry `hidden`.
- After each refresh the page fires `hexa-search:updated` on `document`.

## Directory with its own cards

A search registered with its own cards (people, hosts) prints the same form,
plus a sort when it has several, and its results right after it:

```html
<form class="hls hls--default-1 hls--cards" id="hls-hosts" …>
  <div class="hls-bar">… text field, filters …
    <label class="hls-field hls-field--sort"><select class="hls-select" name="ho">…</select></label>
  </div>
  <p class="hls-status">24 hosts</p>
</form>
<div class="hls-results" id="hls-hosts-results">
  <ol class="hls-list"><li class="hls-item">…one card…</li></ol>
  <a class="hls-more" href="?hs=hosts&hp=2">Load more</a>   <!-- or <nav class="hls-pages"> -->
</div>
<p class="hls-empty">…</p>                                   <!-- instead of the list when nothing matches -->
```

## Header search box

`[hws_search_box]` is a magnifying glass that opens a field in place
(`style="icon-reveal"`) or an always-open field (`style="field"`). It goes to
the search page and never opens a popup.

```html
<form class="hls-head hls-head--icon-reveal is-open" role="search" method="get" action="/">
  <button class="hls-head-toggle" aria-expanded="true">…magnifying glass…</button>
  <label class="hls-head-field"><input class="hls-head-input" type="search" name="s"></label>
</form>
```

## Custom properties

Loader: `--hls-loading-opacity` (default `.4`), `--hls-loader-size` (`40px`),
`--hls-loader-surface` (`#fff`), `--hls-loader-offset` (`64px` from the top of
the results); the spinning edge uses `--hls-accent`. Set them on the results element or any parent of it (for example `body`).

Set on `.hls`; every look reads them.

- `--hls-accent` (focus line, ticked filters, badge), default `currentColor`
- `--hls-border`, default `rgba(127,127,127,.35)`
- `--hls-radius`, default `6px`
- `--hls-panel-bg` (open filter list), default `Canvas`
- `--hls-field-height`, default `36px`
- `--hls-field-width`, default `360px`
- `--hls-font-size`, default `14px`
- `--hls-small` (filter and count text)
- `--hls-depth` (per option, subcategory indent)

The properties are set with `:where(.hls)`, so a site value on `.hls` always
wins. The default looks' other rules use `.hls.hls` (and
`.hls.hls.hls--default-1`), so a theme's general field and checkbox styles
cannot reshape the box. To override one of those rules, start the selector
with `.hls.hls`, or use `style="none"` and style the box from scratch.

## Styling it

- Elementor: put `[hws_search]` in a Shortcode widget directly above the list
  and style it in that widget's Advanced, Custom CSS, every rule starting with
  `selector`, for example
  `selector .hls{--hls-accent:#c2410c;--hls-radius:0}` and
  `selector .hls.hls .hls-filter-toggle{text-transform:uppercase}`. Pick
  `style="none"` when the CSS draws the whole look.
- HTML drafts: copy the structure above as it is, keep every class, and style
  it through the same classes and properties, so the Elementor build is the
  same CSS under `selector`.
- One page only: the look attributes (`accent`, `radius`, ...) set the
  properties for that one box.
