# CampaignBridge developer guide

Start with the [architecture](architecture.md), [testing strategy](testing-strategy.md), [threat model](threat-model.md), and [build/release contract](build-and-release.md).

Use WordPress Studio for the local site, as in the other Aggressive Network repositories. CampaignBridge does not create or manage a WordPress development site.

Install locked dependencies with `composer install` and `pnpm install --frozen-lockfile`. PHP tests require local MySQL server/client binaries; `pnpm test` starts an isolated server on port `13308` and downloads the checksum-verified WordPress core configured by the test harness into `.cache/tests`. Run `pnpm qa` for the complete gate. Production packages are created with `pnpm release:package` and checked with `pnpm release:verify`.

New provider integrations must implement `Provider_Interface`, keep provider response shapes inside the adapter, redact secrets, derive only allowlisted HTTPS origins, and define operation-level idempotency before enabling retries.
