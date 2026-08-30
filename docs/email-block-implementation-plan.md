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
- Rewriting existing template content without a versioned migration and backup.

## Current baseline and risks

The existing editor and blocks are a useful foundation, but the rendering
contract is not yet safe to extend:

- There are two independent output paths: block `render.php` files and
  `Services\Email\BlockProcessor`. They disagree on attributes and output.
- The compiler routes `campaignbridge/post-button`, while the registered block is
  `campaignbridge/post-cta`.
- The excerpt block stores `maxWords`, while the compiler reads `wordCount`.
- The compiler's container path ignores the current max-width and padding schema.
- Post-card compiler output is mock content; other post renderers query live
  WordPress data during rendering.
- Unknown blocks produce an HTML comment instead of a blocking diagnostic.
- `CssProcessor` is explicitly a placeholder rather than a production inliner.
- The standalone editor registers every core block and does not apply one global
  email-block allowlist.
- Existing patterns depend on core columns/buttons even though the container's
  child allowlist does not permit them.
- New templates and every autosave are published immediately.
- Email generation has performance assertions but no renderer unit suite, golden
  documents, migration fixtures, or compatibility validator.

These are migration inputs, not reasons for a rewrite. Existing serialized block
names remain stable until an explicit migration exists.

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
6. **Use explicit legacy adapters.** Supported core blocks remain readable for
   existing templates but disappear from the new-template inserter after their
   native replacements ship.
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
  → WordPress parser adapter
  → provider-neutral Block_Node tree
  → grammar and nesting validation
  → content snapshot resolution
  → renderer registry
  → HTML + plain-text document assembly
  → maintained CSS inliner
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

### Delivery adapters

- WordPress serialized-block parser adapter.
- REST preview controller.
- HTML-download controller.
- Public CSS-inliner adapter selected through the dependency policy.
- Block renderer implementations composed into the registry.

The current `Email_Generator` remains a compatibility facade until every caller
uses `Email_Compiler`. `BlockProcessor` is retired only after legacy fixtures
compile through registered adapters.

## Block grammar v1

### Native blocks

| Block name                         | Role                     | Key constraints                                |
| ---------------------------------- | ------------------------ | ---------------------------------------------- |
| `campaignbridge/container`         | One document root        | Exactly one root; 320–900 px; locked           |
| `campaignbridge/preheader`         | Hidden inbox preview     | At most one; plain constrained text            |
| `campaignbridge/section`           | Full-width content row   | Child of container; spacing/background         |
| `campaignbridge/columns`           | One or two columns       | Child of section; stacks predictably           |
| `campaignbridge/column`            | Column content           | Child of columns only                          |
| `campaignbridge/text`              | Rich email text          | Safe inline marks/links only                   |
| `campaignbridge/heading`           | Heading                  | Levels 1–4; explicit portable typography       |
| `campaignbridge/image`             | Email image              | HTTPS URL, dimensions, alt/decorative decision |
| `campaignbridge/button`            | Bulletproof CTA          | HTTPS URL, label, alignment, VML fallback      |
| `campaignbridge/divider`           | Horizontal rule          | Width, color, and style allowlist              |
| `campaignbridge/spacer`            | Vertical spacing         | Bounded pixel height                           |
| `campaignbridge/compliance-footer` | Required footer controls | Address and unsubscribe tokens                 |

The existing post-card, post-image, post-title, post-excerpt, and post-CTA blocks
remain supported content-binding blocks. Their renderers consume snapshot data.

### Legacy adapters

Initially read these core blocks when they already exist in templates:

- `core/paragraph`
- `core/heading`
- `core/image`
- `core/buttons` and `core/button`
- `core/columns` and `core/column`
- `core/separator`
- `core/spacer`
- `core/group`

Each adapter has an explicit supported-attribute matrix and a diagnostic for
ignored/unsupported settings. No generic core-block fallback is allowed.

Legacy adapters are migration bridges. New templates use native blocks, and the
editor offers an explicit “Convert to CampaignBridge blocks” action once
round-trip fixtures prove it is safe.

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

### Phase 0 — Baseline and safety net

Deliverables:

- Add representative serialized fixtures for every existing CampaignBridge
  block and currently supported core block.
- Add fixtures that expose the current CTA-name, excerpt-attribute, container,
  unknown-block, and live-content discrepancies.
- Add a `tests/Unit/Email/` suite and full-document golden fixture directory.
- Record the initial compiler/profile versioning rules.
- Add a supported-block matrix to documentation.

Exit gate:

- Every known legacy shape has a fixture and an expected migration or diagnostic.
- Tests fail if a supported block silently disappears.

### Phase 1 — Compiler kernel

Deliverables:

- Implement the domain values, renderer interface, registry, diagnostics, result,
  and fingerprint.
- Implement the WordPress parser adapter and normalized block paths.
- Compose the registry in the plugin composition root without side effects.
- Wrap the new compiler behind `Email_Generator` for compatibility.
- Make unsupported blocks return blocking diagnostics.

Exit gate:

- Pure compiler tests run without provider or REST dependencies.
- Duplicate renderer registration and unsupported blocks fail deterministically.
- Identical normalized input produces identical output and fingerprint.

### Phase 2 — First native vertical slice

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

### Phase 3 — Legacy parity and migration

Deliverables:

- Implement explicit legacy core-block adapters.
- Correct existing CampaignBridge block schema mismatches through renderer
  aliases and versioned migrations.
- Add non-destructive conversion previews and template backups/revisions.
- Show template-level migration state: native, legacy-compatible, migration
  available, or blocked.

Exit gate:

- Every baseline fixture either compiles successfully or produces a documented,
  actionable blocking diagnostic.
- Migration is idempotent and undoable through WordPress revisions.

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

- Evaluate a maintained public CSS inliner for license, security, package size,
  PHP compatibility, deterministic output, and release packaging.
- Replace the placeholder inliner behind an adapter.
- Add validators for forbidden elements/CSS, URL policy, image accessibility,
  preheader, physical address, unsubscribe controls, and sender metadata.
- Add representative Gmail, Apple Mail, Outlook.com, and desktop Outlook
  fixtures with documented degradation.
- Add optional hosted client screenshots as a release signal when a service is
  selected.

Exit gate:

- No placeholder inliner remains in the production path.
- Compliance errors block approval; compatibility warnings are visible and
  stable.
- The release ZIP contains and verifies every required runtime dependency.

### Phase 7 — Compiler cutover

Deliverables:

- Make preview, HTML export, approval, and provider draft upload consume
  `Compile_Result`.
- Persist approved snapshot, versions, fingerprint, and final artifacts through
  repository ports.
- Remove direct use of block `render.php` output from transport generation.
- Retire `BlockProcessor` after telemetry/tests show no unsupported callers.
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
  "compiler_version": "1",
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
| Migration        | every serialized legacy version, idempotency, revision recovery    |
| Integration      | parser, registry composition, snapshots, REST wiring               |
| Security         | capabilities, nonce, limits, malicious URLs/markup, redaction      |
| Accessibility    | alt decisions, heading order warnings, preview controls            |
| Performance      | measured block/document budgets and bounded preview requests       |
| Browser E2E      | author, autosave, reload, compile, diagnose, preview, download     |
| Email clients    | documented fixtures and optional hosted screenshots                |

Golden fixture changes require a reviewable explanation. Regenerating snapshots
without inspecting the semantic diff is not an accepted test update.

## Pull-request sequence

Keep each pull request independently releasable:

1. Baseline fixtures and compiler test harness.
2. Domain contracts, registry, diagnostics, and fingerprint.
3. Document/container renderer and compatibility facade.
4. Native section/text/heading/image/button/divider/spacer blocks.
5. Editor allowlist, native patterns, and draft semantics.
6. Legacy core adapters and CampaignBridge mismatch migrations.
7. Content snapshot schema and post-binding renderers.
8. Preview REST controller and compiler response schema.
9. Sandboxed preview UI and HTML download.
10. Public CSS inliner adapter and compatibility/compliance validators.
11. Approval/provider cutover and old processor retirement.

Do not combine provider sending changes with the compiler kernel or block
migrations. That would make artifact regressions and irreversible delivery risk
too difficult to isolate.

## Milestone 1 definition of done

The email-native compiler milestone is complete when:

- new templates expose only the documented native grammar;
- existing supported templates remain readable through adapters or migrations;
- unsupported content fails with an actionable block-path diagnostic;
- compiled preview and HTML download are byte-identical;
- the same approved source/snapshot/profile produces the same fingerprint;
- required compliance data is validated before approval;
- no renderer reads live WordPress content or provider state;
- no transport workflow accepts arbitrary editor/rendered HTML;
- the maintained inliner and all runtime dependencies pass package verification;
- unit, golden, integration, security, accessibility, performance, and browser
  gates pass.

## First work package

Begin with pull request 1 only:

1. Create `tests/Fixtures/Email/legacy/` using real serialized examples of the
   six current CampaignBridge blocks and supported core blocks.
2. Add focused tests that demonstrate the known name/attribute/container and
   unknown-block behavior without changing production output yet.
3. Define compiler/profile version constants and fixture review rules.
4. Add a short supported-block matrix generated from the fixture inventory.

This establishes the rollback and compatibility boundary needed before changing
the compiler or editor.
