# Work tracking

CampaignBridge uses GitHub Issues as the source of truth for actionable work and keeps repository documentation as the durable source for architecture, contracts, rationale, invariants, and closeout evidence.

## The rule

**If work can become done, track it in an Issue. If text explains something that must remain true after the work is done, keep it in docs.**

This prevents `ROADMAP.md` and architecture documents from becoming a second backlog that drifts away from the implementation.

## Responsibilities

### Documentation

Docs own:

- product mission and boundaries;
- architecture and dependency direction;
- domain/state-machine contracts;
- provider and repository extension boundaries;
- security/privacy invariants;
- compiler/design/token contracts;
- milestone outcomes and exit criteria;
- decision records;
- migration/rollback rules that remain relevant after a change ships;
- measured closeout evidence worth preserving.

Docs should not contain a live implementation checklist that duplicates GitHub Issues.

### Milestone / initiative issues

Umbrella issues own a product outcome and its dependency/closeout rules. They are not expected to map to one PR.

Current product milestones:

- M1 — #62
- M2 — #63
- M3 — #64
- M4 — #65
- M5 — #66
- M6 — #67
- M7 — #68

Integration/provider tracks may have their own umbrellas, such as Abilities #40 and post-M3 provider expansion #81.

### Action issues

A focused action issue should normally be reviewable through one coherent PR or a deliberately small PR sequence. It owns concrete acceptance evidence, not an entire product area.

Completed M1 examples:

- M1 content snapshots — #69
- M1 personalization — #70
- M1 preflight closeout — #71
- M1 client fixtures — #72

M2 #63 is complete: storage #73, canonical workflows #74, and REST contracts
#75. In M3, discovery #76, draft handoff #77, and test delivery #78 are
complete; guarded schedule/send/cancel #79 is next.

Completed and dependency-gated examples:

- M2 storage — #73
- M2 workflows — #74
- M2 REST — #75
- M3 provider discovery — #76
- M3 remote draft — #77
- M3 test delivery — #78
- M3 schedule/send/cancel — #79
- M3 reconciliation — #80

Do not create speculative child issues for M4–M7 before their prerequisite contracts make the implementation boundary concrete.

### Pull requests

Implementation PRs should reference the action issue they complete with `Fixes #...` or `Closes #...` when the full acceptance criteria are satisfied.

A PR that only advances part of an issue should use non-closing language such as `Part of #...` and leave the issue open.

PR descriptions should contain the evidence needed to judge the change: tests, failure semantics, migrations, compatibility notes, and any intentionally deferred work.

### Closeout

An umbrella milestone closes only when:

1. required action issues are complete;
2. the milestone exit gate is demonstrated;
3. durable architecture/API/operator/threat-model/runbook docs are current;
4. migrations and rollback behavior are proven where relevant;
5. the authoritative QA/security/accessibility/package gates pass;
6. roadmap status is updated to describe shipped reality.

The merged PR/closed issues provide the implementation history. Do not copy every completed implementation detail back into the roadmap.

## Dependency-first execution order

Issue number is not execution order. M1 #62 with its action issues #69–#72,
M2 #63 with its action issues #73–#75, M3 provider discovery #76, the M3
Mailchimp draft handoff #77, and M3 test delivery #78 are complete. The
remaining order is:

1. M3 guarded schedule/send/cancel — #79.
2. M3 reconciliation and ambiguous outcomes — #80.
3. Close M3 — #64.
4. Complete the operator experience — #65, incrementally as M2/M3 services stabilize.
5. Durable jobs/recovery — #66.
6. Governance/reporting — #67.
7. GA closeout — #68.

Cross-cutting work does not need to wait for the numbered milestone when its dependency is already satisfied. Security, accessibility, i18n, performance, package verification, and documentation travel with every relevant PR.

## Abilities API

#40 is an integration umbrella, not a second product roadmap.

- #82 may build the safe Abilities foundation/read contracts against the current template/Brand Kit architecture.
- #83 may expose compiler validation/compile through the canonical services.
- Campaign mutation abilities may now build on the completed M2
  `Campaign_Workflow`, as the REST adapter does. They must not wrap REST
  controller internals.
- Provider/audience abilities may build on the completed M3 #76 discovery
  contracts.
- Delivery abilities wait for M3 delivery workflows; broad automation/MCP exposure of production send also waits for M5 recovery semantics.
- Reporting abilities wait for M6 metric/governance semantics.

Do not let an adapter become the reason to invent a product workflow early.

## Additional providers

#81 tracks provider expansion after Mailchimp proves the lifecycle. Do not create implementation issues for every provider simply because an API exists. Re-verify a provider's current API when it is selected, then create a focused adapter issue against the proven provider capability contract.

## Documentation drift

When implementation and a planning doc disagree, inspect current `master` before opening an issue. If the feature already shipped, fix the doc. Do not create an issue from stale prose.

A roadmap statement is not evidence that a feature exists; a checkbox is not evidence that it is incomplete. Source, tests, merged PRs, registered routes, and visible product behavior determine current state.
