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
  const padding = props.attributes.padding ?? {
    top: 24,
    right: 0,
    bottom: 24,
    left: 0,
  };
  const blockProps = useBlockProps({
    style: {
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
    },
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: [...EMAIL_BLOCK_NESTING.section],
    templateLock: false,
  });

  return <div {...innerBlocksProps} />;
}
