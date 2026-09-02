/** Child blocks supported by the post-card email grammar. */
export const POST_CARD_ALLOWED_BLOCKS = [
  'campaignbridge/post-image',
  'campaignbridge/post-title',
  'campaignbridge/post-excerpt',
  'campaignbridge/post-cta',
];

/** Initial post-card structure, declared on the block type for Gutenberg. */
export const POST_CARD_TEMPLATE: Array<[string]> = POST_CARD_ALLOWED_BLOCKS.map(
  name => [name]
);
