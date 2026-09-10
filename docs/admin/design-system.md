# CampaignBridge admin design system

CampaignBridge admin screens share tokens from `src/styles/variables.css` and
structural primitives from `src/styles/admin/design-system.css`. Screen-specific
styles should compose these primitives instead of redefining cards, status
badges, focus rings, or action-row behavior.

## Primitives

- `cb-admin-screen`: token scope and shared focus treatment.
- `cb-admin-product-header`: branded screen identity and primary actions.
- `cb-admin-card`, `cb-admin-card__header`, `cb-admin-card__footer`: content
  grouping with consistent border, radius, shadow, and spacing.
- `cb-admin-action-row`: linked rows with shared hover and focus feedback.
- `cb-admin-badge`, `cb-admin-badge--success`: compact semantic status labels.
- `cb-admin-icon-disc`: circular icon treatment used in action lists.
- `cb-admin-title`, `cb-admin-heading`, `cb-admin-body`, `cb-admin-help`, and
  `cb-admin-meta`: stable typography roles.
- `cb-admin-notice--{info,success,warning,error}`: inline semantic feedback.
- `cb-admin-empty`, `cb-admin-skeleton`, and `cb-admin-spinner`: asynchronous
  and first-use states.
- `cb-admin-tabs`, `cb-admin-table`, `cb-admin-stack`, `cb-admin-cluster`, and
  `cb-admin-grid`: navigation, data, and layout primitives.
- `button-destructive`, `button-icon`, and `aria-busy`: button variants and
  loading behavior layered onto WordPress buttons.
- `data-cb-tooltip` with `aria-label`: keyboard-accessible contextual help.
- `cb-admin-menu`, `cb-admin-pagination`, and `cb-admin-dialog`: layered
  navigation and confirmation surfaces.

Buttons and standard form controls inside `cb-admin-screen` automatically use
the shared medium radius. Button icons are centered through the same flex and
line-height contract, so individual screens should not add icon offsets.

## Rules

1. Use semantic HTML first; classes provide presentation, not meaning.
2. Use the global spacing, colour, typography, radius, focus, and transition
   tokens. Add a token only when at least two components need it.
3. Keep unique illustrations and complex page layouts in the screen stylesheet.
4. Never convey status with colour alone; include visible text and accessible
   labels.
5. Preserve WordPress admin notices. Product headers must allow WordPress to
   place notices after the first `h1` without breaking their layout.
6. Every interactive element needs visible hover and keyboard-focus feedback.
7. New shared primitives must be demonstrated on a production screen before
   they are added here.

## Interaction contract

Interactive controls must define hover, active, focus-visible, disabled, and
loading states where applicable. Hover may supplement but never replace focus
feedback. Disabled links use `aria-disabled="true"` and must not remain
keyboard-actionable. Loading buttons use `aria-busy="true"` and retain their
accessible name.

Fields use `aria-invalid="true"` with a visible `cb-admin-field-message--error`
message connected through `aria-describedby`. Success and warning messages use
the corresponding modifier. Read-only and disabled are visually distinct.

## Status vocabulary

- Success: connected, sent, complete, healthy.
- Info: scheduled, syncing, processing.
- Warning: pending, stale, needs attention.
- Error: failed, disconnected after failure, blocked.
- Neutral: not configured, draft, unknown.
