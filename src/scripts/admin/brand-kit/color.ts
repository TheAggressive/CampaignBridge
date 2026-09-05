/**
 * Normalise a picker value to portable six-digit hex.
 *
 * @param value Raw colour from ColorPicker or an input.
 */
export function toPortableHex(value: string): string | null {
  const color = value.replace(/\s+/g, '');

  if (/^#[0-9a-f]{6}$/i.test(color)) {
    return color.toLowerCase();
  }

  if (/^#[0-9a-f]{3}$/i.test(color)) {
    return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`.toLowerCase();
  }

  const rgb = color.match(
    /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)$/i
  );
  if (!rgb) {
    return null;
  }

  return `#${[1, 2, 3]
    .map(index => Number(rgb[index]).toString(16).padStart(2, '0'))
    .join('')}`;
}
