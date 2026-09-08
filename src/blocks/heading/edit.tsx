import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

interface HeadingAttributes {
  content?: string;
  level?: number;
  align?: 'left' | 'center' | 'right';
  textColor?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<HeadingAttributes>): JSX.Element {
  const { content = '', level = 2, align = 'left' } = attributes;
  const headingTags = ['h1', 'h2', 'h3', 'h4'] as const;
  const tagName = headingTags[Math.max(1, Math.min(4, Number(level) || 2)) - 1];
  const heading = createElement(RichText, {
    ...useBlockProps({ style: { textAlign: align } }),
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
        </PanelBody>
      </InspectorControls>
      {heading}
    </>
  );
}
