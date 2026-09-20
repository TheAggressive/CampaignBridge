import columnsMeta from '../../src/blocks/columns/block.json';
import postCardMeta from '../../src/blocks/post-card/block.json';
import postImageMeta from '../../src/blocks/post-image/block.json';
import { COLUMN_ALLOWED_BLOCKS } from '../../src/blocks/column/config';

describe('column block configuration', () => {
  it('accepts the foundation blocks used for generic email content', () => {
    for (const name of [
      'core/paragraph',
      'core/heading',
      'core/image',
      'core/buttons',
      'core/list',
      'core/separator',
      'core/spacer',
    ]) {
      expect(COLUMN_ALLOWED_BLOCKS).toContain(name);
    }
  });

  it('accepts every post block so a post-card can be laid out with columns', () => {
    for (const name of [
      'campaignbridge/post-card',
      'campaignbridge/post-image',
    ]) {
      expect(COLUMN_ALLOWED_BLOCKS).toContain(name);
    }
  });

  it('keeps column nesting flat', () => {
    expect(COLUMN_ALLOWED_BLOCKS).not.toContain('campaignbridge/columns');
    expect(COLUMN_ALLOWED_BLOCKS).not.toContain('campaignbridge/column');
  });
});

describe('post-card layout grammar', () => {
  it('lets a post-card be laid out with columns', () => {
    expect(columnsMeta.parent).toContain('campaignbridge/post-card');
  });

  it('lets post blocks be placed inside a column', () => {
    expect(postImageMeta.parent).toContain('campaignbridge/column');
  });

  it('lets a post-card be placed inside a column', () => {
    expect(postCardMeta.parent).toContain('campaignbridge/column');
  });
});
