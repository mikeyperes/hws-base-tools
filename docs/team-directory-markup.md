# Team directory card markup

The Team tab Card markup setting, or shortcode markup="svg", opts into span-wrapped names (inside the name link when linked) and a 14 by 14 decorative SVG profile arrow. Classic remains the default and retains the original text arrow and unwrapped name. Both paginated/search and ordinary directories share this renderer. No assets or page/template changes are needed.

```html
<a href="/team-member/example/"><span>Example Name</span></a>
<span aria-hidden="true"><svg data-arrow aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h11M12.5 7.5 17 12l-4.5 4.5"/></svg></span>
```

## Portrait image sizes

`photo_sizes` accepts a responsive HTML sizes string, with the Team tab Portrait sizes hint as its default. Empty retains the original grid hint. Custom row layouts can declare their fixed portrait widths without changing any templates, for example `(max-width: 767px) 112px, (max-width: 1024px) 176px, 200px`. Sizes describe CSS pixels, not doubled dimensions; the browser accounts for display pixel density. WordPress may prefix `auto, ` for lazy-loaded images.
