import { toPortableHex } from '../../src/scripts/admin/brand-kit/color';

describe('toPortableHex', () => {
  it('keeps six-digit hex', () => {
    expect(toPortableHex('#1A6DCC')).toBe('#1a6dcc');
    expect(toPortableHex('# 111111')).toBe('#111111');
  });

  it('expands three-digit hex', () => {
    expect(toPortableHex('#f50')).toBe('#ff5500');
  });

  it('converts rgb from the colour picker', () => {
    expect(toPortableHex('rgb(26, 109, 204)')).toBe('#1a6dcc');
  });

  it('rejects unportable values', () => {
    expect(toPortableHex('oklch(0.7 0.1 200)')).toBeNull();
    expect(toPortableHex('color-mix(in srgb, red 50%, blue)')).toBeNull();
  });
});
