---
name: campaignbridge-email-blocks
description: Design, implement, migrate, or review CampaignBridge email-native blocks and the deterministic compiler that renders them into compatible HTML and plain text. Use for block schemas, renderer registration, compiled previews, compatibility validation, and email golden fixtures; do not use for unrelated Gutenberg frontend blocks.
compatibility: CampaignBridge; WordPress 7.1+; PHP 8.4+; Node 24 and pnpm 11.
---

# CampaignBridge email blocks

Read `../../../CLAUDE.md` and
`../../../docs/email-block-architecture.md` before changing the block grammar or
compiler.

## Workflow

1. Inventory the affected `src/blocks/` metadata and compiler path under
   `includes/Services/Email/`.
2. Apply WordPress Native First: if a WordPress Core block already provides the
   content or layout primitive, add it to `includes/Email_Blocks/email-blocks.json`
   with explicit email semantics and a block-specific reader in
   `Core_Block_Normalizer` instead of creating a CampaignBridge duplicate. Create
   a CampaignBridge block only for email structure, compliance, or
   snapshot-bound content that Core does not model.
3. Define the stable semantic attributes, nesting constraints, validation, and
   target-profile behavior before writing output markup.
4. Keep the authoring component separate from the canonical renderer. The
   compiled iframe preview, not editor DOM, represents the send artifact.
5. Resolve dynamic WordPress data into an immutable snapshot before rendering.
   Renderers never fetch live content or call providers.
6. Emit conservative email markup through a registered renderer. Unsupported
   blocks and invalid attributes produce explicit compiler errors.
7. Add renderer tests, golden HTML/plain-text fixtures, and serialization
   migration fixtures. Verify deterministic output and escaping.
8. Run the focused tests, `pnpm build:blocks`, and `pnpm qa:fast`.

## Invariants

- Persist semantic source blocks, not compiled transport HTML.
- Never use generic `render_block()` output as the email source.
- Core blocks are normalized from their known serialization contract only;
  arbitrary `innerHTML` is never canonical, and unsupported Core blocks,
  attributes, styles, and classes fail closed.
- Never silently omit, flatten, or approximate an unsupported block.
- Keep provider merge syntax and response shapes outside block renderers.
- Treat block names, attribute shapes, compiler profiles, and output fixtures as
  versioned contracts.
- A compiler failure blocks approval; warnings remain visible to the operator.
- Do not claim universal WYSIWYG fidelity. State which profile/client behavior
  is tested and where degradation is intentional.

## Output baseline

Use presentation tables, inline critical CSS, absolute HTTPS URLs, explicit
image dimensions, accessible alternative text, and narrowly scoped MSO/VML
fallbacks. Do not emit JavaScript, forms, iframes, external stylesheets, or
unvalidated arbitrary HTML.
