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

## Release procedure

1. Run the complete quality pipeline.
2. Build assets from a clean `dist/`.
3. Run `pnpm release:package` and `pnpm release:verify`.
4. Trigger the CI workflow manually with `publish=true` from `main` or `master`.
5. Install the resulting ZIP in a clean WordPress environment and perform an activation smoke test.

## Rollback

Reinstall the last known-good ZIP. Restore data only when a versioned migration changed persisted state. Credential schema migrations are idempotent and key rotation retains previous decrypt keys, so normal code rollback does not require ciphertext rollback.
