# Quick Start and Review Center

Quick Start is the repeatable HWS site-launch policy. It uses the generic Hexa
WP Core checklist engine, so every action can run independently or in an ordered
batch, progress persists between requests, and the screen updates after each
task without a page reload. A failed read-only check is reported while the batch
continues, but any failed mutation stops all later tasks.

## Profiles

- **Standard Website** applies the safe production baseline.
- **Diamond Website** applies the standard baseline and records the Diamond
  profile in its report.
- **News / Publication** includes the SMP stack and editorial LiteSpeed profile.
- **Dry Run** performs only read-only readiness checks.

## Ordered launch policy

1. Capture a verified WordPress option before-state and install the secure
   bootstrap loader.
2. Audit WordPress, PHP, identity, and permalinks.
3. synchronize Hexa WP Core across every registered host plugin.
4. Install current WordPress, plugin, and theme updates, then enable future
   automatic updates.
5. Provision and verify the required plugin stack.
6. Set WordPress memory to `4096M`, disable production debug output, close all
   comments and pings, hard-repair permalinks, and audit the real-cron handoff.
7. Apply brand and admin defaults.
8. Provision LiteSpeed, apply the selected profile, and verify it.
9. Configure the Wordfence baseline from server-injected licensing and audit
   SMTP. Sending a test email remains an individual action.
10. Purge caches, verify the public home and inner page, and build a before/after
    report.

The runtime preflight reports the PHP handler, required and optional extensions,
OPcache, Redis, upload/post/runtime limits, and whether a LiteSpeed/CloudLinux
runtime indicator is visible. CloudLinux LVE CPU, process, I/O, and account
memory quotas remain explicitly marked as server-only because WordPress cannot
reliably read or change them.

Quick Start intentionally does not delete content, plugins, themes, files, or
comments. Those candidates appear in Review Center, where scans can run as a
batch but every destructive action must be clicked individually.

Review Center covers all comments, migration plugins, overlapping cache
plugins, inactive plugins, inactive themes, WordPress sample content, temporary
cleanup tools, log/backup debris, and restoration of approved settings from the
latest Quick Start before-state.

Every cleanup requires the task's native WordPress capabilities, an
action-specific typed phrase, and the same administrator's matching scan from
the last 15 minutes. The server stores the exact reviewed targets and rejects
the action if the target set or file identity changes. Plugin and theme removal
is explicitly unsupported on multisite because it could affect other network
sites.

Sample-content cleanup only recognizes conservatively fingerprinted, untouched
WordPress defaults and moves them to Trash. Backup cleanup never targets a
backup newer than seven days and retains the newest backup from every detected
source.

Quick Start stores up to five integrity-checked, site-scoped before-state
records. Restore is deliberately limited to the approved WordPress option keys;
plugin, theme, WordPress, Core, file, and wp-config inventory is not rolled
back. Each option is read back for verification, and a failed restore attempt
is rolled back to the state present immediately before that attempt.
