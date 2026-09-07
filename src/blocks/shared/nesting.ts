/**
 * Single source of truth for the email block nesting grammar.
 *
 * These allowlists mirror `allowed_children()` on each `*_Renderer` under
 * `includes/Services/Email/Renderer/`. They are the same tree the server-side
 * compiler enforces, so the editor must present the same options.
 *
 * Keep in sync with the PHP renderers (and each block's `block.json` `parent`
 * list): `tests/js/block-nesting.test.ts` fails when a parent here no longer
 * matches the declared parent relationship.
 */
export const EMAIL_BLOCK_NESTING = {
  container: [
    'campaignbridge/preheader',
    'campaignbridge/section',
    'campaignbridge/post-card',
    'campaignbridge/compliance-footer',
  ],
  section: [
    'campaignbridge/columns',
    'campaignbridge/text',
    'campaignbridge/heading',
    'campaignbridge/image',
    'campaignbridge/button',
    'campaignbridge/divider',
    'campaignbridge/spacer',
    'campaignbridge/post-card',
  ],
  'post-card': [
    'campaignbridge/columns',
    'campaignbridge/post-image',
    'campaignbridge/post-title',
    'campaignbridge/post-excerpt',
    'campaignbridge/post-button',
    'campaignbridge/post-link',
  ],
  columns: ['campaignbridge/column'],
  column: [
    'campaignbridge/text',
    'campaignbridge/heading',
    'campaignbridge/image',
    'campaignbridge/button',
    'campaignbridge/divider',
    'campaignbridge/spacer',
    'campaignbridge/post-card',
    'campaignbridge/post-image',
    'campaignbridge/post-title',
    'campaignbridge/post-excerpt',
    'campaignbridge/post-button',
    'campaignbridge/post-link',
  ],
} as const;
