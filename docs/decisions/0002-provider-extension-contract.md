# ADR 0002: Provider extension contract

- Status: Accepted
- Date: 2026-09-11
- Issue: Milestone 0

## Context

CampaignBridge supports multiple email service providers (Mailchimp, future
providers). Each provider must implement a consistent surface for identity,
configuration validation, and connection verification. Without a documented
contract, providers drift in error reporting and capability semantics.

The existing `Provider_Interface` and `Abstract_Provider` already define the
method surface. This ADR locks the behavioral contract so new providers and
future provider features can be added without re-architecting the workflow
layer.

## Decision

A provider is a self-contained adapter that implements a **four-method
contract**:

1. **`slug(): string`** — Returns a stable, unique identifier used in
   storage, REST routes, and logs. Examples: `'mailchimp'`, `'html'`.

2. **`label(): string`** — Returns a human-readable display name for the
   admin UI. Examples: `'Mailchimp'`, `'HTML Export'`.

3. **`is_configured( array $settings ): bool`** — Validates that all
   required configuration (API keys, endpoints, etc.) is present and well
   formed. The workflow layer never inspects provider-specific settings
   directly; it relies on this method.

4. **`verify_connection( array $settings ): Connection_Result`** — Verifies
   that the configured provider account is reachable and authorized. Returns
   a `Connection_Result` value object that is either:
   - **Success**: carries normalized account details (e.g. account name,
     region) for display in the admin UI.
   - **Failure**: carries a `Provider_Error` with a normalized category,
     stable machine code, and an operator-safe message. Provider-specific
     payloads never leak into the result.

### Error normalization

Failures are normalized into `Provider_Error` values using the
`Provider_Error_Category` taxonomy. The category determines retryability:
`rate_limited`, `timeout`, `network`, and `unknown` are retryable;
`authentication`, `authorization`, `validation`, `not_found`, `conflict`,
and `provider_error` are not.

### `Connection_Result` contract

`Connection_Result` is a final value object in the domain layer
(`CampaignBridge\Domain\Campaign`). It is the sole return type of
`verify_connection()` and is consumed by the workflow, REST, and admin
layers. It exposes:

- `is_success(): bool`
- `error(): ?Provider_Error`
- `account_details(): array<string, mixed>`
- `to_array(): array<string, mixed>`

## Capabilities and discovery (#76)

Amended 2026-10-01 for M3 provider discovery.

**Capabilities.** `Provider_Operation` is the closed vocabulary of
operations. These cover verification, discovery of audiences, merge fields,
segments, senders and template sections, export, draft, test, schedule,
send, cancel, reconcile, and reports. `Abstract_Provider::capabilities()`
validates an adapter's flags into a `Provider_Capabilities` value. Unknown
operation names and non-boolean flags are rejected. Every operation is
reported as explicitly supported or unsupported; nothing is guessed.
Mailchimp's flags live in `Mailchimp_Provider::CAPABILITIES` as the single
source of truth.

**Discovery port.** Read-only reference discovery is a separate port,
`Domain\Provider\Provider_Discovery`, rather than more methods on
`Provider_Interface`. Existing providers are therefore unaffected. An
adapter:

- receives decrypted settings for one call only and never caches or
  persists them;
- returns a bounded `Discovery_Batch` of normalized DTOs
  (`Discovered_Audience`, `Sender_Identity`, `Discovered_Merge_Field`,
  `Discovered_Segment`) or a `Provider_Error`;
- requests only the fields it keeps, never member records, and reports
  `complete: false` when the remote list is longer than the bound or an
  entry fails validation;
- exposes `account_key()`, a non-reversible account identity used only to
  partition the cache.

Audiences are account-wide. Merge fields and segments are scoped to one
audience. Sender identities are the audience's default from-name and
from-address, which belong to the organization, not to subscribers.
Mailchimp tags are reported as segments of kind `tag`.

**Cache and refresh.** `Workflow\Provider\Provider_Discovery_Service` owns
the explicit refresh contract, and `Provider_Discovery_Repository` stores
results as transients under hashed keys.

- `cached()` never contacts the provider, so listing references cannot
  become an implicit remote call.
- `refresh()` is the only remote path.
- A result older than `FRESH_SECONDS` (15 minutes) is still returned but
  flagged stale. It is retained for at most `RETENTION_SECONDS` (one day).
- A failed refresh returns the previous cached list flagged stale, with the
  normalized error, and never overwrites the cached list.
- Unsupported kinds return an explicit unsupported outcome.

**Errors.** `Mailchimp_Errors` maps every Mailchimp transport and HTTP
failure to the shared categories, used by both verification and discovery.
Raw Mailchimp error bodies are never read into results.

**Token mapping.** The compiled artifact keeps canonical `{{cb:...}}`
tokens. A `Provider_Token_Mapper` produces a `Token_Mapping` for one
audience. Every provider-resolved token in the registry is either mapped to
the provider's representation or declared unsupported with a reason code:
`merge_field_missing`, `merge_fields_incomplete`, or `unsupported`.
Construction refuses a mapping that leaves any token unaccounted for.
`Token_Mapping::translate()` validates content with the canonical
`Token_Parser`, then substitutes only validated tokens. It returns new
content and never alters the artifact. A parse error, or any token without
a representation, makes the translation incomplete, and incomplete content
must not be handed off.

Mailchimp maps email, view-online and unsubscribe to the system tags
`*|EMAIL|*`, `*|ARCHIVE|*` and `*|UNSUB|*`. First and last name map to
`*|FNAME|*` and `*|LNAME|*` only when that audience's discovered merge
fields prove the field exists, because audience owners can rename or delete
those fields. Audience custom merge fields stay provider-scoped and never
become canonical tokens.

A mapping may declare the provider's own literal token syntax. Canonical
content that already contains it fails translation, because the provider
would evaluate author-typed text such as `*|FNAME|*` after handoff.

**Remote drafts (#77).** `Provider_Draft_Gateway` creates and fills one
remote draft and never schedules or sends. An adapter must not retry a
non-idempotent create, and must classify every result as a `Draft_Outcome`:

- `created`: the draft exists with the uploaded content;
- `content_pending`: the draft exists and its ID is known, but the content
  upload did not complete;
- `failed`: the provider definitely created nothing;
- `ambiguous`: the provider may have created a draft.

A refusal reported before creation (validation, authentication,
authorization, not found, conflict, rate limit) is definite. A timeout,
transport loss, server error, or unreadable response is ambiguous.
`Mailchimp_Draft_Gateway` sends `POST /campaigns` once, without retry, then
uploads content with an idempotent `PUT`. Response bodies are read only for
the draft ID. `Campaign_Draft_Handoff` owns the protocol around the gateway;
see `campaign-workflows.md`.

## Ownership boundaries

- `Provider_Interface` defines the contract; it contains no implementation.
- `Abstract_Provider` supplies shared infrastructure (HTTP client, error
  mapping, policy defaults) but no provider-specific logic.
- Concrete providers (e.g. `Mailchimp_Provider`) implement the remote API
  calls and map responses to `Connection_Result` and `Provider_Error` values.
- The workflow layer consumes `Connection_Result` and `Provider_Error`
  values; it never inspects provider-specific response structures.
- `Capabilities` (Core) is the single source of truth for WordPress
  capability names. Providers do not define or check capabilities.
- `Provider_Connection_Repository` (Repository) persists and retrieves
  `Provider_Connection` value objects. It is the sole storage boundary for
  provider credentials.

## Versioning and migration

The `Provider_Interface` method signatures are a versioned contract. Adding
a new method requires a default implementation in `Abstract_Provider` so
existing providers continue to work. Removing or changing a method signature
requires a new major version of the plugin.

## Consequences

New providers must implement all four methods in `Provider_Interface` and
extend `Abstract_Provider`. The workflow layer is provider-agnostic: it
operates solely through the interface and normalized domain types. Adding a
new provider requires no changes to the workflow, REST, or UI layers.