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

Current implementation-ready M2 work begins with storage #73. Its dependent
slices are M2 workflows #74 and M2 REST #75.

Current and dependency-gated examples:

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

Issue number is not execution order. M1 #62 and its action issues #69–#72 are
complete. The remaining order is:

1. M2 durable storage — #73.
2. M2 canonical campaign workflows — #74.
3. M2 REST adapter — #75.
4. Close M2 — #63.
5. M3 provider discovery/token mapping — #76.
6. M3 idempotent Mailchimp draft/content handoff — #77.
7. M3 test delivery — #78.
8. M3 guarded schedule/send/cancel — #79.
9. M3 reconciliation and ambiguous outcomes — #80.
10. Close M3 — #64.
11. Complete the operator experience — #65, incrementally as M2/M3 services stabilize.
12. Durable jobs/recovery — #66.
13. Governance/reporting — #67.
14. GA closeout — #68.

Cross-cutting work does not need to wait for the numbered milestone when its dependency is already satisfied. Security, accessibility, i18n, performance, package verification, and documentation travel with every relevant PR.

## Abilities API

#40 is an integration umbrella, not a second product roadmap.

- #82 may build the safe Abilities foundation/read contracts against the current template/Brand Kit architecture.
- #83 may expose compiler validation/compile through the canonical services.
- Campaign mutation abilities wait for M2.
- Provider/audience abilities wait for M3 discovery contracts.
- Delivery abilities wait for M3 delivery workflows; broad automation/MCP exposure of production send also waits for M5 recovery semantics.
- Reporting abilities wait for M6 metric/governance semantics.

Do not let an adapter become the reason to invent a product workflow early.

## Additional providers

#81 tracks provider expansion after Mailchimp proves the lifecycle. Do not create implementation issues for every provider simply because an API exists. Re-verify a provider's current API when it is selected, then create a focused adapter issue against the proven provider capability contract.

## Documentation drift

When implementation and a planning doc disagree, inspect current `master` before opening an issue. If the feature already shipped, fix the doc. Do not create an issue from stale prose.

A roadmap statement is not evidence that a feature exists; a checkbox is not evidence that it is incomplete. Source, tests, merged PRs, registered routes, and visible product behavior determine current state.
