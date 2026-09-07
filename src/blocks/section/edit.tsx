import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { EMAIL_BLOCK_NESTING } from '../shared/nesting';
import type { NormalizedSpacing } from '../shared/spacing';
import type { EmailBlockEditProps } from '../types';

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
    allowedBlocks: [...EMAIL_BLOCK_NESTING.section],
    templateLock: false,
  });

  return <div {...innerBlocksProps} />;
}
