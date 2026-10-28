# Page collections

The `hws_page_collection` taxonomy applies only to pages. It is hierarchical for native checkbox selection and bulk editing, REST-enabled, and has no public archive, rewrite rules or front-end assets. No collection or assignment is created automatically during an update. Native WordPress page editing permissions govern management and assignment.

Create a collection under Pages → Page Collections. The reusable Elementor query `hws_magazine_issues` selects the exact `magazine-issues` slug (not descendants), published pages only, by publication date descending and ID descending for ties. It preserves widget page size, offset, pagination and existing taxonomy restrictions (combined with AND), and clears manual ID inclusion. With no matching assignments it returns zero pages.

For an eight-cover homepage section, choose Pages as the Loop Grid source, use Query ID `hws_magazine_issues`, set Items Per Page to 8 and leave include filters empty. Use dynamic Featured Image with Post URL, Post Title and Post Date inside the loop template. New issues appear automatically once published and assigned. Older issue content is never rewritten. Editors can uncheck the collection to remove an issue from the query.

For a full magazine index use the same query on a separate page with pagination, then point View more to that page. An existing editorial category link can also be retained, but it lists its category content rather than these page assignments.

Supported automation uses WordPress taxonomy interfaces: `wp term create hws_page_collection "Magazine issues" --slug=magazine-issues`, followed by `wp post term add <page-id> hws_page_collection magazine-issues --by=slug`. Review exact page IDs before assignment; do not classify by URL patterns at runtime.
