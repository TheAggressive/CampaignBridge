# Architecture

CampaignBridge is moving toward four enforceable layers:

| Layer | Responsibility | May depend on |
|---|---|---|
| Domain | Email composition rules and provider-neutral value objects | Pure PHP only |
| Repository | WordPress options, metadata, posts, cache, and campaign custom-table persistence | Domain ports and WordPress data APIs |
| Workflow | Credential migration, campaign creation, reconciliation, and sending | Domain and application services |
| Delivery | Admin screens, REST controllers, blocks, and provider adapters | Workflow and query interfaces |

The desired dependency direction is Delivery → Workflow → Domain ← Repository.
Repository implements Domain ports consumed through dependency injection; it
must never depend on Workflow. The repository-boundary CI guard enforces that
rule as well as the existing persistence boundary. When a use case needs both
stored WordPress input and Workflow behavior, Repository returns a typed Domain
value and a Workflow coordinator performs the application-level composition.
New direct WordPress data access outside Repository/Core Storage is prohibited.

The M2 campaign storage ports, five site-local tables, migration policy, and
data-minimization rules are documented in
[`campaign-persistence.md`](campaign-persistence.md). The canonical
provider-neutral application operations, state/concurrency rules, authorization
inputs, and transaction assumptions are documented in
[`campaign-workflows.md`](campaign-workflows.md). The campaign REST adapter
(`includes/REST/Campaign_Routes.php`, see [`api.md`](api.md)) calls only that
workflow layer. The repository boundary check rejects campaign REST files that
import repositories or providers. Future Abilities, CLI, and UI adapters must
call the same workflow layer rather than repositories or REST controller
internals.

## Composition root

`campaignbridge.php` validates the runtime and hands off to `CampaignBridge\Plugin`. `Plugin` owns initialization order. Service registration must not cause behavior; hooks and migrations begin only during explicit initialization.

## Provider boundary

Provider adapters receive already-validated, decrypted settings for the duration of one operation. They must not persist credentials, render raw provider errors, or retry non-idempotent mutations. Provider-specific identifiers and response shapes stay inside the adapter.

## Build boundary

Authored frontend code lives under `src/`; generated assets live under `dist/`. Every build deletes `dist/` first so removed source cannot survive as a stale production asset. The release ZIP is constructed from an allowlist and verified independently.
