# CampaignBridge

CampaignBridge is a WordPress email-template editor and deterministic email
compiler under active development. It turns a constrained Gutenberg block
grammar into portable HTML and plain text and reports unsupported or invalid
content before it reaches a provider.

> **Product status:** The secure WordPress foundation, template editor,
> compiler, Brand Kit, and early provider adapters are present. Complete
> campaign, audience, delivery, scheduling, reconciliation, and reporting
> workflows are roadmap work. See [ROADMAP.md](ROADMAP.md) for the product
> contract and delivery plan.

## What is available now

- A dedicated email-template post type and standalone block editor.
- An email-native block grammar with deterministic HTML and plain-text output.
- Compiled previews with visible validation diagnostics.
- A Brand Kit for portable colors and typography.
- Mailchimp connection settings and discovery foundations.
- An HTML export provider boundary.
- Capability-protected REST routes used by the editor and admin screens.
- Encrypted storage for provider credentials.

CampaignBridge does not yet provide the complete workflow for audiences,
provider campaign drafts, test delivery, scheduling, sending, reconciliation,
or reporting.

## Current workflow

1. Open **CampaignBridge > Email Templates** in WordPress admin.
2. Create a template with the CampaignBridge email blocks.
3. Configure the Brand Kit and template metadata.
4. Compile the template and resolve preview diagnostics before publishing it.

The compiled preview is the source of truth for output. General-purpose core
and third-party frontend blocks are not valid compiler input.

## Requirements

- WordPress 6.5 or newer.
- PHP 8.2 or newer.
- Node.js 24 and pnpm 11 for source builds and JavaScript development.
- Composer and local MySQL server/client binaries for the PHP test suites.

Production API connections must use HTTPS. Browser support targets current
evergreen Chrome, Firefox, Safari, and Edge releases.

## Installation

CampaignBridge is under active development and is not documented here as a
WordPress.org install. To install a release package:

1. Obtain a verified CampaignBridge release ZIP.
2. In WordPress admin, open **Plugins > Add New Plugin > Upload Plugin**.
3. Upload and activate the ZIP.
4. Open **CampaignBridge > Settings**.

For source development, clone the repository into `wp-content/plugins/`, install
the locked dependencies, build the assets, and activate the plugin:

```bash
composer install
pnpm install --frozen-lockfile
pnpm build
```

## Architecture

`campaignbridge.php` checks runtime requirements and hands initialization to
`CampaignBridge\Plugin`, the composition root. The intended dependency direction
is:

```text
Delivery -> Workflow -> Domain <- Repository implementations
```

- `Domain` contains provider-neutral email rules and value objects.
- `Repository` contains WordPress persistence implementations.
- `Workflow` coordinates use cases such as deterministic compilation and future
  campaign operations.
- `Delivery` contains admin screens, REST controllers, blocks, and provider
  adapters.

Provider adapters receive validated credentials only for an operation. Provider
response shapes remain inside adapters, and potentially accepted remote
mutations must not be retried without idempotency and reconciliation rules.

The shared admin form code is an internal API for CampaignBridge screens, not a
general-purpose form framework. Each form directly constructs its security,
validation, data, handling, and rendering collaborators. There is no form
service container, form factory, configuration cache, query optimizer, or asset
optimizer.

See [docs/architecture.md](docs/architecture.md) for the layer and provider
boundaries.

## Repository layout

```text
campaignbridge.php        Plugin bootstrap and runtime guards
includes/
  Admin/                  Admin screens, controllers, assets, and form internals
  Blocks/                 Server-side block registration
  Core/                   Shared storage, encryption, HTTP, and error services
  Domain/Email/           Provider-neutral email and Brand Kit rules
  Post_Types/             Email-template post type
  Providers/              Provider contracts and adapters
  Repository/             WordPress persistence implementations
  REST/                   Editor, preview, and Brand Kit REST controllers
  Services/Email/         Deterministic compiler and renderers
  Workflow/               Application workflows
src/
  blocks/                 Authored email block definitions
  scripts/                Editor and admin TypeScript
  styles/                 Authored CSS
dist/                     Generated runtime assets; do not edit directly
tests/                    PHP, JavaScript, integration, and browser tests
bin/                      Test, CI, build, and release tooling
docs/                     Architecture and contributor documentation
```

## Development

CampaignBridge uses WordPress Studio for its local site but does not create or
manage that site. From this plugin directory, WP-CLI commands can target the
parent Studio site with:

```bash
studio wp --path=../../.. <command>
```

Common project commands:

```bash
pnpm build          # rebuild blocks and shared runtime assets
pnpm start          # watch blocks and shared assets
pnpm qa:fast        # lint, static analysis, tooling checks, and JS tests
pnpm qa             # complete quality, security, JS, and PHP test gate
pnpm test           # all native PHPUnit suites
pnpm test:unit
pnpm test:integration
pnpm test:security
pnpm test:e2e       # authenticated browser tests against WordPress Studio
pnpm release:package
pnpm release:verify
```

PHP tests use disposable local fixtures under `.cache/tests/`; they must never
run against the Studio database. Authored assets belong in `src/`. `pnpm build`
cleans and recreates `dist/`, so generated files should not be edited directly.

Read [docs/development.md](docs/development.md) before contributing. New behavior
should include tests at the narrowest useful level, followed by the relevant
quality gate.

## Security

REST and form operations use explicit capability checks, nonces where
appropriate, type-aware validation and sanitization, and output escaping.
Provider credentials are encrypted at rest and must not appear in logs,
responses, or provider-facing error messages. See [SECURITY.md](SECURITY.md) and
[docs/threat-model.md](docs/threat-model.md) for the maintained security
contract.

Please report vulnerabilities through the process in [SECURITY.md](SECURITY.md),
not through a public issue.

## Documentation

- [Product roadmap](ROADMAP.md)
- [Developer guide](docs/development.md)
- [Architecture](docs/architecture.md)
- [API documentation](docs/api.md)
- [Email block architecture](docs/email-block-architecture.md)
- [Email block catalog](docs/email-block-catalog.md)
- [Admin interface guide](docs/admin/admin-interface.md)
- [Internal form system](docs/admin/form-system.md)
- [Testing strategy](docs/testing-strategy.md)
- [Build and release contract](docs/build-and-release.md)
- [Changelog](CHANGELOG.md)

## License

CampaignBridge is licensed under GPL-2.0-or-later, as declared in the plugin
header.
