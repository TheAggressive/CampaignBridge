# CampaignBridge developer guide

Start with the [architecture](architecture.md), [testing strategy](testing-strategy.md), [threat model](threat-model.md), and [build/release contract](build-and-release.md).

Use WordPress Studio for the local site, as in the other Aggressive Network repositories. CampaignBridge does not create or manage a WordPress development site.

Install locked dependencies with `composer install` and `pnpm install --frozen-lockfile`. PHP tests require local MySQL server/client binaries; `pnpm test` starts an isolated server on port `13308` and downloads the checksum-verified WordPress core configured by the test harness into `.cache/tests`. Run `pnpm qa` for the complete gate. Production packages are created with `pnpm release:package` and checked with `pnpm release:verify`.

The current `Provider_Interface` covers metadata, settings validation, and discovery. Future delivery adapters must receive compiled artifacts, keep provider response shapes inside the adapter, redact secrets, derive only allowlisted HTTPS origins, and define operation-level idempotency before enabling retries. The unused section-array send API has been removed.

The package-script tests check literal source/tool entrypoints and matching webpack
configs for build/watch. They do not prove PHP class reachability: WordPress hooks,
autoloading, and screen discovery require runtime wiring tests and consumer searches.

## Google Fonts lookup

The Brand screen can search the official Google Web Fonts API when its server-side
API key is configured in `wp-config.php`:

```php
define( 'CAMPAIGNBRIDGE_GOOGLE_FONTS_API_KEY', 'your-restricted-api-key' );
```

Restrict the key to the Web Fonts Developer API and to the server addresses that
host WordPress. CampaignBridge sends the key in a request header, caches the
catalogue for one day, and never exposes the key to browser JavaScript. A deployment
may supply the key from a secrets manager with the
`campaignbridge_google_fonts_api_key` filter instead.
