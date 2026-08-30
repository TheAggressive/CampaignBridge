import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const height = Number(attributes.height) || 24;

  return (
    <div
      {...useBlockProps({
        style: { height, backgroundColor: 'rgba(0, 0, 0, 0.04)' },
      })}
      aria-label={__('Email spacer', 'campaignbridge')}
    >
      <InspectorControls>
        <PanelBody title={__('Email spacer', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Height', 'campaignbridge')}
            value={height}
            min={4}
            max={120}
            onChange={value => setAttributes({ height: Number(value) || 24 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
    </div>
  );
}
