import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { BoxControl, ColorPalette, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  normalizeSpacing,
  toControlSpacing,
  type NormalizedSpacing,
} from '../shared/spacing';
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

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<SectionAttributes>): JSX.Element {
  const {
    padding = { top: 24, right: 0, bottom: 24, left: 0 },
    backgroundColor = '#ffffff',
  } = attributes;
  const blockProps = useBlockProps({
    style: {
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
      backgroundColor,
    },
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    templateLock: false,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email section', 'campaignbridge')} initialOpen>
          <BoxControl
            label={__('Padding', 'campaignbridge')}
            values={toControlSpacing(padding)}
            onChange={values =>
              setAttributes({ padding: normalizeSpacing(values) })
            }
            __next40pxDefaultSize
          />
          <p>{__('Background color', 'campaignbridge')}</p>
          <ColorPalette
            value={backgroundColor}
            onChange={value =>
              setAttributes({ backgroundColor: value || '#ffffff' })
            }
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
