# CampaignBridge product roadmap

## Product mission

CampaignBridge lets a WordPress team turn site content into compliant,
provider-ready email campaigns without leaving WordPress. It owns template
composition, content selection, review, delivery orchestration, and operational
history while the email service provider remains responsible for subscriber
membership and final delivery.

The intended operator flow is:

`Connect provider → Design template → Select content → Preview/test → Approve → Create provider draft → Schedule/send → Reconcile/report`

CampaignBridge is not intended to become a subscriber database, SMTP server, or
general marketing-automation suite during the first production cycle.

Actionable work is tracked in GitHub Issues. See
[`docs/work-tracking.md`](docs/work-tracking.md) for the repository's planning
convention and dependency-first execution order.

## Product assumptions

These are durable product rules until an explicit decision changes them:

- WordPress is the system of record for templates, campaigns, content snapshots,
  delivery attempts, and audit events.
- Provider audiences, segments, tags, and subscriber records remain in the
  provider. CampaignBridge stores stable remote references, not audience PII.
- Campaign content is snapshotted for review/approval. Refreshing content after
  approval is explicit and requires review again.
- Remote campaigns are created as drafts first. Sending and scheduling are
  separate, explicit, capability-protected operations.
- Every remote mutation has an idempotency strategy and a reconciliation path.
  An uncertain response must never cause an automatic duplicate send.
- Mailchimp and HTML export prove the first complete delivery lifecycle before a
  second provider is implemented.
- Templates use the `cb_templates` post type. Campaigns, jobs, remote references,
  delivery attempts, snapshots, and audit events use repository abstractions
  backed by durable, indexed storage as their milestones introduce them.

## Current product state

CampaignBridge has a production-oriented template/editor/compiler foundation,
but it is not yet a complete campaign-management and delivery product.

| Area | Shipped today | Remaining product boundary |
| --- | --- | --- |
| Template authoring | Standalone `core-data` editor; draft/save/publish; autosave; native revisions with paginated history and safe restore; allowlisted duplication; constrained CampaignBridge block grammar | Dynamic content selection/snapshot and campaign-level review workflow |
| Email generation | Deterministic HTML/plain compiler; renderer registry; compiled preview; shared resolved email design; Brand Kit; theme `campaignbridge/email.json`; structured diagnostics; artifact fingerprinting | M1 closeout: content snapshots, portable personalization, remaining preflight/compliance gaps, representative client fixtures |
| Providers | Canonical encrypted connection repository; truthful Mailchimp verification; normalized connection/provider errors; Mailchimp discovery foundations; HTML export boundary | Remote draft/content handoff, test send, guarded schedule/send/cancel, reconciliation/reporting |
| Campaigns | Provider-neutral campaign state/state-machine foundations | Durable campaign/snapshot/attempt/audit storage and canonical workflows |
| Admin | Settings, Brand Kit, provider connection/verification, audience-selection foundations, template editor lifecycle | Full campaign/operator workflow and delivery/recovery surfaces |
| API | Editor/content support routes, Brand Kit, compiled preview, template revision restore and core template REST lifecycle | Campaign/delivery/reconciliation/reporting APIs |
| Operations | Hardened CI, security/accessibility gates, signed/reproducible packaging, runbook foundations | Durable jobs/locks, webhooks, reconciliation monitor, operational metrics and support tooling |

The README is intentionally conservative: only shipped capabilities belong in
its "available now" claims.

## Target domain and architecture

The dependency direction in [`docs/architecture.md`](docs/architecture.md)
remains authoritative:

`Delivery → Workflow → Domain ← Repository implementations`

The production campaign lifecycle builds around these concepts:

- **Provider connection** — encrypted credentials, configuration, capabilities,
  health, and stable provider account identity.
- **Template** — reusable block content and email metadata stored in WordPress.
- **Content snapshot** — immutable resolved WordPress content/design/compiler
  inputs sufficient to reproduce reviewed output.
- **Campaign** — provider-neutral subject, sender, audience reference, approved
  artifact/snapshot, lifecycle state, ownership, and version.
- **Delivery attempt** — one recorded create/test/schedule/send/cancel/reconcile
  operation with idempotency and ambiguity semantics.
- **Remote campaign reference** — provider, remote ID, normalized observed state,
  and reconciliation metadata.
- **Audit event** — actor, action, timestamp, target, result, and redacted context.

The guarded lifecycle remains conceptually:

`draft → ready_for_review → approved → provider_draft → scheduled|sending → sent`

Failure/recovery states include `failed`, `cancelled`, and `unknown`.
`unknown` is load-bearing: a timeout after an irreversible provider request is
not evidence that the request failed or is safe to retry.

Provider-specific response shapes and syntax remain inside adapters. Campaign,
snapshot, personalization, workflow, and reporting contracts stay provider
neutral and expose provider-specific capability only through explicit extension
metadata/capability discovery.

## Delivery milestones

Milestones are ordered by dependency and release outcome, not calendar date.
The live implementation backlog for each milestone is its linked GitHub issue.

### Milestone 0 — Product truth and stable contracts *(complete)*

The repository now has truthful provider connection status, canonical provider
connection persistence, normalized provider/domain contracts, granular
capability foundations, architecture decision records, and boundary checks.
M0 is historical foundation rather than an active backlog.

### Milestone 1 — Production template and email compiler

**Tracking:** #62

**Outcome:** A template can be designed, populated from selected WordPress
content, validated, and compiled into deterministic, compliant, email-safe HTML
before any provider is involved.

Current actionable slices:

- #69 — WordPress content bindings and immutable review snapshot inputs.
- #70 — Provider-neutral personalization/system-token contract.
- #71 — Remaining preflight/compliance/artifact-inspection closeout.
- #72 — Representative Outlook/Gmail/Apple Mail compatibility fixtures.

**Exit gate:** the same template + content snapshot + resolved design always
produces the same reviewed artifact; invalid/non-compliant output cannot advance
to approval; export contains no provider credentials or WordPress-only markup.

### Milestone 2 — Provider-neutral campaign workflow

**Tracking:** #63

**Outcome:** CampaignBridge manages a complete local campaign lifecycle through
approval without depending on Mailchimp-specific types or response shapes.

Current actionable slices:

- #73 — Durable campaign/snapshot/remote-reference/delivery-attempt/audit storage.
- #74 — Canonical campaign workflows, state transitions, concurrency and audit.
- #75 — Stable REST adapter over those workflows.

**Exit gate:** provider-neutral integration tests exercise every legal
transition and reject illegal/duplicate transitions; a campaign reaches
`approved` using HTML export only with a complete local audit history.

### Milestone 3 — Mailchimp end-to-end vertical slice

**Tracking:** #64

**Outcome:** An administrator can safely take an approved CampaignBridge
campaign through Mailchimp draft creation, testing, scheduling/sending, and
status reconciliation.

Current actionable slices:

- #76 — Provider capabilities, Mailchimp discovery and personalization mapping.
- #77 — Idempotent Mailchimp draft/content handoff.
- #78 — Test delivery.
- #79 — Guarded schedule/send/cancel operations.
- #80 — Remote-state reconciliation and ambiguous-outcome recovery.

**Exit gate:** one sandbox campaign completes the full lifecycle with one remote
campaign, a local audit trail and a reconciled terminal state; failure injection
proves retries/timeouts cannot duplicate a production send.

### Milestone 4 — Operator experience

**Tracking:** #65

**Outcome:** Non-developer WordPress operators can configure, compose, review,
approve, deliver, and troubleshoot campaigns without using raw APIs for normal
operations.

Decompose this milestone into action issues as the M2/M3 workflows stabilize;
do not create UI tickets that need to invent missing business rules.

**Exit gate:** browser E2E covers onboarding through provider draft creation and
the guarded schedule/send confirmation path, with role-appropriate data/actions
and complete accessible failure/empty/loading/stale states.

### Milestone 5 — Durable scheduling and operational reliability

**Tracking:** #66

**Outcome:** Campaign work survives crashes, cron delay, provider outage, and
ambiguous remote results without duplicate delivery.

This milestone owns durable jobs/leases/locks, safe retries, scheduled
reconciliation, signed webhooks with replay protection and polling fallback,
WP-CLI recovery tools, and operational Site Health.

**Exit gate:** failure injection covers worker crashes, lock expiry, provider
429/5xx responses, timeouts, duplicate webhooks and delayed cron; every
non-terminal campaign is recoverable or explicitly requires operator action.

### Milestone 6 — Compliance, reporting, and governance

**Tracking:** #67

**Outcome:** Delivery is auditable, required email controls are enforced, and
normalized provider reporting is useful without fabricating freshness or
precision and without over-retaining data.

This milestone closes retention/privacy export-erasure, normalized
provider-report provenance, redacted structured logs/support bundles, data-flow
inventory, incident/threat-model updates, and credential-rotation procedures.
Compliance checks needed to block earlier approval/send travel with those
features rather than waiting until M6.

**Exit gate:** incomplete campaigns cannot reach provider draft/send, and a
security review confirms credential, PII, retention, audit, webhook and deletion
boundaries.

### Milestone 7 — General availability

**Tracking:** #68

**Outcome:** CampaignBridge is supportable as a production WordPress plugin.

GA closes upgrade/rollback coverage, browser/accessibility/REST/package/multisite
and localization/timezone testing, representative large-data/performance
budgets, operator/developer/privacy/recovery docs, stable public extension
contracts, lifecycle/uninstall behavior, the support matrix, and an observed
release-candidate pilot.

**Exit gate:** the release candidate passes install, upgrade, rollback, full QA,
browser E2E, security review, package verification, and an observed pilot
campaign using a non-production provider account.

## Cross-cutting tracks

### WordPress Abilities API

**Tracking:** #40

Abilities are an adapter over canonical CampaignBridge workflows, not a second
business layer. #82 and #83 are the only current implementation-ready slices.
Campaign mutation/provider/delivery/reporting abilities wait for the respective
M2/M3/M5/M6 contracts.

### Additional providers

**Tracking:** #81

Provider expansion begins only after the Mailchimp vertical slice proves the
provider-neutral lifecycle. Candidate provider APIs are re-verified when a
provider is selected; implementation issues are then created against the proven
capability/idempotency/reconciliation contract.

## Post-GA opportunities

These remain product opportunities, not active implementation promises unless a
tracked issue sequences them:

- editorial approval chains and separation-of-duties policies;
- recurring and event-triggered campaigns;
- A/B subject/content testing;
- reusable campaign recipes/patterns and organization design systems;
- later email-block waves mapped in [`docs/email-block-catalog.md`](docs/email-block-catalog.md);
- network-level multisite connection/policy management;
- advanced analytics, attribution, and data-warehouse exports.

## Cross-cutting definition of done

Every relevant implementation change carries its own:

- capability/object authorization and nonce/REST authentication behavior;
- input validation, output escaping, credential/PII redaction, and bounded/rate-limited external surfaces;
- persistence migrations/rollback behavior when data changes;
- unit/integration/provider/browser evidence proportional to the boundary;
- idempotency and reconciliation semantics for remote mutations;
- accessibility and translatable user-facing states;
- durable API/architecture/threat-model/runbook/operator documentation updates;
- production build and allowlisted release-package verification.

Mock data, demo-only screens, silent fallbacks, untracked provider state, and
documentation-only capabilities do not satisfy completion.

## Product success measures

- Time from provider connection to first verified remote draft.
- Percentage of campaigns that reconcile to a known terminal state.
- Zero duplicate sends caused by CampaignBridge retries or ambiguous responses.
- Queue age, provider error rate, reconciliation lag, and manual-recovery rate.
- Preview-to-delivered HTML regression rate across supported email fixtures.
- Accessibility and operator task-completion results.
- Upgrade, rollback, and support-bundle success during release pilots.
