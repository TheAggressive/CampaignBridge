# WordPress content snapshots and review inputs

## Canonical content path

`Snapshot_References` walks parsed blocks recursively and collects distinct post
ID/type references in first-seen order. `Post_Snapshot_Repository` is the sole
WordPress content resolver. It returns immutable `Post_Snapshot` objects keyed
by post ID; unresolved references are absent.

The version-1 post contract contains source ID, source post type, and the
allowlisted values `title`, `excerpt`, `url`, optional `image`, `postParentUrl`,
and `postTypeArchiveUrl`. An image contains URL, alt text, width, and height.
Unknown fields, malformed values, and unsupported schema versions are rejected.
Arbitrary post meta and provider/audience data are not part of this contract.
An empty title or excerpt is representable; the title renderer still reports a
missing title when the selected block requires one.

Fresh capture resolves published, publicly viewable posts, pages, and custom
post types only. Private, draft, trashed, password-protected, deleted, and
non-public sources are omitted, including for administrators. Missing sources
produce `post.snapshot.missing`, with no partial artifact or live-content
substitution. A supplied snapshot with the wrong source ID/type produces
`post.snapshot.mismatch`. Unsupported block binding attributes produce
`block.attributes.unsupported`.

## Typed rendering

`Render_Context::post_snapshot()` returns the canonical object. The post-card
renderer passes that same instance into `with_post_binding()`; child renderers
and the shared button/link destination helper read `post_binding()`. No post
values array is stored beside the object. Generic array bindings remain
available for non-post consumers. The temporary post values view in
`Render_Context::snapshot()` has been removed.

Typed scope is transient traversal state. Setting, replacing, clearing, or
copying it does not change fingerprint input. Sibling cards receive independent
scopes. Renderers do not query WordPress or invoke a repository.

## Capture, compile, and refresh

`Template_Preview` exposes the same path to ephemeral editor previews and callers
that need repeatable review input:

```php
$preview = new Template_Preview( new Post_Snapshot_Repository(), $brand_kit );
$input = $preview->capture( $serialized_template, $metadata );
$artifact = $preview->compile_frozen( $input );

// Retain both values when a reviewer accepts a successful artifact.
$reviewed_revision = $input->revision();
$reviewed_fingerprint = $artifact->fingerprint();

// Only an explicit refresh reads current WordPress content again.
$replacement = $preview->refresh( $input );
$still_reviewed = $preview->matches_review(
    $replacement,
    $reviewed_revision,
    $reviewed_fingerprint
); // false: refresh advances the content revision, even if values match.
```

`Review_Input` groups the existing canonical context and resolved design with
the frozen template, capture revision, and compiler version. It is not a second
post schema or resolver. `compile_frozen()` neither resolves current content nor
reloads the active design. A later WordPress edit, visibility change, deletion,
or Brand Kit change cannot change compilation from that input.

`refresh()` replaces the post collection and increments the capture revision.
It retains the frozen template, metadata, design, and compiler requirement.
Template/design changes require a new capture and review. The schema version
remains 1: a content refresh is not a schema migration. An unavailable captured
compiler fails closed with `snapshot.compiler.unsupported`; recapture is
required to adopt a new compiler.

`matches_review()` requires a successful compilation, a nonempty matching
artifact fingerprint, and the reviewed capture revision. Changed values change
the artifact fingerprint; an unchanged refresh keeps the same deterministic
artifact but still invalidates the previous review through its new revision.
Failed refreshes cannot inherit approval.

The existing `compile()` editor API performs a fresh capture followed by frozen
compilation. Editor preview is deliberately ephemeral; it does not persist or
approve a campaign.

## Determinism and boundaries

Context fingerprint input uses each object's canonical
`Post_Snapshot::fingerprint_payload()`, with post keys in deterministic map
order. `Artifact_Fingerprinter` remains the single compiler hashing mechanism:
it sorts associative maps recursively and preserves list order. The artifact
includes template source, canonical content, metadata, resolved design identity,
compiler/profile versions, HTML, text, and referenced assets. Equivalent input
produces identical bytes and fingerprints regardless of object identity or map
insertion order.

This M1 contract supplies frozen inputs and review invalidation rules. Durable
storage of inputs/artifacts and reviewed revision/fingerprint pairs belongs to
M2 (#73/#74), together with authorization, atomic replacement, audit history,
and campaign state transitions. No persistence, approval UI, provider delivery,
or personalization implementation is introduced here. Future campaign consumers
must apply `matches_review()` before relying on a previous review.

Referenced image/font URLs and image dimensions are frozen, not the remote
binary resources. Reproducibility here means compiler HTML, text, asset records,
and fingerprint; it does not archive files served at those URLs.

## Evidence

- `Post_Snapshot_Test`: bounded schema, rejected fields, canonical serialization,
  round trips, and immutable values.
- `Post_Snapshot_Repository_Test`: resolved identity/values, absent sources,
  allowlisted metadata, and repository-to-preview compilation.
- `Render_Context_Test`: exact object identity, immutable copies, scoped state
  excluded from fingerprints, canonical serialization and collection ordering.
- `Post_Snapshot_Renderer_Test`: exact scoped object, sibling isolation, raw-array
  rejection, source mismatch, and unsupported-binding diagnostics.
- `Email_Compiler_Test` and post renderer fixtures: unchanged golden HTML/text,
  assets, destination behavior, and deterministic canonical fingerprints.
- `Review_Input_Test`: frozen output after source edits/deletion/privacy changes,
  explicit refresh and review invalidation, protected/non-public source omission,
  frozen design, and unavailable-compiler diagnostics.
