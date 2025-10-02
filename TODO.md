# CampaignBridge — V1 TODO (Detailed Spec)

# 🎯 Goal of CampaignBridge

The goal of **CampaignBridge** is to transform WordPress into a **native email template design environment** powered by the Gutenberg block editor.

Instead of copy-pasting content into third-party tools, users can **create reusable, branded email templates directly inside WordPress**, using the same blocks they already know for posts and pages.

Once designed, templates can be:

- **Previewed** in the WordPress admin (subject, preheader, HTML body, text fallback)
- **Exported** as email-safe HTML (table-based layouts with inline CSS)
- **Sent** directly to providers (starting with Mailchimp, with support for others in future)

---

## 🔑 Key Objectives

- **Unified workflow** → Keep content creators inside WordPress to design and manage campaigns.
- **Email-safe output** → Ensure HTML is reliable across Gmail, Outlook, and Apple Mail with tables, inline styles, and bulletproof buttons.
- **Extensible system** → Developers can add new blocks, providers, or filters without rewriting the core.
- **Provider adapters** → Normalize payloads (subject, HTML, text, merge tags) so one template can work across multiple providers.
- **Seamless UX** → Preview, export, and “send test” buttons available directly from the template screen.

---

## 📝 In Short

**CampaignBridge is a bridge between WordPress and email marketing platforms, giving users a Gutenberg-powered editor to design, preview, and deliver robust email templates without leaving their site.**


---

**Goal:** From a `cb_email_template`, users can compose with CB blocks, **preview**, **export HTML**, and **send to Mailchimp** (test list) using a provider adapter.
**Quality bar:** Email-safe (table-based) HTML, inline CSS, predictable rendering in Gmail/Outlook.com/Apple Mail, robust server architecture, and clean admin UX.

---

**Note:** Repo uses a custom autoloader (not Composer).
All namespaces under `CampaignBridge\...` must map correctly via your autoloader.

---

## 🎯 Project Execution Strategy

### Priority Levels
- **🚨 CRITICAL** - Must be completed first; blocks other work
- **⚠️ HIGH** - Important for core functionality; complete early
- **📋 MEDIUM** - Standard features; can be deferred if needed
- **💡 LOW** - Nice-to-have; can be added later

### Risk Assessment
- **🔴 HIGH** - Complex integrations, external dependencies, email client compatibility
- **🟡 MEDIUM** - Custom implementations, new patterns, performance concerns
- **🟢 LOW** - Standard WordPress patterns, well-understood functionality

### Block Development Strategy
1. **Phase 1 (Core)**: header, text, image, button - Essential building blocks
2. **Phase 2 (Layout)**: columns, spacer, divider - Structural elements
3. **Phase 3 (Advanced)**: post-list, hero, social, footer - Complex integrations

---

## M0. Setup & Dependencies

- [⚠️] **Node.js Build Process**
  - [√] Set up webpack/build process for block assets (`src/blocks/*/index.js` → `dist/`)
  - [√] Ensure proper enqueuing in PHP with `wp_enqueue_script()`
  - [ ] **Test:** Block editor loads without JS errors

- [📋] **WordPress Coding Standards**
  - [ ] Run PHPCS on all PHP files
  - [ ] Follow WordPress coding standards for security and consistency
  - [ ] **Test:** No PHPCS errors or warnings

- [⚠️] **Security Audit**
  - [ ] Review REST endpoints for proper nonce validation
  - [ ] Validate meta field sanitization
  - [ ] Check for SQL injection, XSS, CSRF vulnerabilities
  - [ ] **Test:** Security scan passes

- [📋] **Performance Baseline**
  - [ ] Profile email generation time for complex templates
  - [ ] Set performance budgets (e.g., <2s for template generation)
  - [ ] **Test:** Email generation completes within acceptable time

---

## M1. Foundations & Wire-up

- [🚨] **Custom Autoloader**
  - [√] Ensure your autoloader handles PSR-4 style mapping: `CampaignBridge\` → `includes/`.
  - [√] Test by instantiating a class from each main namespace (Admin, Providers, Services).
  - [√] **Behavior:** No Composer needed; autoloader must resolve everything predictably.
  - [√] **Test:** Activate plugin → no `Class not found` fatals.

- [√] **Service_Container**
  - [√] Central registry for all services (Admin, Post_Types, Providers, REST, CLI).
  - [√] `get( id )` must always return the same instance.
  - [√] Should register hooks only once (idempotent).
  - [√] **Test:** `get('mailchimp_provider')` and `get('email_generator')` work; no double hooks.

- [⚠️] **CPT: cb_email_template**
  - [ ] Registered with editor support.
  - [ ] Sidebar meta fields:
    - `_cb_subject` (string), `_cb_preheader` (string), `_cb_from_name` (string), `_cb_from_email` (email), `_cb_list_id` (string), `_cb_utms` (assoc), `_cb_defaults` (json).
  - [ ] Sanitization: `sanitize_text_field`, `sanitize_email`, whitelist UTM keys.
  - [ ] Expose to REST safely.
  - [ ] **Test:** Create/edit template, fields save, values appear in REST.

- [x] **Settings Page**
  - [x] For Mailchimp API key, default list, from name/email.
  - [x] Stored in `options` with `autoload=no`.
  - [x] Mask API key in UI; only last 4 chars shown.
  - [x] **Security:** `manage_options` cap; nonce protection.
  - [x] **Test:** Save settings → refresh → values persist, key masked.

---

## M1.5. Core Services (Validate Foundation)

- [⚠️] **EmailPayload DTO**
  - [ ] File: `includes/Core/EmailPayload.php`
  - [ ] Fields: `subject`, `html`, `text`, `images[]`, `merge_tags{}`, `meta{}`.
  - [ ] Data-only, no business logic.
  - [ ] **Test:** Instantiate and encode to JSON.

- [⚠️] **Basic Email_Generator**
  - [ ] File: `includes/Services/Email_Generator.php`
  - [ ] Basic implementation: Load CPT + meta → render simple HTML → return payload.
  - [ ] **Test:** Generate basic payload from simple template.

---

## M2. Payload Pipeline

- [📋] **Email_Generator** (Full Implementation)
  - [ ] File: `includes/Services/Email_Generator.php`
  - [ ] Steps:
    1. Load CPT + meta.
    2. Render block tree into **table-based HTML** (via block `render.php`).
    3. Wrap in 600px container, inline CSS with `CssInliner`.
    4. Convert URLs to absolute + append UTM params.
    5. Generate `text/plain` fallback.
    6. Extract image URLs.
    7. Collect merge tags (`FNAME`, `LNAME`, `UNSUBSCRIBE_URL`).
    8. Return `EmailPayload`.
  - [ ] **Constraints:** No floats, flex, position. Inline all styles. Outlook-safe.
  - [ ] **Test:** Preview output in Gmail/Outlook.com.

- [🔴] **CssInliner** (HIGH - Email Compatibility)
  - [ ] File: `includes/Services/CssInliner.php`
  - [ ] Inline whitelisted properties only (`color`, `font-size`, `padding`, `margin`, `border`, `width`, etc.).
  - [ ] Strip disallowed properties (`position`, `float`, etc.).
  - [ ] Remove `<style>` tags except minimal Outlook resets.
  - [ ] **Test:** Input HTML with styles → output has inline attributes only.

- [🟡] **Utils** (MEDIUM - URL Processing)
  - [ ] `includes/Utils/Images.php` → extract/normalize image URLs.
  - [ ] `includes/Utils/Urls.php` → append UTMs to links, preserve queries.
  - [ ] **Test:** Links include UTMs; images become absolute.

---

## M3. Block Set (V1)

Each block lives in `src/blocks/<block-name>/` with `block.json`, `edit.js`, and `render.php`.
Rendering must be **email-safe** (nested tables, inline CSS).

### Phase 1 (Core Blocks) - Essential Building Blocks
- [📋] **header** — container row; props: bg, padding, align.
- [📋] **text** — limited rich text, align, size.
- [📋] **image** — URL, alt, caption optional.
- [📋] **button** — label, URL, align, width, radius, padding (VML fallback).

### Phase 2 (Layout Blocks) - Structural Elements
- [📋] **columns** — 1–3 cols, stack on mobile; inner blocks limited to text/image/button.
- [📋] **divider** — thickness, style, color.
- [📋] **spacer** — height px.

### Phase 3 (Advanced Blocks) - Complex Integrations
- [⚠️] **preheader** — tiny text, hidden option.
- [⚠️] **logo** — image URL, width, link.
- [🔴] **hero** — image + headline + subcopy + CTA (VML fallback button). **HIGH:** Complex layout, VML buttons.
- [🔴] **post-list** — render posts (title, image, excerpt, button). **HIGH:** Dynamic content integration.
- [⚠️] **social** — networks, icon size, align.
- [⚠️] **footer** — company, address, legal, unsubscribe token.

**Test:** Insert each block in template; preview output in Gmail/Outlook.com; no broken layout.

---

## M4. Provider: Mailchimp (HIGH - External API)

- [⚠️] **Factory** (MEDIUM - Interface Design)
  - [ ] File: `includes/Providers/Factory.php`
  - [ ] `make('mailchimp'|'html') → ProviderInterface`.
  - [ ] Throws error on unknown provider.
  - [ ] **Test:** Returns `MailchimpProvider` or `HtmlProvider`.

- [🔴] **MailchimpProvider** (HIGH - API Integration)
  - [ ] Ensure methods: `upsertTemplate`, `createCampaign`, `sendCampaign`.
  - [ ] Validate: subject + html required.
  - [ ] Use API key from Settings; handle errors gracefully.
  - [ ] **Test:** With valid key/list, creates a test campaign.

- [🟡] **SendService** (MEDIUM - Idempotency Logic)
  - [ ] File: `includes/Services/SendService.php`
  - [ ] `send_email_via_provider( $post_id, ProviderInterface, $args )`.
  - [ ] Generate idempotency key: hash of post_id + payload checksum.
  - [ ] Skip duplicates if already sent.
  - [ ] **Test:** Click Send twice quickly → only one campaign created.

---

## M5. Admin UX (MEDIUM - UI Integration)

- [📋] **Preview Panel** (LOW - Standard UI)
  - [ ] File: `includes/Admin/PreviewPanel.php`
  - [ ] Show: Subject + Preheader, Text preview (`<pre>`), HTML iframe (`srcdoc`).
  - [ ] Buttons: Copy HTML, Download HTML, Send Test.
  - [ ] **Test:** UI works on CPT screen; preview matches exported HTML.

- [📋] **ExportController** (LOW - File Download)
  - [ ] File: `includes/REST/ExportController.php`
  - [ ] POST returns `.html` download of rendered payload.
  - [ ] **Test:** Download matches preview iframe.

- [🟡] **SendController** (MEDIUM - Async Operations)
  - [ ] File: `includes/REST/SendController.php`
  - [ ] POST `{ post_id, provider, args }` with nonce.
  - [ ] Calls `SendService`; returns provider result.
  - [ ] **Test:** Send Test button creates campaign; success notice shown.

---

## M6. REST + CLI + Logging + Validation (LOW - Standard WP Patterns)

- [📋] **REST Endpoints** (LOW - Standard WP REST)
  - [ ] PreviewController → `/preview`
  - [ ] ExportController → `/export`
  - [ ] SendController → `/send`
  - [ ] ValidateController → `/validate`
  - [ ] **Security:** Nonce + `edit_post` cap.
  - [ ] **Test:** Postman requests succeed with nonce.

- [📋] **CLI Commands** (LOW - Standard WP CLI)
  - [ ] File: `includes/CLI/SendCommand.php`
  - [ ] `wp campaignbridge payload <post_id> --format=json`
  - [ ] `wp campaignbridge send <post_id> --provider=mailchimp --list=<id>`
  - [ ] **Test:** Commands return JSON or create campaigns as expected.

- [📋] **Logging** (LOW - Standard WP Options)
  - [ ] File: `includes/Utils/Logging.php`
  - [ ] Store last 50 sends: time, post_id, provider, hash, response.
  - [ ] **Test:** View/send history shows entries; no duplicates.

- [🟡] **ValidateService** (MEDIUM - Complex Validation Rules)
  - [ ] File: `includes/Services/ValidateService.php`
  - [ ] Checks: subject/html present, images absolute, links https, width ≤ 800px, no floats/position, warn on >998 char lines, >2MB images.
  - [ ] **Test:** `/validate` returns green or warnings.

---

## M7. Docs (LOW - Documentation)

- [📋] **README** (LOW - Standard Docs)
  - [ ] Quickstart: create template → preview → export → send.
  - [ ] REST endpoints list.
  - [ ] **Test:** New dev can follow in <15 min.

- [📋] **Dev Guide** (LOW - Standard Docs)
  - [ ] How to add provider (plug into Factory).
  - [ ] How to add block (folder + render.php).
  - [ ] List of filters/actions.
  - [ ] **Test:** Another dev can extend plugin without Slack/DMs.

---

## 🚀 Execution Recommendations

### **Immediate Next Steps**
1. **Complete M0** - Validate all dependencies and setup before core development
2. **Start with M1** - Get foundations solid (autoloader, service container)
3. **Implement M1.5** - Validate core services early with basic Email_Generator
4. **Begin Phase 1 blocks** - header, text, image, button (test email rendering)

### **Risk Mitigation**
- **🚨 CRITICAL & 🔴 HIGH Risk items** should be prototyped early
- **Test email rendering** at every milestone
- **Validate Mailchimp API** integration before full implementation

### **Success Metrics**
- ✅ **MVP**: Create template → preview → export HTML → send to Mailchimp
- ✅ **Quality**: Email renders correctly in Gmail/Outlook/Apple Mail
- ✅ **Performance**: Template generation <2s, email sending <30s
- ✅ **Security**: All REST endpoints properly secured with nonces

### **Timeline Estimate**
- **M0 + M1**: 1-2 weeks (foundations)
- **M1.5 + M2**: 2-3 weeks (core pipeline)
- **M3 Phase 1**: 2 weeks (essential blocks)
- **M4 + M5**: 2-3 weeks (Mailchimp + admin UX)
- **M3 Phase 2-3**: 2-3 weeks (remaining blocks)
- **M6 + M7**: 1 week (polish + docs)

**Total: 10-14 weeks for complete V1**

---

## ✅ Acceptance Tests

- [x] **Build template** with header, hero, text, button, post-list, footer.
- [x] **Preview panel** renders subject/preheader and iframe HTML.
- [x] **Export HTML** opens cleanly in browser; images absolute; UTMs appended.
- [x] **CLI commands** - `payload` shows JSON; `send` creates Mailchimp campaign.
- [x] **Idempotency test** - Double-click Send → no duplicate campaign.
- [x] **Validation endpoint** returns green or expected warnings.
