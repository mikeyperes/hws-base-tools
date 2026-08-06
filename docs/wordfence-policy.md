# Wordfence Policy

Quick Start applies a conservative WordPress-level Wordfence baseline through
Wordfence's own configuration validation and save methods. The policy verifies
security alerts, scheduled scanning, the WordPress firewall, login protection,
breached-password protection, author-enumeration protection, and TLS
verification. Existing valid alert recipients are retained and the WordPress
administrator email is added when valid.

## Licensing and legal review

`HWS_WORDFENCE_LICENSE_KEY` and the
`hws_base_tools_wordfence_license_key` filter are deployment inputs for the
current site only. Each site must receive its own authorized key. HWS never
returns a key in checklist data, copies a key from another site, or writes
`apiKey` directly. Wordfence's validated `wfConfig::save()` path checks a new
key with Wordfence and pings an unchanged key before the task can pass.

The task does not request a free key automatically and does not record
acceptance of Wordfence terms or privacy policy. A missing, conflicted,
expired, deleted, malformed, or rejected key is a failed review item. So is a
pending `touppPromptNeeded` state; an authorized user must resolve that state in
Wordfence.

## Firewall scope

The baseline accepts an enabled firewall or Wordfence learning mode. If the
firewall is disabled, it enters Wordfence learning mode through the official
save API with a seven-day grace period. Extended WAF optimization is a separate
server-level operation: status reports `extended`, `basic`, or inherited
subdirectory protection and flags non-extended protection for manual review.
It never edits `auto_prepend_file`, `.user.ini`, or `.htaccess` as part of the
WordPress-level task.
