# 404 page HTML

One design is printed between the theme header and footer. `<design>` is `plinth`, `divider` or `headline`.

```html
<main class="hws-404 hws-404--plinth" id="main"><div class="inner">
  <p class="code" aria-hidden="true">404</p>
  <h1>Page not found</h1>
  <p class="text">The page you are looking for does not exist or has moved.</p>
  <a class="btn" href="https://example.com/">Back to homepage</a>
</div></main>
```

`divider` adds `<span class="rule">` after the code and wraps the heading, text and button in `<div class="msg">`. `headline` puts the code inside the heading: `<h1><span class="code">404</span> Page not found</h1>`.
