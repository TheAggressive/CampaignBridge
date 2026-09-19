# Email-native block architecture

## Decision

CampaignBridge will provide a constrained email-native block grammar and
compiler. It will not treat arbitrary Gutenberg frontend markup as the source
for an email and then try to repair that markup after rendering.

**WordPress Native First:** CampaignBridge uses WordPress Core blocks for
authoring when Core already provides the appropriate content or layout
primitive. Supported Core blocks are normalized into CampaignBridge's bounded
email semantics and compiled into deterministic email-safe output. Core
frontend rendering is not used as email output.

```text
WordPress native block editor
  → selected supported Core blocks + CampaignBridge email blocks
  → bounded CampaignBridge normalization (Core_Block_Normalizer)
  → canonical email semantics
  → CampaignBridge deterministic compiler
  → email-safe HTML + plain text
```

This does not make every Core block supported. Only the Core blocks named in the
authoring contract below are email input; everything else fails closed.

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

The implemented [content snapshot and review-input contract](content-snapshots.md)
defines canonical post objects, typed renderer scopes, frozen compilation,
explicit refresh revisions, and the boundary with future campaign persistence.

Normalization supplies documented defaults for omitted attributes and performs
lossless canonicalization such as trimming URL fields. It must not clamp,
substitute, or otherwise repair explicitly malformed persisted input. Invalid
types, ranges, colors, alignments, and enum values produce blocking compiler
diagnostics so an approved artifact remains auditable against its source.

## Initial library

The first production set should stay deliberately small:

| Group     | Blocks                                                    |
| --------- | --------------------------------------------------------- |
| Document  | email root, preheader, section, compliance footer         |
| Layout    | one- to six-column row, column, Core spacer and separator |
| Content   | Core paragraph, heading, image, list, buttons             |
| WordPress | post card, post image, post title, post excerpt, post CTA |

Add social links and more layout variants only after the compiler and fixtures
prove the base contract. Forms, scripts, video embeds, arbitrary HTML, navigation,
and unrestricted nested core blocks are out of scope initially.

The post-v1 candidates, classifications, dependencies, patterns, and promotion
gates are mapped in [`email-block-catalog.md`](email-block-catalog.md). Inclusion
there does not add an item to the supported grammar or editor allowlist.

## Supported authoring contract

`includes/Email_Blocks/email-blocks.json` is the single authoritative contract.
It names every supported authoring block, its origin (`core` or
`campaignbridge`), the CampaignBridge email semantics it compiles through, and
its permitted children. Consumers derive from it or are parity-tested against
it:

- the template editor allowlist (`Native_Editor::allowed_block_types()`);
- editor nesting (`src/blocks/shared/nesting.ts`);
- server nesting (each renderer's `allowed_children()` reads the contract);
- Core normalization (`Core_Block_Normalizer::SEMANTICS`);
- the compiler renderer registry (`Compiler_Factory::registry()`).

### Supported WordPress Core blocks

| Core block       | Email semantics | Normalized from                                                            |
| ---------------- | --------------- | -------------------------------------------------------------------------- |
| `core/paragraph` | text            | `<p>` rich text, `style.typography.textAlign`, colour/typography/spacing   |
| `core/heading`   | heading         | `<h1>`–`<h4>` rich text, `level`, text alignment, colour/typography        |
| `core/image`     | image           | `<img src/alt>`, `figure > a[href]`, pixel `width`/`height`, `align`       |
| `core/buttons`   | button group    | `layout.justifyContent` (left, center, right)                              |
| `core/button`    | button          | `<a href>` and label, `fill`/`outline`/`ghost` style, colours, font family |
| `core/list`      | list            | `ordered`                                                                  |
| `core/list-item` | list item       | `<li>` rich text                                                           |
| `core/separator` | divider         | background colour; thickness and line style come from the email design     |
| `core/spacer`    | spacer          | `height` (Core default 100px), 0–600 px                                    |

Users insert and edit the real Core blocks with native Gutenberg behaviour
(inserter, toolbar, inspector, RichText, List View, transforms, undo/redo). The
editor extension narrows their native design supports through the public
`blocks.registerBlockType` filter (`src/scripts/editor/core-email-blocks.ts`),
removes block styles the compiler cannot express (image `rounded`, separator
`dots`), adds the email-only `ghost` ("Text link") button style, and limits
heading levels to 1–4. It never forks a Core edit component.

### Remaining CampaignBridge blocks

`campaignbridge/container`, `campaignbridge/preheader`, `campaignbridge/section`,
`campaignbridge/columns`, `campaignbridge/column`, `campaignbridge/post-card`,
`campaignbridge/post-image`, `campaignbridge/post-title`,
`campaignbridge/post-excerpt`, `campaignbridge/post-button`,
`campaignbridge/post-link`, and `campaignbridge/compliance-footer` stay custom:
they own the email document structure, compliance, or snapshot-bound post
content that Core blocks do not model.

`core/columns`/`core/column` were evaluated and not adopted. Email columns are
presentation-table cells whose widths are integer percentages of the row with
a deterministic automatic share, and the email grammar requires flat columns
whose cells may hold post-binding blocks. Core `column.width` is a free-form CSS
length (px, em, vw, `calc()`), Core columns may nest inside Core columns, a Core
column's inner allowlist is open unless every block carries an `allowedBlocks`
attribute, and Core layout exposes a two-axis `blockGap` and per-column
vertical alignment that a single-row email table cannot honour. The
CampaignBridge pair keeps those guarantees without registration overrides.

### Normalization and compiler boundary

`Core_Block_Normalizer` runs once per Core node before renderer lookup. It reads
only each block's known serialization contract: comment attributes plus, where
Core sources a value from saved markup, the exact wrapper Core's `save()`
emits. It drops editor-only values (`lock`, `placeholder`, List View
`metadata.name`) and frontend-only link/media metadata (`id`, `sizeSlug`,
`linkTarget`, `rel`, button `title`, `lightbox`, separator `opacity` and
`tagName`), canonicalizes
Core link-format anchors (`data-type`, `data-id`), and converts Core values into
renderer semantics. Any other attribute, custom class, block style, markup
shape, or unsupported style produces a stable `block.attribute.invalid`
diagnostic. Renderers then validate and render as for any other block. Arbitrary
`innerHTML` is never canonical, and `render_block()` or theme CSS is never used.

### Unsupported blocks

Every Core block not listed above (for example `core/group`, `core/cover`,
`core/gallery`, `core/embed`, `core/video`, `core/html`, `core/shortcode`,
`core/query`, `core/navigation`, `core/columns`, `core/quote`, `core/table`) and
every third-party block is outside the grammar. The editor does not offer them,
and the compiler returns `block.child.unsupported` or `block.unsupported` with
the exact block path. There is no adapter or generic fallback that silently
strips or approximates markup.

Known v1 limits: nested lists, list `start`/`reversed`/`type`, image captions,
cropping (`aspectRatio`/`scale`), `space-between` button groups, and button
widths, border radius, or font size are rejected. Several buttons in one
`core/buttons` group render as stacked rows. The editor removes list insertion
inside list items, but Core's keyboard indent in a list still creates a nested
list; the compiler reports it as `block.child.unsupported`. Core controls the
editor cannot hide (list start/reversed, image caption and crop, button-group
`space-between`) are likewise reported by the compiler rather than ignored.

The inline rich-text parser is intentionally a small, fail-closed grammar for
balanced emphasis, underline, strikethrough, line breaks, and HTTPS links. Do
not evolve it into a general HTML parser as new content features arrive. Use
semantic blocks (such as `core/list`) for structures, and move to a
purpose-built, allowlisted parser before accepting spans, arbitrary attributes,
or styles. Core rich-text formats outside that subset (highlight, inline code,
inline images, sub/superscript) are rejected.

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

### Native editor composition

`cb_templates` uses WordPress's normal post block editor. Core owns the header,
canvas, inserter, List View, Inspector, selection, history, dirty state,
save/publish flow, autosaves, revisions, notices, preferences, and keyboard
shortcuts. CampaignBridge registers only template-specific extensions:

```text
WordPress post editor (cb_templates)
  → compiler-registry block allowlist
  → resolved email design settings
  → CampaignBridge document settings panels
  → compiled Email Preview
```

New templates receive one canonical container from the post type's native block
template. Existing saved content is loaded and persisted by Core without a
parallel editor data store or save lifecycle. Preview serializes the current
in-memory block tree, including unsaved changes, and sends it to the canonical
server-side compiler.

## Compiler boundary

The compiler uses an O(1) renderer registry rather than a block-name switch.
Registration maps one stable block name to its schema, normalizer, validators,
HTML renderer, plain-text renderer, and supported profiles. Duplicate
registrations fail at boot.

The compiler returns a result object containing HTML, plain text, warnings,
errors, referenced assets, compiler/profile versions, and a deterministic hash.
It does not return an apparently successful partial email when a block is
unsupported or invalid.

Renderers emit inline critical CSS directly. If authored style sheets are added
later, select a maintained public CSS inliner under `docs/dependency-policy.md`.
Inlining remains a compiler stage, not a mechanism for repairing browser markup.

## Canonical tokens

Personalization and system values use one provider-neutral syntax,
`{{cb:category.name}}`. `Token_Registry` (`includes/Domain/Email/Token/`) is the
only vocabulary, and `Token_Parser` is the only parser.

CampaignBridge never generates provider-specific syntax: its canonical model,
templates, and artifacts express personalization only as `{{cb:...}}`. The
parser does not interpret foreign provider syntax such as Mailchimp's
`*|FNAME|*` as a CampaignBridge token; such text is ordinary literal content and
may appear in templates, snapshots, and compiled artifacts. Provider handoff
(not yet implemented) must ensure provider-specific literal syntax cannot be
accidentally activated by the provider.

The compiler resolves tokens after Core authoring normalization and renderer
`normalize()`, and before renderer `validate()`. Tokens are therefore handled in
canonical email semantics, and resolved values still pass the rich-text, URL,
and length rules. `Token_Resolver` treats a parsed token in one of two ways:

- **CampaignBridge-resolved** (`organization.name`, `organization.address`):
  replaced during the deterministic compile with the value from the explicit
  `token_values` metadata map on `Render_Context`, keyed by canonical ID (for
  example `'cb:organization.name' => 'Example Company'`). A missing value fails
  closed with `token.unresolved`. The compiler never reads options, the
  database, or providers for a value. The map is part of the fingerprinted
  context, so a changed value changes the artifact fingerprint.
- **Provider-resolved** (`subscriber.*`, `campaign.view_online_url`,
  `campaign.unsubscribe_url`): validated, then kept verbatim in the HTML and
  plain-text artifact. A provider adapter translates them at handoff.
  Compilation needs no subscriber or provider data.

`token_values` may hold only registered CampaignBridge-resolved IDs, with
bounded plain text that has no control characters or double braces. Any other
map, including a subscriber or provider-owned value, fails with
`token.values.invalid` at `context.token_values`.

Renderers declare which normalized attributes accept tokens through
`token_attributes()`:

| Block | Attribute | Context |
| --- | --- | --- |
| `core/paragraph`, `core/heading`, `core/list-item` | `content` | rich text |
| `core/button` | `label` | text |
| `core/button` | `url` | URL |
| `campaignbridge/preheader` | `content` | text |

In rich text, tokens may appear in text runs (including inside `strong`, `em`,
`u`, `s`) and as the complete `href` of an anchor. Entity-encoded braces are
decoded before validation. A token split by markup fails closed. Local values
are HTML-encoded into rich text and escaped by the renderer in plain text.

A URL context accepts either a literal HTTP(S) URL or exactly one token whose
value type is `url`. Partial interpolation (`https://example.com/?e={{cb:…}}`),
prefixes (`javascript:{{cb:…}}`), and non-URL tokens are rejected. Link output
goes through `Renderer_Support::link_url()`. `https_url()` is unchanged and
still accepts only literal HTTP(S) URLs.

Every other string attribute rejects `{{cb:` syntax with
`token.context.unsupported`. This covers image URL/alt/link, post blocks, and
the compliance footer. Token failures produce one diagnostic per code per
attribute at `<block path>.attrs.<attribute>`, and messages never echo token
IDs or values: `token.unknown`, `token.malformed`, `token.nested`,
`token.limit_exceeded`, `token.unresolved`, `token.url.invalid`,
`token.url.value_type`, `token.context.unsupported`, `token.values.invalid`.

Post snapshot content is never a token context. Renderers declare the snapshot
fields they emit through `snapshot_fields()` (title, excerpt, image URL,
non-decorative image alt, and any post, parent, or archive URL a block links
to), and the compiler rejects
`{{cb:` in any emitted field with `token.snapshot.unsupported` at
`<block path>.snapshot.posts[<post ID>].<field>`. Snapshot content is neither
resolved, preserved, nor rewritten; the compile fails closed. Every canonical
token in a successful artifact therefore originates from a
`token_attributes()` attribute.

Not yet implemented: editor token insertion, synthetic preview values,
compliance-token validation, and provider mapping.

## Validation and tests

A block is production-ready only when the following pass:

- attribute/schema and nesting validation;
- renderer unit tests for defaults, boundaries, and escaping;
- golden full-document HTML and plain-text fixtures;
- serialization round-trip fixtures for each production schema version;
- forbidden-element, URL, CSS, accessibility, and compliance validators;
- deterministic compilation test from identical snapshot input;
- representative Outlook, Gmail, and Apple Mail fixtures;
- visual regression of compiled iframe previews.

A hosted email-client service such as Litmus or Email on Acid can later validate
screenshots in real clients. That is an additional release signal, not a reason
to omit deterministic local compiler tests.

## Clean cutover completed

CampaignBridge took a pre-release clean break rather than preserving its
prototype renderers. The completed foundation:

1. Introduced the block schema, render context, compiler result, renderer
   registry, and deterministic fingerprint.
2. Cut compiler consumers directly to the new result contract.
3. Removed `BlockProcessor`, `CssProcessor`, `EmailStructure`, block `render.php`
   transport output, and core-block conversion in the same bounded rollout.
4. Ported the container and WordPress post-binding blocks to registered renderers
   that consume immutable snapshots.
5. Added native section, text, heading, image, button, divider, and spacer blocks
   before enabling their inserter choices; preheader, columns, and compliance
   controls remain gated.
6. Add the compiled-preview endpoint and iframe, then gate approval on compiler
   validation and artifact hashing.
7. Replaced the text, heading, image, button, divider, and spacer duplicates
   with the supported WordPress Core blocks and added Core lists (WordPress
   Native First), without aliases or migrations.

Existing prototype templates are unsupported input after cutover. If durable
production data is declared later, handle it with a finite, observable data
migration and remove the migration after its support window; do not retain a
second rendering architecture.

The phased work breakdown, proposed contracts, preview API, migration gates, and
first pull request are defined in
[`email-block-implementation-plan.md`](email-block-implementation-plan.md).

## Native WordPress styling

Email block authoring uses WordPress block supports and the native `style`
object. Color and font presets retain core's top-level preset attributes;
button variations use registered `is-style-*` classes. The email renderer
resolves presets to portable values and emits inline CSS and presentation
tables. It does not use the editor DOM as transport HTML.

The versioned design vocabulary and defaults are defined by the packaged
[`email.json`](../includes/Email_Design/email.json) contract. Its architecture,
precedence, and ownership boundaries are recorded in
[`ADR 0001`](decisions/0001-email-design-contract.md). Runtime integration must
resolve that manifest and Brand Kit into one immutable design consumed by both
the editor adapter and compiler; neither consumer interprets raw manifest data.

Supported controls are declared per block: Core paragraphs expose text and
background color, font size, font family, line height, text alignment, and
spacing; Core headings expose text color, typography, and alignment; Core
buttons expose colors and font family; Core images expose margin and alignment;
Core separators expose a color while their thickness and line style come from
the email design; Core spacers use their height. Containers, sections, cards and
footers expose their supported spacing; columns use native block gap. Container content width uses constrained layout.
Gap applies only between columns, without adding outside gutters. The preview
uses the compiled artifact for both desktop and mobile.

Older CampaignBridge style attributes are converted during block parsing and
saved through the native schema. Explicit native values take precedence over
legacy values. Compiler compatibility accepts these older attributes while
saved templates are converted; there is no second renderer. Regression fixtures
cover migration and both forms of compiler input.

Unsupported style properties and unresolved colors produce compiler errors
instead of being silently discarded or replaced. Native link hover color is
preserved as scoped CSS for clients that support hover; critical link color
remains inline. Real email-client screenshot testing remains a separate release
check from local compiler and browser verification.

Columns distribute unspecified widths across the remaining space and normalize
explicit proportions. The editor uses a nonwrapping flex row with zero minimum
column widths; email output uses a fixed-layout presentation table. The column
count control supports one through six columns, and the native toolbar sets
vertical alignment. Mobile stacking is optional and starts at 480px, below the
default 600px desktop email width. Stacked columns lose horizontal gutter
padding and retain the chosen gap vertically. Clients without media-query
support keep the table layout.
