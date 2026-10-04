# Operations runbook

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
2. In Mailchimp, look for a draft campaign titled
   `CampaignBridge <attempt id>`.
3. If it exists, record its campaign ID for reconciliation. If it does not,
   the create did not take effect.
4. Automated reconciliation is tracked in #80. Until it ships, resolving the
   attempt requires a developer to update the attempt record.

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

### Schedule or unschedule returned `reconciliation_required`

The provider did not confirm the request, so the campaign may or may not
send. The campaign is now `unknown`, and CampaignBridge refuses every
further schedule, unschedule, or send for it, whatever key is sent.

1. Do not retry. Note the `attempt.id` and the campaign's `scheduled_for`.
2. In Mailchimp, open the campaign by its `remote.remote_id` and check
   whether it is scheduled, and for when.
3. If it is scheduled and should not send, unschedule it in Mailchimp
   before its send time.
4. Automated reconciliation is tracked in #80. Until it ships, resolving
   the attempt and the campaign state requires a developer.

### Test or schedule refused because the provider draft changed

CampaignBridge re-asserts the approved draft before every test or schedule.
It refuses when the provider reports the campaign as scheduled, sent, or
otherwise not an unsent draft (`409 reconciliation_required`), or when a
segment narrows the approved audience. It also refuses when the draft could
not be read or updated (`502 provider_failed`). Nothing was delivered and no
attempt was recorded.

1. Open the campaign in Mailchimp by its `remote_id`.
2. If it was scheduled or sent there, it was changed outside CampaignBridge.
   Unschedule it there if it should not send, and reconcile the campaign
   (#80).
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
