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

| Area               | Shipped today                                                                                                                                                                                                                                                                                                                                                                      | Remaining product boundary                                                                      |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Template authoring | Native WordPress block editor; draft/save/publish; autosave; native revisions; allowlisted duplication; constrained Core/CampaignBridge block grammar; read-only post selection and immutable snapshot inputs | Reusable sections and patterns |
| Email generation | Deterministic HTML/plain compiler; compiled preview/export; immutable content/design inputs; structured diagnostics; artifact fingerprints; durable immutable campaign snapshots and exact approved-artifact references | Provider handoff and delivery |
| Providers | Canonical encrypted connection repository; truthful Mailchimp verification; normalized connection/provider errors; Mailchimp discovery; idempotent Mailchimp draft handoff; bounded Mailchimp test sends; guarded Mailchimp schedule/unschedule/send; on-demand reconciliation; HTML export boundary | Background reconciliation, reporting |
| Campaigns | Durable provider-neutral campaigns, snapshots, attempts and audit history; authoritative state machine; canonical create/edit/audience/snapshot/validate/preview/review/approve/revoke/archive/duplicate workflows; optimistic concurrency; stable permission-safe campaign REST contracts; operator screens for the whole lifecycle | Durable background jobs |
| Admin | Settings, Brand Kit, provider connection/verification, campaign list and creation, onboarding checklist, review/approval, provider handoff and guarded delivery confirmations, campaign timeline and reconciliation recovery, truthful status | Reporting and compliance surfaces |
| API | Editor/content support routes, Brand Kit, compiled preview, template revision restore and core template REST lifecycle; campaign, delivery, reconciliation, history, and onboarding APIs | Reporting APIs |
| Operations         | Hardened CI, security/accessibility gates, signed/reproducible packaging, runbook foundations                                                                                                                                                                                                                                                                                      | Durable jobs/locks, webhooks, reconciliation monitor, operational metrics and support tooling   |

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

### Milestone 0 — Product truth and stable contracts _(complete)_

The repository now has truthful provider connection status, canonical provider
connection persistence, normalized provider/domain contracts, granular
capability foundations, architecture decision records, and boundary checks.
M0 is historical foundation rather than an active backlog.

### Milestone 1 — Production template and email compiler _(complete)_

**Tracking:** #62

**Outcome:** A template can be designed, populated from selected WordPress
content, validated, and compiled into deterministic, compliant, email-safe HTML
before any provider is involved.

Completed slices:

- #69 — WordPress content bindings and immutable review snapshot inputs.
- #70 — Provider-neutral personalization/system-token contract.
- #71 — Preflight, compliance, and exact artifact inspection/export closeout.
- #72 — Representative Outlook/Gmail/Apple Mail structural compatibility fixtures.

**Exit gate:** the same template + content snapshot + resolved design always
produces the same reviewed artifact; invalid/non-compliant output cannot advance
to approval; export contains no provider credentials or WordPress-only markup.

The exit gate is satisfied by the frozen review-input/compiler fingerprint
contract, fail-closed structured diagnostics, canonical-token provenance rules,
exact compiled preview/download surface, and repository-owned client expectation
matrix. Durable storage of approved artifacts and the approval transition itself
begin in M2; they do not introduce another rendering path.

### Milestone 2 — Provider-neutral campaign workflow _(complete)_

**Tracking:** #63

**Outcome:** CampaignBridge manages a complete local campaign lifecycle through
approval without depending on Mailchimp-specific types or response shapes.

Completed slices:

- #73 — Durable campaign/snapshot/remote-reference/delivery-attempt/audit storage.
- #74 — Canonical campaign workflows, state transitions, concurrency and audit.
- #75 — Stable REST contracts over those workflows.

**Exit gate:** provider-neutral integration tests exercise every legal
transition and reject illegal/duplicate transitions; a campaign reaches
`approved` using HTML export only with a complete local audit history.

The exit gate is satisfied by the exhaustive state-machine table test, which
checks all state pairs, and workflow tests for every local transition. The
workflow, REST, and security suites prove stale-version, illegal-state and
idempotent-duplicate refusal without mutation. An HTML-export-only campaign
reaches `approved` through both the workflow and the REST adapter, with no
provider or audience, while referencing the exact reviewed snapshot fingerprint
and recording a complete local audit history. Provider handoff, delivery and
reconciliation begin in M3 and reuse `Campaign_Workflow` rather than adding a
second lifecycle.

### Milestone 3 — Mailchimp end-to-end vertical slice _(complete)_

**Tracking:** #64

**Outcome:** An administrator can safely take an approved CampaignBridge
campaign through Mailchimp draft creation, testing, scheduling/sending, and
status reconciliation.

Completed slices:

- #76 — Provider capabilities, Mailchimp discovery and personalization mapping.
- #77 — Idempotent Mailchimp draft/content handoff.
- #78 — Test delivery.
- #79 — Guarded schedule, unschedule, and immediate send; cancel is declared
  unsupported for Mailchimp.
- #80 — On-demand reconciliation and ambiguous-outcome recovery.

**Exit gate:** one sandbox campaign completes the full lifecycle with one remote
campaign, a local audit trail and a reconciled terminal state; failure injection
proves retries/timeouts cannot duplicate a production send.

The exit gate is satisfied by the live sandbox run of 2026-10-04: one approved
campaign became exactly one Mailchimp campaign, went through scheduling,
unscheduling, a test send and an immediate send to a one-contact audience, and
reconciled to `sent`, with every attempt and transition in the local audit
trail. The run found two defects unit tests could not (a stale `reconciled_at`
after a new delivery, and Mailchimp reporting an unscheduled campaign as
`paused`); both are fixed, and the sanitized exchanges replay in
`tests/Fixtures/Mailchimp/`. Failure injection is covered by the scheduler,
reconciler, and route suites: a timeout, transport loss, server error, or
exception after the provider call leaves the campaign `unknown` with no retry,
blocks every further delivery whatever idempotency key is sent, and settles
only from provider evidence after the settle window. The operator surface for
this lifecycle is the REST API; its admin UI is M4.

### Milestone 4 — Operator experience _(complete)_

**Tracking:** #65

**Outcome:** Non-developer WordPress operators can configure, compose, review,
approve, deliver, and troubleshoot campaigns without using raw APIs for normal
operations.

Completed slices:

- #156 — Read-only campaign history and delivery attempts REST contract.
- #150 — Campaigns list and create screen.
- #151 — Campaign review, preflight, preview, and approval screen.
- #152 — Provider handoff, test send, and guarded delivery confirmations.
- #153 — Campaign timeline, remote state, and reconciliation recovery.
- #154 — First-run onboarding checklist and truthful status.
- #164 — Deterministic campaign history order within the same second.
- #155 — Exit-gate journey, role matrix, and accessible states.

**Exit gate:** browser E2E covers onboarding through provider draft creation and
the guarded schedule/send confirmation path, with role-appropriate data/actions
and complete accessible failure/empty/loading/stale states.

The exit gate is satisfied by three browser specs that run in CI against a
test-only simulated Mailchimp, so no real email is sent:

- `m4-journey.spec.ts`: a new operator follows the onboarding checklist,
  publishes a template in the block editor, creates a Mailchimp campaign from
  the checklist, prepares, submits, and approves it, creates the Mailchimp
  draft, sends a test, schedules through the audience-naming confirmation,
  unschedules, sends now through the same confirmation, and reconciles to
  `sent`. Exactly one request of each kind reaches the provider, and the
  history records every step.
- `role-matrix.spec.ts`: with separation of duties on, an author, manager,
  approver, deliverer, and a user without CampaignBridge capabilities each see
  only their own data, menus, buttons, and published actions, and the outsider
  is refused the screen and the REST collection.
- `campaign-states.spec.ts`: axe finds no serious WCAG 2.1 AA violation in the
  empty, loading, offline (with retry), review, stale (version conflict), and
  permission-denied states; a keyboard-only operator creates and prepares a
  campaign; nothing animates under reduced motion; and a pseudo-locale shows
  every campaign screen string passing through translation, including a real
  translation delivered through WordPress script translations.

The role matrix found three defects: a template author who was not a
WordPress editor was refused the template editor, and the CampaignBridge menu
required the management capability for every screen beneath it; screens
offered template steps (edit, snapshot, submit, approve, duplicate) the
workflow would refuse without template access; and users without template
access saw the menu's own title repeated as a submenu entry. Building every
screen controller on each admin request also checked the Mailchimp key from
unrelated pages; controllers are now built only for their own screen. All are
fixed with regression tests.

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
