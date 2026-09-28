# Campaign persistence

Issue #73 introduced the provider-neutral storage foundation for the M2
campaign lifecycle. #74 now consumes it through the canonical application
workflow documented in [`campaign-workflows.md`](campaign-workflows.md).
REST endpoints, jobs, and provider mutations remain outside this layer.

## Site-local schema

`Schema_Manager` owns schema version 1 in the
`campaignbridge_database_schema` site option. Tables use the active site's
`$wpdb->prefix`; CampaignBridge is not network-global.

| Table | Purpose | Identity and read indexes |
|---|---|---|
| `{prefix}campaignbridge_campaigns` | Current provider-neutral campaign record and optimistic version | Primary `id`; `owner_updated (owner_user_id, updated_at)` supports bounded owner listings |
| `{prefix}campaignbridge_campaign_snapshots` | Insert-only frozen M1 review input and its exact successful artifact | Primary `id`; unique `campaign_revision (campaign_id, revision)` prevents in-place refresh replacement and supports revision history |
| `{prefix}campaignbridge_remote_campaigns` | Normalized local/provider/remote identity and observed state | Primary `(campaign_id, provider)` permits one mapping per local campaign/provider; unique `provider_remote (provider, remote_id)` supports reverse lookup without duplicates |
| `{prefix}campaignbridge_delivery_attempts` | Keyed attempt identity and normalized result for remote mutations plus the retry-safe local duplicate workflow | Primary `id`; unique `campaign_idempotency (campaign_id, operation, idempotency_key)` prevents duplicate keyed attempts; `campaign_created (campaign_id, created_at)` supports bounded history |
| `{prefix}campaignbridge_audit_events` | Append-only, minimized operator/security history | Primary `id`; `target_created (target_type, target_id, created_at)` supports bounded target history |

Relationships are logical rather than database foreign keys because WordPress
`dbDelta()` does not provide a reliable cross-version foreign-key migration
contract. Repositories refuse snapshots, remote references, and delivery
attempts for missing campaigns. A campaign can select an active snapshot only
when that snapshot belongs to it.

Identifiers and indexed strings are bounded `varchar` values. Large frozen
HTML/text and review input use `longtext`; bounded asset and audit JSON use
`text`. Domain timestamps are strict UTC ISO-8601 values ending in `Z` and are
stored as UTC `datetime` values.

## Repository boundary

Domain ports describe typed application needs and never expose database rows:

- `Campaign_Source`
- `Campaign_Snapshot_Source`
- `Remote_Campaign_Reference_Source`
- `Delivery_Attempt_Source`
- `Audit_Event_Source`

Their WordPress implementations live in `Repository/`. Workflow and delivery
code must use these ports rather than `$wpdb`, options, post meta, or provider
responses. Repositories are not authorization boundaries: #74 resolves explicit
actor authority in the application layer, while #75 remains responsible for
REST authentication, nonces, and HTTP permission callbacks.

Every stored record carries a `data_version`. Typed hydration rejects malformed
rows, unknown fields, and unsupported versions. List reads skip records that
cannot be interpreted safely; detail reads return `null`. Reads never rewrite
or delete malformed or future-version data.

## Frozen snapshots and artifacts

`Campaign_Snapshot` stores the complete serialized M1 `Review_Input` plus the
exact successful `Compiled_Artifact`: HTML, plain text, bounded asset records,
artifact fingerprint, compiler version, and profile version. Loading it does
not read the live template, source posts, Brand Kit, or theme.

The repository is insert-only. Both the snapshot ID and campaign/revision pair
are unique. Refreshing content therefore requires a new snapshot ID and the
next review revision. Source edits cannot mutate stored review input or output.
The #74 workflow captures live inputs before freezing, recompiles through the
M1 compiler, and atomically inserts/selects the snapshot with compare-and-swap.

## Concurrency, idempotency, and remote outcomes

Campaign records carry an integer version. `compare_and_swap()` updates only
when the stored version equals the caller's expected version, and the
replacement must carry `expected + 1`. The #74 workflow maps a stale write to
the stable `conflict` result and never retries or overwrites the newer state.

An idempotency identity is `(campaign_id, operation, idempotency_key)`. A
non-null key can be inserted only once. #74 uses it only for retry-safe campaign
duplication and resolves a replay to the original duplicate; other local
mutations rely on expected versions instead of mechanical idempotency keys.
Attempt results use the explicit states `pending`, `succeeded`, `failed`, and
`unknown`. Future ambiguous provider outcomes therefore cannot be collapsed
into failure. Retryability remains a stored classification, not an automatic
retry trigger.

Remote references preserve normalized state and optional bounded cursor data,
not raw provider payloads. A mapping cannot change its local campaign,
provider, or remote ID during an observation update.

## Audit minimization

Audit context is a named map with these limits:

- maximum encoded size: 4096 bytes;
- maximum fields across the map: 32;
- maximum nesting depth: 3;
- maximum string value: 512 bytes;
- scalar, null, or nested named-map values only; lists, objects, and non-finite
  numbers are rejected.

Keys are bounded names and stored in deterministic order. Keys containing
credential, authorization, password, secret, token, API-key, subscriber,
recipient, email, phone, personal-name, or address markers are replaced with
`[redacted]` before persistence. Events are append-only.

Campaign storage explicitly excludes credentials, raw request bodies, raw
provider responses, stack traces, arbitrary post meta, subscriber lists, and
subscriber PII. Provider connection credentials remain in their existing
encrypted repository and are not copied into these tables.

## Migration and rollback policy

Activation runs the migration before granting campaign capabilities. Admin
initialization retries it so an interrupted/current-version install with a
missing table is repaired. `dbDelta()` receives the full idempotent schema and
the version is stamped only after all five tables can be described. Repeated
runs preserve rows.

An absent version is version zero. Malformed or newer schema versions fail
closed: no migration, downgrade, option rewrite, or data deletion occurs.
Repositories also refuse access unless the installed schema is current.
Rolling plugin code back across an unsupported future schema therefore leaves
the newer data intact for operator recovery or re-upgrade. Explicit WordPress
plugin uninstall removes the five allowlisted tables and schema option;
deactivation does not.
