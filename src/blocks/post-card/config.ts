/** Child blocks supported by the post-card email grammar. */
export const POST_CARD_ALLOWED_BLOCKS = [
  'campaignbridge/columns',
  'campaignbridge/post-image',
  'campaignbridge/post-title',
  'campaignbridge/post-excerpt',
  'campaignbridge/post-cta',
];

/**
 * Initial post-card structure, declared on the block type for Gutenberg.
 *
 * Deliberately not derived from the allowed list: columns is a layout choice
 * an author opts into, so a new card starts as the stacked post composition.
 */
export const POST_CARD_TEMPLATE: Array<[string]> = [
  ['campaignbridge/post-image'],
  ['campaignbridge/post-title'],
  ['campaignbridge/post-excerpt'],
  ['campaignbridge/post-cta'],
];
