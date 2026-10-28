# First-screen images

WordPress marks every image it draws as lazy (`loading="lazy"`), including the founder portraits and photos a widget
prints. On the first screen that makes a photo appear late or show empty on a phone.

Add the CSS class `hws-eager` to the Elementor widget that draws the photo, or to the widget that holds the shortcode
that does (Advanced, CSS Classes). Its images are drawn with `loading="eager"` and `data-no-lazy="1"` (the cache plugin skips them), and the first one with
`fetchpriority="high"`. Add the same class to the cache plugin's lazy-load parent-class exclusion list so it does not
re-add its own placeholder.

Module: `src/FrontendContent/FirstScreenImages.php`, hooked on `elementor/widget/render_content`.
