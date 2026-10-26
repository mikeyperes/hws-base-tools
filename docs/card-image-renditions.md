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
pixel gaps and active breakpoints. A boxed ancestor supplies the content cap;
full-width grids retain the viewport bound. Native lazy images additionally
use `auto` for their actual measured slot. Existing loading priority, image
ratio, crop, alt text, links, width and height stay intact. Other image sizes
and widgets retain their own attributes. Processing happens at the list
boundary so cached loop-item HTML gets the same correction.

The feature adds no visitor-facing controls or assets. It corrects the existing
card rendition contract automatically. Custom CSS that overrides grid column
geometry can supply `hws_base_tools_card_image_sizes` (sizes string, widget).
Only pixel gaps and pixel container caps are calculated; other units retain a
conservative viewport fallback.

Generate missing copies through WordPress:
`wp media regenerate --image_size=hws-card --only-missing --skip-delete --yes`.
This writes attachment metadata through WordPress and preserves existing crops.
It can report an unavailable rendition for a source narrower than 600px, a
missing original, or an unreadable image. Such a source must not be enlarged.

No CSS, JavaScript, network endpoint, site hostname, or layout setting is added.
