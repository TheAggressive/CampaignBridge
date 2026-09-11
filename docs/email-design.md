# Email design contract

CampaignBridge's v1 email design contract is the packaged
[`email.json`](../includes/Email_Design/email.json), validated by the adjacent
[`email-design-v1.schema.json`](../includes/Email_Design/email-design-v1.schema.json).
It is an email-safe design language, not a subset implementation of every
`theme.json` feature.

## V1 scope

V1 defines:

- one content width from 320px through 900px;
- a bounded semantic color palette using six-digit hexadecimal values;
- a bounded font-size and spacing scale using whole pixel values;
- font choices by slug from CampaignBridge's curated font catalog;
- global color and typography defaults;
- block defaults for the currently declared heading, text, button, columns,
  divider, spacer, post-title, post-excerpt, post-link, and post-button surfaces.

Custom color, font-size, and spacing creation is disabled in the v1 editor.
Existing explicit raw values remain a compiler compatibility concern and do not
expand the manifest vocabulary.

## Brand Kit and design responsibilities

Brand Kit answers what the brand is: semantic identity colors, font roles, and
the validated custom Google Font. `email.json` answers how an email uses the
available identity: layout, available presets, typography, spacing, and block
defaults. Brand Kit semantic values are merged before manifest design rules are
applied; explicit block values win last.

The standard color slugs (`text`, `secondary`, `background`, `card`, `border`,
`brand`, and `on-brand`) are identity slots. The packaged values are safe
fallbacks. The active Brand Kit supplies the effective values for those slots.

## References and normalization

Persisted preset references use Gutenberg syntax:

```text
var:preset|color|brand
var:preset|font-size|medium
var:preset|font-family|arial
var:preset|spacing|50
```

References are exact and typed. Normalization looks up the slug in the matching
resolved catalog and converts it to an email-safe literal. It does not coerce an
unknown kind or search other catalogs. Final HTML must contain neither
`var:preset|` references nor unresolved CSS variables.

## Supported vocabulary

| Location | V1 properties |
| --- | --- |
| Global settings | `layout.contentWidth`, color palette/custom switch, font families/sizes/custom switch, spacing sizes/custom switch |
| Global styles | background/text color; font family, size, weight, and line height |
| Heading/text/post title/post excerpt | color, typography, bottom spacing |
| Button/post button | background/text color and font family |
| Columns | block gap |
| Divider | color, safe border style, and width |
| Spacer | minimum height |
| Post link | text color |

The schema is closed at every object boundary. Arbitrary `css`, selectors,
unknown properties, unknown block names, literal font stacks, font URLs, and
unsupported styles are invalid. Renderer target profiles can still reject or
warn about a schema-valid value when client compatibility requires it.

## Diagnostics

Phase 1 should use stable codes headed by:

- `design.unsupported_version`
- `design.invalid_property`
- `design.invalid_color`
- `design.invalid_font`
- `design.invalid_spacing`
- `design.invalid_block`
- `design.unsupported_block_style`
- `design.unsafe_value`
- `design.unresolved_preset`

Schema or normalization failures that prevent deterministic rendering are
blocking. Compatibility limitations that have a defined safe representation may
be warnings.

See [ADR 0001](decisions/0001-email-design-contract.md) for precedence,
ownership, and versioning decisions.
