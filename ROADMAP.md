# CampaignBridge product roadmap

## Product mission

CampaignBridge should let a WordPress team turn site content into compliant,
provider-ready email campaigns without leaving WordPress. It owns template
composition, content selection, review, delivery orchestration, and operational
history while the email service provider remains responsible for subscriber
membership and final delivery.

The intended operator flow is:

`Connect provider → Design template → Select content → Preview/test → Approve → Create provider draft → Schedule/send → Reconcile/report`

CampaignBridge is not intended to become a subscriber database, SMTP server, or
general marketing-automation suite during the first production cycle.

## Product assumptions

These are the recommended defaults until a product decision explicitly changes
them:

- WordPress is the system of record for templates, campaigns, content snapshots,
  delivery attempts, and audit events.
- Provider audiences, segments, tags, and subscriber records remain in the
  provider. CampaignBridge stores stable remote references, not audience PII.
- Campaign content is snapshotted at approval time. Refreshing content after
  approval is an explicit action that requires another review.
- Remote campaigns are created as drafts first. Sending and scheduling are
  separate, explicit, capability-protected operations.
- Every remote mutation has an idempotency strategy and a reconciliation path.
  An uncertain response must never cause an automatic duplicate send.
- Mailchimp and HTML export are the first complete delivery paths. Additional
  providers wait until the provider-neutral workflow is proven end to end.
- Templates continue to use the `cb_templates` post type. Campaigns, jobs, and
  audit events use repository abstractions backed by durable, indexed storage.

## Current product state

CampaignBridge has a substantial engineering foundation, but it is not yet a
complete campaign-management product.

| Area               | Present today                                                                                          | Material gap                                                                                                                                        |
| ------------------ | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Template authoring | Custom post type, React block-editor shell, autosave, template metadata, CampaignBridge content blocks | Draft/publish semantics, preview/test workflow, supported-block contract, production output fixtures                                                |
| Email generation   | Provider-neutral generator, block processor, responsive structure, HTML provider                       | The CSS inliner is explicitly simplified; compliance validation and email-client regression coverage are missing                                    |
| Providers          | Provider interface, Mailchimp adapter, HTML export adapter, encrypted credential foundation            | The interface reduces delivery to `send_campaign`; there is no complete connection, audience, draft, test, schedule, status, or reporting lifecycle |
| Campaigns          | README-level product concept                                                                           | No campaign domain model, persistence, state machine, content snapshot, operator workflow, or audit timeline                                        |
| Admin              | File-based screens, secure form system, settings, post-type selection, status page                     | Demo/test screens remain, some status values are mocked, and provider settings are not yet one canonical connection model                           |
| API                | Posts, post types, editor settings, and encrypted-field routes                                         | No provider discovery, campaign, preview, delivery, reconciliation, or reporting API                                                                |
| Operations         | Hardened CI, package verification, security controls, runbook, broad PHP test suites                   | No durable job runner, delivery locks, webhook ingestion, reconciliation monitor, browser E2E, or production metrics                                |

The README describes several target-state capabilities as though they are
already complete. Milestone 0 makes documentation and visible UI match shipped
behavior before broader product development continues.

## Target domain and architecture

The existing dependency direction in `docs/architecture.md` remains the target:

`Delivery → Workflow → Domain ← Repository implementations`

The first production domain should contain these concepts:

- **Provider connection**: encrypted credentials, configuration, capabilities,
  health, and stable provider account identity.
- **Template**: reusable block content and email metadata stored in WordPress.
- **Campaign**: provider-neutral subject, sender, audience reference, content
  snapshot, lifecycle state, and ownership.
- **Delivery attempt**: one idempotent attempt to create, test, schedule, send,
  cancel, or reconcile a remote campaign.
- **Remote campaign reference**: provider, remote ID, last observed state, and
  reconciliation cursor.
- **Audit event**: actor, action, timestamp, target, result, and redacted context.

The campaign lifecycle should be explicit and guarded:

`draft → ready_for_review → approved → provider_draft → scheduled|sending → sent`

Failure and recovery states must include `failed`, `cancelled`, and `unknown`.
`unknown` is important: it prevents a timeout after an irreversible provider
request from being mistaken for a safe retry.

The current provider interface should evolve from one coarse
`send_campaign()` operation into capability-driven operations such as connection
verification, audience discovery, remote draft creation, content update, test
send, schedule, send, cancel, status lookup, and report retrieval. Provider DTOs
and normalized errors must prevent provider response shapes from leaking into
the domain or UI.

## Delivery milestones

Milestones are ordered by dependency and release outcome rather than calendar
date. A milestone is complete only when its exit gate passes.

### Milestone 0 — Product truth and stable contracts

**Outcome:** The repository, UI, and documentation accurately describe a secure
template-building foundation, and new campaign work has stable contracts to
build upon.

Deliverables:

- Replace scattered provider options with one versioned connection/settings
  schema and migration path.
- Make connection status a real provider verification result; remove fabricated
  campaign and subscriber statistics.
- Remove production menu exposure for conditional, repeater, and form demo
  screens, keeping examples in development documentation or fixtures.
- Reconcile README and API documentation with routes and capabilities that
  actually ship.
- Define granular capabilities for managing connections, editing templates,
  creating campaigns, approving/sending campaigns, and viewing reports.
- Define repository ports, provider DTOs, normalized error categories, campaign
  states, schema versioning, and migration/rollback rules.
- Add architectural tests that prevent delivery code from bypassing workflow
  services or persistence ports.

Exit gate:

- No production screen reports mock data or a guessed provider connection.
- Settings migrate safely from the current option keys.
- Documentation, registered routes, visible screens, and capabilities agree.

### Milestone 1 — Production template and email compiler

**Outcome:** A template can be designed and compiled into deterministic,
compliant, email-safe HTML before any provider is involved.

Deliverables:

- Establish the email-native block grammar and renderer registry described in
  [`docs/email-block-architecture.md`](docs/email-block-architecture.md); use
  explicit adapters for a supported core-block subset rather than converting
  arbitrary frontend markup. Follow the phased
  [implementation plan](docs/email-block-implementation-plan.md).
- Correct template draft, publish, revision, duplication, and autosave semantics.
- Publish a supported core/custom block matrix and fail clearly for unsupported
  blocks instead of silently producing incomplete email.
- Define dynamic content bindings for one or more selected WordPress posts and
  create an immutable content snapshot for review.
- Replace the simplified CSS inliner with a maintained public implementation and
  deterministic sanitization pipeline.
- Enforce required sender, physical-address, unsubscribe, view-online, alt-text,
  and preheader rules before approval.
- Add desktop/mobile preview, generated-HTML inspection, and secure HTML download.
- Add golden HTML fixtures, snapshot tests, URL normalization tests, and
  representative Outlook/Gmail/Apple Mail compatibility fixtures.

Exit gate:

- The same template and content snapshot always generate the same reviewed HTML.
- Invalid or non-compliant output cannot advance to approval.
- HTML export produces a verified, downloadable artifact with no provider
  credentials or WordPress-only markup.

### Milestone 2 — Provider-neutral campaign workflow

**Outcome:** CampaignBridge can manage a complete campaign lifecycle without
depending on Mailchimp-specific types or response shapes.

Deliverables:

- Add durable repositories and migrations for campaigns, content snapshots,
  remote references, delivery attempts, and audit events.
- Implement the campaign state machine and reject invalid transitions.
- Implement create, edit, select audience, snapshot content, validate, preview,
  approve, revoke approval, archive, and duplicate workflows.
- Add idempotency keys, optimistic concurrency/version checks, actor attribution,
  and immutable delivery history.
- Add REST controllers with schemas, pagination, permissions, rate limits, and
  consistent error envelopes.
- Separate reversible draft operations from irreversible schedule/send
  operations at service and capability boundaries.

Exit gate:

- Provider-neutral integration tests exercise every valid state transition and
  reject invalid or duplicate transitions.
- A campaign can reach `approved` using HTML export only, with a complete audit
  history and no direct provider dependency.

### Milestone 3 — Mailchimp end-to-end vertical slice

**Outcome:** An administrator can safely take an approved WordPress campaign
through Mailchimp draft creation, testing, scheduling or sending, and status
reconciliation.

Deliverables:

- Verify credentials against the Mailchimp account and record redacted health
  information without treating API-key length as connectivity.
- Discover and cache audiences, segments/tags, sender identities, and relevant
  provider capabilities with explicit refresh behavior.
- Create a remote campaign draft, upload generated content, retain its remote ID,
  and make repeated requests idempotent.
- Support test delivery, provider preview links where available, explicit
  schedule, explicit send, and safe cancellation where Mailchimp permits it.
- Normalize authentication, validation, rate-limit, transient, conflict, and
  uncertain-result failures.
- Poll/reconcile remote state and prevent duplicate sends after timeouts.
- Add provider contract tests and recorded/sandbox integration tests with secrets
  isolated from pull-request workflows.

Exit gate:

- A sandbox campaign completes the full operator flow with one remote campaign,
  a local audit trail, and a reconciled terminal state.
- Failure-injection tests prove that timeouts and retries cannot create a
  duplicate remote send.

### Milestone 4 — Operator experience

**Outcome:** Non-developer WordPress operators can configure, compose, review,
deliver, and troubleshoot campaigns without using raw APIs.

Deliverables:

- Add a first-run onboarding and provider-connection wizard.
- Add campaign list, creation wizard, content picker, audience selector,
  validation summary, preview, test, approval, and final confirmation screens.
- Add campaign detail with state timeline, remote links, delivery attempts,
  reconciliation status, and safe recovery actions.
- Replace dashboard mock values with repository/provider-derived health and
  campaign statistics.
- Add clear empty, loading, permission, offline, partial-failure, and stale-data
  states.
- Complete keyboard, screen-reader, responsive, and reduced-motion behavior.

Exit gate:

- Browser E2E tests cover onboarding through provider draft creation and the
  guarded schedule/send confirmation path.
- Each operator role sees only authorized data and actions.

### Milestone 5 — Durable scheduling and operational reliability

**Outcome:** Campaign work survives process crashes, cron delays, provider
outages, and ambiguous network results without duplicate delivery.

Deliverables:

- Add a durable queue/runner abstraction with claimed jobs, leases, heartbeats,
  dead-letter handling, and WP-Cron-compatible dispatch.
- Add per-campaign and per-remote-operation locks.
- Apply bounded exponential backoff only to demonstrably safe operations.
- Add reconciliation jobs for scheduled, sending, and unknown campaigns.
- Add signed webhook ingestion where supported, with replay protection and
  polling fallback.
- Add WP-CLI commands for queue inspection, reconciliation, connection checks,
  retrying safe jobs, and exporting redacted diagnostics.
- Add Site Health checks and alerts for stalled jobs, broken cron, expired
  connections, repeated failures, and schema drift.

Exit gate:

- Failure-injection tests cover worker crashes, lock expiry, provider 429/5xx
  responses, timeouts, duplicate webhooks, and delayed cron.
- Every non-terminal campaign is recoverable or explicitly marked for operator
  intervention.

### Milestone 6 — Compliance, reporting, and governance

**Outcome:** Delivery is auditable, required email controls are enforced, and
provider reporting is useful without fabricating or over-retaining data.

Deliverables:

- Enforce physical address, unsubscribe behavior, sender identity, and consent
  configuration appropriate to the selected provider and campaign type.
- Add configurable retention for campaigns, generated artifacts, job payloads,
  provider responses, and audit context.
- Integrate WordPress privacy export/erase hooks for locally stored personal data
  and document which audience data remains provider-owned.
- Synchronize normalized delivery, open, click, bounce, and unsubscribe metrics
  where the provider exposes them.
- Preserve provider totals and timestamps so reporting never implies fresher or
  more precise data than the provider supplied.
- Add redacted structured logs, correlation IDs, operational metrics, and an
  exportable support bundle.
- Update the threat model, incident runbook, data-flow inventory, and credential
  rotation procedures.

Exit gate:

- Compliance validation blocks incomplete campaigns before provider draft/send.
- Security review confirms credential, PII, retention, audit, webhook, and
  deletion boundaries.

### Milestone 7 — General availability

**Outcome:** CampaignBridge is supportable as an enterprise WordPress plugin.

Deliverables:

- Add upgrade-path tests from every supported schema version and rollback-safe
  release procedures.
- Complete Playwright, Axe, visual, REST contract, package-install, multisite,
  localization, timezone/DST, and large-dataset coverage.
- Establish performance budgets for editor load, campaign queries, generation,
  queue latency, and provider synchronization.
- Complete administrator, operator, developer, API, privacy, troubleshooting,
  backup/restore, and disaster-recovery documentation.
- Publish a support matrix for WordPress, PHP, MySQL/MariaDB, browsers, providers,
  and multisite behavior.
- Run a release-candidate pilot with real operators and a non-production provider
  account before enabling production sends.

Exit gate:

- The release candidate passes install, upgrade, rollback, full QA, browser E2E,
  security review, package verification, and an observed pilot campaign.

## Post-GA opportunities

These are intentionally outside the first production scope:

- Additional providers implemented through the proven provider contract.
- Editorial approval chains and separation-of-duties policies.
- Recurring and event-triggered campaigns.
- A/B subject/content testing.
- Reusable campaign recipes, block patterns, and organization design systems.
- Network-level multisite connection and policy management.
- Advanced analytics, attribution, and data-warehouse exports.

## Cross-cutting definition of done

Every roadmap item must include, in the same change:

- Capability and nonce enforcement at the server boundary.
- Input validation, output escaping, credential/PII redaction, and rate limits.
- Repository migrations and rollback behavior where persistence changes.
- Unit/integration coverage plus provider or browser coverage proportional to the
  affected boundary.
- Idempotency and reconciliation behavior for remote mutations.
- Accessibility states and translatable user-facing strings.
- Updated operator, API, architecture, threat-model, and runbook documentation.
- Production build and allowlisted package verification.

Mock data, demo screens, silent fallbacks, untracked provider state, and
documentation-only capabilities do not satisfy the definition of done.

## Product success measures

- Time from provider connection to first verified remote draft.
- Percentage of campaigns that reconcile to a known terminal state.
- Zero duplicate sends caused by CampaignBridge retries or ambiguous responses.
- Queue age, provider error rate, reconciliation lag, and manual-recovery rate.
- Preview-to-delivered HTML regression rate across supported email fixtures.
- Accessibility and task-completion results for the operator workflow.
- Upgrade, rollback, and support-bundle success during release pilots.
