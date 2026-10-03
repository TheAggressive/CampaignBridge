# Operations runbook

## Provider authentication failures

1. Confirm the configured API key has the expected Mailchimp data-center suffix.
2. Use the settings connection check without logging the credential or Authorization header.
3. Confirm outbound HTTPS and DNS access to `<dc>.api.mailchimp.com`.
4. Rotate the provider credential if disclosure is suspected. Do not rotate the local encryption key as a substitute for rotating the provider key.

## Encryption failures

1. Preserve the database and configuration before changing keys.
2. Check `campaignbridge_key_metadata` and the retired-key option; do not delete either during recovery.
3. Confirm OpenSSL and AES-256-GCM support through the plugin security check.
4. If migration fails, restore the backup and investigate the original stored format. Never replace an unreadable value with plaintext.

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

## Release procedure

1. Run the complete quality pipeline.
2. Build assets from a clean `dist/`.
3. Run `pnpm release:package` and `pnpm release:verify`.
4. Trigger the CI workflow manually with `publish=true` from `main` or `master`.
5. Install the resulting ZIP in a clean WordPress environment and perform an activation smoke test.

## Rollback

Reinstall the last known-good ZIP. Restore data only when a versioned migration changed persisted state. Credential schema migrations are idempotent and key rotation retains previous decrypt keys, so normal code rollback does not require ciphertext rollback.
