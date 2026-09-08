/** Format only the source display; never use this text as the send artifact. */
export function formatPreviewSource(html: string): string {
  // Keep comments, quoted attribute values and raw-text elements intact.
  const tokens =
    html.match(
      /<!--[\s\S]*?-->|<(style|script|pre|textarea)\b[^>]*>[\s\S]*?<\/\1\s*>|<![^>]*>|<\/?[a-z][^>"']*(?:(?:"[^"]*"|'[^']*')[^>"']*)*>|[^<]+|</gi
    ) ?? [];
  const voidTags = new Set([
    'area',
    'base',
    'br',
    'col',
    'embed',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'param',
    'source',
    'track',
    'wbr',
  ]);
  const lines: string[] = [];
  let depth = 0;
  for (const token of tokens) {
    const value = token.trim();
    if (!value) continue;
    const closing = /^<\//.test(value);
    const tag = value.match(/^<\/?([a-z][\w:-]*)/i)?.[1].toLowerCase();
    const opaque =
      /^<!--/.test(value) || /^<(style|script|pre|textarea)\b/i.test(value);
    if (closing) depth = Math.max(0, depth - 1);
    const indent = '  '.repeat(depth);
    if (tag && !closing && !opaque && value.length > 80) {
      const attributes =
        value
          .slice(tag.length + 1, value.endsWith('/>') ? -2 : -1)
          .match(/[^\s=]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s]+))?/g) ?? [];
      lines.push(`${indent}<${tag}`);
      for (const attribute of attributes) lines.push(`${indent}  ${attribute}`);
      lines.push(`${indent}${value.endsWith('/>') ? '/>' : '>'}`);
    } else {
      lines.push(...value.split('\n').map(line => indent + line));
    }
    if (
      tag &&
      !closing &&
      !opaque &&
      !voidTags.has(tag) &&
      !value.endsWith('/>')
    )
      depth++;
  }
  return lines.join('\n');
}
