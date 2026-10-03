# CampaignBridge REST API

The API namespace is `campaignbridge/v1`. Routes are registered in `includes/REST/Routes.php`, `includes/REST/Brand_Kit_Routes.php`, `includes/REST/Preview_Routes.php`, `includes/REST/Campaign_Routes.php`, and `includes/REST/Provider_Discovery_Routes.php`.

Email templates use the core `/wp/v2/cb_templates` routes and their `/revisions` and `/autosaves` sub-routes for create, save, publish, autosave, revision listing, revision fetching, and duplication; CampaignBridge adds no parallel template persistence endpoints. Revision restore fetches one core revision and applies its editable fields as unsaved core-data edits; the operator must explicitly save to change the canonical template. The `cb_templates` post type maps its post capabilities, including create and publish, to `campaignbridge_edit_templates`, and every registered template meta key's REST `auth_callback` requires the same capability; generic `edit_posts` does not grant template access. The editor pages revision history through the core revisions collection using `X-WP-Total` and `X-WP-TotalPages`, excluding the template's autosave IDs in the request. Duplication creates a new draft through the core create route with the saved title, content, and only the meta keys returned by `Post_Type_Email_Template::get_duplicable_meta_keys()`. If a core create fails after inserting the template, the inserted template is deleted, so a failed create leaves nothing behind.

`GET` and `PUT /campaignbridge/v1/brand-kit` read and update the stored email Brand Kit. `PUT` accepts one colour slot (`id` and a portable six-digit hex `color`). `GET` and `PUT /campaignbridge/v1/brand-kit/fonts` search the validated Google Fonts catalog and update the Brand Kit's `heading`, `body`, and `button` font roles; the Brand Kit may retain up to 12 validated custom families. These routes require the management capability.

`GET` and `POST /campaignbridge/v1/design-fonts` search the same validated catalog and resolve one family for use in a template's revisioned Design Fonts registry without mutating the Brand Kit. Templates may retain up to 24 design-only families plus optional `heading`, `body`, and `button` overrides. These routes require the template-editing capability. Arbitrary font providers, raw stylesheet URLs, and uploads are not accepted.

`POST /campaignbridge/v1/preview` compiles unsaved editor content into the canonical email artifact. It accepts `template_id` (integer, required), `content` (string, required, max 512 KB), optional `metadata` (object with `title`, `language`, `background_color`, `unsubscribe_url`), and optional bounded `design_fonts` JSON so unsaved per-template typography is compiled exactly as shown in the editor. The response includes `html`, `text`, `diagnostics`, `assets`, `compiler_version`, `profile_version`, `fingerprint`, and `sample`. `sample` is `null` unless a successful artifact contains provider-resolved tokens; it is then an object with `html` and `text` in which those tokens show fixed synthetic values (or are omitted, per each token's preview behavior). `sample` is display-only: `html`, `text`, and `fingerprint` always describe the canonical artifact with its `{{cb:...}}` tokens. A document that fails validation returns diagnostics with HTTP 200 and no HTML. Requires `campaignbridge_edit_templates` plus `edit_post` on the target template. Rate-limited.

General administrative endpoints require the `campaignbridge_manage` capability (defined in `includes/Core/Capabilities.php`). Provider credential operations use the narrower `campaignbridge_manage_connections` capability instead. Mutations additionally validate their WordPress nonce. Request arguments use WordPress REST schemas with sanitization and validation callbacks; errors return `WP_Error` with an HTTP status.

Credential encryption and reveal endpoints are rate-limited `campaignbridge_manage_connections` operations. Reveal accepts a registered field identifier only; the server resolves the stored ciphertext and its security context rather than accepting ciphertext from the browser. The current registered credential field is `mailchimp_api_key`. Consumers must not cache responses containing revealed credentials.

## Campaigns

`includes/REST/Campaign_Routes.php` exposes the local, provider-neutral campaign
lifecycle. It is a thin adapter over `Campaign_Workflow`
([`campaign-workflows.md`](campaign-workflows.md)). The workflow owns
ownership and template authorization, state transitions, snapshots,
concurrency, idempotency, and audit. The routes authenticate the request,
validate the transport shape, resolve the current user into a
`Campaign_Actor`, call the workflow, and map its typed result. They never write
repositories, call providers, or recompile content themselves. The repository
boundary check (`bin/ci/check-repository-boundary.sh`) rejects any
`includes/REST/Campaign*` file that imports `CampaignBridge\Repository` or
`CampaignBridge\Providers`, or touches `$wpdb` or options directly.

### Endpoints

All routes are under `/campaignbridge/v1`. Every action route is `POST`.

| Method | Route | Body fields | Workflow | Success |
| --- | --- | --- | --- | --- |
| `GET` | `/campaigns` | query: `owner_user_id?`, `page` (≥ 1, default 1), `per_page` (1–100, default 20) | `list` | 200 collection |
| `POST` | `/campaigns` | `template_id`, `owner_user_id?`, `provider?`, `audience_reference?` | `create` | 201 campaign |
| `GET` | `/campaigns/{id}` | — | `get` | 200 campaign |
| `POST` | `/campaigns/{id}/template` | `expected_version`, `template_id` | `edit_template` | 200 campaign |
| `POST` | `/campaigns/{id}/targeting` | `expected_version`, `provider`, `audience_reference` (both keys required; each may be `null`) | `select_audience` | 200 campaign |
| `POST` | `/campaigns/{id}/snapshot` | `expected_version` | `snapshot` | 200 snapshot result |
| `POST` | `/campaigns/{id}/validation` | — | `validate` | 200 validation result |
| `POST` | `/campaigns/{id}/preview` | — | `preview` | 200 preview result |
| `POST` | `/campaigns/{id}/submit` | `expected_version` | `submit_for_review` | 200 campaign |
| `POST` | `/campaigns/{id}/approve` | `expected_version` | `approve` | 200 campaign |
| `POST` | `/campaigns/{id}/revoke-approval` | `expected_version` | `revoke_approval` | 200 campaign |
| `POST` | `/campaigns/{id}/archive` | `expected_version` | `archive` | 200 campaign |
| `POST` | `/campaigns/{id}/duplicate` | `idempotency_key` | `duplicate` | 201 new, or 200 replay |
| `POST` | `/campaigns/{id}/provider-draft` | `expected_version`, `idempotency_key` | draft handoff | 201 created, or 200 replay |
| `POST` | `/campaigns/{id}/test-send` | `recipients`, `format?`, `idempotency_key` | test delivery | 202 sent, or 200 replay |
| `POST` | `/campaigns/{id}/schedule` | `expected_version`, `scheduled_for`, `confirm_audience_reference`, `idempotency_key` | scheduler | 200 scheduled or replay |
| `POST` | `/campaigns/{id}/unschedule` | `expected_version`, `idempotency_key` | scheduler | 200 unscheduled or replay |

There are no immediate send, cancel, or reconcile campaign routes yet. Those
belong to M3 (#79–#80) and will be separate contracts. Creating a provider
draft or sending a test never reaches the audience; only `/schedule` does.

Validation and preview are `POST` because they compile live template content,
are rate-limited, and validation writes an audit event. Neither persists
preview state.

### Argument schemas

Each route declares WordPress REST argument schemas
(`includes/REST/Campaign_Rest_Schema.php`). Malformed input is refused with
WordPress's `rest_invalid_param` or `rest_missing_callback_param` (400) before
the workflow runs. The bounds match the domain:

- `id`, `provider`: lowercase opaque identifier `^[a-z0-9][a-z0-9_-]{0,63}$`.
- `template_id`, `owner_user_id`, `expected_version`: integer ≥ 1.
- `audience_reference`: string of 1–191 characters, or `null`. The domain also
  enforces a 191-byte limit; a longer multibyte value returns
  `campaignbridge_campaign_invalid_input`.
- `idempotency_key`: `^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$`.
- `recipients`: 1–5 email addresses, each at most 254 characters.
- `format`: `html` (default) or `text`.
- `scheduled_for`: RFC 3339 date-time with an explicit offset (`Z` or
  `±hh:mm`).
- `confirm_audience_reference`: string of 1–191 characters.
- `page`: 1–1000000; `per_page`: 1–100.

Unknown body fields are ignored and never reach the workflow. Values are never
truncated.

### Authentication and authorization

- Requests must be authenticated. Cookie-authenticated requests must send a
  valid `wp_rest` nonce (`X-WP-Nonce` or `_wpnonce`), as with core. A request
  with no nonce is treated as logged out (401). An invalid nonce returns
  `rest_cookie_invalid_nonce` (403). Application passwords work as in core.
- Every route's permission callback requires `campaignbridge_create_campaigns`
  or `campaignbridge_manage`. `/approve`, `/provider-draft`, `/schedule`,
  and `/unschedule` also require `campaignbridge_send_campaigns`. `/test-send` also requires
  `campaignbridge_test_campaigns`, which is separate from approval and send
  authority. Callers without these capabilities receive
  `rest_forbidden` (401 logged out, 403 logged in) before any campaign is
  loaded.
- Object authority remains in the workflow. Holders of
  `campaignbridge_create_campaigns` manage only campaigns they own, and
  `campaignbridge_manage` manages all campaigns. Approval requires
  `campaignbridge_send_campaigns` plus management of that campaign. Every
  operation that captures, compiles, or targets a template also requires
  `edit_post` on that email template. A denial returns
  `campaignbridge_campaign_forbidden` with only `status` in `data`. It never
  includes the campaign, its version, compiler output, or template content.
  An unknown ID returns 404, so a caller holding a specific ID can tell that it
  exists. Campaign IDs are 128-bit random opaque values, so this does not allow
  enumeration. Collection queries are scoped to an authorized owner and never
  reveal other owners' campaigns or counts.
- `owner_user_id` on create defaults to the current user. Creating a campaign
  for another owner requires `campaignbridge_manage`.

### Optimistic concurrency

Every mutation that changes an existing campaign requires `expected_version`,
the `version` the client last read. A missing or invalid value is a 400. A
stale value returns `409 campaignbridge_campaign_conflict` with
`data.current_version`. The stored campaign is unchanged. The server never
reloads and retries, and never applies last-write-wins. Clients should re-read
the campaign, reconcile, and resubmit. Each successful mutation returns the new
`version`.

Duplication does not take `expected_version`; it reads the source campaign and
is protected by its idempotency key.

### Pagination

`GET /campaigns` lists one owner's campaigns. The default owner is the current
user. `owner_user_id` may name another owner only for callers with
`campaignbridge_manage`; anyone else receives 403 and no items. The query is
indexed (`owner_user_id, updated_at`), bounded by `per_page`, and ordered
`updated_at DESC, id ASC`. The response body carries `pagination`
(`page`, `per_page`, `total`, `total_pages`), and the response sets
`X-WP-Total` and `X-WP-TotalPages`. A page beyond the last non-empty page
returns `400 campaignbridge_campaign_invalid_page`. There is no search or
state filter.

### Idempotent duplication

`POST /campaigns/{id}/duplicate` requires the `idempotency_key` body field. The
workflow derives the duplicate's ID deterministically from the source ID and
key. The first request returns 201 with `idempotent_replay: false`. A retry
with the same key returns 200 with the same campaign and
`idempotent_replay: true`. If the prior result no longer matches the source
(different owner, template, provider, or audience, or it has been edited),
the request returns `409 campaignbridge_campaign_idempotency_conflict`. The
duplicate is a fresh version-1 draft with no snapshot, audit history, remote
reference, or delivery attempt copied. No REST-level idempotency store exists.

### HTML-export-only approval

`provider` and `audience_reference` are optional opaque references and are
never required by any M2 route. A campaign created without them can go through
`create → snapshot → validation → submit → approve` and reach `approved`. Its
`active_snapshot_id` then references the exact immutable snapshot and
fingerprint that was reviewed.

### Response shapes

Each route publishes its response schema through `OPTIONS`. Representations
are built by `Campaign_Rest_Resource`, not from persistence arrays. They
exclude review inputs, block content, stored artifacts, credentials, remote
references, provider payloads, and subscriber data. Every successful response
sends `Cache-Control: no-store` because versioned state must not be served
stale.

Campaign (`campaignbridge-campaign-result` wraps it as `{ "campaign": … }`):

```json
{
  "campaign": {
    "id": "campaign-3f9a…",
    "state": "approved",
    "version": 4,
    "owner_user_id": 1,
    "template_id": 42,
    "provider": null,
    "audience_reference": null,
    "active_snapshot_id": "snapshot-8c1d…",
    "created_at": "2026-09-29T12:00:00Z",
    "updated_at": "2026-09-29T12:05:00Z",
    "scheduled_for": null
  }
}
```

`scheduled_for` is the UTC delivery time once the campaign is scheduled. It is
kept through `sending`, `sent`, and `unknown`, and is `null` before
scheduling and after unscheduling.

Snapshot result (`campaignbridge-campaign-snapshot-result`):

```json
{
  "campaign": { "…": "campaign as above, version incremented" },
  "snapshot": {
    "id": "snapshot-8c1d…", "revision": 1, "fingerprint": "sha256:…", "created_at": "2026-09-29T12:01:00Z",
    "envelope": { "subject": "Spring sale", "preview_text": "", "from_name": "Example Shop",
      "from_email": "news@example.com", "complete": true, "problems": [] }
  },
  "validation": { "valid": true, "diagnostics": [], "compiler_version": "…", "profile_version": "…", "fingerprint": "sha256:…" }
}
```

`snapshot.envelope` is the subject, preview text, and sender frozen with the
snapshot. `complete` is `false` and `problems` lists codes such as
`subject_missing` or `sender_email_invalid` when a provider handoff would
refuse it; local review and HTML-export approval do not require it. It is
`null` for a snapshot taken before envelopes were frozen.

Validation result (`campaignbridge-campaign-validation-result`):
`{ "campaign": …, "validation": { "valid", "diagnostics", "compiler_version", "profile_version", "fingerprint" } }`.
`fingerprint` is `null` when `valid` is `false`.

Preview result (`campaignbridge-campaign-preview-result`):
`{ "campaign": …, "preview": { … } }`. `preview` is the same canonical artifact
representation as `POST /preview`: `html`, `text`, `diagnostics`, `assets`,
`compiler_version`, `profile_version`, `fingerprint`, and synthetic-token
`sample`.

Duplicate result (`campaignbridge-campaign-duplicate-result`):
`{ "campaign": …, "idempotent_replay": false }`.

Collection (`campaignbridge-campaign-collection`):
`{ "items": [campaign, …], "pagination": { "page", "per_page", "total", "total_pages" } }`.

Diagnostics are `{ "severity": "error"|"warning", "code", "path", "message" }`.

### Provider draft handoff

`POST /campaigns/{id}/provider-draft` creates one remote draft from an
approved campaign, using the provider in the campaign's own targeting
(currently `mailchimp`). It requires `campaignbridge_send_campaigns` plus
management of the campaign, the same authority as approval. It is limited to
10 requests per user per minute.

Preconditions, all checked before any provider call:

- The campaign is `approved` at `expected_version` and targets a provider
  that supports drafts and an audience.
- The selected snapshot still reproduces its fingerprint. Content comes only
  from that snapshot, never live WordPress content.
- The snapshot's frozen envelope is complete.
- Every canonical token in the HTML, text, subject, and preview text maps to
  the provider for that audience. First- and last-name tokens need that
  audience's merge fields to have been discovered; refresh them through the
  discovery route first.
- The content contains no author-typed provider merge syntax such as
  `*|FNAME|*`, because the provider would evaluate it.

A failed precondition returns `400 validation_failed` or `invalid_input`,
`409 invalid_state` or `conflict`, or `403 forbidden`, and nothing is sent.

A success response is `campaignbridge-campaign-provider-draft-result`:

```json
{
  "campaign": { "…": "campaign, now provider_draft, version incremented" },
  "remote": { "provider": "mailchimp", "remote_id": "mc0042", "observed_state": "draft", "observed_at": "2026-10-01T12:00:00Z" },
  "attempt": { "id": "attempt-9f2c…", "status": "succeeded", "retryability": "not_retryable" },
  "idempotent_replay": false
}
```

There is exactly one remote draft per campaign and provider. Once it
exists, any further request returns it with 200, `idempotent_replay: true`,
and `attempt: null`, whatever key is sent, and nothing is sent to the
provider.

Outcomes after the provider is contacted:

- **Created:** 201. The campaign moves to `provider_draft`.
- **Refused by the provider** (a definite 4xx): `502 provider_failed`. No
  draft exists. The same `idempotency_key` returns the same failure without
  contacting the provider; use a new key to try again.
- **Unconfirmed** (timeout, lost connection, 5xx, or an unreadable
  response): `409 reconciliation_required`. The draft may or may not exist.
  It is never retried automatically, and every further request is refused
  with the same code, whatever key is sent, until the attempt is reconciled.
- **Draft created but content upload failed:** `502 provider_failed` with
  `data.remote.observed_state` of `content_pending`. Repeat the request to
  resume. The existing draft's audience, envelope, and content are
  re-asserted from the currently approved snapshot with idempotent updates,
  so a campaign whose approval was revoked and given again with a new
  audience or snapshot never relies on stale remote settings. No second
  draft is created.
- **Draft created but the campaign changed concurrently:** `409 conflict`
  with `data.remote`. The draft's identity is kept.

Error `data` may include `remote`, `attempt` (`id`, `status`,
`retryability`), and `provider_error` (`code`, `category`, `retryable`), so a
client knows what already exists. Raw provider error bodies and credentials
are never returned.

### Test delivery

`POST /campaigns/{id}/test-send` sends one test of the campaign's existing
remote draft to named addresses, using the provider in the campaign's own
targeting (currently `mailchimp`). Before the test, the remote draft must
still be unsent, and its audience, envelope, and content are re-asserted from
the approved snapshot, so the test shows exactly what would be delivered even
if the draft was edited in the provider. Editor HTML is never sent. A remote
campaign that was scheduled or sent outside CampaignBridge is refused with
`409 reconciliation_required`. It requires
`campaignbridge_test_campaigns` plus management of the campaign. Holding
`campaignbridge_send_campaigns` does not grant it. A test never changes the
campaign's state or version.

```json
{ "recipients": ["qa@example.com"], "format": "html", "idempotency_key": "test-7f3a" }
```

Preconditions, all checked before any provider call:

- `recipients` holds 1–5 valid addresses. Duplicates are merged after
  lowercasing. `format` is `html` or `text`.
- The campaign is `provider_draft` and its remote draft is confirmed
  (`observed_state` `draft`).
- The campaign has sent fewer than 10 tests in the last 24 hours. This
  quota is durable and per campaign, so another user or a new transport
  window does not reset it. It is in addition to the limit of 10 requests
  per user per minute.

A failed precondition returns `400 invalid_input`, `409 invalid_state`,
`429 rate_limited`, or `403 forbidden`, and nothing is sent or recorded as
an attempt.

Recipients are used for the one provider call and never stored, logged, or
returned. The attempt and audit event record only the recipient count, the
format, the remote draft ID, and the snapshot ID and fingerprint tested.

A success response is `campaignbridge-campaign-test-send-result`:

```json
{
  "campaign": { "…": "campaign, unchanged" },
  "remote": { "provider": "mailchimp", "remote_id": "mc0042", "observed_state": "draft", "observed_at": "2026-10-01T12:00:00Z" },
  "attempt": { "id": "attempt-4b7e…", "status": "succeeded", "retryability": "not_retryable" },
  "test": { "format": "html", "recipient_count": 1, "snapshot_id": "snapshot-8c1d…", "fingerprint": "sha256:…" },
  "idempotent_replay": false
}
```

The `idempotency_key` identifies one test request. Repeating a key never
sends again: a key whose test succeeded returns 200 with
`idempotent_replay: true`, the recorded `attempt`, and `test: null`, because
the request was not stored. A key is not compared with the recipients sent
with it.

Outcomes after the provider is contacted:

- **Sent:** 202. The provider accepted the test for delivery.
- **Refused by the provider** (a definite 4xx, such as a rejected address or
  an exhausted provider test quota): `502 provider_failed`. Nothing was
  sent. The same key returns the same failure; use a new key to try again.
- **Unconfirmed** (timeout, lost connection, 5xx, or an unexpected
  response): `409 reconciliation_required`. The test may or may not have
  been delivered. It is never retried automatically, and the same key keeps
  returning this error. Check the test inboxes before sending another test
  with a new key. Unlike an unconfirmed draft, this does not block further
  tests, because a duplicate test reaches only the named test addresses.

Mailchimp has no private preview link for a draft. Its archive URL is a
public link, so it is not returned. The compiled preview
(`POST /campaigns/{id}/preview`) and the test email are the review surfaces.

### Scheduling

`POST /campaigns/{id}/schedule` schedules the campaign's remote draft to send
to its audience. This is the first route that can reach the audience. It
requires `campaignbridge_send_campaigns` plus management of the campaign;
the test-send capability does not grant it.

```json
{ "expected_version": 5, "scheduled_for": "2026-10-05T08:00:00-07:00", "confirm_audience_reference": "abc123", "idempotency_key": "schedule-7f3a" }
```

Preconditions, all checked before any provider call:

- `confirm_audience_reference` equals the campaign's `audience_reference`.
  A client should show the audience to the operator and send back what was
  confirmed, so a stale screen or a mistargeted call cannot schedule the
  wrong audience.
- The campaign is `provider_draft` at `expected_version`, and its remote
  draft is confirmed (`observed_state` `draft`).
- `scheduled_for` has an explicit offset, falls on the provider's
  scheduling interval (Mailchimp: :00, :15, :30, :45), is at least 10
  minutes ahead, and is within one year. It is stored in UTC.
- The selected snapshot still reproduces its fingerprint and its envelope
  is complete and translatable for the audience.
- No earlier schedule, unschedule, or send attempt for the campaign is
  `pending` or `unknown`.
- **The remote draft matches what was approved.** CampaignBridge never
  trusts it as-is. It reads the draft and requires it to be unsent,
  re-asserts the approved audience, envelope, and content with idempotent
  updates, then reads it back and requires exactly the approved audience
  with no segment. A draft that was scheduled, sent, or segmented in the
  provider is refused with `409 reconciliation_required`; a draft that could
  not be read or updated is refused with `502 provider_failed`. These calls
  never reach the audience.

A failed precondition returns `400 invalid_input` or `validation_failed`,
`409 invalid_state`, `conflict`, or `reconciliation_required`,
`502 provider_failed`, or `403 forbidden`. Nothing is scheduled, no attempt
is recorded, and no version is consumed.

Before contacting the provider, one transaction records a `pending` attempt
and consumes a campaign version. Concurrent requests holding the same
`expected_version` are refused with `409 conflict`, so a double-click
cannot reach the provider twice.

A success response is `campaignbridge-campaign-delivery-result`, the same
shape as the provider draft result. The campaign is `scheduled`, its
`scheduled_for` is set, and its version has advanced by two (the claim and
the transition). The same key returns 200 with `idempotent_replay: true` and
contacts nothing.

Outcomes after the provider is contacted:

- **Scheduled:** 200.
- **Refused by the provider** (a definite 4xx, such as a campaign Mailchimp
  considers not ready): `502 provider_failed`. The campaign stays
  `provider_draft` at the version the claim consumed, which the error
  reports as `data.current_version`. The same key returns the same failure;
  use a new key and the new version to try again.
- **Unconfirmed** (timeout, lost connection, 5xx, or an unexpected status):
  `409 reconciliation_required`. The campaign moves to `unknown`, because it
  may or may not send. It is never retried automatically, and every further
  schedule, unschedule, or send is refused until reconciliation (#80).

`POST /campaigns/{id}/unschedule` returns a `scheduled` campaign to
`provider_draft` and clears `scheduled_for`. It needs no audience
confirmation, because it stops delivery rather than starting it. It is
refused with `409 invalid_state` once `scheduled_for` has passed, because the
send may already have started; unscheduling cannot pretend to stop a send
the provider has accepted. Its outcomes mirror scheduling: an unconfirmed
unschedule moves the campaign to `unknown`.

Mailchimp's in-flight cancel (`/actions/cancel-send`) is not used. It requires
Mailchimp Pro and cannot recall delivered messages, so CampaignBridge does not
advertise it.

### Content validation outcomes

Content problems are not server errors. `/validation` and `/preview` treat
diagnostics as the requested resource and return them with 200, like
`POST /preview`. `/snapshot` is a mutation. When content fails it returns
`400 campaignbridge_campaign_validation_failed` with `data.diagnostics`, and no
snapshot, version change, or state change is persisted. `/submit` and
`/approve` recompile the selected snapshot. If it no longer reproduces its
stored fingerprint, `/submit` returns `validation_failed` and `/approve` returns
`approval_not_allowed`.

### Error envelope and workflow error mapping

Errors use the WordPress REST envelope:

```json
{ "code": "campaignbridge_campaign_conflict", "message": "Campaign version is stale.", "data": { "status": 409, "current_version": 4 } }
```

`data` always contains `status`. It adds `current_version` for `conflict`,
and for `provider_failed` and `reconciliation_required` from remote
operations, and `diagnostics` only for compiler failures. Messages are fixed
workflow strings. They never contain SQL, exception traces, class names, raw
persistence errors, credentials, or provider payloads. `Campaign_Rest_Errors`
is the single mapping:

| Workflow code | HTTP | REST code |
| --- | --- | --- |
| `invalid_input` | 400 | `campaignbridge_campaign_invalid_input` |
| `validation_failed` | 400 (200 on `/validation`, `/preview`) | `campaignbridge_campaign_validation_failed` |
| `forbidden` | 403 | `campaignbridge_campaign_forbidden` |
| `not_found` | 404 | `campaignbridge_campaign_not_found` |
| `conflict` | 409 | `campaignbridge_campaign_conflict` |
| `invalid_state` | 409 | `campaignbridge_campaign_invalid_state` |
| `missing_snapshot` | 409 | `campaignbridge_campaign_missing_snapshot` |
| `approval_not_allowed` | 409 | `campaignbridge_campaign_approval_not_allowed` |
| `idempotency_conflict` | 409 | `campaignbridge_campaign_idempotency_conflict` |
| `reconciliation_required` | 409 | `campaignbridge_campaign_reconciliation_required` |
| `provider_failed` | 502 | `campaignbridge_campaign_provider_failed` |
| `rate_limited` | 429 | `campaignbridge_campaign_rate_limited` |
| `persistence_failed` (and any unmapped code) | 500 | `campaignbridge_campaign_persistence_failed` |

The repository does not use 422. Transport-level refusals keep WordPress codes:
`rest_invalid_param`, `rest_missing_callback_param`, `rest_forbidden`,
`rest_cookie_invalid_nonce`, and `rate_limit_exceeded` (429).

### Rate limits

Limits are per authenticated user per 60-second window and use the shared
`Rate_Limiter`:

- 10 per window: create, snapshot, validation, preview, duplicate,
  provider-draft, test-send, schedule, and unschedule. These compile, capture, create records, or
  call the provider. Test sends also have the durable per-campaign quota
  described above.
- 30 per window: each versioned lifecycle mutation (template, targeting,
  submit, approve, revoke-approval, archive).
- Unlimited: reads (`GET`), which are bounded by pagination instead.

## Provider capabilities and discovery

`includes/REST/Provider_Discovery_Routes.php` exposes provider capabilities
and discovered targeting references. It wraps
`Workflow\Provider\Provider_Discovery_Service` (see ADR 0002). Only
providers with a discovery adapter are served (currently `mailchimp`).
Others return `404 campaignbridge_provider_not_found`.

| Method | Route | Remote call |
| --- | --- | --- |
| `GET` | `/providers/{provider}/capabilities` | No |
| `GET` | `/providers/{provider}/discovery/{kind}` | No: reads the cache only |
| `POST` | `/providers/{provider}/discovery/{kind}/refresh` | Yes: the only remote path |

- `kind` is one of `audiences`, `merge_fields`, or `segments`.
- `merge_fields` and `segments` require `audience` (an opaque ID matching
  `^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$`). `audiences` must omit it. A scope
  mismatch returns `400 campaignbridge_discovery_invalid_scope`.
- Every route requires authentication plus `campaignbridge_create_campaigns`,
  `campaignbridge_manage`, or `campaignbridge_manage_connections`.
- Refresh is limited to 10 requests per user per 60 seconds. Cache reads are
  not rate-limited.
- Credentials are decrypted server-side for one call and never returned.
- Every successful response sends `Cache-Control: no-store`.

`capabilities` returns `{ provider, operations }`, where `operations` maps
every known operation to `true` or `false`.

A discovery response is:

```json
{
  "provider": "mailchimp",
  "kind": "audiences",
  "audience": null,
  "supported": true,
  "source": "cache",
  "stale": false,
  "fetched_at": "2026-10-01T12:00:00Z",
  "complete": true,
  "items": [
    { "id": "abc123", "name": "Customers", "member_count": 12,
      "default_sender": { "from_name": "Example Shop", "from_email": "news@example.com" } }
  ],
  "error": null
}
```

- `source` is `remote` after a successful refresh, `cache` for a cached
  list, or `none` when nothing is cached.
- `stale` is `true` once a list is older than 15 minutes, or when it is
  returned after a failed refresh.
- `complete` is `false` when the provider holds more than 1000 entries or
  an entry failed validation.
- Merge-field items are `{ tag, name, type, required }`.
- Segment items are `{ id, name, kind: "segment"|"tag", member_count }`.
- An unsupported kind returns `supported: false` with no items.

When a refresh fails but a previous list is cached, the response is 200
with the stale list and the normalized `error`
(`{ code, category, message, retryable }`). When nothing is cached, the
failure is returned as a WordPress error with `data.category` and
`data.retryable`:

- `409` when the provider is not configured;
- `429` when the provider rate-limits;
- `502` for any other upstream failure, including rejected credentials.

Raw provider error bodies, member records, and credentials never appear in
responses.

## Contract source

The route implementation remains the authoritative reference while a generated OpenAPI contract is developed.
