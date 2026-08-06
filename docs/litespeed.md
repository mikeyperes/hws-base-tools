# LiteSpeed Profiles

The HWS LiteSpeed tab is a persistent, real-time checklist built on the generic
Hexa WP Core checklist. HWS owns the profiles and task orchestration; Hexa WP
Core owns casting, audit/apply/verify, result assembly, and the official
LiteSpeed Cache `Conf` adapter. HWS does not duplicate that engine or audit and
mutate the underlying `litespeed.conf.*` database rows directly.

## Profiles

- **Compatibility** keeps risky page optimization disabled.
- **Safe Baseline** is the recommended production profile.
- **Editorial / News** adjusts caching for frequently updated publication sites.
- **Aggressive — Test First** isolates deferred JavaScript, UCSS, Guest Mode,
  and other changes that require visual/regression testing.

Every profile keeps CSS and JavaScript combining off, keeps the crawler off,
and assigns a zero TTL to HTTP 500 responses. Environment checks report server
support and Redis availability before related settings are applied. Operators
can audit or apply groups individually, or execute the safe batch in order.

Redis configuration and activation verification are separate checklist tasks.
Configuration uses LiteSpeed's official save API and managed drop-in; the next
request verifies that WordPress loaded that drop-in, connected to Redis, and
passed a cache set/get/delete round trip. Foreign object-cache drop-ins fail
closed instead of being reported as active.

Core profile application batches writable differences into LiteSpeed's normal
`update_confs()` save cycle, which performs LiteSpeed's type normalization,
purge decisions, cron work, generated-file updates, and cloud synchronization.
Verification re-reads effective values. Audit results retain stored/effective
values and their writability provenance. Missing option IDs and values
controlled by constants, filters, server settings, or multisite/network
inheritance are reported as review items and are not hidden by an ineffectual
local database write.

The final public-cache check makes two anonymous requests. It passes only when
at least one response has an HTTP status from 200 through 399 and a recognized
LiteSpeed `hit`/`miss` cache signal, or an equivalent public-control plus tag
signal. A healthy response with no LiteSpeed headers remains a failed,
explicitly proxy-ambiguous verification instead of being reported as success.
Aggressive profiles are not the Quick Start default.
