import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { NormalizedSpacing } from '../shared/spacing';
import type { EmailBlockEditProps } from '../types';

const ALLOWED_BLOCKS = [
  'campaignbridge/text',
  'campaignbridge/heading',
  'campaignbridge/image',
  'campaignbridge/button',
  'campaignbridge/divider',
  'campaignbridge/spacer',
  'campaignbridge/post-card',
];

interface SectionAttributes {
  padding?: NormalizedSpacing;
  backgroundColor?: string;
}

export default function Edit(
  props: EmailBlockEditProps<SectionAttributes>
): JSX.Element {
  void props;
  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    templateLock: false,
  });

  return <div {...innerBlocksProps} />;
}
