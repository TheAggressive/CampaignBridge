# Email-client compatibility fixtures

CampaignBridge claims that its universal target profile (`universal@1`) renders
predictably in a named set of email clients. This document describes the
repository-owned evidence for that claim, what it proves, and what it does not.

The suite is `tests/Unit/Email/Client_Compatibility_Test.php`.

## What this is, and what it is not

These fixtures are **structural regression evidence**. They compile
representative documents through the production compiler and assert that the
markup properties each target client depends on are still present.

They are **not** a rendering test. No message is opened in Outlook, Gmail, or
Apple Mail. Nothing here proves visual parity, and no fixture should be cited as
proof that a message "looks correct" in a client.

A hosted screenshot service such as Litmus or Email on Acid remains a separate,
optional release signal. It is not a replacement for these deterministic checks,
and these checks are not a replacement for it.

## How the pieces fit together

| Path                                                    | Role                                                             |
| ------------------------------------------------------- | ---------------------------------------------------------------- |
| `tests/Fixtures/Email/compatibility/scenarios.php`      | Input documents and render context for the matrix                |
| `tests/Fixtures/Email/compatibility/clients/*.json`     | Per-client structural expectations and declared limitations      |
| `tests/Support/Email/Compatibility_Scenarios.php`       | Compiles a scenario through the production compiler graph        |
| `tests/Support/Email/Client_Expectations.php`           | Evaluates one client's expectations against a compiled artifact  |
| `tests/Fixtures/Email/golden/*`                         | Separate byte-exact golden artifacts; unchanged by this suite    |

Golden fixtures and client expectations are deliberately kept apart. A golden
fixture pins exact output so any change is reviewed. A client expectation pins a
*property* so the output can evolve freely as long as the property survives.

## Scenario matrix

| Scenario               | Covers                                                                                                   |
| ---------------------- | -------------------------------------------------------------------------------------------------------- |
| `universal-newsletter` | Document shell, preheader, section, bound brand logo, navigation, social links, image, rich text and links, list, button, divider, spacer, compliance footer |
| `stacked-columns`      | Three columns, mobile stacking, column gap, per-column image and button                                  |
| `branded-typography`   | Brand kit web font and resolved design colours across heading levels and all three button variants       |

Every scenario must compile without diagnostics and produce both HTML and plain
text. A scenario is an input; it is never an expected result.

## Client profiles

| Profile             | Engine                        | What its expectations prove                                                                                      |
| ------------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `universal-profile` | All target clients            | No active or unsupported content, absolute HTTP(S) destinations, image dimensions and alternative text            |
| `outlook-word`      | Microsoft Word (Windows)      | Layout tables reset their chrome, widths are HTML attributes, DPI is pinned, every CTA carries a VML fallback     |
| `gmail`             | Gmail HTML and CSS handling    | Typography and colour reach text inline, and fluid dimensions remain available if a stylesheet is ignored |
| `apple-mail`        | WebKit                        | Reformatting and text-inflation opt-outs, responsive viewport, accessible heading and encoding declarations        |

## Rule vocabulary

A client fixture may only use the rules `Client_Expectations::RULES` declares.
This is a bounded structural vocabulary, not an expression language, and the
suite fails if a fixture invents a rule or names an unknown scenario.

| Rule                       | Meaning                                                                  |
| -------------------------- | ------------------------------------------------------------------------ |
| `element_required`         | At least `min` elements match the CSS selector                           |
| `element_forbidden`        | No element matches the CSS selector                                      |
| `attribute_required`       | Every matched element has the attribute, optionally in `values` or matching `matches` |
| `attribute_forbidden`      | No matched element has the attribute                                     |
| `attribute_name_forbidden` | No matched element has an attribute whose name starts with `prefix`      |
| `style_property_required`  | Every matched element declares the inline CSS property                   |
| `style_property_forbidden` | No matched element declares the inline CSS property                      |
| `markup_required`          | The raw artifact contains the literal at least `min` times               |
| `markup_forbidden`         | The raw artifact does not contain the literal                            |
| `markup_count_equals`      | The first literal occurs at least `min` times, and both counts agree     |

Every expectation carries an `id` and a `because` naming the client constraint
it protects. An expectation without a `scenarios` list applies to every scenario.

A required rule needs at least one match by default. `markup_count_equals` also
requires its first literal to appear, so two missing literals cannot pass by
having equal counts. An `attribute_required` rule may set `min: 0` only when the
matched element is optional and every instance still needs checking.

## Declared limitations

Each profile declares the degradations it accepts. A limitation carries a
`detail`, a `degrades_to`, and optionally a `detect` probe using the same rule
vocabulary.

When a limitation has a `detect` probe, the suite asserts that the probe still
matches. **If the compiler stops producing the limitation, the suite fails and
the stale limitation must be removed or restated.** Limitations cannot rot into
inaccurate documentation.

Two limitations currently record real gaps in CampaignBridge's own output rather
than client behavior:

- `universal-profile / list-text-inherits-client-defaults` — `core/list` and
  `core/list-item` emit no inline typography, so list text falls back to the
  client default face while headings and paragraphs use the resolved design.
- `gmail / body-style-gap-unverified` — the column-gap media query is emitted as
  a `<style>` element inside a table cell. Whether Gmail applies it in each
  viewing path needs client rendering evidence; the vertical gap may be absent.
- `gmail / navigation-stacking-unverified` — the navigation stacking query is
  emitted as a `<style>` element in the message body. Whether Gmail applies it
  in each viewing path needs client rendering evidence; links may stay in a row.

These are recorded rather than silently accepted. The list typography gap needs
a compiler change and golden-fixture review. The Gmail gaps need direct client
rendering evidence or a change that moves the rules into the head.

## What this matrix does not prove

- Visual appearance in any real client, including with images blocked.
- Dark-mode colour behavior in any client; no scenario declares a colour scheme.
- Outlook.com webmail and Outlook for Mac, which do not use the Word engine.
- Apple Mail data detectors, which may auto-link the compliance postal address.
- Gmail's sanitizer itself; these checks inspect compiler output, not client
  processing. [Gmail's documentation](https://developers.google.com/workspace/gmail/design/css)
  supports style blocks, class selectors, and media queries, so these fixtures
  do not claim Gmail always strips them.

These are listed as limitations in the client fixtures so the gap is visible in
the same place as the evidence.

## Updating the fixtures

A compiler change that alters compiled markup requires an explicit review of the
delta rather than a silent refresh.

1. Run `pnpm test:unit`. A compatibility failure names the client, the scenario,
   the expectation `id`, the observed markup, and the `because` behind the rule.
2. Decide which of three cases applies:
   - **The change is a regression.** Fix the compiler. Do not weaken the fixture.
   - **The change is intended and keeps the property.** Only the golden fixtures
     need regenerating; the client expectations should still pass untouched.
   - **The change intentionally drops a property a client needs.** Amend the
     client fixture in the same commit, state the new `because`, and add or
     update the matching limitation. A reviewer must see the claim change.
3. If a `detect` probe stops matching, the limitation no longer applies. Remove
   it, and say so in the change description.
4. Adding a block or renderer means extending the scenario matrix so the new
   output is actually exercised. An expectation that matches nothing fails.

### Proving a new expectation has teeth

`Client_Compatibility_Test::deliberate_regressions()` removes exactly one
property per case and asserts the owning profile reports it. Add a case there for
any compatibility-critical behavior a new expectation protects. An expectation
with no corresponding regression case has not been shown to fail.
