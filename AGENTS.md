# HWS Base Tools Agent Instructions

## Project Identity

- Repository: `https://github.com/mikeyperes/hws-base-tools.git`
- WordPress plugin slug: `hws-base-tools`
- Canonical entry point: `hws-base-tools.php`
- Test command: `php tests/run.php`
- This repository is a Hexa-managed plugin. Do not treat a T3 chat title as an
  agent binding, and do not assume a new task inherited a sibling chat.

## Automatic Routing

For any plugin bug fix, source change, version update, release, or live-site
issue proven to be owned by this plugin, the parent must automatically invoke
the registered `hexaweb_plugin_fix_release_worker`. The user does not need to
name that agent. Pass it one live site, one plugin, one repository, the exact
reproduction, source evidence, protected surfaces, and the required proof URL.

If a request starts as a general website bug, use
`wordpress_live_website_editor` only to diagnose ownership. When it returns
`HANDOFF_REQUEST: hexaweb_plugin_fix_release_worker`, perform that handoff
automatically instead of asking the user to repeat the request.

## Required Task Context

Before mutation, establish the exact live hostname and URL, server, validated
WordPress root, active plugin path and version, expected behavior, observed
failure, source file or data owner, canonical branch, and rollback path. Never
guess a website root or repository from a similar name.

Fresh T3 tasks inherit this file, the repository state, and global Codex
instructions. They do not inherit messages, screenshots, decisions, or evidence
from other tasks. Put durable project rules here; include task-specific facts in
the new task prompt.

## Change And Release Rules

1. Fetch and inspect status before editing. Preserve unrelated and untracked
   files, including server-generated `.ea-php-cli.cache` and `error_log`.
2. Reproduce the issue on the named live site and prove this plugin owns it
   before changing plugin source.
3. Make the smallest maintainable source change. Do not patch generated output,
   caches, WordPress core, or third-party plugins.
4. Shared Hexa WordPress Plugin Core changes belong to its canonical source and
   release flow. Do not make an unsynchronized one-off edit only in `lib/`.
5. Run focused checks and `php tests/run.php` when plugin code changes.
6. Run exact browser proof on server 236 from `/root/codex-browser` for visible
   behavior. HTTP status or selector existence alone is not visual proof.
7. For a plugin release, update every authoritative version reference and the
   README/changelog, commit only relevant files, push without force, verify the
   remote commit, and confirm the intended live site reports the new version.
8. Use the supported WordPress or plugin UI for settings changes. Do not mutate
   WordPress options or SQL without explicit permission for that exact task.
9. Never print, store, commit, replace, merge, log out, or otherwise alter Codex,
   GitHub, SSH, WordPress, or server authentication material.

## Completion Gate

Report the live URL, source owner, changed files, tests, browser proof, old and
new version when applicable, repository and commit, deployment state, protected
surfaces, rollback path, and exact remaining blocker. Do not claim completion
without concrete proof for the requested behavior.
