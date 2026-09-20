# Email-native block implementation plan

## Objective

Build a constrained CampaignBridge block library whose semantic source compiles
deterministically into the exact HTML and plain text used by preview, export, and
provider delivery.

The first complete vertical slice is:

`Create template → author native email blocks → compile → inspect exact preview → validate → download HTML`

Provider delivery and campaign approval consume the same compiler artifact later;
they do not introduce another rendering path.

## Non-goals

- Supporting every core or third-party Gutenberg block.
- Rendering arbitrary frontend HTML and repairing it after the fact.
- Perfect visual emulation of every email client inside a browser.
- Provider-specific markup inside block renderers.
- Fetching live WordPress content during final rendering.
- Preserving prototype template markup through permanent compatibility code.

## Cutover status and remaining risks

The compiler foundation now replaces the prototype rendering paths:

- one explicit renderer registry owns the seventeen currently supported blocks;
- compilation fails closed for unknown attributes, blocks, nesting, missing
  snapshots, depth, and block-count violations;
- HTML, plain text, diagnostics, versions, and a deterministic fingerprint are
  returned together;
- the native WordPress editor registers only compiler-supported CampaignBridge
  blocks for `cb_templates`;
- WordPress Core owns entity editing, undo/redo, the block canvas, history, and
  persistence while CampaignBridge supplies email-specific extensions;
- new templates remain drafts through autosave and start with one canonical
  container;
- block `render.php`, `Email_Generator`, `BlockProcessor`, `CssProcessor`, and
  `EmailStructure` have been removed;
- golden artifacts and measured compiler performance are covered by tests.

The remaining production risks are compiled preview/export wiring, snapshot
resolution and persistence, advanced layout/document blocks, compliance and
compatibility validators, draft/approval semantics, and email-client evidence.
Existing prototype templates are not a compatibility boundary for the compiler.

## Product and technical decisions

1. **Use an email block library, not a WordPress data “store.”** The inserter
   catalog is the library; serialized blocks form the email grammar.
2. **Keep `campaignbridge/container` as the stable root block.** It already exists
   in saved content and becomes the canonical email document root.
3. **Persist semantic source.** Final HTML, plain text, diagnostics, compiler
   version, profile, and fingerprint are generated artifacts.
4. **Compile on the server.** One PHP compiler serves preview, HTML export,
   approval, and provider workflows.
5. **Use explicit renderers.** One registered renderer owns each supported block
   name. Duplicate registration fails during composition.
6. **WordPress Native First.** Supported WordPress Core authoring blocks are
   used directly and normalized into bounded email semantics; every other Core
   or third-party block is rejected compiler input and is not offered for
   email templates. Core frontend rendering is never email output.
7. **Fail closed.** Unsupported blocks, invalid nesting, unresolved required
   content, or compliance failures prevent approval and delivery.
8. **Snapshot before approval.** Post bindings resolve to immutable content before
   final compilation. Final renderers never query live posts.
9. **Treat compiled preview as truth.** The editable canvas is approximate. The
   sandboxed compiled preview is the artifact that will be exported or sent.
10. **Start with a universal profile.** Provider-specific profiles may add merge
    syntax later but cannot bypass universal validation.

## Target flow

```text
WordPress block comments
  → bounded serialized-block parser
  → provider-neutral Block_Node tree
  → grammar and nesting validation
  → content snapshot resolution
  → renderer registry
  → HTML + plain-text document assembly
  → optional authored-style inliner
  → compatibility/compliance validators
  → Compile_Result + deterministic fingerprint
        ├── compiled iframe preview
        ├── HTML download
        ├── campaign approval snapshot
        └── provider draft upload
```

Preview may build an ephemeral content snapshot from current WordPress content.
Approval persists a snapshot and compiles only from that immutable input.

## Proposed compiler contracts

The names below are implementation targets; adjust only when the code proves a
clearer boundary.

### Domain

Place pure PHP contracts and values under `includes/Domain/Email/`:

- `Block_Node`: stable name, schema version, normalized attributes, children,
  and a block-path identifier for diagnostics.
- `Target_Profile`: profile identifier and declared compatibility capabilities.
- `Render_Context`: target profile, email metadata, content snapshot, and
  resolved email design tokens.
- `Renderer_Interface`: supported block name/schema, normalize, validate,
  render HTML, and render plain text.
- `Renderer_Registry`: immutable block-name-to-renderer map.
- `Compile_Diagnostic`: stable code, severity, block path, message, and safe
  context.
- `Compile_Result`: HTML, plain text, diagnostics, referenced assets, compiler
  version, profile version, and fingerprint.

Domain objects must not call WordPress functions, persistence, REST, or providers.

### Workflow

Place orchestration under `includes/Workflow/Email/`:

- `Email_Compiler`: owns the ordered compilation pipeline.
- `Content_Snapshot_Builder`: resolves bindings through repository/query ports.
- `Template_Validator`: applies grammar, compatibility, accessibility, and
  compliance rules.
- `Artifact_Fingerprinter`: hashes normalized source, snapshot, metadata,
  compiler version, and profile version.

### Delivery integrations

- Bounded WordPress serialized-block parser.
- REST preview controller.
- HTML-download controller.
- Maintained public CSS inliner only if authored style sheets are introduced.
- Block renderer implementations composed into the registry.

There is no compatibility facade or parallel transport renderer.

## Block grammar v1

### Currently supported blocks

This is the exact compiler/editor allowlist, defined once in
`includes/Email_Blocks/email-blocks.json`:

| Block name                         | Role                            | Key constraints                                                       |
| ---------------------------------- | ------------------------------- | --------------------------------------------------------------------- |
| `campaignbridge/container`         | One document root               | Exactly one root; 320–900 px; locked                                  |
| `campaignbridge/section`           | Full-width content row          | Child of container; spacing/background                                |
| `core/paragraph`                   | Rich email text                 | Safe inline marks and HTTPS links only; left/center/right             |
| `core/heading`                     | Heading or bound post title                         | Levels 1–4; portable typography; no background                        |
| `core/image`                       | Email image                     | HTTPS URL, pixel dimensions, explicit alt choice; no caption or crop  |
| `core/buttons`                     | Button group                    | Children are `core/button` only; left/center/right                    |
| `core/button`                      | Bulletproof CTA                 | HTTPS URL; fill/outline/ghost; Outlook VML fallback                   |
| `core/list`                        | Ordered or unordered list       | Children are `core/list-item` only; no nested lists in v1             |
| `core/list-item`                   | List item                       | Safe inline rich text                                                 |
| `core/separator`                   | Horizontal divider              | Full width; colour only; thickness/style from the email design        |
| `core/spacer`                      | Vertical spacing                | 0–600 px; Core default 100 px                                         |
| `campaignbridge/post-card`         | Immutable post binding          | Child of container/section; snapshot needed; padding, background      |
| `campaignbridge/post-image`        | Featured image binding          | Child of post card or column; width, align, link-to-post, decorative  |
| `campaignbridge/preheader`         | Hidden inbox preview            | First child of container; at most one; 1-150 characters               |
| `campaignbridge/columns`           | One to six columns              | Child of section or post card; 1-6 columns; gap 0-48 px               |
| `campaignbridge/column`            | Column content                  | Child of columns; integer percentage width; flat (no nested columns)  |
| `campaignbridge/compliance-footer` | Sender and unsubscribe controls | Last child of container; at most one; address required                |

`core/columns` and `core/column` are intentionally not adopted; the reasons are
recorded in [`email-block-architecture.md`](email-block-architecture.md#remaining-campaignbridge-blocks).

### Planned v1 native blocks

The v1 grammar is complete. Later parity, commerce, transactional, and
engagement candidates remain in the planning-only
[`email-block-catalog.md`](email-block-catalog.md) and are not scheduled here.

Two-column rows stack through the document media query rather than a ghost-table
hybrid. Clients that ignore `@media` — desktop Outlook in particular — keep the
columns side by side at their declared widths, which is the intended desktop
result. That degradation is deliberate and is covered by the golden fixture.

Horizontal post cards are a composition, not a block attribute. A card places a
`columns` block inside itself and puts the image in one column and the copy in
the other, so any arrangement is expressible and the compiler needs no per-child
layout logic. The `Post card, media left` and `Post card, media right` patterns
ship that composition as a single insert. The post binding reaches the post
blocks through the columns wrapper because context flows down unchanged.

Post titles, excerpts, post bodies, and call-to-action buttons are the real Core
blocks, bound read-only to the snapshot through the one
`campaignbridge/post-data` Block Bindings source; see
[`email-block-architecture.md`](email-block-architecture.md#post-content-one-read-only-block-bindings-source).
Only `campaignbridge/post-image` stays custom, because Core cannot bind an
image's link destination or its intrinsic dimensions.

The unsubscribe destination is never a block attribute. The compliance footer
reads it from immutable render context metadata, so provider merge syntax stays
outside the block grammar and outside the renderer.

### Unsupported blocks

Only the Core blocks listed above are part of the grammar. Every other `core/*`
block and every third-party block is unsupported: the editor does not offer it
and the compiler returns a blocking diagnostic containing the exact block path
if it is received. Supported Core blocks with unsupported attributes, styles,
classes, or markup fail with `block.attribute.invalid`. There are no aliases or
migrations for the replaced `campaignbridge/text`, `heading`, `image`, `button`,
`divider`, and `spacer` blocks; pre-release templates that contain them must be
recreated with the Core blocks.

### Attribute policy

- Dimensions and spacing normalize to bounded integer pixels.
- Colors normalize to portable values or versioned CampaignBridge email tokens;
  unresolved WordPress CSS variables are compilation errors.
- Typography uses a small web-safe stack in the universal profile.
- Rich text permits only the inline markup explicitly declared by the text
  renderer.
- URLs are absolute HTTPS URLs except documented development fixtures and
  provider merge tokens.
- Attribute defaults live in one schema source consumed by editor controls and
  PHP normalization tests; duplicated defaults are contract-tested until they
  can be generated.

## Phased delivery

### Phase 0 — Clean compiler contract

Deliverables:

- Add representative serialized fixtures for every retained CampaignBridge
  block and explicit rejection fixtures for core, third-party, and malformed
  blocks.
- Add a `tests/Unit/Email/` suite and full-document golden fixture directory.
- Record the initial compiler/profile versioning rules.
- Add a supported-block matrix to documentation.

Exit gate:

- Every supported native shape compiles or produces an exact diagnostic.
- Tests fail if unsupported content disappears or produces a partial artifact.

### Phase 1 — Compiler kernel

Deliverables:

- Implement the domain values, renderer interface, registry, diagnostics, result,
  and fingerprint.
- Implement the bounded WordPress parser boundary and normalized block paths.
- Compose the registry in the plugin composition root without side effects.
- Cut isolated generator callers directly to `Email_Compiler`.
- Make unsupported blocks return blocking diagnostics.

Exit gate:

- Pure compiler tests run without provider or REST dependencies.
- Duplicate renderer registration and unsupported blocks fail deterministically.
- Identical normalized input produces identical output and fingerprint.

### Phase 2 — First native vertical slice — block/compiler complete

Deliverables:

- Port `campaignbridge/container` to the registry.
- Add native section, text, heading, image, button, divider, and spacer blocks.
- Implement the universal document shell, plain-text output, and bulletproof
  button renderer.
- Apply one global editor allowlist and a dedicated CampaignBridge Email category.
- Replace core-based starter patterns with native block patterns.
- Create new templates as drafts with one locked root container.

Exit gate:

- A draft containing native text, image, and CTA content compiles without a
  provider.
- Reloading the editor does not invalidate serialized blocks.
- Golden HTML includes presentation roles, inline critical styles, image
  dimensions/alt handling, and Outlook button fallback.

### Phase 3 — Editor and schema cutover

Deliverables:

- Remove core-block registration and core-based patterns from the email editor.
- Replace prototype CampaignBridge schemas directly with the documented native
  contracts.
- Recreate starter templates from native blocks and keep normal WordPress
  revisions for operator undo.
- Show unsupported-template diagnostics rather than conversion or compatibility
  states.

Exit gate:

- Every native fixture compiles and every non-native fixture produces a
  documented, actionable blocking diagnostic.
- The editor cannot insert a block the compiler does not support.

### Phase 4 — Immutable WordPress content bindings

Deliverables:

- Define the content snapshot schema for title, excerpt, canonical URL, image
  URL/dimensions/alt, post type, and source modification time.
- Resolve post-card bindings before rendering.
- Replace all live post reads in final renderers with snapshot access.
- Define missing/deleted/changed-post diagnostics and explicit refresh behavior.
- Port post card, title, excerpt, image, and CTA renderers.

Exit gate:

- Changing a WordPress post after approval cannot alter an approved artifact.
- Refreshing a snapshot changes the fingerprint and requires re-review.
- Missing content cannot silently produce an incomplete email.

### Phase 5 — Compiled preview and HTML export

Deliverables:

- Add `POST /campaignbridge/v1/email-preview` through a dedicated controller.
- Accept serialized content, template ID, profile, and unsaved metadata overrides
  through an explicit JSON schema.
- Require cookie authentication, REST nonce, and object-level template edit
  capability.
- Enforce request-size, block-count, nesting-depth, and rate limits.
- Compile without persistence, provider calls, or remote mutations.
- Return HTML, plain text, diagnostics, assets, versions, and fingerprint.
- Render HTML in a titled sandboxed iframe with no script/same-origin privileges.
- Add desktop/mobile viewport toggles, diagnostic navigation, HTML inspection,
  and secure artifact download.

Exit gate:

- The iframe and downloaded file use the same bytes/fingerprint.
- Permission, nonce, size, nesting, unsupported-block, and malicious-markup tests
  pass.
- Previewing never changes a template or contacts an email provider.

### Phase 6 — Inlining, compatibility, and compliance

Deliverables:

- If authored style sheets are introduced, evaluate a maintained public CSS
  inliner for license, security, package size, PHP compatibility, deterministic
  output, and release packaging.
- Add validators for forbidden elements/CSS, URL policy, image accessibility,
  preheader, physical address, unsubscribe controls, and sender metadata.
- Add representative Gmail, Apple Mail, Outlook.com, and desktop Outlook
  fixtures with documented degradation.
- Add optional hosted client screenshots as a release signal when a service is
  selected.

Exit gate:

- Critical output styles remain deterministic and self-contained.
- Compliance errors block approval; compatibility warnings are visible and
  stable.
- The release ZIP contains and verifies every required runtime dependency.

### Phase 7 — Compiler cutover

Deliverables:

- Make preview, HTML export, approval, and provider draft upload consume
  `Compile_Result`.
- Persist approved snapshot, versions, fingerprint, and final artifacts through
  repository ports.
- Keep `Email_Compiler` as the only transport rendering path.
- Verify removed prototype renderer classes have no callers or packaged files.
- Document compiler/profile upgrade and reapproval rules.

Exit gate:

- One source template and snapshot yield one artifact across every consumer.
- Provider adapters cannot accept unvalidated editor HTML.
- An artifact version change is auditable and cannot alter a previously approved
  send silently.

## Preview endpoint contract

Proposed request:

```json
{
  "template_id": 123,
  "content": "<!-- wp:campaignbridge/container ... -->",
  "profile": "universal",
  "metadata": {
    "subject": "Weekly update",
    "preheader": "This week in...",
    "sender_name": "Example",
    "sender_email": "editor@example.test"
  }
}
```

Proposed success response:

```json
{
  "html": "<!doctype html>...",
  "text": "Weekly update...",
  "diagnostics": [],
  "assets": [],
  "compiler_version": "5",
  "profile_version": "universal@1",
  "fingerprint": "sha256:..."
}
```

Error responses use stable codes and explicit HTTP status values. Compiler
validation errors may return a structured result with no HTML artifact; they are
not converted into generic 500 responses.

## Testing matrix

| Layer            | Required coverage                                                  |
| ---------------- | ------------------------------------------------------------------ |
| Domain           | normalization, bounds, nesting, diagnostics, fingerprint stability |
| Renderer         | escaping, defaults, HTML, plain text, profile behavior             |
| Golden documents | full HTML/text bytes for representative templates                  |
| Serialization    | production schema round trips and explicit unsupported diagnostics |
| Integration      | parser, registry composition, snapshots, REST wiring               |
| Security         | capabilities, nonce, limits, malicious URLs/markup, redaction      |
| Accessibility    | alt decisions, heading order warnings, preview controls            |
| Performance      | measured block/document budgets and bounded preview requests       |
| Browser E2E      | author, autosave, reload, compile, diagnose, preview, download     |
| Email clients    | documented fixtures and optional hosted screenshots                |

Golden fixture changes require a reviewable explanation. Regenerating snapshots
without inspecting the semantic diff is not an accepted test update.

## Remaining pull-request sequence

Keep each pull request independently releasable:

1. Content snapshot resolver, draft semantics, and approval persistence.
2. Preview REST controller and compiler response schema.
3. Sandboxed preview UI and HTML download.
4. Compatibility validators and the remaining compliance checks. The preheader,
   columns, and compliance-footer blocks are implemented; the footer already
   fails closed on a missing address or unsubscribe URL.
5. Approval and provider workflow cutover.

Do not combine provider sending changes with the compiler kernel or block
migrations. That would make artifact regressions and irreversible delivery risk
too difficult to isolate.

## Milestone 1 definition of done

The email-native compiler milestone is complete when:

- new templates expose only the documented native grammar;
- prototype templates are rejected unless they use the current native grammar;
- unsupported content fails with an actionable block-path diagnostic;
- compiled preview and HTML download are byte-identical;
- the same approved source/snapshot/profile produces the same fingerprint;
- required compliance data is validated before approval;
- no renderer reads live WordPress content or provider state;
- no transport workflow accepts arbitrary editor/rendered HTML;
- the maintained inliner and all runtime dependencies pass package verification;
- unit, golden, integration, security, accessibility, performance, and browser
  gates pass.

## First work package — implemented

Begin with the direct compiler cutover:

1. Added the pure compiler contracts, O(1) renderer registry, structured
   diagnostics, profile/version constants, and deterministic fingerprint.
2. Implemented the document/container and retained post-binding renderers against
   an immutable snapshot supplied by the caller.
3. Replaced the isolated generator paths and deleted `Email_Generator`,
   `BlockProcessor`, `CssProcessor`, and `EmailStructure`.
4. Removed PHP `render.php` transport output from block metadata so the canonical
   compiler is the only rendering path.
5. Added golden HTML/plain-text, unsupported-input, deterministic-output, escaping,
   depth/block-budget, and measured performance tests.

The first native authoring vertical slice is implemented. The next pull request
focuses on content snapshot resolution, draft semantics, and approval persistence.
