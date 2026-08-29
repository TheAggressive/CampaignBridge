# Dependency policy

CampaignBridge resolves direct JavaScript dependencies from the public npm
registry and direct PHP dependencies from public Packagist. `package.json`
remains `private: true` because the WordPress plugin is not an npm package; that
flag prevents accidental publication and does not select a private registry.

`bin/ci/check-dependency-sources.mjs` rejects direct Git, URL, file, and custom
Composer repository sources. If a private or VCS dependency becomes necessary,
the guard must be changed explicitly with a narrow package-and-reference
allowlist and a documented reason. Lockfiles remain committed and frozen in CI.

## Compatibility locks

Some public packages intentionally stay on older major or minor lines:

- PHPUnit 9 is the compatible runner for the WordPress PHPUnit library used by
  the database-backed suites.
- PHP_CodeSniffer 3 is required by the current WordPress Coding Standards line.
- React 18 matches the peer graph shipped by the WordPress block editor.
- ESLint 9 and TypeScript 5 match the current WordPress scripts toolchain.
- Tailwind 4.1.17, postcss-nested 7.0.2, and postcss-lightningcss 1.0.2 are an
  exact pipeline: newer releases currently leave BEM suffix nesting unexpanded
  and fail the production CSS build.
- WordPress core 7.0.4, wp-phpunit 7.0.4, and public stubs 7.0.1 are the newest
  aligned releases with complete WordPress.org checksum coverage. WordPress 7.1
  is deferred until its checksum manifest covers every file in its archive.

Dependabot groups non-major updates, applies cooldown periods, and opens review
pull requests. Packages listed as compatibility locks are excluded from routine
version updates so they cannot be separated from the stack they must move with.
Major and compatibility-stack upgrades are deliberate migrations and must pass
install, peer validation, full QA, production build, and package verification.
