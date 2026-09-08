import { addFilter } from '@wordpress/hooks';

export interface NativeStyle {
  color?: { text?: string; background?: string };
  typography?: { fontSize?: string; lineHeight?: string };
  spacing?: {
    padding?: Record<string, string> | string;
    margin?: Record<string, string> | string;
    blockGap?: string;
  };
  border?: { color?: string; width?: string; style?: string };
  dimensions?: { minHeight?: string };
  elements?: { link?: { color?: { text?: string } } };
}

type Attributes = Record<string, any>;

/** Read old templates into native attributes; subsequent saves use core's schema. */
export function migrateNativeStyles(
  name: string,
  source: Attributes
): Attributes {
  const attrs = { ...source };
  const style: NativeStyle =
    typeof source.style === 'object' && source.style !== null
      ? JSON.parse(JSON.stringify(source.style))
      : {};
  const pixels = (value: number) => `${value}px`;
  const spacing = (value: Record<string, number>) =>
    Object.fromEntries(
      Object.entries(value).map(([side, length]) => [side, pixels(length)])
    );
  for (const [legacy, native] of [
    ['padding', 'padding'],
    ['outerPadding', 'margin'],
  ] as const) {
    if (attrs[legacy]) {
      style.spacing = {
        ...style.spacing,
        [native]: style.spacing?.[native] ?? spacing(attrs[legacy]),
      };
      delete attrs[legacy];
    }
  }
  // These fields belonged to the old button-shaped link schema and were never rendered.
  if (name === 'campaignbridge/post-link') {
    delete attrs.textColor;
    delete attrs.backgroundColor;
  }
  for (const [legacy, slot] of [
    ['textColor', 'text'],
    ['backgroundColor', 'background'],
  ] as const) {
    if (typeof attrs[legacy] === 'string' && /^(#|var:)/.test(attrs[legacy])) {
      style.color = {
        ...style.color,
        [slot]: style.color?.[slot] ?? attrs[legacy],
      };
      delete attrs[legacy];
    }
  }
  if (typeof attrs.fontSize === 'number') {
    style.typography = {
      ...style.typography,
      fontSize: style.typography?.fontSize ?? pixels(attrs.fontSize),
    };
    delete attrs.fontSize;
  }
  if (attrs.linkColor) {
    const value = /^(#|var:)/.test(attrs.linkColor)
      ? attrs.linkColor
      : `var:preset|color|${attrs.linkColor}`;
    style.elements = {
      ...style.elements,
      link: {
        ...style.elements?.link,
        color: { text: value, ...style.elements?.link?.color },
      },
    };
    delete attrs.linkColor;
  }
  if (name === 'campaignbridge/container' && attrs.maxWidth !== undefined) {
    attrs.layout = {
      type: 'constrained',
      contentSize: pixels(attrs.maxWidth),
      ...attrs.layout,
    };
    delete attrs.maxWidth;
  }
  if (name === 'campaignbridge/divider') {
    style.border = {
      ...(attrs.color ? { color: attrs.color } : {}),
      ...(attrs.thickness !== undefined
        ? { width: pixels(attrs.thickness) }
        : {}),
      ...(typeof attrs.style === 'string' ? { style: attrs.style } : {}),
      ...style.border,
    };
    delete attrs.color;
    delete attrs.thickness;
  } else if (
    typeof attrs.style === 'string' &&
    ['campaignbridge/button', 'campaignbridge/post-button'].includes(name)
  ) {
    attrs.className = attrs.className || `is-style-${attrs.style}`;
  }
  if (name === 'campaignbridge/columns' && attrs.gap !== undefined) {
    style.spacing = { blockGap: pixels(attrs.gap), ...style.spacing };
    delete attrs.gap;
  }
  if (name === 'campaignbridge/spacer' && attrs.height !== undefined) {
    style.dimensions = { minHeight: pixels(attrs.height), ...style.dimensions };
    delete attrs.height;
  }
  delete attrs.style;
  if (Object.keys(style).length) attrs.style = style;
  return attrs;
}

addFilter(
  'blocks.getBlockAttributes',
  'campaignbridge/native-styles',
  (
    parsed: Attributes,
    block: { name: string },
    _html: string,
    raw: Attributes
  ) => {
    if (!block.name.startsWith('campaignbridge/')) return parsed;
    const migrated = migrateNativeStyles(block.name, raw);
    const result = { ...parsed, ...migrated };
    for (const key of ['padding', 'outerPadding', 'linkColor'])
      delete result[key];
    if (block.name === 'campaignbridge/divider') {
      delete result.color;
      delete result.thickness;
    }
    if (block.name === 'campaignbridge/columns') delete result.gap;
    if (block.name === 'campaignbridge/spacer') delete result.height;
    return result;
  }
);
