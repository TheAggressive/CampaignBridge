import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  ColorPalette,
  PanelBody,
  RangeControl,
  SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const {
    content = '',
    align = 'left',
    textColor = '#333333',
    fontSize = 16,
  } = attributes;

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email text', 'campaignbridge')} initialOpen>
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
          <RangeControl
            label={__('Font size', 'campaignbridge')}
            value={fontSize}
            min={12}
            max={24}
            onChange={value => setAttributes({ fontSize: Number(value) || 16 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <p>{__('Text color', 'campaignbridge')}</p>
          <ColorPalette
            value={textColor}
            onChange={value => setAttributes({ textColor: value || '#333333' })}
          />
        </PanelBody>
      </InspectorControls>
      <RichText
        {...useBlockProps({
          style: { textAlign: align, color: textColor, fontSize },
        })}
        tagName='p'
        value={content}
        allowedFormats={[
          'core/bold',
          'core/italic',
          'core/strikethrough',
          'core/link',
        ]}
        placeholder={__('Write email text…', 'campaignbridge')}
        onChange={value => setAttributes({ content: value })}
      />
    </>
  );
}
