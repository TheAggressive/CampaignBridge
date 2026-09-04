import {
  POST_CARD_ALLOWED_BLOCKS,
  POST_CARD_TEMPLATE,
} from '../../src/blocks/post-card/config';

describe('post-card block configuration', () => {
  it('starts a new card as the stacked post composition', () => {
    expect(POST_CARD_TEMPLATE).toEqual([
      ['campaignbridge/post-image'],
      ['campaignbridge/post-title'],
      ['campaignbridge/post-excerpt'],
      ['campaignbridge/post-cta'],
    ]);
  });

  it('only seeds blocks the card actually accepts', () => {
    for (const [name] of POST_CARD_TEMPLATE) {
      expect(POST_CARD_ALLOWED_BLOCKS).toContain(name);
    }
  });

  it('accepts columns for layout without seeding an empty one', () => {
    // Columns is opt-in: deriving the template from the allowed list would
    // drop an empty columns block into every new card.
    expect(POST_CARD_ALLOWED_BLOCKS).toContain('campaignbridge/columns');
    expect(POST_CARD_TEMPLATE).not.toContainEqual(['campaignbridge/columns']);
  });
});
