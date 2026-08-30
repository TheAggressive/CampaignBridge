import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { ColorPalette, PanelBody, SelectControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const {
    content = '',
    level = 2,
    align = 'left',
    textColor = '#111111',
  } = attributes;
  const headingTags = ['h1', 'h2', 'h3', 'h4'] as const;
  const tagName = headingTags[Math.max(1, Math.min(4, Number(level) || 2)) - 1];
  const heading = createElement(RichText, {
    ...useBlockProps({ style: { textAlign: align, color: textColor } }),
    tagName,
    value: content,
    allowedFormats: [],
    placeholder: __('Write a heading…', 'campaignbridge'),
    onChange: value => setAttributes({ content: value }),
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email heading', 'campaignbridge')} initialOpen>
          <SelectControl
            label={__('Level', 'campaignbridge')}
            value={String(level)}
            options={[1, 2, 3, 4].map(value => ({
              label: `H${value}`,
              value: String(value),
            }))}
            onChange={value => setAttributes({ level: Number(value) })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Alignment', 'campaignbridge')}
            value={align}
            options={[
              { label: __('Left', 'campaignbridge'), value: 'left' },
              { label: __('Center', 'campaignbridge'), value: 'center' },
              { label: __('Right', 'campaignbridge'), value: 'right' },
            ]}
            onChange={value => setAttributes({ align: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <p>{__('Text color', 'campaignbridge')}</p>
          <ColorPalette
            value={textColor}
            onChange={value => setAttributes({ textColor: value || '#111111' })}
          />
        </PanelBody>
      </InspectorControls>
      {heading}
    </>
  );
}
