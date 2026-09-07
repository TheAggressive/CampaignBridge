import columnsMeta from '../../src/blocks/columns/block.json';
import postCardMeta from '../../src/blocks/post-card/block.json';
import postButtonMeta from '../../src/blocks/post-button/block.json';
import postExcerptMeta from '../../src/blocks/post-excerpt/block.json';
import postImageMeta from '../../src/blocks/post-image/block.json';
import postTitleMeta from '../../src/blocks/post-title/block.json';
import { COLUMN_ALLOWED_BLOCKS } from '../../src/blocks/column/config';

describe('column block configuration', () => {
  it('accepts the foundation blocks used for generic email content', () => {
    for (const name of [
      'campaignbridge/text',
      'campaignbridge/heading',
      'campaignbridge/image',
      'campaignbridge/button',
      'campaignbridge/divider',
      'campaignbridge/spacer',
    ]) {
      expect(COLUMN_ALLOWED_BLOCKS).toContain(name);
    }
  });

  it('accepts every post block so a post-card can be laid out with columns', () => {
    for (const name of [
      'campaignbridge/post-card',
      'campaignbridge/post-image',
      'campaignbridge/post-title',
      'campaignbridge/post-excerpt',
      'campaignbridge/post-button',
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
    expect(postTitleMeta.parent).toContain('campaignbridge/column');
    expect(postExcerptMeta.parent).toContain('campaignbridge/column');
    expect(postButtonMeta.parent).toContain('campaignbridge/column');
  });

  it('lets a post-card be placed inside a column', () => {
    expect(postCardMeta.parent).toContain('campaignbridge/column');
  });
});
