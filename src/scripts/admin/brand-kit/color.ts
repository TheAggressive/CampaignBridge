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

/** WCAG contrast ratio for two portable hexadecimal colours. */
export function contrastRatio(foreground: string, background: string): number {
  const luminance = (hex: string) => {
    const channels = [1, 3, 5].map(offset =>
      parseInt(hex.slice(offset, offset + 2), 16)
    );
    const linear = channels.map(channel => {
      const value = channel / 255;
      return value <= 0.04045
        ? value / 12.92
        : Math.pow((value + 0.055) / 1.055, 2.4);
    });
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
  };

  const first = luminance(foreground);
  const second = luminance(background);
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}
