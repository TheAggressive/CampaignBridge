# Threat model

CampaignBridge stores provider credentials and can create, test, and schedule
public email campaigns. The primary assets are credentials, unpublished
content, audience identifiers, remote campaign IDs, and authorization to
deliver.

## Current delivery boundary (M3)

| Capability | Status |
|---|---|
| Mailchimp discovery (audiences, merge fields, segments, senders) | Shipped |
| Remote draft handoff from the approved artifact | Shipped, idempotent |
| Test delivery to 1–5 named addresses | Shipped, guarded |
| Schedule and unschedule | Shipped, guarded |
| Immediate send | Not implemented; the adapter reports `send` as unsupported (#79) |
| Cancel | Not implemented (#79) |
| Remote-state reconciliation and ambiguous-outcome recovery | Not implemented; `unknown` outcomes need manual resolution (#80) |

## Trust boundaries

- Browser → WordPress admin/REST: require authentication, a specific
  CampaignBridge capability in every `permission_callback`, schema validation,
  and CSRF protection for mutations. Nonces are never a substitute for
  capabilities.
- WordPress → provider API: every request declares an `Http_Origin` fixed in
  the adapter's code, and `Http_Client` refuses any URL outside it before any
  network activity. Only literal `https://<host>/…` URLs without user
  information, ports, fragments, or `@` are accepted. Redirects are never
  followed. Logs carry only scheme, host, and path; never query strings,
  Authorization headers, credentials, or provider response bodies.
- Database → application: credentials use authenticated, versioned
  ciphertext. Values that are not envelopes are refused, never decrypted as
  plaintext.
- Host configuration → application: an external encryption key in
  `wp-config.php` is trusted. A defined but malformed key fails closed.
- Reverse proxy → WordPress: forwarded address headers are untrusted unless a
  site-owned filter validates the proxy hop.
- Source repository → release ZIP: dependencies are locked and audited;
  Actions are SHA-pinned; the ZIP is built from an allowlist and checked after
  creation.

## Required controls and tests

| Threat | Control | Regression proof |
|---|---|---|
| Plaintext provider key at rest | Credentials are encrypted before persistence; non-envelope values fail closed | `Encryption_Test`, `Settings_Persistence_Test` |
| Database-only compromise exposes key and ciphertext | Optional external key (`CAMPAIGNBRIDGE_ENCRYPTION_KEY`) that is never stored in the database; deliberate re-encryption on provider save | `Encryption_Keyring_Test`, `Encryption_Key_Constant_Test`, `Settings_Persistence_Test` |
| Key change makes credentials unreadable | Envelope key IDs, retired database and external keys, re-encryption verified before write, previous key never deleted by the plugin | `Encryption_Keyring_Test`, `Encryption_Test::test_key_rotation` |
| Misconfigured external key silently falls back to the database key | Defined but invalid configuration refuses to encrypt or decrypt and never generates a fallback key | `Encryption_Keyring_Test` |
| Key material or credentials in logs and traces | No stack traces in decryption logs; `#[\SensitiveParameter]` on key, plaintext, and credential arguments; keyrings redact debug output and refuse serialization | `Encryption_Keyring_Test` |
| Wrong Mailchimp region | Data center parsed from the validated key | `Mailchimp_Provider_Test` |
| Provider credential sent to an unexpected host (SSRF/open egress) | Mandatory per-request trusted origin; Mailchimp limited to `<dc>.api.mailchimp.com`, Google Fonts to `fonts.googleapis.com`; refusal is a definite, non-ambiguous failure | `Outbound_Origin_Test`, gateway tests whose fakes enforce the origin |
| Credential forwarded by a redirect | Redirects forced off for every request | `Outbound_Origin_Test`, `Http_Client_Retry_Test` |
| Duplicate campaign creation after 5xx | POST/PATCH retries disabled by default | `Http_Client_Retry_Test` |
| One person approves and delivers alone | Opt-in separation of duties on the recorded approver; manager-only policy settings | `Campaign_Scheduler_Test`, `Campaign_Schedule_Route_Test`, `Admin_Form_Screens_Test` |
| Test sends leak content outside the organization | Opt-in exact-domain allowlist that fails closed when misconfigured | `Campaign_Test_Delivery_Test`, `Delivery_Policy_Test` |
| Test send used to mail arbitrary addresses | Separate test capability, 1–5 recipients, atomic per-user limits, durable per-campaign quota decided again after the attempt is stored | `Campaign_Test_Delivery_Test`, `Campaign_Routes_Security_Test` |
| Concurrent requests exceed a security limit | Rate limits are claimed with one conditional database update per fixed window; the limiter fails closed when it cannot count | `Rate_Limit_Repository_Test` |
| Remote draft drift or tampering (see below) | `Campaign_Remote_Draft_Guard` before every test or schedule | `Campaign_Scheduler_Test`, `Campaign_Draft_Handoff_Test`, `Campaign_Schedule_Route_Test`, `Campaign_Test_Delivery_Test` |
| Duplicate or mistargeted audience delivery | Audience confirmation, version claim before contact, one unresolved delivery attempt blocks all others, no automatic retry | `Campaign_Scheduler_Test`, `Campaign_Schedule_Route_Test` |
| Test recipients retained as personal data | Recipients used for one call; only counts recorded | `Campaign_Test_Delivery_Test` |
| Spoofed client IP bypasses throttling | `REMOTE_ADDR` default, trusted filter opt-in | `Rate_Limiter_Test` |
| Compromised moving Action tag | Full-SHA workflow pins | `bin/ci/check-action-pins.sh` |
| Development files or secrets ship | Allowlist package and archive verification | `bin/release/verify-package.sh` |

## Remote draft drift and tampering

Approval covers the local snapshot, but delivery happens from a draft held by
the provider. That draft can diverge from what was approved:

- it is edited in Mailchimp after approval (content, subject, sender);
- approval is revoked and given again, and the draft still carries the old
  audience, envelope, or content;
- it is scheduled or sent from Mailchimp, outside CampaignBridge;
- a segment is added that narrows or replaces the approved audience.

Before every test and every schedule, `Campaign_Remote_Draft_Guard`:

1. reads the remote campaign and requires an unsent draft, refusing with
   `reconciliation_required` when it is scheduled, sending, sent, or
   otherwise changed outside CampaignBridge;
2. re-asserts the approved audience, envelope, and content with idempotent
   updates, overwriting edits made in the provider;
3. for operations that reach the audience, reads the draft back and requires
   exactly the approved list with no segment.

Every guard call is a read or an idempotent update, so a refusal reaches no
one and leaves nothing to reconcile. Any doubt fails closed before the
delivery call.

## Rate limiting and delivery concurrency

Two separate mechanisms exist, and neither replaces the other.

- `Rate_Limiter` bounds request volume per user (or per trusted client
  address for anonymous requests) in fixed 60-second windows. Each request is
  admitted by one conditional `UPDATE … WHERE hits < maximum` on a site-local
  counter table, so concurrent requests cannot exceed the maximum, and
  counters do not depend on a persistent object cache. When the counter table
  is unavailable the request is refused with `503 rate_limit_unavailable`.
  The security-relevant limits are credential reveal (10/min), credential
  encryption (20/min), and provider-calling campaign operations (10/min).
  Fixed windows allow up to twice a limit across a window boundary.
- Delivery correctness belongs to the campaign workflows: durable delivery
  attempts with unique idempotency keys, a campaign version claim in the same
  transaction as the attempt, and `unknown` outcomes that block further
  delivery until reconciled. The per-campaign test quota is a durable count
  of stored attempts, checked again after this request's attempt is written.

## Response headers

WordPress core sends `X-Frame-Options: SAMEORIGIN`, `Content-Security-Policy:
frame-ancestors 'self'`, `Referrer-Policy: strict-origin-when-cross-origin`,
and no-cache headers on admin screens, and `nosniff` on REST and admin-ajax
responses. CampaignBridge does not override them and sends no site-wide
transport policy: HSTS, its `includeSubDomains` and `preload` directives, and
any site-wide Content-Security-Policy belong to the site owner's server or
CDN. CampaignBridge's own isolation boundary is the sandboxed, script-free
email preview iframe, and campaign and discovery REST responses send
`Cache-Control: no-store`.

## Accepted limitations

- Without `CAMPAIGNBRIDGE_ENCRYPTION_KEY`, the fallback key is stored in
  WordPress options so installations work without external secret
  infrastructure. Authenticated encryption still prevents undetected
  ciphertext modification, but a database-only compromise then exposes both
  key and ciphertext. Production sites should configure the external key (see
  the runbook). Database backups taken before re-encryption remain decryptable
  with the fallback key they contain.
- An attacker who can run PHP inside WordPress, or read `wp-config.php` and
  the database together, can recover credentials regardless of key source.
  KMS integration is not implemented; the keyring resolves key IDs, so it can
  be added without changing the ciphertext format.
- The outbound origin policy is a destination allowlist for fixed public
  APIs. It does not inspect DNS answers. An integration whose host comes from
  site data needs a separately reviewed boundary.
- Immediate send and cancel are not implemented. Before immediate send ships
  it must use the same version claim, single-unresolved-attempt rule, remote
  draft guard, and `unknown`-on-ambiguity protocol as scheduling.
- Reconciliation is manual. An `unknown` schedule or test outcome blocks the
  affected operation until a developer resolves the attempt; automated
  reconciliation, durable jobs, provider webhooks, and recovery are tracked in
  #80 and M5 (#66). Until then, high-volume or multi-operator sending is not
  considered production-ready.
