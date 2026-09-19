import {
  EMAIL_BLOCK_CONTRACT,
  EMAIL_BLOCK_NESTING,
} from '../../src/blocks/shared/nesting';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Parent/child nesting grammar for every container email block.
 *
 * The canonical allowlists live in `includes/Email_Blocks/email-blocks.json`,
 * which the editor (`src/blocks/shared/nesting.ts`) and the PHP renderers
 * (`Email_Block_Contract`) both read. These tests pin each parent to its
 * children so that block cannot be silently dropped or added to either list
 * without a deliberate edit to the shared grammar.
 */
const PARENT_CHILDREN: Record<string, readonly string[]> = {
  'campaignbridge/container': EMAIL_BLOCK_NESTING.container,
  'campaignbridge/section': EMAIL_BLOCK_NESTING.section,
  'campaignbridge/post-card': EMAIL_BLOCK_NESTING['post-card'],
  'campaignbridge/columns': EMAIL_BLOCK_NESTING.columns,
  'core/buttons': EMAIL_BLOCK_NESTING.buttons,
  'core/list': EMAIL_BLOCK_NESTING.list,
  'campaignbridge/column': EMAIL_BLOCK_NESTING.column,
};

const CORE_PARENTS: Record<string, string[]> = {
  'core/button': ['core/buttons'],
  'core/list-item': ['core/list'],
};

describe('email block nesting grammar', () => {
  it('matches every child block parent declaration in block.json', () => {
    for (const [parent, children] of Object.entries(PARENT_CHILDREN)) {
      for (const child of children) {
        if (child.startsWith('core/')) {
          if (CORE_PARENTS[child]) {
            expect(CORE_PARENTS[child]).toContain(parent);
          }
          continue;
        }

        const blockName = child.replace('campaignbridge/', '');
        const metadataPath = path.resolve(
          __dirname,
          `../../src/blocks/${blockName}/block.json`
        );
        const metadata = JSON.parse(fs.readFileSync(metadataPath, 'utf8')) as {
          parent?: string[];
        };

        expect(metadata.parent).toContain(parent);
      }
    }
  });

  it('derives every editor allowlist from the shared contract', () => {
    for (const [parent, children] of Object.entries(PARENT_CHILDREN)) {
      expect(children).toEqual(EMAIL_BLOCK_CONTRACT[parent]?.children);
    }
  });

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

  it('pins the section children (columns, Core blocks, post-card)', () => {
    expect([...EMAIL_BLOCK_NESTING.section].sort()).toEqual([
      'campaignbridge/columns',
      'campaignbridge/post-card',
      'core/buttons',
      'core/heading',
      'core/image',
      'core/list',
      'core/paragraph',
      'core/separator',
      'core/spacer',
    ]);
  });

  it('constrains Core button and list children', () => {
    expect(EMAIL_BLOCK_NESTING.buttons).toEqual(['core/button']);
    expect(EMAIL_BLOCK_NESTING.list).toEqual(['core/list-item']);
    expect(EMAIL_BLOCK_CONTRACT['core/list-item']?.children).toEqual([]);
  });
});
