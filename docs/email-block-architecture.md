# Email-native block architecture

## Decision

CampaignBridge will provide a constrained email-native block library and
compiler. It will not treat arbitrary Gutenberg frontend markup as the source
for an email and then try to repair that markup after rendering.

The phrase "block store" can be confused with a WordPress data store. In this
repository, use **email block library** for the inserter/catalog and **email
block grammar** for the serialized document format.

## Why

Web layouts and email layouts have different execution environments. Browser
features such as flexbox, grid, positioned elements, scripts, complex selectors,
CSS variables, and many responsive techniques cannot be assumed across Outlook,
Gmail, Apple Mail, Yahoo, and mobile clients. Repairing arbitrary rendered block
HTML produces a growing set of lossy conversions and makes editor fidelity
impossible to reason about.

An email-native grammar gives every block a documented compatibility envelope,
a deterministic renderer, and a testable failure mode.

## Source and output model

The saved template contains block names, semantic attributes, nested structure,
and content bindings. It does not contain the final transport HTML.

```text
Serialized blocks
  → schema validation
  → content binding resolution
  → immutable campaign snapshot
  → per-block email renderers
  → document assembly
  → CSS inlining and sanitization
  → compatibility validation
  → deterministic HTML artifact
```

Storing semantic input preserves migrations, provider independence, plain-text
generation, and future compiler improvements. The approved campaign stores the
content snapshot, compiler version/profile, and a hash of the generated artifact
so the delivered output remains auditable.

## Block contract

Each email block defines:

- stable block name and versioned attribute schema;
- allowed parent, ancestor, and child relationships;
- authoring component and constrained controls;
- normalized intermediate representation;
- email HTML renderer and optional plain-text renderer;
- validation rules and actionable error messages;
- target-profile support and documented degradation;
- golden output fixtures and migration fixtures.

Renderers receive normalized values and a rendering context. They must not read
global request input, mutate persistence, call providers, or fetch live post
content. Dynamic WordPress content is resolved before rendering and frozen in
the campaign snapshot.

## Initial library

The first production set should stay deliberately small:

| Group     | Blocks                                                    |
| --------- | --------------------------------------------------------- |
| Document  | email root, preheader, section, compliance footer         |
| Layout    | one-column row, two-column row, column, spacer, divider   |
| Content   | text, heading, image, bullet list, button                 |
| WordPress | post card, post image, post title, post excerpt, post CTA |

Add social links and more layout variants only after the compiler and fixtures
prove the base contract. Forms, scripts, video embeds, arbitrary HTML, navigation,
and unrestricted nested core blocks are out of scope initially.

The post-v1 candidates, classifications, dependencies, patterns, and promotion
gates are mapped in [`email-block-catalog.md`](email-block-catalog.md). Inclusion
there does not add an item to the supported grammar or editor allowlist.

Core blocks may be supported only through explicit adapters with the same
contract and tests. There is no generic fallback that silently strips or
approximates unsupported markup.

## Output rules

The universal target profile uses conservative markup:

- presentation tables for document and column layout;
- inline critical styles plus narrowly tested media queries;
- HTML `width`, `height`, `align`, `valign`, and background attributes when
  clients require them;
- absolute HTTPS URLs and explicit image dimensions;
- meaningful alt text, with explicit decorative-image handling;
- bulletproof buttons and targeted VML fallbacks for desktop Outlook;
- reset and MSO conditional markup owned by the document renderer;
- no JavaScript, forms, iframes, external stylesheets, or unsupported CSS;
- required preheader, sender, physical-address, and unsubscribe merge controls.

Target profiles may later add provider-specific merge syntax or enhanced CSS,
but they cannot weaken the universal artifact without an explicit validation
result and operator-visible warning.

## Editor fidelity

The block editor canvas should be fast and comfortable to author in, so it may
use ordinary editor-only React markup and CSS. It must share the compiler's
tokens, widths, defaults, validation, and content snapshot, but it is not proof
of email-client compatibility.

The source-of-truth preview is a sandboxed iframe populated by the canonical
compiler. Preview requests compile the current unsaved block tree without
persisting or contacting a provider. Desktop/mobile toggles change the iframe
viewport; they do not substitute a separate renderer.

The UI should distinguish:

- authoring canvas — editable and close to expected output;
- compiled preview — exact HTML artifact CampaignBridge will send;
- client confidence — tested support/degradation for the selected profile.

This avoids promising literal WYSIWYG behavior that no browser-based editor can
guarantee across every email client.

## Compiler boundary

Replace `BlockProcessor`'s central block-name switch with a renderer registry.
Registration maps one stable block name to its schema, normalizer, validators,
HTML renderer, plain-text renderer, and supported profiles. Duplicate
registrations fail at boot.

The compiler returns a result object containing HTML, plain text, warnings,
errors, referenced assets, compiler/profile versions, and a deterministic hash.
It does not return an apparently successful partial email when a block is
unsupported or invalid.

The current simplified `CssProcessor` must be replaced with a maintained public
CSS inliner selected under `docs/dependency-policy.md`. Inlining is a compiler
stage, not the mechanism that makes arbitrary browser markup email-safe.

## Validation and tests

A block is production-ready only when the following pass:

- attribute/schema and nesting validation;
- renderer unit tests for defaults, boundaries, and escaping;
- golden full-document HTML and plain-text fixtures;
- legacy serialization migration fixtures;
- forbidden-element, URL, CSS, accessibility, and compliance validators;
- deterministic compilation test from identical snapshot input;
- representative Outlook, Gmail, and Apple Mail fixtures;
- visual regression of compiled iframe previews.

A hosted email-client service such as Litmus or Email on Acid can later validate
screenshots in real clients. That is an additional release signal, not a reason
to omit deterministic local compiler tests.

## Migration from the current processor

1. Freeze and test current template fixtures before changing rendering.
2. Introduce block schema, render context, compiler result, and renderer registry
   interfaces beside the current `BlockProcessor`.
3. Port the container and simple text/heading/image/button primitives first.
4. Port WordPress post-binding blocks against immutable content snapshots.
5. Add the compiled-preview endpoint and iframe.
6. Gate approval on compiler validation and artifact hashing.
7. Retire generic conversion paths after every supported legacy block has an
   adapter or an explicit migration/error path.

Do not rewrite saved templates in place without backups, versioned migrations,
and fixtures proving the old content can still be opened and compiled.

The phased work breakdown, proposed contracts, preview API, migration gates, and
first pull request are defined in
[`email-block-implementation-plan.md`](email-block-implementation-plan.md).
