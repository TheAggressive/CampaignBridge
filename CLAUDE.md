# CLAUDE.md — CampaignBridge

Guidance for AI assistants working on the CampaignBridge WordPress plugin.
This is adapted from Aggressive Apparel's repository guidance, but this file is
authoritative for CampaignBridge. Do not carry theme, WooCommerce, Tailwind, or
`wp-env` assumptions into this repository.

## Product boundary

CampaignBridge turns WordPress content into reviewed, provider-ready email
campaigns. WordPress owns templates, campaign state, content snapshots,
delivery attempts, and audit history. Providers own audiences, subscribers,
and final delivery.

CampaignBridge is not a subscriber database, SMTP server, or general marketing
automation suite. Read `ROADMAP.md` before expanding product scope.

## Runtime and toolchain

- Version: `package.json` is the tracked source and `pnpm version:sync` updates
  the plugin header and `CampaignBridge_Plugin::VERSION`.
- Minimum runtime: WordPress 6.5 and PHP 8.2.
- CI test target: see `.github/workflows/ci.yml`; do not hardcode it in docs.
- Node 24 and pnpm 11 are pinned by `package.json` and CI.
- Namespace: `CampaignBridge\\`.
- Package manager: pnpm. Do not create `package-lock.json` or `yarn.lock`.
- Local browser site: WordPress Studio at the parent site root.
- PHPUnit: native disposable MySQL and checksum-verified WordPress fixtures.
- CampaignBridge does not use `wp-env`; do not add `.wp-env.json` or `wp-env`
  scripts.

## Quick commands

```bash
pnpm install
pnpm build                 # version sync, clean dist, blocks + shared assets
pnpm start                 # asset and block watch mode

pnpm qa:fast               # pre-push quality gate
pnpm qa                    # full quality, security, and PHP test gate
pnpm test                  # all native PHPUnit suites
pnpm test:unit
pnpm test:integration
pnpm test:security
pnpm test:accessibility
pnpm test:performance

pnpm lint:php
pnpm lint:js
pnpm typecheck
pnpm phpstan
pnpm format:check

pnpm release:package
pnpm release:verify
```

Run WP-CLI against the local Studio site from this plugin directory with:

```bash
studio wp --path=../../.. <command>
```

Never point PHPUnit at the Studio database. `pnpm test:*` owns its isolated
fixtures under `.cache/tests/`.

## Architecture

The target dependency direction is:

`Delivery → Workflow → Domain ← Repository implementations`

- Domain: provider-neutral campaign and email composition rules; pure PHP.
- Repository: WordPress persistence implementations behind ports.
- Workflow: use cases, state transitions, idempotency, and reconciliation.
- Delivery: admin UI, REST controllers, blocks, and provider adapters.

New direct WordPress persistence outside repository/Core storage boundaries is
prohibited. Provider response shapes must not leak into domain objects or UI.
Provider adapters receive decrypted credentials only for one operation and
must not persist them or expose raw provider errors.

### Composition root

`campaignbridge.php` validates the runtime and loads the custom autoloader.
`CampaignBridge\\Plugin` is the composition root and owns initialization order.
Service registration must not perform work; hooks and migrations begin during
explicit initialization.

### Important directories

```text
campaignbridge.php        Plugin bootstrap, runtime guards, version contract
includes/
  Admin/                  Screens, controllers, secure form system
  Blocks/                 Metadata block registration
  Core/                   Storage, encryption, HTTP, services, errors
  Post_Types/             Email template post type
  Providers/              Provider adapters and current provider interface
  REST/                   REST route infrastructure
  Services/Email/         Email compiler components
src/
  blocks/                 Authored CampaignBridge blocks
  scripts/editor/         Standalone template editor shell
  scripts/admin/          Admin form behavior
  styles/                 Authored CSS
dist/                     Generated runtime assets
tests/                    PHPUnit suites and fixtures
bin/                      Native test, CI policy, and release tooling
```

Authored assets live in `src/`; generated assets live in `dist/`. Never edit
generated files directly. `pnpm build` deletes `dist/` before rebuilding so
removed source cannot survive as stale production output.

## Email block system

CampaignBridge uses an email-native block grammar, not arbitrary web blocks as
its long-term source format. Read `docs/email-block-architecture.md` before
creating or changing email blocks.

Key rules:

- Store semantic block attributes and content, not final compiled email HTML.
- Compile each supported block through a dedicated email renderer.
- Do not call general `render_block()` and attempt to repair arbitrary frontend
  HTML afterward.
- The email editor registers CampaignBridge email blocks only. Core and
  third-party frontend blocks are unsupported compiler input and fail validation
  visibly; they never disappear silently.
- Final output uses conservative email markup: presentation tables, inline CSS,
  HTML width/alignment attributes where needed, and targeted Outlook VML.
- The editor canvas is an authoring surface. The compiled iframe preview is the
  source of truth for what will be sent.
- Content is snapshotted at approval and compilation is deterministic.
- Every new block needs compiler tests and golden HTML fixtures.

Use the `campaignbridge-email-blocks` repository skill for this work.

## Blocks

Blocks live in `src/blocks/<name>/`, compile to `dist/blocks/<name>/`, and are
auto-registered from metadata by `CampaignBridge\\Blocks\\Blocks`.

```bash
pnpm create-block <name>
pnpm create-block-dynamic <name>
pnpm build:blocks
```

All blocks use `apiVersion: 3`. Once a block enters the production grammar,
treat its name and saved attribute shape as a stable API. Before that promotion
gate, prefer a clean schema replacement over carrying compatibility code. Use a
one-time data migration only when production data has been explicitly declared
durable; do not keep parallel renderers or permanent legacy adapters.

## Coding standards

### PHP

- Use `declare(strict_types=1);`.
- Follow WPCS and the repository PHPCS rules.
- PHPStan runs without an accepted baseline; fix findings rather than suppressing
  them broadly.
- File names currently follow the repository's PSR-4 class paths. Preserve local
  conventions when adding classes.
- Validate and sanitize input early; escape output at the final rendering
  boundary.
- Nonces prevent CSRF and never replace capability checks.
- Use the injected HTTP abstraction for provider traffic; never log credentials,
  authorization headers, subscriber PII, or raw provider payloads.

### TypeScript and React

- Use TypeScript for new editor/admin code.
- Prefer WordPress packages already present in `package.json`.
- Keep editor state and provider/domain state separate.
- Preserve keyboard, focus, screen-reader, loading, empty, stale, and failure
  states in admin workflows.

### File size

`bin/ci/check-file-length.sh` warns above 800 lines and fails above 1000 lines
for production PHP and TypeScript/TSX. Split by responsibility; do not raise the
limit or add an allowlist. Existing warnings are technical debt, not precedent.

## Provider and campaign safety

Remote campaign mutations require idempotency and reconciliation. A timeout
after a potentially irreversible request enters an `unknown` state; never retry
a send automatically unless the provider proves it did not accept the request.

Separate these operations and capabilities:

- verify connection
- discover audiences and sender identities
- create/update provider draft
- send test
- schedule
- send
- cancel
- reconcile status and reports

Do not collapse them into one `send_campaign()` workflow.

## Tests and verification

Tests must prove both behavior and wiring. Security tests should assert that a
guard is registered and then prove the refusal or state transition.

- Unit: pure rules, compiler renderers, provider normalization.
- Integration: WordPress persistence, routes, hooks, migrations.
- Security: authorization, nonce failure, credential redaction, unsafe input.
- Accessibility: rendered admin behavior; browser coverage as it is added.
- Performance: bounded query/request budgets, not fragile wall-clock timing.

Before finishing a code change, run the smallest relevant test first and then
`pnpm qa:fast`. Run `pnpm qa`, `pnpm build`, and package verification for
release-sensitive changes.

## Dependencies

Direct JavaScript dependencies come from public npm and direct PHP dependencies
from public Packagist unless `docs/dependency-policy.md` explicitly documents a
narrow exception. Keep lockfiles committed. Do not move compatibility-locked
packages independently; treat each documented stack as one migration.

## Release workflow

Use Conventional Commits. Pushes and pull requests run quality, isolated PHP
suites, build, package verification, workflow security, and CodeQL. Publishing
is a deliberate workflow dispatch and must not happen as a side effect of a
normal push.

`master` is squash-only and pull-request-only. Pull-request titles become squash
subjects and must pass the `PR Title` check. The PR policy applies type, area,
and risk labels; only trusted, non-high-risk changes may use native auto-merge.
Release metadata returns through a signed version-sync pull request rather than
a protection bypass. See `docs/pull-request-automation.md`.

The release package is allowlisted by `bin/release/package.sh` and independently
inspected by `bin/release/verify-package.sh`. A successful source build alone is
not proof that the distributable is valid.

## Canonical references

- `README.md` — shipped product overview
- `ROADMAP.md` — target product and gated milestones
- `docs/development.md` — local setup and contributor workflow
- `docs/api.md` — REST namespace and security contract
- `docs/architecture.md` — dependency boundaries
- `docs/email-block-architecture.md` — email-native block contract
- `docs/email-block-implementation-plan.md` — phased compiler and block rollout
- `docs/dependency-policy.md` — public/private dependency rules and locks
- `docs/testing-strategy.md` — test responsibilities and environments
- `docs/build-and-release.md` — package and publishing behavior
- `docs/pull-request-automation.md` — labels, native auto-merge, and rulesets
- `docs/threat-model.md` — security boundaries
- `docs/runbook.md` — operational troubleshooting
