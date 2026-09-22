import { applyFilters } from '@wordpress/hooks';
import {
  constrainCoreEmailBlock,
  restrictSupport,
} from '../../src/scripts/editor/core-email-blocks';
import {
  CORE_EMAIL_BLOCK_NAMES,
  EMAIL_BLOCK_CONTRACT,
  EMAIL_BLOCK_NAMES,
  SOCIAL_EMAIL_SERVICE_NAMES,
} from '../../src/blocks/shared/nesting';

/** A trimmed copy of WordPress 7.1 Core supports used as filter input. */
const paragraph = {
  supports: {
    align: ['wide', 'full'],
    splitting: true,
    anchor: true,
    className: false,
    __experimentalBorder: { color: true, radius: true },
    color: {
      gradients: true,
      link: true,
      __experimentalDefaultControls: { background: true, text: true },
    },
    spacing: { margin: true, padding: true },
    typography: {
      fontSize: true,
      lineHeight: true,
      textAlign: true,
      __experimentalFontFamily: true,
      __experimentalLetterSpacing: true,
      __experimentalTextDecoration: true,
      fitText: true,
    },
  },
};

describe('WordPress Core email authoring blocks', () => {
  it('supports exactly the approved Core blocks', () => {
    expect([...CORE_EMAIL_BLOCK_NAMES].sort()).toEqual([
      'core/button',
      'core/buttons',
      'core/heading',
      'core/image',
      'core/list',
      'core/list-item',
      'core/paragraph',
      'core/separator',
      'core/social-link',
      'core/social-links',
      'core/spacer',
    ]);
  });

  it('keeps unsupported Core blocks and replaced duplicates out of the grammar', () => {
    for (const name of [
      'core/group',
      'core/cover',
      'core/gallery',
      'core/embed',
      'core/video',
      'core/html',
      'core/shortcode',
      'core/query',
      'core/navigation',
      'core/columns',
      'core/column',
      'campaignbridge/text',
      'campaignbridge/heading',
      'campaignbridge/image',
      'campaignbridge/button',
      'campaignbridge/divider',
      'campaignbridge/spacer',
      'campaignbridge/list',
      'campaignbridge/list-item',
    ]) {
      expect(EMAIL_BLOCK_NAMES).not.toContain(name);
    }
  });

  it('names a child only when that child is itself supported', () => {
    for (const entry of Object.values(EMAIL_BLOCK_CONTRACT)) {
      for (const child of entry.children) {
        expect(EMAIL_BLOCK_NAMES).toContain(child);
      }
    }
  });

  it('narrows paragraph design tools to the email-safe subset', () => {
    const next = constrainCoreEmailBlock(paragraph, 'core/paragraph');
    const supports = next.supports as Record<string, any>;

    expect(supports.splitting).toBe(true);
    expect(supports.align).toBe(false);
    expect(supports.anchor).toBe(false);
    expect(supports.html).toBe(false);
    expect(supports.customClassName).toBe(false);
    expect(supports.__experimentalBorder).toBe(false);
    expect(supports.color).toEqual({
      gradients: false,
      link: false,
      text: true,
      background: true,
      __experimentalDefaultControls: { background: true, text: true },
    });
    expect(supports.typography).toEqual({
      fontSize: true,
      lineHeight: true,
      textAlign: true,
      __experimentalFontFamily: true,
      __experimentalLetterSpacing: false,
      __experimentalTextDecoration: false,
      fitText: false,
    });
    expect(paragraph.supports.anchor).toBe(true);
  });

  it('removes heading backgrounds and limits heading levels to the compiler range', () => {
    const next = constrainCoreEmailBlock(
      {
        supports: { color: { gradients: true } },
        attributes: { levelOptions: { type: 'array' } },
      },
      'core/heading'
    );

    expect((next.supports as Record<string, any>).color).toEqual({
      gradients: false,
      text: true,
      background: false,
    });
    expect(next.attributes?.levelOptions).toEqual({
      type: 'array',
      default: [1, 2, 3, 4],
    });
  });

  it('adjusts block styles to what the compiler can express', () => {
    expect(
      constrainCoreEmailBlock(
        {
          styles: [
            { name: 'fill', label: 'Fill', isDefault: true },
            { name: 'outline', label: 'Outline' },
          ],
        },
        'core/button'
      ).styles?.map(style => style.name)
    ).toEqual(['fill', 'outline', 'ghost']);
    expect(
      constrainCoreEmailBlock(
        {
          styles: [
            { name: 'default', label: 'Default' },
            { name: 'wide', label: 'Wide' },
            { name: 'dots', label: 'Dots' },
          ],
        },
        'core/separator'
      ).styles?.map(style => style.name)
    ).toEqual(['default', 'wide']);
    expect(
      constrainCoreEmailBlock(
        {
          styles: [
            { name: 'default', label: 'Default' },
            { name: 'rounded', label: 'Rounded' },
          ],
        },
        'core/image'
      ).styles?.map(style => style.name)
    ).toEqual(['default']);
  });

  it('limits image alignment and button group layout', () => {
    const image = constrainCoreEmailBlock(
      { supports: { align: ['left', 'center', 'right', 'wide', 'full'] } },
      'core/image'
    );
    const buttons = constrainCoreEmailBlock(
      {
        supports: {
          layout: { allowSwitching: false, default: { type: 'flex' } },
        },
      },
      'core/buttons'
    );

    expect((image.supports as Record<string, any>).align).toEqual([
      'left',
      'center',
      'right',
    ]);
    expect((buttons.supports as Record<string, any>).layout).toEqual({
      allowSwitching: false,
      default: { type: 'flex' },
      allowOrientation: false,
      allowVerticalAlignment: false,
      allowSizingOnChildren: false,
    });
  });

  it('keeps list items flat as the v1 grammar requires', () => {
    expect(
      constrainCoreEmailBlock(
        { allowedBlocks: ['core/list'] },
        'core/list-item'
      ).allowedBlocks
    ).toEqual([]);
  });

  it('keeps only packaged Social Icon services and email-safe parent controls', () => {
    const parent = constrainCoreEmailBlock(
      {
        supports: {
          align: ['left', 'center', 'right', 'wide'],
          color: { background: true, gradients: true },
          spacing: { blockGap: true, margin: true, padding: true },
          layout: {
            allowSwitching: false,
            allowOrientation: true,
            allowVerticalAlignment: true,
          },
        },
        styles: [
          { name: 'default', label: 'Default', isDefault: true },
          { name: 'logos-only', label: 'Logos Only' },
          { name: 'pill-shape', label: 'Pill Shape' },
        ],
      },
      'core/social-links'
    );
    const child = constrainCoreEmailBlock(
      {
        variations: [
          { name: 'facebook' },
          { name: 'wordpress' },
          { name: 'youtube' },
        ],
      },
      'core/social-link'
    );

    expect(SOCIAL_EMAIL_SERVICE_NAMES).toEqual([
      'bluesky',
      'facebook',
      'instagram',
      'linkedin',
      'mastodon',
      'tiktok',
      'x',
      'youtube',
    ]);
    expect((parent.supports as Record<string, any>).align).toEqual([
      'left',
      'center',
      'right',
    ]);
    expect((parent.supports as Record<string, any>).color).toBe(false);
    expect((parent.supports as Record<string, any>).spacing).toEqual({
      blockGap: true,
      margin: false,
      padding: false,
    });
    expect(parent.styles).toEqual([
      { name: 'logos-only', label: 'Logos Only', isDefault: true },
    ]);
    expect(child.variations?.map(variation => variation.name)).toEqual([
      'facebook',
      'youtube',
    ]);
  });

  it('leaves blocks outside the contract untouched', () => {
    const group = { supports: { anchor: true } };
    expect(constrainCoreEmailBlock(group, 'core/group')).toBe(group);
    expect(restrictSupport({ margin: true }, [])).toBe(false);
  });

  it('is registered on the WordPress block registration filter', () => {
    const next = applyFilters(
      'blocks.registerBlockType',
      { supports: { anchor: true } },
      'core/spacer'
    ) as { supports: Record<string, unknown> };

    expect(next.supports.anchor).toBe(false);
    expect(next.supports.spacing).toBe(false);
  });
});
