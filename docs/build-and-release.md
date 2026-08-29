# Build and release

`pnpm build` synchronizes source versions, removes `dist/`, and builds blocks, Interactivity modules, and shared assets. Stale generated files therefore cannot survive a source deletion.

`bin/release/package.sh` copies only runtime paths into `release/campaignbridge`, checks required files and credential-like filenames, lints packaged PHP, creates the ZIP, and writes a SHA-256 sidecar.

`bin/release/verify-package.sh` inspects and extracts the ZIP rather than trusting staging. It verifies the checksum, top-level directory, forbidden and required paths, version contract, and PHP syntax.

Publishing is a manual workflow action. Merge and push events run quality gates but cannot publish a release as a side effect.

CI blocks releases on Composer advisories and advisories in dependencies shipped to production. Dependabot tracks the larger development-only WordPress toolchain separately so upstream build-tool fixes can be reviewed without forcing incompatible transitive overrides.

Direct dependency sources and compatibility locks are governed by
[`docs/dependency-policy.md`](dependency-policy.md). CI rejects unreviewed Git,
URL, file, and custom Composer repository dependencies.
