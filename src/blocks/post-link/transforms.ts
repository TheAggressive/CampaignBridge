import { type Block } from '@wordpress/blocks';

import type { PostLinkAttributes } from '../types';
import { align, present, safeCreateBlock } from '../shared/transforms';

/**
 * campaignbridge/post-link → campaignbridge/post-button.
 *
 * Preserves label, destination, customUrl, and align. Maps the link's
 * linkColor into the button's textColor (the text-on-fill colour). Lets the
 * button's block.json defaults apply for button-only attributes (backgroundColor,
 * style) rather than hard-coding them here.
 */
export const transforms = {
  to: [
    {
      type: 'block' as const,
      blocks: ['campaignbridge/post-button'],
      transform: (source: unknown) => {
        const attributes = (
          Array.isArray(source) ? source[0] : source
        ) as PostLinkAttributes;

        const linkColor =
          present(attributes.linkColor) &&
          typeof attributes.linkColor === 'string'
            ? attributes.linkColor
            : undefined;

        return safeCreateBlock('campaignbridge/post-button', {
          label: present(attributes.label)
            ? (attributes.label as string)
            : undefined,
          destination: present(attributes.destination)
            ? (attributes.destination as string)
            : undefined,
          customUrl: present(attributes.customUrl)
            ? (attributes.customUrl as string)
            : undefined,
          textColor: linkColor,
          align: align(attributes.align),
        }) as unknown as Block;
      },
    },
  ],
};
