# Lead story on the first batch only

Elementor Pro's Loop Grid can show its first story with an alternate template (a lead story). It counts the first item of
each page it loads, so every "Load more" or infinite-scroll batch gets another lead.

Add the CSS class `hws-lead-first-page` to the Loop Grid (Advanced, CSS Classes). The first batch is drawn as set up; from
the second batch on, the grid is drawn without its alternate templates, so the lead appears once.

Module: `src/FrontendContent/LoopLeadFirstPage.php`, hooked on `elementor/frontend/widget/before_render`.
