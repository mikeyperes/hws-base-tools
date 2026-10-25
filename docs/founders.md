# Founders: HTML structure and styling

`[hws_publication_founders]` prints the website's founders section, set on **Settings → HWS Base Tools → Founders** (`options-general.php?page=hws-core-tools&tab=founders`). `[smpi_founders]` is an alias for pages built with SMP's shortcode; it prints the same markup with the class names `smpi-founders` / `smpi-founder`.

## Who is a founder

- The Primary Entity, when it is a person marked as a founder on the Founders tab, is included automatically, first.
- More founders are added by hand (user accounts, any number, dragged order, optional title each).
- A company Primary Entity is never a founder; its founders are added by hand.
- Founding date and city are company basics (Website Identity), never part of founders.

## Settings

- On/off (off: the shortcode prints nothing; the list is kept and `hws_publication_founders()` still returns it).
- Section title, "Founders" by default; pick a suggestion (Founders, Co-Founders, Executives, Leadership, Owners, Publishers, Our team) or type anything; empty for none. Command line: `wp hws founders set --heading="Executives"`.
- Style: Cards (default), List, No styling (same markup, no built-in CSS).
- Option `hws_publication_founders`: `enabled`, `heading`, `ids`, `titles`, `style`, `entity_is_founder`.

## Markup (one founder)

```html
<section class="hws-founders-section">
  <h2 class="hws-founders__heading">Founders</h2>
  <div class="hws-founders hws-founders--cards" style="--hws-founders-columns:3">
    <article class="hws-founder hws-founder--{user-slug}">
      <a class="hws-founder__photo" href="{author page}"><img class="hws-founder__image" src="…" alt="{Name}"></a>
      <div class="hws-founder__body">
        <h3 class="hws-founder__name"><a href="{author page}">{Name}</a></h3>
        <p class="hws-founder__title">{Founder title}</p>
        <p class="hws-founder__bio">{short bio}</p>
        <a class="hws-founder__link" href="{author page}">View profile</a>
      </div>
    </article>
  </div>
</section>
```

## Shortcode attributes

`style` (cards, list, none), `columns` (1-6), `ids` (user IDs in order), `limit`, `hide` (photo, title, bio, link), `link` (profile, none), `bio_words`, `photo_size`, `heading` (tag for each name), `section_title` (`none` hides it), `section_tag` (h2, h3, h4, p).

## Read API

`hws_publication_founders()` → `[ [ 'user_id', 'name', 'title', 'url', 'bio', 'photo' ], ... ]`; `hws_publication_founders_enabled()`; `hws_publication_founders_edit_url()`. Filter `hws_publication_founders_photo_url( $url, $user_id, $size )`.

Code: `src/Founders/`.
