import type { Block } from '@wordpress/blocks';
import {
  detectActiveLayout,
  POST_CARD_VARIATIONS,
} from '../../src/blocks/post-card/variations';
import { POST_BINDING_SOURCE } from '../../src/blocks/shared/post-bindings';

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

const bindingOf = (block: RawTemplate, attribute: string): unknown =>
  (
    attrsOf(block).metadata as
      { bindings?: Record<string, unknown> } | undefined
  )?.bindings?.[attribute];

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
      expect(names(template('stacked'))).toEqual([
        'campaignbridge/post-image',
        'core/heading',
        'core/paragraph',
        'core/buttons',
      ]);
    });

    it('binds the stacked Core blocks read-only to the selected post', () => {
      const [, , , buttons] = template('stacked');
      expect(bindingOf(template('stacked')[2], 'content')).toEqual({
        source: POST_BINDING_SOURCE,
        args: { field: 'content', maxWords: 50 },
      });
      expect(bindingOf((buttons[2] ?? [])[0] as RawTemplate, 'url')).toEqual({
        source: POST_BINDING_SOURCE,
        args: { field: 'url' },
      });
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
        'core/heading',
        'core/paragraph',
        'core/buttons',
      ]);
      // Side-by-side layouts keep the headline linked to the post.
      expect(bindingOf((second[2] ?? [])[0] as RawTemplate, 'content')).toEqual(
        {
          source: POST_BINDING_SOURCE,
          args: { field: 'titleLink' },
        }
      );
      // The side-by-side layouts use the native ghost style for a text link.
      const buttons = (second[2] ?? [])[2] as RawTemplate;
      expect(attrsOf((buttons[2] ?? [])[0] as RawTemplate)).toMatchObject({
        className: 'is-style-ghost',
      });
    });

    it('lays out a media-right card with the copy column first', () => {
      const [columns] = template('media-right');
      expect(columns[0]).toBe('campaignbridge/columns');

      const [first, second] = (columns[2] ?? []) as RawTemplate[];
      expect(first[0]).toBe('campaignbridge/column');
      expect(attrsOf(first)).toMatchObject({ width: 65 });
      expect(names(first[2] ?? [])).toEqual([
        'core/heading',
        'core/paragraph',
        'core/buttons',
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
          block('core/heading'),
          block('core/paragraph'),
          block('core/buttons'),
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
          block('core/buttons'),
        ])
      ).toBe('stacked');
    });
  });
});
