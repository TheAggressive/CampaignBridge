/**
 * Container Block Edit Component
 *
 * Provides the editing interface for the CampaignBridge container block. This component
 * manages the root layout structure of email templates with configurable dimensions,
 * padding, and color support.
 *
 * Key Features:
 * - Responsive max-width control (320px - 900px)
 * - Content-area padding via native WordPress spacing support
 * - Automatic block locking to prevent accidental removal/movement
 * - Inner blocks support with restricted allowed blocks
 * - Dynamic appender based on inner block presence
 * - WordPress color panel integration for background/text colors
 *
 * Block Structure:
 * - Outer wrapper with color support
 * - Inner container with max-width and auto margins (centered)
 * - InnerBlocks area with configurable appender behavior
 */

import {
  InnerBlocks,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { useBlockSelection } from '../../scripts/editor/hooks/useBlockSelection';
import { EMAIL_BLOCK_NESTING } from '../shared/nesting';
import type { NormalizedSpacing } from '../shared/spacing';

interface ContainerBlockAttributes {
  layout?: { contentSize?: string; wideSize?: string };
  outerPadding?: NormalizedSpacing;
  padding?: NormalizedSpacing;
  backgroundColor?: string;
  textColor?: string;
  lock?: { remove?: boolean; move?: boolean };
  [key: string]: any;
}

interface EditProps extends BlockEditProps<ContainerBlockAttributes> {
  clientId: string;
}

const DEFAULT_INNER_PADDING = { top: 0, right: 0, bottom: 0, left: 0 };

export default function Edit({ attributes, clientId }: EditProps): JSX.Element {
  const { padding = DEFAULT_INNER_PADDING } = attributes;
  const maxWidth =
    attributes.layout?.contentSize ?? attributes.layout?.wideSize ?? '600px';
  const { updateBlockAttributes } = useDispatch('core/block-editor');
  const { hasInnerBlocks } = useBlockSelection(clientId);

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'cb-email-container__inner',
      style: {
        maxWidth,
        margin: '0 auto',
      },
    },
    {
      allowedBlocks: [...EMAIL_BLOCK_NESTING.container],
      templateLock: false,
      renderAppender: hasInnerBlocks
        ? InnerBlocks.DefaultBlockAppender
        : InnerBlocks.ButtonBlockAppender,
    }
  );

  // Hard lock: cannot remove or move the root container
  useEffect(() => {
    if (attributes.lock?.remove === true && attributes.lock.move === false) {
      return;
    }

    updateBlockAttributes(clientId, {
      lock: { remove: true, move: false },
    });
  }, [attributes.lock, clientId, updateBlockAttributes]);

  // Note: Background/Text colors come from core color support UI
  const blockProps = useBlockProps({
    style: {
      maxWidth,
      boxSizing: 'border-box' as const,
      margin: '0 auto',
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
    },
  });

  return (
    <>
      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
