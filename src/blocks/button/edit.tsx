import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  ColorPalette,
  PanelBody,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

interface ButtonAttributes {
  label?: string;
  url?: string;
  align?: 'left' | 'center' | 'right';
  backgroundColor?: string;
  textColor?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ButtonAttributes>): JSX.Element {
  const {
    label = __('Learn more', 'campaignbridge'),
    url = '',
    align = 'left',
    backgroundColor = '#111111',
    textColor = '#ffffff',
  } = attributes;

  return (
    <div {...useBlockProps({ style: { textAlign: align } })}>
      <InspectorControls>
        <PanelBody title={__('Email button', 'campaignbridge')} initialOpen>
          <TextControl
            label={__('Label', 'campaignbridge')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('HTTPS URL', 'campaignbridge')}
            type='url'
            value={url}
            onChange={value => setAttributes({ url: value })}
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
          <p>{__('Background color', 'campaignbridge')}</p>
          <ColorPalette
            value={backgroundColor}
            onChange={value =>
              setAttributes({ backgroundColor: value || '#111111' })
            }
          />
          <p>{__('Text color', 'campaignbridge')}</p>
          <ColorPalette
            value={textColor}
            onChange={value => setAttributes({ textColor: value || '#ffffff' })}
          />
        </PanelBody>
      </InspectorControls>
      <a
        href={url || '#'}
        style={{
          display: 'inline-block',
          padding: '12px 24px',
          borderRadius: 4,
          backgroundColor,
          color: textColor,
          textDecoration: 'none',
          fontWeight: 700,
        }}
      >
        {label}
      </a>
    </div>
  );
}
