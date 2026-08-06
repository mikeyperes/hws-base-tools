# HWS Bootstrap URL

The bundled MU-plugin loader gives every prepared WordPress installation the
same administrator URL:

```text
/wp-admin/tools.php?page=hws-bootstrap-installer
```

The page resolves the latest non-draft, non-prerelease GitHub release and
downloads its immutable tag archive. Before changing the site it confirms that
the release tag and plugin version match, verifies the bundled Hexa WP Core
`PACKAGE_HASH`, and parses every PHP file. It then normalizes the extracted
directory to `hws-base-tools` and activates the plugin.

The prior installation remains in a private rollback directory until the next
WordPress request confirms that plugins loaded, HWS stayed active, its version
matches, and its bundled Core still passes integrity verification. A failed
health check restores the prior installation automatically.
Quick Start installs and hash-verifies the loader at
`wp-content/mu-plugins/hws-base-tools-bootstrap.php`.

Freshly migrated sites can be seeded without downloading a plugin ZIP or
loading WordPress. On a server, the CLI-only fleet seeder discovers healthy WP
Toolkit installations and atomically installs this one MU-plugin file:

```bash
php deploy/seed-bootstrap-fleet.php --toolkit          # read-only plan
php deploy/seed-bootstrap-fleet.php --toolkit --apply  # apply verified plan
```

Use `--root=/absolute/wordpress/root` for one newly migrated site. The seeder
rejects invalid WordPress roots and symlink targets, preserves filesystem
ownership, verifies the staged and committed hashes, and restores an existing
loader if commit verification fails.

For an automation runner, a server may inject a unique secret of at least 32
characters as `HWS_BOOTSTRAP_SECRET`. Generate a short-lived signed URL without
printing the secret:

```bash
HWS_BOOTSTRAP_SECRET='server-managed-secret' \
  php deploy/generate-bootstrap-url.php https://example.com 300
```

Signed URLs expire in at most ten minutes and use one-use request IDs. Each
signature is bound to the exact canonical site origin and subdirectory path,
the install action, and the GET method, so a URL generated for one site cannot
be replayed on another site in the same network. The normal browser workflow
still requires a logged-in user with plugin install and activation capabilities
plus a WordPress nonce. No shared secret belongs in the repository, WordPress
options, dashboard HTML, logs, or generated reports.
