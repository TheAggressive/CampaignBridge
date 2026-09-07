import { type Block } from '@wordpress/blocks';

import type { PostButtonAttributes } from '../types';
import { align, present, safeCreateBlock } from '../shared/transforms';

/**
 * campaignbridge/post-button → campaignbridge/post-link.
 *
 * Preserves label, destination, customUrl, and align. Maps the button's
 * linkColor (or textColor) into the link's linkColor. Drops button-only
 * attributes (backgroundColor, style) that a text link does not use.
 */
export const transforms = {
  to: [
    {
      type: 'block' as const,
      blocks: ['campaignbridge/post-link'],
      transform: (source: unknown) => {
        const attributes = (
          Array.isArray(source) ? source[0] : source
        ) as PostButtonAttributes;

        const linkColor =
          present(attributes.linkColor) &&
          typeof attributes.linkColor === 'string'
            ? attributes.linkColor
            : undefined;

        const textColor =
          present(attributes.textColor) &&
          typeof attributes.textColor === 'string'
            ? attributes.textColor
            : undefined;

        return safeCreateBlock('campaignbridge/post-link', {
          label: present(attributes.label)
            ? (attributes.label as string)
            : undefined,
          destination: present(attributes.destination)
            ? (attributes.destination as string)
            : undefined,
          customUrl: present(attributes.customUrl)
            ? (attributes.customUrl as string)
            : undefined,
          linkColor: linkColor || textColor,
          align: align(attributes.align),
        }) as unknown as Block;
      },
    },
  ],
};
