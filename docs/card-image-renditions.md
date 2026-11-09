# Card image renditions

HWS Base Tools registers `hws-card` at 600 pixels wide with proportional height
and no crop. Standard WordPress image generation never upscales an original.
The new size fills the gap between medium (typically 300px) and medium_large
(typically 768px). Keep the native rendition selected from the desktop box:
medium through 360px, medium_large for wider cards. The browser can then choose
600px at narrower breakpoints where a card fills a wider column.

For Elementor Loop Grid, Loop Carousel, Posts and Archive Posts, card-sized
WordPress images (`attachment-medium` or `attachment-hws-card`) receive a
responsive `sizes` attribute calculated from the list widget's column counts,
pixel gaps and active breakpoints. The nearest boxed container, read from the current document's saved element tree (so Elementor's element cache does not hide it), supplies the content cap;
full-width grids retain the viewport bound. Native lazy images additionally
use `auto` for their actual measured slot. Existing loading priority, image
ratio, crop, alt text, links, width and height stay intact. Other image sizes
and widgets retain their own attributes. Card images offer no srcset candidate wider than 768px (medium_large). Processing happens at the list
boundary so cached loop-item HTML gets the same correction.

The feature adds no visitor-facing controls or layout CSS. It corrects the existing
card rendition contract automatically. Custom CSS that overrides grid column
geometry can supply `hws_base_tools_card_image_sizes` (sizes string, widget).
Only pixel gaps and pixel container caps are calculated; other units retain a
conservative viewport fallback.

Generate missing copies through WordPress:
`wp media regenerate --image_size=hws-card --only-missing --skip-delete --yes`.
This writes attachment metadata through WordPress and preserves existing crops.
It can report an unavailable rendition for a source narrower than 600px, a
missing original, or an unreadable image. Such a source must not be enlarged.

## External loop photos and author avatars

Raster photos in Elementor card lists use a source-signed resizing route, including local imports, orientation-marked images and inaccurate responsive candidate lists. The attachment original supplies the image editor when available; the browser receives only the measured rendition.
The original is cached once under `uploads/hws-card-renditions/`; proportional
WebP renditions are cached separately. No attachment, featured-image assignment,
post content or original upload is changed. The route rejects unsigned sources,
uses WordPress safe HTTP requests and image editors, and never enlarges originals.

`card-image-delivery.js` loads only for a list that renders a measured card photo
(or replays cached delivery markup). It observes the visible image's actual box,
requests its width at 1x/2x density, rounds down to eight pixels and caps the result
at 768px. It updates on viewport/layout changes and discovers newly appended cards.
The original remote URL is never installed as the browser image source. The WordPress srcset-enrichment filter is disabled only for marked measured images, and the script removes any stale candidate list before setting their bounded source. Existing
alt text, links, dimensions, crop, loading priority and layout remain unchanged.

Post Info author avatars are rendered at twice the configured pixel box (22px
becomes 44px; 28px becomes 56px). Local avatar uploads are resized proportionally;
Gravatar requests use the requested pixel size. The same cached delivery service
handles avatars without a global avatar filter or changes to other author output.
No CSS, site hostname, template ID or content-specific width is added.


## Responsive heroes

Add the existing `hws-fi-hero` class to an Elementor image or featured-image
widget, regardless of site type or selected WordPress image size. This explicitly
opts that hero into measured delivery; untagged images and existing card/author
behavior remain unchanged. There is no new setting, page CSS or page script.

The shared delivery observer reads the actual image box on first visibility and
on layout/viewport changes. It sets `sizes` to that exact CSS width in pixels,
then offers proportional WebP candidates for 1x and 2x, rounded down to eight
pixels. Hero candidates are capped at 2560px and the source width; cards remain
capped at 768px. A 342px box offers 336w/680w; a 944px box offers 944w/1888w
when the source is large enough. A 1200px source limits the latter to 1200w.
A 635.3359375px desktop box declares `sizes="635.3359375px"`, not 800px.
The selected builder size and attachment remain intact; only browser delivery
uses the measured renditions.

Hero endpoint signatures bind the separate 2560px maximum. Changing a legacy
card endpoint's cap invalidates its signature. Existing card signatures, avatar
delivery, safe-source validation and no-upscale behavior remain compatible.
Local source dimensions are read directly; external dimensions are resolved
through the existing safe fetch/cache. No attachment metadata or content is changed.

Loading still requires JavaScript, as existing measured cards do. Until the
shared observer runs the image uses the existing lightweight placeholder.
Links, alt text, orientation fallback, crop, dimensions and loading attributes
remain in place. The feature has no dependency on publication templates.
