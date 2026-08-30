# Tooling and testing

Use this file when deciding what commands to run and what “good verification” looks like.

## Common toolchains

- `@wordpress/scripts` for build/lint/test:
  - https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
- `@wordpress/create-block` to scaffold new blocks:
  - https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/
- Interactivity API template for `create-block`:
  - https://www.npmjs.com/package/@wordpress/create-block-interactive-template
- WordPress Studio for the browser-facing development site. CampaignBridge's
  PHPUnit environment is native and disposable; do not add `wp-env` commands or
  configuration.

## Verification checklist

- `pnpm build` succeeds.
- JS lint passes (repo-specific).
- E2E tests pass if present.
- Manual: insert block, save post, reload editor, confirm no “Invalid block”.
