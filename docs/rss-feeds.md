# RSS feed list: HTML structure and styling

`[hws_rss_feeds]` prints the site's active RSS feeds (Settings → HWS Base Tools → RSS Feeds). This page is the reference for building and styling it in Elementor or an HTML draft.

## Markup

One `<ul>`, one `<li>` per feed, in the order of the RSS Feeds tab (or the order given in `only=""`):

```html
<ul class="hws-rss-feeds hws-rss-feeds--default">
  <li class="hws-rss-feed hws-rss-feed--posts">
    <span class="hws-rss-feed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18">…</svg></span>
    <span class="hws-rss-feed__body">
      <a class="hws-rss-feed__title" href="https://example.com/feed/">All posts</a>
      <span class="hws-rss-feed__count">1,034</span>
      <span class="hws-rss-feed__url">https://example.com/feed/</span>
    </span>
    <button type="button" class="hws-rss-feed__copy" data-hws-copy="https://example.com/feed/">Copy link</button>
  </li>
  <li class="hws-rss-feed hws-rss-feed--press-release">…</li>
</ul>
```

- `hws-rss-feed--<id>` carries the feed's ID (`posts`, `comments`, a content type's name such as `press-release`, `categories`, `tags`, `authors`, `podcast`), so one feed can be styled on its own.
- `hws-rss-feeds--default` is present only with the built-in styling; `style="none"` leaves it off and prints no CSS.
- `hide="icon,title,url,count,copy"` removes those elements entirely (nothing hidden with CSS).
- The count is the number of published items behind the feed, not the number in the feed.
- The icon is an inline SVG drawn with `currentColor`, so it follows the colour set on `.hws-rss-feed__icon`.
- The copy button copies `data-hws-copy` and reads "Copied" for 1.5 seconds; one small script is printed once per page.

## Built-in styling

Printed once per page as `<style id="hws-rss-feeds-css">`. It inherits the site's fonts and colours; every rule is a single class, so site CSS overrides it:

```css
.hws-rss-feeds--default{list-style:none;margin:0;padding:0}
.hws-rss-feeds--default .hws-rss-feed{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(127,127,127,.2);margin:0}
.hws-rss-feeds--default .hws-rss-feed__icon{flex:0 0 auto;display:inline-flex;color:#f26522}
.hws-rss-feeds--default .hws-rss-feed__body{flex:1;min-width:0}
.hws-rss-feeds--default .hws-rss-feed__title{font-weight:600;text-decoration:none}
.hws-rss-feeds--default .hws-rss-feed__count{font-size:.85em;opacity:.65}
.hws-rss-feeds--default .hws-rss-feed__url{display:block;font-size:.85em;opacity:.7;overflow-wrap:anywhere}
.hws-rss-feeds--default .hws-rss-feed__copy{flex:0 0 auto;font:inherit;font-size:.85em;padding:4px 10px;border:1px solid currentColor;border-radius:4px;background:none;color:inherit;cursor:pointer;opacity:.8}
```

## Styling it

- **Elementor:** place a Shortcode widget with `[hws_rss_feeds style="none"]` and style the classes above in the widget's Custom CSS with `selector .hws-rss-feed…`, or keep the default styling and override single rules.
- **HTML draft:** copy the markup above with the real feeds from the RSS Feeds tab, so the approved draft maps one to one onto the shortcode's output.
- **One feed different:** target `.hws-rss-feed--<id>`.

Code: `src/SitemapTools/FeedListShortcode.php`.
