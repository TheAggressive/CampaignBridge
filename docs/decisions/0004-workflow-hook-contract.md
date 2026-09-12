# ADR 0004: Workflow hook contract

- Status: Accepted
- Date: 2026-09-11
- Issue: Milestone 0

## Context

CampaignBridge orchestrates a multi-step campaign lifecycle: draft → review →
approve → create provider draft → schedule/send → sent. Each step involves
domain validation, provider interaction, and persistence. Without a documented
workflow contract, the orchestration layer risks:

- Calling providers directly from REST or admin code.
- Bypassing state transition guards.
- Mixing provider-specific logic into the workflow.
- Making state transitions that the domain does not permit.

The `Campaign_State` and `Campaign_State_Machine` classes already define the
lifecycle and its guards. This ADR locks how the workflow layer consumes them
and how it coordinates with providers and repositories.

## Decision

The workflow layer is the **only** layer that orchestrates multi-step
operations. It sits between the presentation layer (REST, admin) and the
domain/provider/repository layers.

### State machine as the single guard

1. **Every state transition must pass through `Campaign_State_Machine`.**
   The workflow calls `assert_transition()` before persisting a new state.
   A transition that violates the state machine is rejected with an
   `\InvalidArgumentException`. No code path may write a campaign state
   without this guard.

2. **Terminal states are immutable.** `sent` and `cancelled` have no
   outgoing transitions. Once a campaign reaches a terminal state, no
   further mutations are permitted.

3. **Failure and recovery are explicit.** `failed` and `unknown` are
   non-terminal. Recovery from `failed` or `unknown` returns to
   `provider_draft` (retry) or `draft` (restart). The `unknown` state
   prevents a timeout after an irreversible provider request from being
   mistaken for a safe retry.

### Workflow operation boundaries

Each workflow operation is a discrete, named unit with a single
responsibility:

| Operation | Responsibility |
|-----------|---------------|
| `create_draft` | Validate input, create campaign in `draft` state. |
| `submit_for_review` | Transition `draft → ready_for_review`. |
| `approve` | Transition `ready_for_review → approved`. |
| `create_provider_draft` | Call provider, transition `approved → provider_draft`. |
| `schedule` | Call provider, transition `provider_draft → scheduled`. |
| `send` | Call provider, transition `provider_draft/scheduled → sending → sent`. |
| `cancel` | Transition any non-terminal state → `cancelled`. |
| `retry` | Transition `failed/unknown → provider_draft`. |

Each operation:

1. Validates preconditions via the domain layer.
2. Asserts the state transition via `Campaign_State_Machine`.
3. Calls the provider (if applicable) through `Provider_Interface`.
4. Normalizes provider failures into `Provider_Error`.
5. Persists the new state via the repository port.
6. Returns a domain DTO or a `Provider_Error`.

### Provider interaction rules

1. **The workflow is provider-agnostic.** It calls `Provider_Interface`
   methods and receives normalized types. It never references a concrete
   provider class.

2. **Provider errors are normalized before the workflow sees them.**
   `Abstract_Provider` maps raw HTTP errors to `Provider_Error` values.
   The workflow inspects `Provider_Error::is_retryable()` to decide
   whether to set the state to `failed` or `unknown`.

3. **Idempotency is the provider's responsibility.** The workflow may
   retry a `provider_draft` creation or a `send` call. The provider must
   handle duplicate requests safely (e.g. via idempotency keys).

4. **Unknown outcomes are not failures.** If a provider request times out
   after an irreversible action (e.g. email sent), the workflow sets the
   state to `unknown`, not `failed`. This prevents automatic retry from
   causing duplicate sends.

### Hook extension points

The workflow layer exposes WordPress hooks at each operation boundary:

| Hook | When | Parameters |
|------|------|-----------|
| `campaignbridge_campaign_{operation}_before` | Before the operation executes. | Campaign DTO, provider slug. |
| `campaignbridge_campaign_{operation}_after` | After the operation completes. | Campaign DTO, result or Provider_Error. |
| `campaignbridge_campaign_state_changed` | On any state transition. | Campaign ID, from state, to state. |

Hooks are observational: they may log, notify, or trigger side effects, but
they cannot alter the campaign state or the provider call. The workflow does
not filter or short-circuit based on hook return values.

### Capability gates

Each workflow operation requires a specific capability from
`Capabilities`:

| Operation | Required capability |
|-----------|-------------------|
| `create_draft` | `campaignbridge_create_campaigns` |
| `submit_for_review` | `campaignbridge_create_campaigns` |
| `approve` | `campaignbridge_send_campaigns` |
| `create_provider_draft` | `campaignbridge_send_campaigns` |
| `schedule` | `campaignbridge_send_campaigns` |
| `send` | `campaignbridge_send_campaigns` |
| `cancel` | `campaignbridge_send_campaigns` |
| `retry` | `campaignbridge_send_campaigns` |

The presentation layer (REST, admin) checks capabilities before calling the
workflow. The workflow does not re-check capabilities; it trusts the
presentation layer's authorization.

## Ownership boundaries

- `Domain/Campaign/` contains `Campaign_State`, `Campaign_State_Machine`,
  and campaign DTOs. Pure PHP, no WordPress functions.
- `Workflow/` (future) contains the orchestration logic. It calls domain,
  provider, and repository code.
- `Providers/` contains remote API adapters. No state management.
- `Repository/` contains persistence. No business rules.
- `REST/` and `Admin/` are presentation layers. They check capabilities and
   call the workflow. They do not orchestrate multi-step operations.

## Versioning and migration

The state machine transition table is a versioned contract. Adding a new
state or transition requires:

1. A new constant in `Campaign_State`.
2. Updated entries in `Campaign_State_Machine::TRANSITIONS`.
3. Updated unit tests in `tests/Unit/Campaign/`.
4. A migration path for any campaigns in affected states.

Removing a state or transition is a breaking change that requires a major
version bump.

## Consequences

The workflow layer is the single point of orchestration. Adding a new
campaign operation requires:

1. A new method in the workflow class.
2. State machine entries (if new transitions are needed).
3. Capability assignment.
4. Hook registration.
5. Unit tests for the workflow operation.
6. Integration tests for the full operation (WordPress environment).

The presentation layer remains thin: it validates input, checks capabilities,
and delegates to the workflow. The domain layer remains pure: it validates
state and provides DTOs. The provider layer remains isolated: it adapts
remote APIs to normalized types.