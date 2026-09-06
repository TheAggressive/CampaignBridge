/**
 * Child blocks supported by the column email grammar.
 *
 * Keep in sync with `Column_Renderer::allowed_children()` so the editor and
 * compiler accept the same block trees.
 */
export const COLUMN_ALLOWED_BLOCKS = [
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
  'campaignbridge/post-cta',
];
