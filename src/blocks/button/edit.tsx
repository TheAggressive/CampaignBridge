import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import type { EmailBlockEditProps } from '../types';

interface ButtonAttributes {
  label?: string;
  url?: string;
  align?: 'left' | 'center' | 'right';
  backgroundColor?: string;
  textColor?: string;
  className?: string;
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

  const variant =
    attributes.className?.match(/is-style-([a-z0-9-]+)/)?.[1] ?? 'primary';

  const buttonStyle: CSSProperties =
    variant === 'outline'
      ? {
          backgroundColor: 'transparent',
          color: backgroundColor,
          padding: '10px 20px',
          border: `2px solid ${backgroundColor}`,
          textDecoration: 'none',
        }
      : variant === 'ghost'
        ? {
            backgroundColor: 'transparent',
            color: backgroundColor,
            padding: '12px 24px',
            border: 'none',
            textDecoration: 'underline',
          }
        : {
            backgroundColor,
            color: textColor,
            padding: '12px 24px',
            border: 'none',
            textDecoration: 'none',
          };

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
            label={__('URL', 'campaignbridge')}
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
        </PanelBody>
      </InspectorControls>
      <a
        href={url || '#'}
        style={{
          display: 'inline-block',
          borderRadius: 4,
          fontWeight: 700,
          ...buttonStyle,
        }}
      >
        {label}
      </a>
    </div>
  );
}
