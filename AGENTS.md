# HWS Base Tools Project Context

This file gives a new thread basic project orientation. It does not activate an
agent or prescribe a fixed workflow. Handle the user's task proportionally.

## Project

- Repository: `https://github.com/mikeyperes/hws-base-tools.git`
- Plugin slug: `hws-base-tools`
- Entry point and authoritative version: `hws-base-tools.php`
- Full focused test suite: `php tests/run.php`
- Architecture notes: `docs/architecture.md` and
  `HEXA_PLUGIN_CORE_LIBRARY.md`
- The plugin is installed on many WordPress sites, generally on server 236.
  A bug may be reproduced on one site while this repository remains the shared
  source of truth.

## Working Context

- For a simple lookup or small edit, inspect only the relevant file or behavior.
  Do not automatically start an agent, architecture audit, browser matrix, or
  release checklist.
- When a live site exposes the bug, establish that exact hostname, WordPress
  root, installed plugin path/version, and visible failure before changing
  shared source.
- Make maintainable source changes here rather than patching WordPress core,
  generated caches, minified files, or one site's rendered output.
- Shared Hexa WordPress Plugin Core code has its own canonical source. Do not
  leave an unsynchronized one-site copy when the change belongs to shared Core.
- If plugin source changes, keep the plugin version and Git repository
  consistent and run the focused tests relevant to the change. Documentation
  updates should be concise and relevant.

## WordPress And Elementor Boundaries

- Use WP Toolkit and supported WordPress, plugin, or Elementor interfaces for
  discovery, admin access, settings, and content when available.
- Do not mutate SQL, WordPress options, or stored content through WP-CLI, REST,
  temporary code, or another bypass without explicit permission for that exact
  task.
- Keep Elementor data structured and natively editable. A narrow Elementor
  issue does not require a site-wide audit.
- Browser tooling is available on server 236 at `/root/codex-browser` when the
  requested result needs visible browser verification.

## Safety

- Preserve unrelated files, sites, content, plugins, repositories, and data.
- Never expose, replace, log out, or reconfigure authentication material unless
  the user explicitly asks.
- Do not change an existing user's password unless directly instructed.
