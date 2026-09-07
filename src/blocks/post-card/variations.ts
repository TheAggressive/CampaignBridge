import type { Block, BlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { media, mediaAndText, stack } from '@wordpress/icons';

/**
 * Post Card layout variations.
 *
 * The same three compositions as the Post Card patterns (stacked, media
 * left, media right) exposed as inserter variations, so any layout can be
 * inserted with a single click. The inner block templates mirror the
 * pattern content in src/scripts/editor/utils/blockPatterns.ts; keep the
 * two in sync.
 *
 * Variations are scoped to the inserter so they are never auto-applied to an
 * existing card. The Post Card editor (see edit.tsx) additionally exposes an
 * explicit "Layout" control that applies a chosen variation — replacing the
 * card's inner blocks — only when the user picks one.
 */
export const POST_CARD_VARIATIONS: BlockVariation[] = [
  {
    name: 'stacked',
    title: __('Post card', 'campaignbridge'),
    description: __(
      'Post image, title, excerpt, and call to action',
      'campaignbridge'
    ),
    icon: stack,
    isDefault: true,
    keywords: ['stacked', 'vertical', 'layout'],
    scope: ['inserter'],
    attributes: {
      postType: 'post',
      postId: 0,
    },
    innerBlocks: [
      ['campaignbridge/post-image'],
      ['campaignbridge/post-title'],
      ['campaignbridge/post-excerpt'],
      ['campaignbridge/post-button'],
    ],
  },
  {
    name: 'media-left',
    title: __('Post card, media left', 'campaignbridge'),
    description: __(
      'A post card with the image beside the text, stacking on narrow screens',
      'campaignbridge'
    ),
    icon: mediaAndText,
    keywords: ['media left', 'image left', 'layout'],
    scope: ['inserter'],
    attributes: {
      postType: 'post',
      postId: 0,
    },
    innerBlocks: [
      [
        'campaignbridge/columns',
        { gap: 24, verticalAlign: 'middle' },
        [
          [
            'campaignbridge/column',
            { width: 35 },
            [['campaignbridge/post-image', { linkToPost: true }]],
          ],
          [
            'campaignbridge/column',
            { width: 65 },
            [
              ['campaignbridge/post-title', { linkToPost: true }],
              ['campaignbridge/post-excerpt'],
              ['campaignbridge/post-button', { style: 'link' }],
            ],
          ],
        ],
      ],
    ],
  },
  {
    name: 'media-right',
    title: __('Post card, media right', 'campaignbridge'),
    description: __(
      'A post card with the image beside the text, stacking on narrow screens',
      'campaignbridge'
    ),
    icon: media,
    keywords: ['media right', 'image right', 'layout'],
    scope: ['inserter'],
    attributes: {
      postType: 'post',
      postId: 0,
    },
    innerBlocks: [
      [
        'campaignbridge/columns',
        { gap: 24, verticalAlign: 'middle' },
        [
          [
            'campaignbridge/column',
            { width: 65 },
            [
              ['campaignbridge/post-title', { linkToPost: true }],
              ['campaignbridge/post-excerpt'],
              ['campaignbridge/post-button', { style: 'link' }],
            ],
          ],
          [
            'campaignbridge/column',
            { width: 35 },
            [['campaignbridge/post-image', { linkToPost: true }]],
          ],
        ],
      ],
    ],
  },
];

/**
 * The layout names a Post Card can take, mirroring POST_CARD_VARIATIONS.
 */
export type PostCardLayoutName = 'stacked' | 'media-left' | 'media-right';

/**
 * Map a Post Card's current inner blocks to the layout they represent, so the
 * editor's Layout control can highlight the active option.
 *
 * A single `campaignbridge/columns` child means a side-by-side layout: the
 * first column holds the copy (width 65) for `media-right` and the image
 * (width 35) for `media-left`. Anything else is treated as `stacked`.
 *
 * @param innerBlocks The card's current inner blocks.
 * @returns The name of the variation the card currently matches.
 */
export function detectActiveLayout(innerBlocks: Block[]): PostCardLayoutName {
  if (
    innerBlocks.length === 1 &&
    innerBlocks[0].name === 'campaignbridge/columns'
  ) {
    const firstColumn = innerBlocks[0].innerBlocks[0];
    return firstColumn?.attributes?.width === 65 ? 'media-right' : 'media-left';
  }

  return 'stacked';
}
