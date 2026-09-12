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