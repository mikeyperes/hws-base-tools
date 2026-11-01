# Form protection acceptance

2026-11-01 01:11:49 EST

- Canonical HWS-owned test site: https://wellnessvoice.com.
- New protection unit checks: 49 passed, zero failures.
- Native Contact Form 7 browser submission with a filled honeypot returned spam.
- Normal submission after the minimum time returned mail_sent and reached the bounded WordPress mail capture.
- External email sent: no. The capture returned success before SMTP and recorded its receipt, so this proves native form-to-mail handoff, not external mailbox delivery.
- The scanner command read the blocked attempt with form key and honeypot reason.
- Temporary form 58138, page 58139 and the mail capture fixture were removed.
- The site's form protection switch was restored to off.
- Turnstile is optional and remains unconfigured; its acceptance/rejection branches are covered with deterministic fixtures.

The full existing plugin suite was also run. It stopped in tests/plugin-policy-unit.php with: FAIL temporary WP-Sweep cleanup tooling stays out of the Quick Start required stack (10 passed, 1 failed). That unrelated policy was preserved and reported to the parent session.

Reproduction: prepare the canonical tests/fixtures/form-protection-setup.php fixture on an authorized HWS-owned site, then run tests/form-protection-browser.mjs through server 236's managed Playwright with HWS_FORM_TEST_DOMAIN and HWS_FORM_PLUGIN_PATH. Its finally block removes fixtures and restores the original switch. Never use a client site or an external send as the test.
