import {
  POST_CARD_ALLOWED_BLOCKS,
  POST_CARD_TEMPLATE,
} from '../../src/blocks/post-card/config';

describe('post-card block configuration', () => {
  it('declares its initial children on the block type configuration', () => {
    expect(POST_CARD_TEMPLATE).toEqual(
      POST_CARD_ALLOWED_BLOCKS.map(name => [name])
    );
  });
});
