# Analytics and navigation ownership follow-up

2026-11-01 00:57:41 EST

## Outcome

Both requested refinements passed on podcast.michaelperes.com using server 236's shared Playwright and system Chrome. Base Tools is OFF after the runs, and the player's original navigation setting is restored to ON. No 13.47.0 release was made in this follow-up.

The earlier 19:30 EST ETA was a time-zone mistake. A first-working-swap check-in was sent again after proving Home to About with the player's navigation disabled.

## Analytics hook tests

Manual hook ownership uses exactly one provider path per completed swap:

- gtag exists: call `gtag('event', 'page_view', {page_location, page_title, page_referrer})` once.
- No gtag, existing GTM dataLayer array: push one `{event: 'page_view', page_location, page_title, page_referrer}` object.
- Both exist: gtag owns its normal queue. The module adds no second direct dataLayer event.
- Neither exists: no call, no queue creation and no global provider creation.

Each case completed Home to About to Voices, two swaps per case, eight total. All preserved the window token and made only the initial document request. Each completed swap fired one page-changed event. Payload location and title matched the new page. gtag-only and GTM-only cases had one signal per swap; the shared-queue stub had one call and one queue entry per swap; the absent-provider case remained silent. All four cases had zero JavaScript exceptions and zero console errors.

Real GA4 delivery is explicitly out of scope until Site Kit is connected. A missing measurement ID is not a release blocker for these hook tests.

## Competing navigation protection

The backend reads the active SMP Podcast PlaybackSettings API. It blocks Base Tools startup only when both the player and its own AJAX navigation setting are enabled. An inactive or disabled player does not block unrelated sites.

With the player enabled and its navigation ON, the test saved Base Tools ON and verified:

- The AJAX Navigation tab rendered the blocked notice.
- The settings response reported `blocked: true` and the frontend eligibility check returned false.
- Chrome received HTTP 200, the normal-load meta marker and zero Base Tools navigation scripts.
- Neither Turbo nor the Base Tools runtime started.

The test then turned only player navigation OFF through its supported settings API. Base Tools became eligible, cached pages were purged by the option-change hook, and all positive browser swaps ran with the player navigation flag confirmed OFF. Cleanup turned Base Tools OFF and restored only the player's navigation flag, preserving its other current settings.

The cache hook reacts to changes in the player enabled/navigation settings while Base Tools is enabled. Unrelated player changes do not cause a navigation-cache purge.

## Automated checks and evidence

- Focused PHP unit test: 68 checks passed, including missing plugin, the player/owner truth table, startup blocking and targeted cache invalidation.
- Admin tab PHP syntax and adapter JavaScript syntax passed.
- Browser evidence: `/tmp/ajax-1-evidence/analytics-followup.json`, `/tmp/ajax-1-evidence/conflict-followup.json`, and `/tmp/ajax-1-evidence/followup.txt` on server 236.
- [Machine-readable results](ajax-navigation-results.json) include the full follow-up data.
- [Original full acceptance report](ajax-navigation-acceptance.md) retains its historical results. Its contact submission and external Google console observations were not retested or waived by this narrower follow-up.
