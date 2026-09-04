import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

const ALLOWED_BLOCKS = ['campaignbridge/column'];

/** Two even columns is the layout authors reach for first. */
const TEMPLATE: [string, Record<string, unknown>][] = [
  ['campaignbridge/column', {}],
  ['campaignbridge/column', {}],
];

interface ColumnsAttributes {
  gap?: number;
  verticalAlign?: 'top' | 'middle' | 'bottom';
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ColumnsAttributes>): JSX.Element {
  const { gap = 24, verticalAlign = 'top' } = attributes;
  const blockProps = useBlockProps({
    style: {
      display: 'flex',
      alignItems:
        verticalAlign === 'middle'
          ? 'center'
          : verticalAlign === 'bottom'
            ? 'flex-end'
            : 'flex-start',
      gap: `${gap}px`,
    },
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    template: TEMPLATE,
    templateLock: false,
    orientation: 'horizontal',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email columns', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Gap', 'campaignbridge')}
            help={__('Space between columns, in pixels.', 'campaignbridge')}
            value={gap}
            onChange={value => setAttributes({ gap: value ?? 24 })}
            min={0}
            max={48}
            step={2}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Vertical alignment', 'campaignbridge')}
            value={verticalAlign}
            options={[
              { label: __('Top', 'campaignbridge'), value: 'top' },
              { label: __('Middle', 'campaignbridge'), value: 'middle' },
              { label: __('Bottom', 'campaignbridge'), value: 'bottom' },
            ]}
            onChange={value =>
              setAttributes({
                verticalAlign: value as ColumnsAttributes['verticalAlign'],
              })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
