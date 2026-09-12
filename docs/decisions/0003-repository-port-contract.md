# ADR 0003: Repository port contract

- Status: Accepted
- Date: 2026-09-11
- Issue: Milestone 0

## Context

CampaignBridge domain objects (Brand Kit, Campaign, future DTOs) need
persistence. The domain layer must remain pure PHP with no WordPress
function calls. WordPress storage (options, transients, post meta, custom
tables) belongs in a separate repository layer. Without a documented port
pattern, domain code risks calling WordPress functions directly, coupling
the domain to a specific storage mechanism, and making unit testing
impossible.

The existing `Brand_Kit_Source` interface and `Brand_Kit_Repository`
implementation already demonstrate the intended pattern. This ADR locks it
as the canonical approach for all future persistence ports.

## Decision

Every domain object that requires persistence has a corresponding **port
interface** in the `Domain/` namespace and a **concrete repository
implementation** in the `Repository/` namespace.

### Naming convention

| Element | Pattern | Example |
|---------|---------|---------|
| Port interface | `<Name>_Source` | `Brand_Kit_Source` |
| Repository impl | `<Name>_Repository` | `Brand_Kit_Repository` |
| Domain DTO | `<Name>` | `Brand_Kit` |
| Port namespace | `CampaignBridge\Domain\<Area>` | `CampaignBridge\Domain\Email` |
| Impl namespace | `CampaignBridge\Repository` | `CampaignBridge\Repository` |

### Port rules

1. **Ports are interfaces** with no implementation. They declare the
   read/write surface the domain needs.
2. **Ports use domain types** in their signatures. A port never returns raw
   arrays, `\WP_Post`, or WordPress-specific types.
3. **Ports are minimal.** Each port exposes only the operations the domain
   actually consumes. No speculative CRUD methods.
4. **Ports are synchronous.** Async or batch operations are separate methods,
   not hidden behind a generic `execute()`.

### Repository rules

1. **Repositories are the only layer that calls WordPress storage functions**
   (`get_option`, `update_option`, `wp_insert_post`, etc.).
2. **Repositories validate and sanitize on write.** The domain DTO is
   already validated; the repository enforces storage-level constraints
   (size limits, allowed values, schema version).
3. **Repositories return domain DTOs on read.** They deserialize stored
   data into the domain type. Tolerant reads: missing or legacy fields are
   normalized to defaults rather than throwing.
4. **Repositories are idempotent.** Calling `save()` twice with the same
   DTO produces the same stored state.
5. **Repositories never mutate the DTO.** They receive immutable value
   objects.

### Storage enforcement

The `_Storage_Enforcement` test scans all PHP files outside `Repository/`
and `Core/` for direct calls to WordPress storage functions. Any violation
is a test failure. This enforces the boundary mechanically.

## Ownership boundaries

- `Domain/` contains DTOs, ports, and pure business logic. No WordPress
  functions.
- `Repository/` contains WordPress-specific persistence. No business rules.
- `Core/` contains cross-cutting infrastructure (capabilities, encryption,
  autoloader) that may call WordPress functions.
- `Providers/` contains remote API adapters. No WordPress storage.
- `REST/` and `Admin/` are presentation layers. They call domain and
  repository code but never call storage functions directly.

## Versioning and migration

Stored representations are versioned contracts. The repository is
responsible for:

- **Tolerant reads:** legacy or missing fields are normalized to defaults.
- **Strict writes:** the current schema version is stamped on every write.
- **Migration:** when the schema changes, the repository includes a
  deterministic migration path from the previous version.
- **Rejection of future versions:** an unknown or higher schema version is
  rejected with a clear error rather than silently misinterpreted.

## Consequences

Adding a new domain object with persistence requires:

1. A DTO in `Domain/<Area>/`.
2. A `<Name>_Source` interface in `Domain/<Area>/`.
3. A `<Name>_Repository` in `Repository/`.
4. Unit tests for the DTO and port contract.
5. Integration tests for the repository (WordPress environment).

The domain layer remains testable without WordPress. The repository layer
is testable with a WordPress test environment. No layer depends on another
in the wrong direction.