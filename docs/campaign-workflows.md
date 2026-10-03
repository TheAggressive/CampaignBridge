# Campaign workflows

Issue #74 adds the canonical provider-neutral application layer for local
campaign lifecycle mutations. Future REST, Abilities, CLI, and admin adapters
call `Campaign_Workflow`; they do not write campaign repositories or reproduce
transition rules.

The workflow composes the #73 ports and implementations:

- `Campaign_Source` for current campaign state and compare-and-swap versions;
- `Campaign_Snapshot_Source` for immutable review revisions and artifacts;
- `Audit_Event_Source` for append-only bounded/redacted history;
- `Campaign_Review_Input_Source` for one live capture before freezing;
- `Campaign_Template_Authority` for WordPress-native template object access;
- `Campaign_Transaction` for atomic multi-repository mutations.

`Campaign_Workflow_Factory` builds the production graph. No operation in this
layer creates a provider campaign, invokes Mailchimp, schedules, sends, cancels,
or reconciles remote state.

Live capture keeps the dependency direction explicit. The Repository layer
implements the Domain `Campaign_Template_Input_Source` port and returns only
typed template content and metadata. The Workflow
`Campaign_Review_Input_Capture` coordinator combines that value with post and
Brand Kit sources and the canonical email preview/compiler service. Repository
classes never import or instantiate Workflow classes; the CI repository-boundary
guard enforces that rule.

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
history. Its required idempotency key and source campaign ID produce a
deterministic duplicate campaign ID. The campaign primary key is the local
idempotency boundary: a compatible replay resolves the existing draft, while a
reused key whose current source references no longer match returns
`idempotency_conflict`. Duplication does not create a delivery-attempt row;
that table is reserved for provider mutations and their remote outcomes. The
duplicate campaign and its single creation audit are one transaction.

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

Provider and audience references are optional during local review and approval,
so an HTML-export-only campaign can reach `approved`. Adding, changing, or
removing those references on an approved campaign still revokes approval and
returns it to `ready_for_review` while preserving the immutable selected
snapshot. Submission requires a selected snapshot that recompiles successfully
to its stored fingerprint. Approval also requires separate approval authority,
the legal `ready_for_review` state, the selected snapshot, a successful
canonical compile, and an exact stored fingerprint match. The approved campaign
therefore identifies frozen HTML, plain text, assets, design/content inputs,
compiler version, and profile; it never means live WordPress content later.

A snapshot also freezes the template's authored envelope: subject, preview
text, sender name, and sender email. It is read in the same template load as
the content, so both come from the same edit. The envelope is captured as
authored, even when incomplete, so HTML-export-only review and approval are
unchanged. `Campaign_Envelope::problems()` reports what a provider handoff
would refuse, and the snapshot audit records only whether the envelope is
complete, never its values. Provider handoff (#77) uses the approved
snapshot's envelope, never the live template.

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
`campaignbridge_send_campaigns` to approval, provider draft creation, and
scheduling, and
`campaignbridge_test_campaigns` to test sends. Campaign authority and template
authority are independent: operations that introduce or read template content
also require WordPress object authorization for that exact `cb_templates`
post through `user_can( $actor_id, 'edit_post', $template_id )`. The mapped
post-type capabilities therefore remain authoritative for create, template
change, snapshot, live validation/preview, review submission, approval, and
duplication. A broad campaign capability cannot bypass a template-specific
denial.

Template authorization runs before capture or compilation. Denials return the
stable `forbidden` code without compiler output and without disclosing template
content or metadata in the result or audit context. Approval authority stays
separate from authoring and template authority. The #75 REST adapter adds
WordPress authentication, `wp_rest` nonce handling, and coarse capability
permission callbacks in front of these checks. It does not replace them.

Successful mutations append actor/action/target/result context in the same
transaction as the state change. Rejected transitions, conflicts, and denied
operations append failure/denial events when the target can be represented
safely. Context continues to use #73's size, depth, field, and redaction limits.

## Atomicity

`Database_Transaction` wraps every multi-repository mutation:

- campaign creation plus audit;
- snapshot insert, campaign pointer/version change, and audit;
- state/version change plus audit;
- duplicate campaign plus its creation audit.

A false repository result or exception rolls the unit back. The implementation
uses the current WordPress database connection and assumes CampaignBridge tables
run on a transactional MySQL-compatible engine such as InnoDB, which is the
supported production/test configuration. CampaignBridge does not provide a
custom transaction framework or emulate transactions on non-transactional
engines.

## Provider draft handoff

`Campaign_Draft_Handoff` creates exactly one remote draft from an approved
campaign through a `Provider_Draft_Gateway`. It requires approval authority
and never schedules or sends. Its protocol uses only the #73 records:

1. **One draft per campaign and provider.** A remote reference that already
   exists is a replay; nothing new is created. The `(provider, remote_id)`
   uniqueness means one remote draft can never map to two campaigns.
2. **Write-ahead.** A `pending` `create_draft` attempt is stored before the
   remote create. The remote draft's title carries the attempt ID, so
   reconciliation can find a draft whose response was lost.
3. **Never retry blindly.** While any create attempt is `pending` or
   `unknown`, further creates are refused with `reconciliation_required`,
   whatever idempotency key is sent.
4. **Record what is known.** A provider refusal before creation marks the
   attempt `failed`. A timeout, lost connection, server error, or unreadable
   response marks it `unknown`.
5. **Resume partial work.** If the draft was created but its content upload
   failed, the reference is stored as `content_pending`. The next request
   re-uploads content only, which is an idempotent operation.

On success, one transaction stores the reference, marks the attempt
`succeeded`, moves the campaign `approved → provider_draft`, and audits it.
If the campaign changed concurrently, the reference and attempt are still
stored and the result is `conflict`; the concurrent change is not
overwritten.

Content is the approved snapshot's artifact and frozen envelope, verified by
`Campaign_Snapshot_Verifier`, the same check used for review and approval.
Tokens are translated with the provider's `Token_Mapping`; the stored
artifact is never altered. Audit events record identifiers, states, and
normalized error categories, never content or provider payloads.

## Test delivery

`Campaign_Test_Delivery` sends one test of a campaign's existing remote draft
through a `Provider_Test_Gateway`. It requires the test capability plus
management of the campaign; approval authority does not grant it. A test
never changes the campaign's state or version. Its protocol:

1. **Test only the approved remote draft.** The campaign must be
   `provider_draft` with a confirmed remote reference. The provider sends the
   content it received from the approved snapshot at handoff; editor HTML is
   never sent.
2. **Bound the request first.** `Test_Delivery` accepts 1–5 normalized
   addresses and a format. A durable quota of 10 `test_send` attempts per
   campaign in a rolling 24 hours is counted from the attempt records, so it
   holds across users and transport windows.
3. **Never store recipients.** The attempt records the remote draft ID as its
   correlation. The audit event records the remote ID, attempt ID, snapshot
   ID and fingerprint, format, and recipient count. Neither records an
   address.
4. **Write-ahead, one send per key.** A `pending` `test_send` attempt is
   stored before the provider call. A repeated idempotency key returns the
   recorded outcome and never sends again.
5. **Never retry an unconfirmed test.** An ambiguous result marks the attempt
   `unknown` and returns `reconciliation_required` for that key. Unlike an
   unconfirmed draft, it does not block tests with a new key, because a
   duplicate test reaches only named test addresses.

## Scheduling

`Campaign_Scheduler` schedules a confirmed remote draft through a
`Provider_Delivery_Gateway`, or unschedules it. Both require delivery
authority plus management of the campaign. Its protocol:

1. **Check everything first.** Delivery authority, the audience
   confirmation (schedule only), state, version, the confirmed remote
   reference, the schedule time (`Schedule_Time`: explicit offset, provider
   interval, at least 10 minutes ahead, within a year), and the approved
   snapshot and envelope, via `Campaign_Snapshot_Verifier`.
2. **One delivery operation at a time.** While any `schedule`,
   `unschedule`, or `send` attempt is `pending` or `unknown`, every new one
   is refused with `reconciliation_required`, whatever key is sent. This is
   stricter than test sends, because these reach the audience.
3. **Claim before contact.** One transaction stores the `pending` attempt
   and consumes the campaign version (`Campaign::claim()`). A concurrent
   request holding the same expected version fails its compare-and-swap,
   rolls back its attempt, and never reaches the provider.
4. **Record what is known.** On acceptance, one transaction marks the
   attempt `succeeded`, updates the reference's observed state, applies the
   transition, and audits it. A definite refusal leaves the campaign where
   it was. An unconfirmed outcome marks the attempt `unknown` and moves the
   campaign to `unknown`, which keeps `scheduled_for` for reconciliation.
5. **Unscheduling cannot pretend.** Once `scheduled_for` has passed, an
   unschedule is refused without a provider call; the send may have
   started.

The `scheduled → provider_draft` transition was added for unscheduling. No
existing campaign is in an affected state, so it needs no data migration.
Audit events identify the actor, operation, snapshot and fingerprint, remote
reference, delivery time, states, and normalized result.

## Deferred adapters and provider work

The #75 REST adapter exposes these operations, plus bounded `get`/`list`
reads, with schemas, pagination, permission callbacks, rate limits, and one
error envelope. See [`api.md`](api.md#campaigns). Issues #79-#80 still own
immediate send and reconciliation. There is still no complete operator
campaign UI or end-to-end Mailchimp delivery flow.
