# Featured image orientation

Heroes are designed for a landscape featured photo. Portrait and square photos are the fallback: they must look acceptable, never cut.

## Markup

The feature adds one class to the featured image of the post being drawn (the page's own, or each story in a front-page list):

```html
<div class="elementor-widget-image hws-fi-hero hws-fi-hero--3x2">
  <div class="elementor-widget-container">
    <a href="…"><img class="attachment-large hws-fi-portrait" width="769" height="1024" …></a>
  </div>
</div>
```

`hws-fi-landscape` when width / height is above 1.05, `hws-fi-portrait` below 0.95, otherwise `hws-fi-square`.

## Styling

- The widget box keeps its shape: 16:9 by default, 3:2 with `hws-fi-hero--3x2`, or any `--hws-fi-ratio`.
- Landscape fills the box (`object-fit: cover`, focus `--hws-fi-focus`, default centre).
- Portrait and square show whole at the box height, centred on `--hws-fi-backdrop` (default: the text color at 7%).
- Under 768px a portrait box turns 4:5 (max 70vh) and a square box 1:1.

## Switches

Option `hws_featured_image_orientation`: `front_page` and `post_types[<type>]`. A missing value is on, so new content types start on. The CSS loads only on the front page and on single pages whose switch is on.

Tagged Elementor hero widgets also use [measured responsive delivery](card-image-renditions.md#responsive-heroes), independent of the orientation switches.
