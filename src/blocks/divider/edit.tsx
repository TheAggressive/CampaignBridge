import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  ColorPalette,
  PanelBody,
  RangeControl,
  SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const {
    color = '#dddddd',
    thickness = 1,
    width = 100,
    style = 'solid',
  } = attributes;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__('Email divider', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Width (%)', 'campaignbridge')}
            value={width}
            min={10}
            max={100}
            onChange={value => setAttributes({ width: Number(value) || 100 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Thickness', 'campaignbridge')}
            value={thickness}
            min={1}
            max={8}
            onChange={value => setAttributes({ thickness: Number(value) || 1 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Style', 'campaignbridge')}
            value={style}
            options={[
              { label: __('Solid', 'campaignbridge'), value: 'solid' },
              { label: __('Dashed', 'campaignbridge'), value: 'dashed' },
              { label: __('Dotted', 'campaignbridge'), value: 'dotted' },
            ]}
            onChange={value => setAttributes({ style: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <p>{__('Color', 'campaignbridge')}</p>
          <ColorPalette
            value={color}
            onChange={value => setAttributes({ color: value || '#dddddd' })}
          />
        </PanelBody>
      </InspectorControls>
      <hr
        style={{
          border: 0,
          borderTop: `${thickness}px ${style} ${color}`,
          width: `${width}%`,
        }}
      />
    </div>
  );
}
