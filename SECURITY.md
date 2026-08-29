# Security

CampaignBridge stores email-provider credentials and can create or send campaigns through third-party APIs. Credential disclosure, unauthorized campaign actions, remote-request forgery, and duplicate sends are security-sensitive issues.

## Reporting a vulnerability

Email security@aggressivenetwork.com with reproduction details. Do not open a public issue for an exploitable vulnerability. An acknowledgement should be sent within two working days.

## Supported versions

Security fixes are provided for the latest stable release. Upgrade to the latest release before requesting a backport.

## Security contracts

- Administrative routes and mutations require a specific CampaignBridge capability and CSRF protection.
- Provider credentials are encrypted before persistence and redacted from responses and logs.
- Decryption fails closed. Legacy plaintext values are migrated explicitly rather than accepted indefinitely.
- Remote mutations are retried only when the operation is demonstrably idempotent.
- Forwarded client-address headers are ignored unless a trusted proxy integration supplies the address.
- Release artifacts are built from an allowlist and verified from the ZIP users install.

The detailed trust boundaries and required regression tests are documented in `docs/threat-model.md`.
