# Threat model

CampaignBridge stores provider credentials and can create or send public email campaigns. The primary assets are credentials, unpublished content, audience identifiers, remote campaign IDs, and authorization to send.

## Trust boundaries

- Browser → WordPress admin/REST: require authentication, a CampaignBridge capability, schema validation, and CSRF protection for mutations.
- WordPress → provider API: allow only adapter-constructed HTTPS origins; redact authorization headers, credentials, and provider response bodies from logs.
- Database → application: credentials use authenticated, versioned ciphertext. Plaintext is accepted only by the explicit one-time migration.
- Reverse proxy → WordPress: forwarded address headers are untrusted unless a site-owned filter validates the proxy hop.
- Source repository → release ZIP: dependencies are locked and audited; Actions are SHA-pinned; the ZIP is built from an allowlist and checked after creation.

## Required controls and tests

| Threat | Control | Regression proof |
|---|---|---|
| Plaintext provider key | `Credential_Migrator` plus encrypted form field | `Credential_Migrator_Test` |
| Key rotation destroys old ciphertext | Retired-key ring and envelope key IDs | `Encryption_Test::test_key_rotation` |
| Wrong Mailchimp region | Data center parsed from validated key | `Mailchimp_Provider_Test` |
| Duplicate campaign creation after 5xx | POST/PATCH retries disabled by default | `Http_Client_Retry_Test` |
| Spoofed client IP bypasses throttling | `REMOTE_ADDR` default, trusted filter opt-in | `Rate_Limiter_Test` |
| Compromised moving Action tag | Full-SHA workflow pins | `bin/ci/check-action-pins.sh` |
| Development files or secrets ship | Allowlist package and archive verification | `bin/release/verify-package.sh` |

## Accepted limitations

The local fallback encryption key is stored in WordPress options so installations work without external secret infrastructure. Authenticated encryption still prevents undetected ciphertext modification, but a database-only compromise exposes both key and ciphertext. External key injection or a managed KMS remains a production-hardening milestone.

Campaign sending is still synchronous. A durable idempotent send workflow and append-only audit log are required before high-volume or multi-operator sending is considered enterprise-ready.
