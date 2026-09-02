# Testing strategy

Tests must prove both behavior and wiring. A security guard is covered only when a test asserts that its hook, route permission callback, or migration registration is present and then proves the refusal or state transition.

## Suites

- Unit: pure PHP rules with no WordPress bootstrap. This is the target for provider URL parsing, retry policy, and email composition rules.
- Integration: persistence, activation, migrations, REST routing, and provider HTTP contracts against real WordPress.
- Security: authorization, nonce failure, credential redaction, upload refusal, and cross-user access.
- Accessibility: rendered admin behavior and future browser-level Axe checks.
- Performance: bounded query/request budgets based on measured fixtures rather than wall-clock assertions.
- End-to-end: critical settings, template, preview, and send-confirmation flows in a real browser.

The current PHPUnit configuration still boots WordPress for every PHP suite. Splitting a millisecond pure-unit bootstrap from the WordPress integration bootstrap is the next test-infrastructure migration.

## Local and CI environment

The browser-facing development site is managed with WordPress Studio. The repository does not own a Docker or `wp-env` runtime. Install the pinned Chromium browser once with `pnpm test:e2e:install`, then run `pnpm test:e2e`; the local wrapper obtains a short-lived Studio auto-login URL without printing it and stores browser authentication only under the ignored `.cache/` directory.

PHPUnit runs natively with `pnpm test`. The runner starts a disposable MySQL instance under `.cache/tests/mysql`, downloads the pinned WordPress core under `.cache/tests/wordpress`, verifies core checksums through the pinned WP-CLI binary, and uses the Composer-pinned WordPress PHPUnit library. Use `pnpm test:setup` to prepare the environment without running tests and `pnpm db:local stop` to stop the database.

CI uses the same WordPress version, test configuration, and PHPUnit library with a MySQL 8.4 service. Suite jobs are isolated from one another and do not depend on a long-lived development environment. Browser E2E tests install a disposable WordPress site natively, serve it with PHP, and retain Playwright traces, screenshots, video, and the server log on failure.

## Failure policy

Risky tests and warnings fail the build. Tests must not accept contradictory outcomes such as success or rate limiting. Before a new security regression test is accepted, deliberately break the protected implementation and confirm the test fails for the named reason.
