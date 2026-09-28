# Campaign workflows

Issue #74 adds the canonical provider-neutral application layer for local
campaign lifecycle mutations. Future REST, Abilities, CLI, and admin adapters
call `Campaign_Workflow`; they do not write campaign repositories or reproduce
transition rules.

The workflow composes the #73 ports and implementations:

- `Campaign_Source` for current campaign state and compare-and-swap versions;
- `Campaign_Snapshot_Source` for immutable review revisions and artifacts;
- `Delivery_Attempt_Source` for the retry-safe duplicate idempotency identity;
- `Audit_Event_Source` for append-only bounded/redacted history;
- `Campaign_Review_Input_Source` for one live WordPress capture before freezing.

`Campaign_Workflow_Factory` builds the production graph. No operation in this
layer creates a provider campaign, invokes Mailchimp, schedules, sends, cancels,
or reconciles remote state.

## Operations

The application service owns these operations:

- create a local draft;
- change the selected template;
- select normalized provider/audience references without audience PII;
- capture and persist a new immutable snapshot revision;
- validate or preview through the production compiler;
- submit a valid selected artifact for review;
- approve the exact selected snapshot and artifact fingerprint;
- revoke approval;
- archive through the state machine;
- duplicate reusable authoring references into a new draft.

A duplicate copies template, provider, and audience references. It does not copy
the active snapshot, state, delivery attempts, remote references, or audit
history. Its required idempotency key uses the existing #73 attempt identity.
A replay for the same source campaign and key resolves the first duplicate.
There is no second request payload whose reuse could disagree: the source
campaign and operation are part of the key identity and the duplicate shape is
fixed by this contract.

Validation records an audit event because it is an explicit operational review
action. Preview remains ephemeral and is not audited or persisted. Neither
operation stores arbitrary preview HTML.

## State and approval

`Campaign_State_Machine` is the one transition authority. #74 adds `archived` as
a terminal local state. Draft, ready-for-review, approved, and failed campaigns
may be archived; active or ambiguous provider states cannot be archived by the
local workflow.

Template edits invalidate the selected artifact and return the campaign to
`draft`. Audience/reference changes and snapshot refreshes preserve the
immutable historical snapshot but move an approved campaign back to
`ready_for_review`. Explicit revocation uses that same approved-to-review
transition.

Submission requires normalized provider and audience references plus a selected
snapshot that recompiles successfully to its stored fingerprint. Approval also
requires separate approval authority, the legal `ready_for_review` state, the
selected snapshot, a successful canonical compile, and an exact stored
fingerprint match. The approved campaign therefore identifies frozen HTML,
plain text, assets, design/content inputs, compiler version, and profile; it
never means live WordPress content at a later send time.

## Concurrency and results

Every mutable operation after creation accepts the campaign's expected integer
version. The workflow builds version `expected + 1` and delegates the atomic
write to `Campaign_Source::compare_and_swap()`. A stale actor receives the
stable `conflict` result; the workflow never retries or overwrites the newer
record.

Application results contain typed campaign, snapshot, and compiler values where
relevant. Stable provider-neutral errors are limited to:

- `not_found`;
- `invalid_state`;
- `conflict`;
- `invalid_input`;
- `validation_failed`;
- `missing_snapshot`;
- `approval_not_allowed`;
- `forbidden`;
- `persistence_failed`;
- `idempotency_conflict`.

No result contains SQL, raw database errors, HTTP responses, provider payloads,
credentials, or stack traces.

## Authorization and audit

Adapters resolve a `Campaign_Actor` before invocation. The WordPress adapter
maps `campaignbridge_create_campaigns` to owned-campaign creation/editing,
`campaignbridge_manage` to cross-owner management, and
`campaignbridge_send_campaigns` to approval. Approval authority stays separate
from authoring authority. REST authentication and nonce checks remain adapter
work for #75.

Successful mutations append actor/action/target/result context in the same
transaction as the state change. Rejected transitions, conflicts, and denied
operations append failure/denial events when the target can be represented
safely. Context continues to use #73's size, depth, field, and redaction limits.

## Atomicity

`Database_Transaction` wraps every multi-repository mutation:

- campaign creation plus audit;
- snapshot insert, campaign pointer/version change, and audit;
- state/version change plus audit;
- duplicate attempt identity, duplicate campaign, audit, and completed result.

A false repository result or exception rolls the unit back. The implementation
uses the current WordPress database connection and assumes CampaignBridge tables
run on a transactional MySQL-compatible engine such as InnoDB, which is the
supported production/test configuration. CampaignBridge does not provide a
custom transaction framework or emulate transactions on non-transactional
engines.

## Deferred adapters and provider work

Issue #75 still owns campaign REST routes, schemas, pagination, HTTP permission
callbacks, rate limits, and error envelopes. Issues #76-#80 still own
Mailchimp discovery/capability mapping, remote draft creation, test delivery,
schedule/send/cancel, and reconciliation. There is still no complete operator
campaign UI or end-to-end Mailchimp delivery flow.
