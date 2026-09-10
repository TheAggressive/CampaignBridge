# CampaignBridge developer guide

Start with the [architecture](architecture.md), [testing strategy](testing-strategy.md), [threat model](threat-model.md), and [build/release contract](build-and-release.md).

Use WordPress Studio for the local site, as in the other Aggressive Network repositories. CampaignBridge does not create or manage a WordPress development site.

Install locked dependencies with `composer install` and `pnpm install --frozen-lockfile`. PHP tests require local MySQL server/client binaries; `pnpm test` starts an isolated server on port `13308` and downloads the checksum-verified WordPress core configured by the test harness into `.cache/tests`. Run `pnpm qa` for the complete gate. Production packages are created with `pnpm release:package` and checked with `pnpm release:verify`.

The current `Provider_Interface` covers metadata, settings validation, and discovery. Future delivery adapters must receive compiled artifacts, keep provider response shapes inside the adapter, redact secrets, derive only allowlisted HTTPS origins, and define operation-level idempotency before enabling retries. The unused section-array send API has been removed.

The package-script tests check literal source/tool entrypoints and matching webpack
configs for build/watch. They do not prove PHP class reachability: WordPress hooks,
autoloading, and screen discovery require runtime wiring tests and consumer searches.

## Google Fonts lookup

The Brand screen searches the versioned Google Fonts catalogue bundled with the
plugin, so installations do not need a Google API key and catalogue search does
not depend on a remote request. Refresh the snapshot from Google's official font
repository with `pnpm build:font-catalog` before a release that updates it.
Use `pnpm build:font-catalog --latest` to resolve the latest upstream commit;
the committed provenance file pins that immutable revision and records the
catalog checksum. Large catalog-count changes fail closed for manual review.

An exact family that is newer than the snapshot is validated against Google's
fixed CSS2 endpoint. These bounded validation requests use no API key, are cached,
and cannot target a user-supplied host. Selected fonts retain email-safe fallback
stacks because web-font support varies across email clients.

Sites that prohibit external font requests can disable resolution and loading:

```php
add_filter( 'campaignbridge_external_google_fonts_enabled', '__return_false' );
```

Custom Google Fonts intentionally load at most weights 400, 600, and 700 when
those variants exist in the bundled catalogue. This covers normal, semibold,
and bold email typography without requesting every published family variant;
newly validated families fall back to weight 400 until the catalogue includes
their variant metadata.
