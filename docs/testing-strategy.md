# Testing strategy

Tests must prove both behavior and wiring. A security guard is covered only when a test asserts that its hook, route permission callback, or migration registration is present and then proves the refusal or state transition.

## Suites

- Unit: pure PHP rules with no WordPress bootstrap. This is the target for provider URL parsing, retry policy, and email composition rules.
- Integration: persistence, activation, migrations, REST routing, and provider HTTP contracts against real WordPress.
- Security: authorization, nonce failure, credential redaction, upload refusal, and cross-user access.
- Accessibility: rendered admin behavior and future browser-level Axe checks.
- Performance: bounded query/request budgets based on measured fixtures rather than wall-clock assertions.
- End-to-end: critical settings, template, preview, and send-confirmation flows in a real browser.
- Email-client compatibility: structural expectations for the universal profile across Outlook's Word engine, Gmail's sanitizer, and Apple Mail's WebKit build. See [`email-compatibility.md`](email-compatibility.md).

The current PHPUnit configuration still boots WordPress for every PHP suite. Splitting a millisecond pure-unit bootstrap from the WordPress integration bootstrap is the next test-infrastructure migration.

## Local and CI environment

The browser-facing development site is managed with WordPress Studio. The repository does not own a Docker or `wp-env` runtime. Install the pinned Chromium browser once with `pnpm test:e2e:install`, then run `pnpm test:e2e`; the local wrapper obtains a short-lived Studio auto-login URL without printing it and stores browser authentication only under the ignored `.cache/` directory. CI runs the suite with one Playwright worker. Against a local Studio site, Playwright's default parallel workers can exceed Studio's request capacity and time out, so run `pnpm test:e2e --workers=1` for a result comparable to CI. Specs that insert blocks must first wait for the rendered template container (`waitForInsertableCanvas()` in `tests/e2e/support/editor.ts`), because an insertion made before the container's inner-block settings register is silently dropped. Run `pnpm qa:accessibility` for the combined PHP and browser accessibility gate. CI runs the browser accessibility spec as an explicit step before the complete E2E suite.

PHPUnit runs natively with `pnpm test`. The runner starts a disposable MySQL instance under `.cache/tests/mysql`, downloads the pinned WordPress core under `.cache/tests/wordpress`, verifies core checksums through the pinned WP-CLI binary, and uses the Composer-pinned WordPress PHPUnit library. Use `pnpm test:setup` to prepare the environment without running tests and `pnpm db:local stop` to stop the database.

CI runs the primary suites against the pinned current WordPress version and also runs every PHP suite against the declared minimum WordPress 7.1/PHP 8.4 platform with its matching PHPUnit library. Jobs use isolated MySQL 8.4 services and do not depend on a long-lived development environment. Browser E2E tests install a disposable WordPress site natively, serve it with PHP, and retain Playwright traces, screenshots, video, and the server log on failure. Packaging waits for the primary suites, minimum-platform suite, build, and browser E2E job, so a release cannot bypass a failed runtime gate.

## Email-client compatibility

Compiled email output is covered twice, deliberately. Golden fixtures in `tests/Fixtures/Email/golden/` pin exact bytes so any change to the artifact is reviewed. The client expectations in `tests/Fixtures/Email/compatibility/` pin the structural properties named email clients depend on, so output can evolve as long as those properties survive.

These fixtures are regression evidence, not a rendering claim. What they deliberately do not prove is declared as limitations inside the client fixtures, and a limitation with a detection probe fails the suite once it stops applying. [`email-compatibility.md`](email-compatibility.md) documents the matrix and the fixture-update procedure.

## Recorded provider exchanges

Hand-written provider fakes encode what we believe the provider does. `tests/Fixtures/Mailchimp/` holds exchanges recorded from a live Mailchimp account instead, and the integration tests replay them through the REST routes in strict order: every request must match the next recorded method and path, and every recording must be used. The first live run found a defect the fakes hid (Mailchimp reports an unscheduled campaign as `paused`, not `save`), so new provider behavior should be confirmed live and recorded.

Before committing a recording, replace campaign and audience IDs with the test placeholders, drop response bodies CampaignBridge does not read, and confirm it contains no API key, Authorization header, account name, email address, or other subscriber data. Each fixture's `source` field states when and how it was recorded.

## Simulated Mailchimp for browser tests

Delivery browser tests must never reach a real Mailchimp account, including
the account a developer connected to their local site. They run against
`tests/e2e/mu-plugins/campaignbridge-e2e-mailchimp.php`, a test-only
must-use plugin that answers every request to `*.api.mailchimp.com` through
`pre_http_request`, so nothing leaves the site. It keeps simulated campaigns
in an option, so draft, test, schedule, unschedule, send, and status reads
behave like a real account across requests, and it counts provider actions so
a spec can prove a double click reached the provider once. Its
`campaignbridge-e2e/v1/mailchimp` route (administrators only) resets the
simulation, seeds a fake connection on a site without one, and makes the next
action apply but answer with a 503, the ambiguous outcome CampaignBridge must
never retry.

The one exception is the read-only connection check (`/ping`): it is answered
only for the simulator's own fake key. A development site's real key gets
Mailchimp's real answer, because CampaignBridge records that answer as the
connection's verification and a simulated success must never mark a real key
verified.

## Test operators and pseudo-locale

`tests/e2e/mu-plugins/campaignbridge-e2e-operators.php` is a second test-only
must-use plugin. Its `campaignbridge-e2e/v1/operators` route (administrators
only) creates a throwaway subscriber holding exactly the requested
CampaignBridge capabilities, with a random password, and deletes only accounts
it created. The role matrix and the onboarding journey sign in as these
accounts in their own browser contexts. Adding `campaignbridge_e2e_pseudo=1`
to an admin URL wraps every `campaignbridge` string in `⟦…⟧` through the
`@wordpress/i18n` filters and serves one real translation for the Campaigns
script through `pre_load_script_translations`, so a spec can prove that every
visible string is translatable and that script translations reach the screen.

| Spec | Proves |
| --- | --- |
| `m4-journey.spec.ts` | Onboarding through template, campaign, review, approval, provider draft, test, guarded schedule and send, and reconciliation, in the UI |
| `role-matrix.spec.ts` | Author, manager, approver, deliverer, and outsider each see only their data, menus, buttons, and published actions |
| `campaign-states.spec.ts` | Accessible empty, loading, offline, stale, and denied states; keyboard-only creation; reduced motion; translatable strings |

CI copies both plugins into the disposable E2E site. Neither is in the release
package. Locally, delivery, journey, matrix, and state specs skip unless they
are installed; install them into the Studio site's `wp-content/mu-plugins/`
only for the run and remove them afterwards, because while the simulator is
present no other Mailchimp request from that site reaches Mailchimp.

## Failure policy

Risky tests and warnings fail the build. Tests must not accept contradictory outcomes such as success or rate limiting. Before a new security regression test is accepted, deliberately break the protected implementation and confirm the test fails for the named reason.
