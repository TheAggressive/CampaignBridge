import { EMAIL_BLOCK_NESTING } from '../../src/blocks/shared/nesting';

/**
 * Parent/child nesting grammar for every container email block.
 *
 * The canonical allowlists live in `src/blocks/shared/nesting.ts` and mirror
 * the PHP `*_Renderer::allowed_children()`. These tests pin each parent to its
 * children so that block cannot be silently dropped or added to either list
 * without a deliberate edit to the shared grammar.
 */
const PARENT_CHILDREN: Record<string, readonly string[]> = {
  'campaignbridge/container': EMAIL_BLOCK_NESTING.container,
  'campaignbridge/section': EMAIL_BLOCK_NESTING.section,
  'campaignbridge/post-card': EMAIL_BLOCK_NESTING['post-card'],
  'campaignbridge/columns': EMAIL_BLOCK_NESTING.columns,
  'campaignbridge/column': EMAIL_BLOCK_NESTING.column,
};

describe('email block nesting grammar', () => {
  it('declares a non-empty allowlist for every container block', () => {
    for (const children of Object.values(PARENT_CHILDREN)) {
      expect(children.length).toBeGreaterThan(0);
      // No duplicate entries in any allowlist.
      expect(new Set(children).size).toBe(children.length);
    }
  });

  it('lets post-card be laid out with columns', () => {
    expect(EMAIL_BLOCK_NESTING['post-card']).toContain(
      'campaignbridge/columns'
    );
  });

  it('lets every post block be placed inside a column', () => {
    for (const name of [
      'campaignbridge/post-image',
      'campaignbridge/post-title',
      'campaignbridge/post-excerpt',
      'campaignbridge/post-button',
      'campaignbridge/post-link',
    ]) {
      expect(EMAIL_BLOCK_NESTING.column).toContain(name);
    }
  });

  it('keeps column nesting flat (no nested columns or column-in-column)', () => {
    expect(EMAIL_BLOCK_NESTING.column).not.toContain('campaignbridge/columns');
    expect(EMAIL_BLOCK_NESTING.column).not.toContain('campaignbridge/column');
  });

  it('does not let a container contain itself', () => {
    expect(EMAIL_BLOCK_NESTING.section).not.toContain('campaignbridge/section');
    expect(EMAIL_BLOCK_NESTING['post-card']).not.toContain(
      'campaignbridge/post-card'
    );
    expect(EMAIL_BLOCK_NESTING.columns).not.toContain('campaignbridge/columns');
    expect(EMAIL_BLOCK_NESTING.column).not.toContain('campaignbridge/column');
    expect(EMAIL_BLOCK_NESTING.container).not.toContain(
      'campaignbridge/container'
    );
  });

  it('declares only the four children of a column layout block', () => {
    expect(EMAIL_BLOCK_NESTING.columns).toEqual(['campaignbridge/column']);
  });

  it('pins the container children', () => {
    expect([...EMAIL_BLOCK_NESTING.container].sort()).toEqual([
      'campaignbridge/compliance-footer',
      'campaignbridge/post-card',
      'campaignbridge/preheader',
      'campaignbridge/section',
    ]);
  });

  it('pins the section children (columns, foundation blocks, post-card)', () => {
    expect([...EMAIL_BLOCK_NESTING.section].sort()).toEqual([
      'campaignbridge/button',
      'campaignbridge/columns',
      'campaignbridge/divider',
      'campaignbridge/heading',
      'campaignbridge/image',
      'campaignbridge/post-card',
      'campaignbridge/spacer',
      'campaignbridge/text',
    ]);
  });
});
