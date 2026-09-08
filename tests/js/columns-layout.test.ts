import { columnWidths } from '../../src/blocks/columns/layout';
import metadata from '../../src/blocks/columns/block.json';

describe('email column proportions', () => {
  it.each([
    [[undefined], [100]],
    [
      [undefined, undefined],
      [50, 50],
    ],
    [
      [60, undefined],
      [60, 40],
    ],
    [
      [50, undefined, undefined],
      [50, 25, 25],
    ],
    [
      [60, 60],
      [50, 50],
    ],
    [
      [65, 35],
      [65, 35],
    ],
  ])(
    'distributes %j without exceeding the available space',
    (widths, expected) => {
      expect(columnWidths(widths)).toEqual(expected);
    }
  );
  it('keeps six automatic columns equal', () => {
    const widths = columnWidths(Array(6).fill(undefined));
    expect(widths).toHaveLength(6);
    widths.forEach(width => expect(width).toBeCloseTo(100 / 6));
  });
  it('uses the native layout default to prevent wrapping at desktop widths', () => {
    expect(metadata.supports.layout.default.flexWrap).toBe('nowrap');
    expect(metadata.attributes.isStackedOnMobile.default).toBe(true);
  });
});
