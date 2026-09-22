# Email block catalog and expansion map

## Purpose

This document maps the email-native blocks CampaignBridge may add after the
initial compiler work. It is a planning catalog, not a promise that every item
will ship and not a stable schema contract. A proposed block name becomes a
versioned public contract only when its schema, renderer, migrations, and
fixtures are accepted in an implementation change.

The catalog is informed by the common building surfaces in Mailchimp and
Omnisend, but CampaignBridge should compete through dependable email output and
native WordPress content workflows rather than block count alone.

## Classification rules

Every catalog item must be classified before implementation:

- **Native block** — owns distinct semantic attributes, validation, HTML and
  plain-text behavior.
- **Binding block** — resolves WordPress, commerce, recipient, or event data into
  an immutable snapshot before using a renderer.
- **Pattern** — a reusable composition of existing blocks with no new transport
  semantics.
- **Editor capability** — behavior such as responsive controls or saved sections
  that should not create a new serialized block type.
- **Integration feature** — depends on a provider or hosted service and cannot be
  represented honestly as a self-contained universal email block.

Prefer patterns when existing primitives can produce the output. Do not add a
renderer merely to give a visual composition a separate inserter label.

## Current foundation

The v1 grammar and its delivery order remain defined in
[`email-block-implementation-plan.md`](email-block-implementation-plan.md). The
following are the foundation for every later wave.

Supported WordPress Core blocks are authored natively and normalized into
CampaignBridge email semantics (WordPress Native First); see
[`email-block-architecture.md`](email-block-architecture.md#supported-authoring-contract).

| Block                              | Classification | Purpose                                         | Status             |
| ---------------------------------- | -------------- | ----------------------------------------------- | ------------------ |
| `campaignbridge/container`         | Native block   | Locked email document root and width contract   | Compiler-supported |
| `campaignbridge/preheader`         | Native block   | Hidden inbox preview text                       | Compiler-supported |
| `campaignbridge/section`           | Native block   | Full-width content row                          | Compiler-supported |
| `campaignbridge/columns`           | Native block   | One to six columns with optional mobile stacking | Compiler-supported |
| `campaignbridge/column`            | Native block   | Constrained child of columns                    | Compiler-supported |
| `core/paragraph`                   | Core block     | Safe rich text and HTTPS links                  | Compiler-supported |
| `core/heading`                     | Core block     | Portable heading levels 1–4 and typography      | Compiler-supported |
| `core/image`                       | Core block     | Sized, linked image; Brand Logo binds frozen Brand Kit asset | Compiler-supported |
| `core/buttons` + `core/button`     | Core block     | Aligned bulletproof calls to action             | Compiler-supported |
| `core/list` + `core/list-item`     | Core block     | Flat ordered or unordered list                  | Compiler-supported |
| `core/separator`                   | Core block     | Full-width design divider                       | Compiler-supported |
| `core/spacer`                      | Core block     | Bounded vertical spacing                        | Compiler-supported |
| `core/social-links` + `core/social-link` | Core blocks | One to six accessible HTTPS social links with packaged PNG icons | Compiler-supported |
| `campaignbridge/compliance-footer` | Native block   | Address, unsubscribe, and required controls     | Compiler-supported |

The view-online link once grouped with the preheader is not implemented. It is a
separate document-level concern with its own hosted-URL dependency, and the
template already stores `campaignbridge_view_online_url` for it.

## Wave A — general builder parity

Start this wave only after the compiler registry, compiled preview, compliance
validation, and universal-profile fixtures are operational.
Issue #72 remains the merge and release gate for new Wave A output. Navigation
and Brand Logo both participate in the representative compatibility matrix.

| Proposed block                | Classification | Output and constraints                                     | Reuse/dependency                                           |
| ----------------------------- | -------------- | ---------------------------------------------------------- | ---------------------------------------------------------- |
| `core/image` Brand Logo       | Core binding   | Shipped: bounded logo, alt text, optional homepage link    | `campaignbridge/brand-data`; reuses image renderer         |
| `campaignbridge/navigation`   | Native block   | Shipped: one to five explicit HTTPS links; mobile stack      | No live menu lookup; plain-text links                       |
| `core/social-links` + `core/social-link` | Core blocks | Shipped: one to six accessible HTTPS links across eight approved services | Packaged PNG assets and plain-text URLs |
| `campaignbridge/video`        | Binding block  | Linked poster/thumbnail with play treatment                | Never emits an iframe or playable embed                    |

The following parity items are not part of Wave A:

- **Custom HTML** remains unavailable in the universal profile. A future expert
  profile may admit a narrow sanitized fragment only after forbidden markup,
  CSS, URL, and compatibility validators are proven.
- **Countdown timers** require a hosted, time-aware image service or an explicit
  static fallback and therefore belong to the integration wave.
- **Survey forms** cannot be embedded in universal email markup; a survey item
  is a linked card backed by a hosted form.

## Wave B — WordPress-native content

This wave is CampaignBridge's primary product advantage. All bindings resolve
before rendering and are frozen in the campaign snapshot.

| Proposed block                | Classification | Purpose                                         | Reuse/dependency                                       |
| ----------------------------- | -------------- | ----------------------------------------------- | ------------------------------------------------------ |
| `campaignbridge/post-card`    | Binding block  | One selected post or custom post type           | Compiler-supported; immutable snapshot required        |
| `core/heading` (bound)        | Core block     | Post title, optionally linked to the post       | Shipped; `campaignbridge/post-data` binding source     |
| `campaignbridge/post-image`   | Binding block  | Featured image within a post composition        | Compiler-supported                                     |
| `core/paragraph` (bound)      | Core block     | Bounded plain-text excerpt or post body         | Shipped; `campaignbridge/post-data` binding source     |
| `core/button` (bound)         | Core block     | Post-aware button or text link                  | Shipped; bulletproof renderer                          |
| `campaignbridge/post-query`   | Binding block  | Snapshot a bounded, ordered collection of posts | Requires query budget and empty-result policy          |
| `campaignbridge/post-list`    | Binding block  | Render the immutable result of a post query     | Requires deterministic item template and maximum count |

Featured article, latest posts, category digest, event listing, and author digest
should initially be patterns over the post blocks. The media-beside-copy card is
already shipped that way: `Post card, media left` and `Post card, media right`
compose `columns` inside `post-card` rather than adding a layout attribute. Custom post types use the same
binding contract rather than receiving a renderer per post type.

## Wave C — personalization and conditional content

These blocks depend on a provider-neutral recipient and merge-field vocabulary.
Provider adapters translate that vocabulary at the final transport boundary.

| Proposed block                     | Classification | Purpose                                              | Key constraint                                        |
| ---------------------------------- | -------------- | ---------------------------------------------------- | ----------------------------------------------------- |
| `campaignbridge/personalized-text` | Binding block  | Text with typed merge fields and safe fallbacks      | Required fallback for any value that may be absent    |
| `campaignbridge/conditional`       | Binding block  | Include one bounded branch when a typed rule matches | No arbitrary PHP, JavaScript, or provider expressions |
| `campaignbridge/fallback`          | Binding block  | Explicit alternate branch for conditional content    | Must compile and validate independently               |

If the text renderer can safely own typed merge tokens, personalized text should
remain a capability of `core/paragraph` text semantics instead of becoming a separate
block. The schema investigation decides this before implementation.

## Wave D — commerce catalog

Commerce blocks remain provider-neutral. A WooCommerce repository adapter is the
first expected source, but renderers consume only immutable normalized snapshots.

| Proposed block                           | Classification | Purpose                                          | Dependency                                        |
| ---------------------------------------- | -------------- | ------------------------------------------------ | ------------------------------------------------- |
| `campaignbridge/product-card`            | Binding block  | Product image, title, price, and CTA             | Commerce snapshot schema                          |
| `campaignbridge/product-list`            | Binding block  | Bounded manually selected product collection     | Deterministic item order and stock policy         |
| `campaignbridge/product-query`           | Binding block  | Snapshot products by category or rule            | Query budget and empty-result policy              |
| `campaignbridge/product-recommendations` | Binding block  | Recipient-aware recommended products             | Recommendation source, consent, fallback products |
| `campaignbridge/discount`                | Binding block  | Manual or generated coupon, expiry, and CTA      | Coupon source and per-recipient issuance policy   |
| `campaignbridge/review-request`          | Binding block  | Product review prompt linked to an external page | Verified order/product context                    |

Product hero, product grid, collection promotion, and coupon banner are patterns
over these bindings and foundation blocks.

## Wave E — event and transactional commerce

Do not start this wave until CampaignBridge has an event model, automation
trigger contract, recipient-safe preview fixtures, and idempotent snapshot
creation. These blocks appear only when the selected trigger supplies the
required data.

| Proposed block                      | Classification | Purpose                                       | Required event context               |
| ----------------------------------- | -------------- | --------------------------------------------- | ------------------------------------ |
| `campaignbridge/abandoned-products` | Binding block  | Products left in a cart or checkout           | Cart or checkout abandonment         |
| `campaignbridge/order-details`      | Binding block  | Order number, date, and status                | Placed or updated order              |
| `campaignbridge/ordered-products`   | Binding block  | Immutable line items                          | Placed or updated order              |
| `campaignbridge/order-totals`       | Binding block  | Subtotal, discounts, tax, shipping, and total | Placed or updated order              |
| `campaignbridge/customer-address`   | Binding block  | Selected billing or shipping fields           | Order plus explicit PII policy       |
| `campaignbridge/payment-cta`        | Binding block  | Secure link to an external payment flow       | Payment-capable commerce integration |

Sensitive order and address data must be minimized in previews, logs, fixtures,
and artifacts. A missing trigger context is a blocking diagnostic, never an empty
placeholder in an approved campaign.

## Wave F — engagement and integrations

| Proposed item        | Classification                   | Purpose                                            | Boundary                                           |
| -------------------- | -------------------------------- | -------------------------------------------------- | -------------------------------------------------- |
| Countdown            | Integration feature              | Time-sensitive visual with static fallback         | Requires approved hosted image service; no scripts |
| Survey               | Integration feature plus pattern | Linked prompt to a hosted survey                   | No embedded form in universal output               |
| Poll/rating          | Integration feature plus pattern | One-click tracked response links                   | Requires signed links and consent-aware tracking   |
| Integration content  | Extension contract               | Normalize content from an approved external system | No arbitrary provider response shapes in renderers |
| Expert HTML fragment | Restricted native block          | Narrow escape hatch for audited operators          | Non-universal profile; sanitized and warning-gated |

An "Apps" block should not be a generic remote-markup container. Each supported
integration maps external data into a declared snapshot schema and a registered
renderer or an existing native block composition.

## Pattern catalog

These are reusable compositions, not new transport renderers:

- newsletter header with logo and navigation;
- hero image, heading, text, and CTA;
- image-and-text rows in both orientations;
- featured article and multi-article digest;
- two- and three-item product presentations;
- coupon banner;
- video feature card;
- social and compliance footer;
- survey or rating prompt;
- order confirmation composition.

Patterns must use only allowed CampaignBridge blocks and must pass the same
full-document golden fixtures as hand-authored compositions.

## Builder capabilities that are not blocks

The roadmap must account for these alongside block delivery:

- one global inserter allowlist and nesting rules;
- one- through four-column authoring presets with tested responsive behavior;
- mobile stacking order and bounded desktop/mobile style overrides;
- global brand tokens and versioned brand assets;
- typed merge-field insertion and fallback validation;
- saved sections, starter templates, and non-synced pattern insertion;
- undo/redo and non-destructive migrations through WordPress revisions;
- immutable sample-contact, post, product, cart, and order preview data;
- compiled desktop/mobile preview, plain-text preview, and test sends;
- per-block compatibility diagnostics and approval gates.

Reusable sections must be copied or snapshotted into a campaign before approval.
Approved artifacts cannot change because a globally synced section was edited
later.

## Promotion gate for every catalog item

Before moving an item from this map into an implementation milestone:

1. Confirm that it cannot be represented more safely as a pattern or capability.
2. Define its stable semantic attributes, nesting, limits, and migration policy.
3. Define snapshot inputs and missing/stale-data behavior for every binding.
4. Register explicit HTML and plain-text behavior for each supported profile.
5. Add schema, escaping, nesting, deterministic-output, and diagnostic tests.
6. Add golden HTML/plain-text fixtures and representative client degradation.
7. Update the supported-block matrix and editor allowlist from the same contract.

Unsupported or incomplete catalog items stay out of the inserter. They must not
fall back to generic `render_block()` output or disappear from compiled email.
