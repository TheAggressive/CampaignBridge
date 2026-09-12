# ADR 0002: Provider extension contract

- Status: Accepted
- Date: 2026-09-11
- Issue: Milestone 0

## Context

CampaignBridge supports multiple email service providers (Mailchimp, SendGrid,
future providers). Each provider must implement a consistent surface for
connection verification, credential validation, rate-limit policy, and
capability advertisement. Without a documented contract, providers drift
in error reporting, redaction behavior, and capability semantics.

The existing `Provider_Interface` and `Abstract_Provider` already define the
method surface. This ADR locks the behavioral contract so new providers and
future provider features can be added without re-architecting the workflow
layer.

## Decision

A provider is a self-contained adapter that:

1. **Identifies itself** with a stable `slug()` and a human-readable `label()`.
   The slug is the only identifier used in storage, REST routes, and logs.

2. **Validates its own configuration** via `is_configured()`. The workflow
   layer never inspects provider-specific settings directly.

3. **Verifies connectivity** via `verify_connection()`, which returns
   normalized account details or a `\WP_Error`. The error message must be
   operator-safe and must not leak credentials or raw provider payloads.

4. **Normalizes failures** into `Provider_Error` values using the
   `Provider_Error_Category` taxonomy. Providers must not return
   provider-specific error strings to the workflow layer. The category
   determines retryability: `rate_limited`, `timeout`, `network`, and
   `unknown` are retryable; `authentication`, `authorization`, `validation`,
   `not_found`, `conflict`, and `provider_error` are not.

5. **Advertises capabilities** via `get_capabilities()`. Keys represent
   working CampaignBridge operations (e.g. `audiences`, `templates`,
   `scheduling`), not theoretical features of the remote API. The workflow
   layer gates UI and operations on these keys.

6. **Declares a rate-limit policy** via `rate_limit_policy()`, returning a
   bucket name and a maximum-per-minute limit. The HTTP client enforces this
   policy; providers do not implement their own throttling.

7. **Redacts sensitive settings** via `redact_settings()`. The redacted form
   is the only form that may appear in logs, REST responses, or admin UI.

8. **Sanitizes settings** via `sanitize_settings()` according to its own
   `settings_schema()`. The schema is the single source of truth for field
   names, types, and validation rules.

9. **Provides an API key pattern** via `get_api_key_pattern()` for use during
   configuration and credential migration.

The `Abstract_Provider` base class supplies shared behavior (HTTP client
access, error normalization helpers, and rate-limit policy defaults).
Concrete providers extend it and override only the methods that differ.

## Ownership boundaries

- `Provider_Interface` defines the contract; it contains no implementation.
- `Abstract_Provider` supplies shared infrastructure (HTTP client, error
  mapping, policy defaults) but no provider-specific logic.
- Concrete providers (e.g. `Mailchimp_Provider`) implement the remote API
  calls and map responses to normalized types.
- The workflow layer consumes `Provider_Error` values and capability arrays;
  it never inspects provider-specific response structures.
- `Capabilities` (Core) is the single source of truth for WordPress
  capability names. Providers do not define or check capabilities.

## Versioning and migration

The `Provider_Interface` method signatures are a versioned contract. Adding
a new method requires a default implementation in `Abstract_Provider` so
existing providers continue to work. Removing or changing a method signature
requires a new major version of the plugin.

## Consequences

New providers must implement every method in `Provider_Interface` and extend
`Abstract_Provider`. The workflow layer is provider-agnostic: it operates
solely through the interface and normalized types. Adding a new provider
requires no changes to the workflow, REST, or UI layers.