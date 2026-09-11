# ADR 0001: One versioned email design contract

- Status: Amended
- Date: 2026-09-10
- Issue: [#43](https://github.com/TheAggressive/CampaignBridge/issues/43)

## Context

CampaignBridge currently assembles editor presets from `Design_Presets`, Brand
Kit state, and handwritten editor CSS. Renderers separately own fallback values.
That makes editor/compiler drift possible as the supported design surface grows.
WordPress `theme.json` cannot be authoritative because it contains browser
features that the email compiler cannot safely or deterministically reproduce.

## Decision

CampaignBridge owns a deliberately narrow, versioned `email.json` format. The
repository-packaged manifest at `includes/Email_Design/email.json` is the v1
base manifest. The active parent and child themes may provide partial manifests
at `campaignbridge/email.json`. Its canonical schema is stored beside it.

The governing rule is:

> `email.json` describes design intent; `Resolved_Email_Design` is the runtime
> truth; Gutenberg and the compiler are consumers of that same truth.

V1 does not provide database persistence, uploads, user-created manifests,
style switching, arbitrary CSS, selectors, HTML, URLs, or asset declarations.
Theme manifests are code-owned layers and use the same closed contract.
Font-family entries select from CampaignBridge's curated catalog by slug. Brand
Kit remains the validated route for brand colors and a custom Google Font.

The runtime precedence, from lowest to highest, is:

1. CampaignBridge safe fallback values.
2. The packaged `email.json` manifest.
3. Parent theme `campaignbridge/email.json`.
4. Child theme `campaignbridge/email.json`.
5. Brand Kit identity values.
6. Future template design overrides.
7. Future campaign snapshot overrides.
8. Explicit native Gutenberg block styles.

Later layers win. Layers 6 and 7 are reserved contract positions, not v1
features. An explicit legacy raw value already accepted by the compiler remains
readable for compatibility, but v1 editor settings do not offer arbitrary new
colors, font sizes, or spacing values.

Gutenberg's `var:preset|<kind>|<slug>` form is the persisted semantic reference.
Normalization resolves every reference against a typed preset map. An unknown,
malformed, circular, wrong-kind, or unsupported reference is a blocking
diagnostic. No preset reference or CSS custom property may reach compiled HTML.

Unknown properties, unknown blocks, and styles outside a block's declared
manifest vocabulary are rejected. They are never forwarded or silently
ignored. The block's `block.json` support and its registered renderer remain the
upper bound on author-visible controls and compilable behavior.

## Ownership boundaries

- The loader locates, decodes, and layers the packaged, parent-theme, and
  child-theme files; it contains no rendering policy.
- The v1 validator enforces the closed schema, bounds, known blocks, and values.
- The normalizer produces one canonical typed representation and resolves
  preset references; raw JSON does not reach consumers.
- The resolver applies precedence and creates immutable
  `Resolved_Email_Design`, including version and deterministic fingerprint data.
- `Editor_Design_Settings` adapts the resolved design to WordPress settings and
  owns only editor mechanics or structural preview CSS.
- Renderers and `Style_Resolver` consume the same resolved values and remain
  authoritative for target-profile representation and diagnostics.
- Approval snapshots must eventually capture enough resolved design data to
  reproduce the compiled artifact after Brand Kit or manifest changes.

## Versioning and migration

The top-level integer `version` is the public contract version. V1 input is
strict and closed. Unsupported future versions fail with
`design.unsupported_version`; they are not interpreted as v1. Compatible
clarifications may tighten documentation and diagnostics without changing
normalized output. A changed shape or meaning requires a new version and an
explicit deterministic v1-to-v2 normalization path. Internal PHP class names
and storage details are not part of the JSON contract.

## Consequences

The first runtime implementation must load, validate, normalize, and resolve
the contract before editor or compiler integration. Existing hard-coded design
defaults will temporarily coexist until parity is proven, then design decisions
move behind the resolver while structural editor CSS remains in the editor.
Adding a manifest property requires matching schema, normalizer, editor adapter,
renderer/profile behavior, diagnostics, and parity tests.
