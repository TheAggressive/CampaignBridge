import { applyFilters } from '@wordpress/hooks';
import { migrateNativeStyles } from '../../src/blocks/shared/native-styles';

describe('native WordPress style migration', () => {
  it('moves legacy spacing and sizing into the core schema without changing native choices', () => {
    const raw = {
      maxWidth: 600,
      padding: { top: 20, right: 20, bottom: 20, left: 20 },
      outerPadding: { top: 0, right: 0, bottom: 0, left: 0 },
      backgroundColor: '#123456',
      style: { spacing: { padding: '8px' } },
    };
    const migrated = migrateNativeStyles('campaignbridge/container', raw);
    expect(migrated).toEqual({
      layout: { type: 'constrained', contentSize: '600px' },
      style: {
        color: { background: '#123456' },
        spacing: {
          padding: '8px',
          margin: { top: '0px', right: '0px', bottom: '0px', left: '0px' },
        },
      },
    });
    expect(migrateNativeStyles('campaignbridge/container', migrated)).toEqual(
      migrated
    );
    expect(raw.padding.top).toBe(20);
  });

  it.each([
    [
      'divider',
      { thickness: 3, color: '#123456', style: 'dashed' },
      {
        style: { border: { width: '3px', color: '#123456', style: 'dashed' } },
      },
    ],
    ['columns', { gap: 24 }, { style: { spacing: { blockGap: '24px' } } }],
    [
      'spacer',
      { height: 48 },
      { style: { dimensions: { minHeight: '48px' } } },
    ],
    [
      'post-title',
      { fontSize: 24, textColor: 'brand' },
      {
        fontSize: undefined,
        textColor: 'brand',
        style: { typography: { fontSize: '24px' } },
      },
    ],
    ['post-button', { style: 'link' }, { className: 'is-style-link' }],
  ])(
    'migrates %s through the WordPress attribute parsing filter',
    (name, raw, expected) => {
      const result = applyFilters(
        'blocks.getBlockAttributes',
        {},
        { name: `campaignbridge/${name}` },
        '',
        raw
      );
      expect(result).toEqual(expected);
    }
  );

  it('preserves native link hover colors and drops unused legacy button fields', () => {
    const style = {
      elements: {
        link: {
          color: { text: '#123456' },
          ':hover': { color: { text: '#654321' } },
        },
      },
    };
    expect(
      migrateNativeStyles('campaignbridge/post-link', {
        style,
        textColor: '#111111',
        backgroundColor: '#ffffff',
        linkColor: 'brand',
      })
    ).toEqual({ style });
  });

  it('does not change core block attributes', () => {
    const parsed = { content: 'Hello' };
    expect(
      applyFilters(
        'blocks.getBlockAttributes',
        parsed,
        { name: 'core/paragraph' },
        '',
        { padding: {} }
      )
    ).toBe(parsed);
  });
});
