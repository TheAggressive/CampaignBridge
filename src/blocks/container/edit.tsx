/**
 * Container Block Edit Component
 *
 * Provides the editing interface for the CampaignBridge container block. This component
 * manages the root layout structure of email templates with configurable dimensions,
 * padding, and color support.
 *
 * Key Features:
 * - Responsive max-width control (320px - 900px)
 * - Configurable outer padding (gutter around inner table)
 * - Configurable inner padding (content area padding)
 * - Automatic block locking to prevent accidental removal/movement
 * - Inner blocks support with restricted allowed blocks
 * - Dynamic appender based on inner block presence
 * - WordPress color panel integration for background/text colors
 *
 * Block Structure:
 * - Outer wrapper with outer padding and color support
 * - Inner container with max-width and auto margins (centered)
 * - InnerBlocks area with configurable appender behavior
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { BoxControl, PanelBody, RangeControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockSelection } from '../../scripts/editor/hooks/useBlockSelection';

interface ContainerBlockAttributes {
  maxWidth?: number;
  outerPadding?: {
    top?: string;
    right?: string;
    bottom?: string;
    left?: string;
  };
  padding?: {
    top?: string;
    right?: string;
    bottom?: string;
    left?: string;
  };
  backgroundColor?: string;
  textColor?: string;
  [key: string]: any;
}

interface EditProps extends BlockEditProps<ContainerBlockAttributes> {
  clientId: string;
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EditProps): JSX.Element {
  const { maxWidth, outerPadding, padding } = attributes;
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
      allowedBlocks: [
        'campaignbridge/post-excerpt',
        'campaignbridge/post-image',
        'campaignbridge/post-cta',
        'campaignbridge/post-card',
      ],
      templateLock: false,
      renderAppender: hasInnerBlocks
        ? InnerBlocks.DefaultBlockAppender
        : InnerBlocks.ButtonBlockAppender,
    }
  );

  // Hard lock: cannot remove or move the root container
  useEffect(() => {
    updateBlockAttributes(clientId, {
      lock: { remove: true, move: false },
    });
  }, [clientId, updateBlockAttributes]);

  // Note: Background/Text colors come from core color support UI
  const blockProps = useBlockProps({
    style: {
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
    },
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Container', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Max width (px)', 'campaignbridge')}
            min={320}
            max={900}
            value={maxWidth}
            onChange={v => setAttributes({ maxWidth: v })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__('Outer padding (around inner table)', 'campaignbridge')}
            values={outerPadding}
            onChange={vals => setAttributes({ outerPadding: vals })}
            __next40pxDefaultSize
          />

          <BoxControl
            label={__('Inner padding (content area)', 'campaignbridge')}
            values={padding}
            onChange={vals => setAttributes({ padding: vals })}
            __next40pxDefaultSize
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
