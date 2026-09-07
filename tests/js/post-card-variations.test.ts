import type { Block } from '@wordpress/blocks';
import {
  detectActiveLayout,
  POST_CARD_VARIATIONS,
} from '../../src/blocks/post-card/variations';

type RawTemplate = [
  name: string,
  attrs?: Record<string, unknown>,
  innerBlocks?: RawTemplate[],
];

const template = (name: string): RawTemplate[] =>
  (POST_CARD_VARIATIONS.find(v => v.name === name)?.innerBlocks ??
    []) as unknown as RawTemplate[];

const names = (blocks: RawTemplate[]) => blocks.map(b => b[0]);

const attrsOf = (block: RawTemplate): Record<string, unknown> => block[1] ?? {};

describe('post-card layout variations', () => {
  it('offers the three card layouts with the stacked card as default', () => {
    expect(POST_CARD_VARIATIONS.map(v => v.name)).toEqual([
      'stacked',
      'media-left',
      'media-right',
    ]);
    expect(
      POST_CARD_VARIATIONS.find(v => v.name === 'stacked')?.isDefault
    ).toBe(true);
    expect(
      POST_CARD_VARIATIONS.find(v => v.name === 'media-left')?.isDefault
    ).toBeFalsy();
    expect(
      POST_CARD_VARIATIONS.find(v => v.name === 'media-right')?.isDefault
    ).toBeFalsy();
  });

  it('is inserter-scoped so it is not auto-applied to a selected card', () => {
    for (const variation of POST_CARD_VARIATIONS) {
      expect(variation.scope).toContain('inserter');
      expect(variation.scope).not.toContain('block');
    }
  });

  describe('block templates', () => {
    it('stacks the post blocks in reading order', () => {
      expect(template('stacked')).toEqual([
        ['campaignbridge/post-image'],
        ['campaignbridge/post-title'],
        ['campaignbridge/post-excerpt'],
        ['campaignbridge/post-button'],
      ]);
    });

    it('lays out a media-left card as a 35/65 split', () => {
      const [columns] = template('media-left');
      expect(columns[0]).toBe('campaignbridge/columns');
      expect(attrsOf(columns)).toMatchObject({
        gap: 24,
        verticalAlign: 'middle',
      });

      const [first, second] = (columns[2] ?? []) as RawTemplate[];
      expect(first[0]).toBe('campaignbridge/column');
      expect(attrsOf(first)).toMatchObject({ width: 35 });
      expect(names(first[2] ?? [])).toEqual(['campaignbridge/post-image']);
      expect(attrsOf((first[2] ?? [])[0] as RawTemplate)).toMatchObject({
        linkToPost: true,
      });

      expect(second[0]).toBe('campaignbridge/column');
      expect(attrsOf(second)).toMatchObject({ width: 65 });
      expect(names(second[2] ?? [])).toEqual([
        'campaignbridge/post-title',
        'campaignbridge/post-excerpt',
        'campaignbridge/post-button',
      ]);
    });

    it('lays out a media-right card with the copy column first', () => {
      const [columns] = template('media-right');
      expect(columns[0]).toBe('campaignbridge/columns');

      const [first, second] = (columns[2] ?? []) as RawTemplate[];
      expect(first[0]).toBe('campaignbridge/column');
      expect(attrsOf(first)).toMatchObject({ width: 65 });
      expect(names(first[2] ?? [])).toEqual([
        'campaignbridge/post-title',
        'campaignbridge/post-excerpt',
        'campaignbridge/post-button',
      ]);

      expect(second[0]).toBe('campaignbridge/column');
      expect(attrsOf(second)).toMatchObject({ width: 35 });
      expect(names(second[2] ?? [])).toEqual(['campaignbridge/post-image']);
      expect(attrsOf((second[2] ?? [])[0] as RawTemplate)).toMatchObject({
        linkToPost: true,
      });
    });
  });

  describe('detectActiveLayout', () => {
    const block = (
      name: string,
      attrs: Record<string, unknown> = {},
      inner: Block[] = []
    ): Block =>
      ({
        clientId: 'card',
        name,
        attributes: attrs,
        innerBlocks: inner,
        innerHTML: null,
        isValid: true,
      }) as Block;

    it('reports stacked for the default post block stack', () => {
      expect(
        detectActiveLayout([
          block('campaignbridge/post-image'),
          block('campaignbridge/post-title'),
          block('campaignbridge/post-excerpt'),
          block('campaignbridge/post-button'),
        ])
      ).toBe('stacked');
    });

    it('reports media-left when the image column comes first', () => {
      expect(
        detectActiveLayout([
          block('campaignbridge/columns', {}, [
            block('campaignbridge/column', { width: 35 }),
            block('campaignbridge/column', { width: 65 }),
          ]),
        ])
      ).toBe('media-left');
    });

    it('reports media-right when the copy column comes first', () => {
      expect(
        detectActiveLayout([
          block('campaignbridge/columns', {}, [
            block('campaignbridge/column', { width: 65 }),
            block('campaignbridge/column', { width: 35 }),
          ]),
        ])
      ).toBe('media-right');
    });

    it('falls back to stacked when the card is not a single columns child', () => {
      expect(detectActiveLayout([])).toBe('stacked');
      expect(
        detectActiveLayout([
          block('campaignbridge/columns', {}, [
            block('campaignbridge/column', { width: 35 }),
          ]),
          block('campaignbridge/post-button'),
        ])
      ).toBe('stacked');
    });
  });
});
