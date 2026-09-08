import { formatPreviewSource } from '../../src/scripts/editor/utils/formatPreviewSource';

describe('preview source formatting', () => {
  it('indents nested tables and does not nest content inside void tags', () => {
    expect(
      formatPreviewSource(
        '<table><tr><td><img src="https://example.com/a.jpg">Hello</td></tr></table>'
      )
    ).toBe(
      [
        '<table>',
        '  <tr>',
        '    <td>',
        '      <img src="https://example.com/a.jpg">',
        '      Hello',
        '    </td>',
        '  </tr>',
        '</table>',
      ].join('\n')
    );
  });

  it('keeps quoted delimiters, merge tags, comments and raw CSS intact', () => {
    const comment = '<!--[if mso]><table><tr><td><![endif]-->';
    const style = '<style>.test::before{content:"< >"}</style>';
    const html = `${comment}${style}<p title="a > b">Hello &amp; {{first_name}}</p>`;
    const formatted = formatPreviewSource(html);
    expect(formatted).toContain(comment);
    expect(formatted).toContain(style);
    expect(formatted).toContain('<p title="a > b">');
    expect(formatted).toContain('Hello &amp; {{first_name}}');
  });

  it('breaks long tags into readable attributes without splitting values', () => {
    const style =
      'font-family:Arial,sans-serif;color:#333333;padding:12px 24px;';
    expect(
      formatPreviewSource(`<td align="center" style="${style}">Hi</td>`)
    ).toBe(`<td\n  align="center"\n  style="${style}"\n>\n  Hi\n</td>`);
  });
});
