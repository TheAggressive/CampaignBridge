# Security

CampaignBridge stores email-provider credentials and can create or send campaigns through third-party APIs. Credential disclosure, unauthorized campaign actions, remote-request forgery, and duplicate sends are security-sensitive issues.

## Reporting a vulnerability

Email security@aggressivenetwork.com with reproduction details. Do not open a public issue for an exploitable vulnerability. An acknowledgement should be sent within two working days.

## Supported versions

Security fixes are provided for the latest stable release. Upgrade to the latest release before requesting a backport.

## Security contracts

- Administrative routes and mutations require a specific CampaignBridge capability and CSRF protection.
- Provider credentials are encrypted before persistence and redacted from responses and logs. Production sites should supply the encryption key from outside the database with `CAMPAIGNBRIDGE_ENCRYPTION_KEY` (see `docs/runbook.md`).
- Decryption fails closed, including for a defined but malformed external key. Values that are not encrypted envelopes are refused rather than read as plaintext.
- Outbound requests reach only the HTTPS origin each integration declares in code, and never follow redirects.
- Remote mutations are retried only when the operation is demonstrably idempotent. A remote draft is re-asserted and verified before every test or schedule.
- Security-relevant rate limits are claimed atomically and fail closed.
- Forwarded client-address headers are ignored unless a trusted proxy integration supplies the address.
- Transport security headers such as HSTS are the site owner's responsibility; CampaignBridge does not set them.
- Release artifacts are built from an allowlist and verified from the ZIP users install.

The detailed trust boundaries and required regression tests are documented in `docs/threat-model.md`.
