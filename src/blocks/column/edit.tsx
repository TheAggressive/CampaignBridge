import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
  Button,
  ColorPalette,
  PanelBody,
  RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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

interface ColumnAttributes {
  width?: number;
  backgroundColor?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ColumnAttributes>): JSX.Element {
  const { width, backgroundColor } = attributes;
  const blockProps = useBlockProps({
    style: {
      // An unset width shares the row evenly, matching the compiler default.
      flexBasis: width === undefined ? 0 : `${width}%`,
      flexGrow: width === undefined ? 1 : 0,
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
        <PanelBody title={__('Email column', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Width', 'campaignbridge')}
            help={__(
              'Percentage of the row. Widths must total 100 across the row, or leave every column unset to divide it evenly.',
              'campaignbridge'
            )}
            value={width}
            onChange={value => setAttributes({ width: value })}
            min={20}
            max={80}
            step={5}
            allowReset
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <p>{__('Background color', 'campaignbridge')}</p>
          <ColorPalette
            value={backgroundColor}
            onChange={value => setAttributes({ backgroundColor: value })}
          />
          {backgroundColor !== undefined && (
            <Button
              variant='tertiary'
              onClick={() => setAttributes({ backgroundColor: undefined })}
            >
              {__('Clear background', 'campaignbridge')}
            </Button>
          )}
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
