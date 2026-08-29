# Architecture

CampaignBridge is moving toward four enforceable layers:

| Layer | Responsibility | May depend on |
|---|---|---|
| Domain | Email composition rules and provider-neutral value objects | Pure PHP only |
| Repository | WordPress options, metadata, posts, cache, and future job persistence | WordPress data APIs |
| Workflow | Credential migration, campaign creation, reconciliation, and sending | Domain and Repository |
| Delivery | Admin screens, REST controllers, blocks, and provider adapters | Workflow and query interfaces |

The desired dependency direction is Delivery → Workflow → Domain, with Repository implementing persistence ports required by Workflow. New direct WordPress data access outside Repository/Core Storage is prohibited; existing call sites are migrated incrementally.

## Composition root

`campaignbridge.php` validates the runtime and hands off to `CampaignBridge\Plugin`. `Plugin` owns initialization order. Service registration must not cause behavior; hooks and migrations begin only during explicit initialization.

## Provider boundary

Provider adapters receive already-validated, decrypted settings for the duration of one operation. They must not persist credentials, render raw provider errors, or retry non-idempotent mutations. Provider-specific identifiers and response shapes stay inside the adapter.

## Build boundary

Authored frontend code lives under `src/`; generated assets live under `dist/`. Every build deletes `dist/` first so removed source cannot survive as a stale production asset. The release ZIP is constructed from an allowlist and verified independently.
