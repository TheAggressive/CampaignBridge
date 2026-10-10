# Operations runbook

## Checking site health

**CampaignBridge → Status → CampaignBridge Health** reads stored state only:
whether the database tables are current, whether Mailchimp is connected and
when the connection was last verified, whether a default audience is chosen,
the number of published templates, and campaign counts by state. When any
campaign needs reconciliation, a warning links to the Campaigns screen; open
each such campaign and reconcile it before acting on it (see **Duplicate or
uncertain remote campaign** below).

Viewing **Settings → Providers** checks the stored Mailchimp key with
Mailchimp's read-only ping (at most once every five minutes per key) and
records the answer: verified, or refused by Mailchimp. A timeout or network
failure proves nothing, so the previous result stands. "Not checked yet" in
Status means nobody has opened the Providers tab since the key was saved.

## Background jobs not running

**Status → CampaignBridge Health** warns when background jobs are overdue,
when a worker stopped while holding a job, or when the job tick is not
scheduled. CampaignBridge runs jobs on a one-minute WP-Cron tick, and WP-Cron
runs only when the site receives visits or a system cron requests
`wp-cron.php`. On a site with `DISABLE_WP_CRON`, add a system cron that calls
`wp cron event run --due-now` (or requests `wp-cron.php`) every minute. A job
whose worker stopped is taken over automatically after its two-minute lease;
work that is not safe to repeat is never run again and is handed to
reconciliation instead.

## Provider authentication failures

1. Confirm the configured API key has the expected Mailchimp data-center suffix.
2. Use the settings connection check without logging the credential or Authorization header.
3. Confirm outbound HTTPS and DNS access to `<dc>.api.mailchimp.com`.
4. Rotate the provider credential if disclosure is suspected. Do not rotate the local encryption key as a substitute for rotating the provider key.

## Disconnecting Mailchimp

**Settings → Providers → Disconnect** removes the stored API key and
connection details from this site and switches delivery back to HTML Email.
It requires `campaignbridge_manage_connections`.

- It does not revoke the key. If the key may be compromised, also delete it
  in Mailchimp (**Profile → Extras → API keys**).
- Campaigns Mailchimp already holds are untouched. A scheduled campaign
  still sends, and CampaignBridge cannot unschedule or reconcile it until a
  key for the same account is saved again. Unschedule anything that should
  not send first.

## Encryption key configuration

By default the credential encryption key is generated and stored in WordPress
options, so a database-only compromise exposes it with the ciphertext. Keep
the key outside the database on production sites.

### Configure an external key

1. Generate 32 random bytes, base64-encoded: `openssl rand -base64 32`.
2. Store the value in the host's secret store or environment, and back it up
   with the same care as the database. Losing it makes every credential
   encrypted with it unreadable.
3. In `wp-config.php`, define the canonical constant before WordPress loads:

   ```php
   define( 'CAMPAIGNBRIDGE_ENCRYPTION_KEY', getenv( 'CAMPAIGNBRIDGE_ENCRYPTION_KEY' ) );
   ```

   The constant must be the base64 encoding of exactly 32 bytes. If it is
   defined but empty or malformed (for example, a missing environment
   variable), CampaignBridge refuses to encrypt or decrypt any credential
   rather than fall back to the database key.
4. Open **CampaignBridge → Status**. **Credential Encryption** shows the key
   source and whether the Mailchimp credential uses the current key.
5. Existing credentials stay readable through the database key. To move the
   credential to the external key, open **Settings → Providers** as a user
   who can manage connections and save. The credential is re-encrypted,
   verified, and only then stored; the database key is not deleted.
6. Once Status reports the credential as protected by the current key, the
   database fallback key protects nothing current. It may be removed with
   `wp option delete campaignbridge_master_key campaignbridge_retired_encryption_keys campaignbridge_key_metadata`.
   Keep it while database backups that predate re-encryption must remain
   restorable.

### Rotate an external key

1. Generate a new key. Set it as `CAMPAIGNBRIDGE_ENCRYPTION_KEY` and move the
   old key to `CAMPAIGNBRIDGE_ENCRYPTION_RETIRED_KEYS` (a comma-separated list
   in the same format), which is used only for decryption.
2. Save **Settings → Providers** to re-encrypt the credential, and confirm in
   Status.
3. Remove the old key from the retired list.

### Roll back to the database key

1. Move the external key from `CAMPAIGNBRIDGE_ENCRYPTION_KEY` to
   `CAMPAIGNBRIDGE_ENCRYPTION_RETIRED_KEYS`. The database key becomes current
   again (it is created if it was removed).
2. Save **Settings → Providers**, then confirm in Status before removing the
   retired constant.

Removing an external key without retiring it makes credentials encrypted
with it unreadable; restore the constant or re-enter the provider API key.

## Encryption failures

1. Preserve the database and configuration before changing keys.
2. Check **CampaignBridge → Status → Credential Encryption**. "Invalid" means
   `CAMPAIGNBRIDGE_ENCRYPTION_KEY` is defined but is not the base64 encoding
   of 32 bytes. "Cannot be decrypted with the configured keys" means the key
   that encrypted the credential is no longer configured: restore it as a
   retired key, or re-enter the provider API key.
3. Do not delete `campaignbridge_key_metadata` or the retired-key option during
   recovery.
4. Confirm OpenSSL and AES-256-GCM support through the plugin security check.
5. Never replace an unreadable value with plaintext.

## Requests refused with `503 rate_limit_unavailable`

Rate limits are counted in the `{prefix}campaignbridge_rate_limits` table and
fail closed. A 503 means the CampaignBridge schema migration has not
completed. Load any wp-admin page to retry it, and confirm the database user
can create tables.

## Duplicate or uncertain remote campaign

1. Stop manual retries.
2. Search the provider by the local campaign title/time and record the remote campaign ID.
3. Reconcile content and send state before resuming.
4. Preserve correlation IDs and sanitized error logs for incident review.

### Draft handoff returned `reconciliation_required`

The provider did not confirm whether a draft was created. CampaignBridge will
not create another draft for that campaign until this is resolved, whatever
idempotency key is sent.

1. Do not retry. Note the `attempt.id` from the error response.
2. Call `POST /campaigns/{id}/reconcile`. It searches Mailchimp for a draft
   titled `CampaignBridge <attempt id>`.
   - Found: the draft is recorded. Repeat `/provider-draft` to finish the
     handoff; no second draft is created.
   - Not found: the attempt is settled as `failed` once the search is
     complete and 5 minutes have passed. Create the draft again with a new
     key.
   - "Still in progress" or "absence cannot be confirmed yet": wait and
     reconcile again.
   - "Holds N drafts for this request": delete the extra drafts in
     Mailchimp, keeping one, then reconcile again.

### Test send returned `reconciliation_required`

The provider did not confirm whether a test was delivered. CampaignBridge
will not resend it with the same idempotency key.

1. Do not retry with the same key. Note the `attempt.id` from the error
   response.
2. Check the test inboxes. If the test arrived, nothing more is needed.
3. If it did not arrive, send another test with a new key. Unconfirmed tests
   do not block further tests.

### Test send returned `rate_limited`

The campaign has sent 10 tests in the last 24 hours. The quota is per
campaign and rolling, so it frees up as the earliest tests age out. A `502
provider_failed` with `mailchimp_request_rejected` is different: Mailchimp
refused the addresses or its own account test-email limit was reached.

### Schedule, unschedule, or send returned `reconciliation_required`

The provider did not confirm the request, so the campaign may or may not
send, or may already have sent. The campaign is now `unknown`, and CampaignBridge refuses every
further schedule, unschedule, or send for it, whatever key is sent.

1. Do not retry. Open the campaign in **CampaignBridge → Campaigns** and use
   **Reconcile** in its Delivery panel (or call
   `POST /campaigns/{id}/reconcile`). It reads the
   campaign in Mailchimp and follows it: scheduled (with Mailchimp's send
   time), back to `provider_draft`, sending, or sent. The unconfirmed
   attempt is settled from that evidence and delivery is unblocked.
2. If the campaign is now `scheduled` and should not send, unschedule it
   before its send time.
3. If reconciliation still returns `reconciliation_required`, the Delivery
   panel shows the next step for its `reason` (and counts down an
   `in_progress` wait before Reconcile is offered again). By message:
   - "still in progress": wait the stated time and reconcile again.
   - "no longer has this campaign": it was deleted in Mailchimp. Confirm in
     Mailchimp's campaign list and reports that nothing was sent.
   - "status CampaignBridge does not track" (for example a cancellation in progress) or
     "scheduled but not when": resolve it in Mailchimp, then reconcile
     again.
   - "contradicts its local state": the provider disagrees with a settled
     campaign, such as a sent campaign reported as a draft. Investigate in
     Mailchimp; CampaignBridge will not follow it.
4. Audit events for `campaign_reconcile` record what Mailchimp reported and
   what changed; the campaign's History section shows them with the
   delivery attempts. `unexplained: true` ("Changed in the provider without
   a CampaignBridge request") means Mailchimp changed state without a
   CampaignBridge request, for example a campaign scheduled there directly.

### Test or schedule refused because the provider draft changed

CampaignBridge re-asserts the approved draft before every test or schedule.
It refuses when the provider reports the campaign as scheduled, sent, or
otherwise not an unsent draft (`409 reconciliation_required`), or when a
segment narrows the approved audience. It also refuses when the draft could
not be read or updated (`502 provider_failed`). Nothing was delivered and no
attempt was recorded.

1. Open the campaign in Mailchimp by its `remote_id`.
2. If it was scheduled or sent there, it was changed outside CampaignBridge.
   Unschedule it there if it should not send, then reconcile the campaign
   (`POST /campaigns/{id}/reconcile`).
3. If a segment was added, remove it, or create a new campaign with the
   intended audience in CampaignBridge.
4. Retry the request. Any content or settings edited in Mailchimp are
   overwritten with the approved values.

### Delivery refused by a policy

- **"Separation of duties is required"** (`403`): the person scheduling
  approved the campaign. Ask another person with delivery authority. If the
  message says the campaign was approved before approvers were recorded,
  independence cannot be verified. A manager decides: duplicate the
  campaign and have it approved again, or deliver it with the policy
  temporarily off.
- **"Test recipients must use an allowed domain"** (`400`): use an address
  on an allowed domain, or ask a manager to update
  **Settings → Policies**. "No valid domain is configured" means the policy
  was saved with entries that are not domains, so all tests are blocked
  until it is corrected.

## Release procedure

1. Run the complete quality pipeline.
2. Build assets from a clean `dist/`.
3. Run `pnpm release:package` and `pnpm release:verify`.
4. Trigger the CI workflow manually with `publish=true` from `main` or `master`.
5. Install the resulting ZIP in a clean WordPress environment and perform an activation smoke test.

## Rollback

Reinstall the last known-good ZIP. Restore data only when a versioned migration changed persisted state. Credential schema migrations are idempotent and key rotation retains previous decrypt keys, so normal code rollback does not require ciphertext rollback. A credential re-encrypted under `CAMPAIGNBRIDGE_ENCRYPTION_KEY` cannot be read by a release that predates external key support; roll back the key first (see above) before installing such a release.

### Rolling back across database schema 6

Schema 6 adds `sequence_number`, an auto-increment insertion order, to
`{prefix}campaignbridge_audit_events` so campaign history keeps write order
within one second. No existing value changes. A release built for schema 5
sees version 6 as newer than it knows and refuses campaign storage rather
than guess. Because version 5 code never reads the new column and its inserts
still number themselves, it is safe to lower the stamped version after
reinstalling that release:

```bash
wp option update campaignbridge_database_schema 5
```

Upgrading again later re-runs the migration, which finds the column already
present and only re-stamps version 6.

### Rolling back across database schema 7

Schema 7 adds the `{prefix}campaignbridge_jobs` table and changes no existing
data. A release built for schema 6 never reads it, so after reinstalling that
release lower the stamped version with
`wp option update campaignbridge_database_schema 6`. Queued jobs stay in the
table and run again once the newer release is reinstalled.
