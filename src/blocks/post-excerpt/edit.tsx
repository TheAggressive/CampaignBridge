import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useExcerptPreview } from './hooks/useExcerptPreview';
import {
  AlignmentSelect,
  EmailColor,
  type EmailAlignment,
} from '../shared/controls';
import type { EmailBlockEditProps } from '../types';

interface PostExcerptAttributes {
  maxWords?: number;
  align?: EmailAlignment;
  textColor?: string;
  fontSize?: number;
}

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostExcerptAttributes>): JSX.Element {
  const maxWords = Number(attributes.maxWords) || 50;
  const { align = 'left', textColor = '#333333', fontSize = 16 } = attributes;
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const excerpt = useExcerptPreview({
    postId,
    postType,
    maxWords,
  });

  return (
    <p
      {...useBlockProps({
        style: {
          textAlign: align,
          color: textColor,
          fontSize: `${fontSize}px`,
        },
      })}
    >
      <InspectorControls>
        <PanelBody title={__('Excerpt', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Maximum words', 'campaignbridge')}
            value={maxWords}
            min={10}
            max={150}
            onChange={value => setAttributes({ maxWords: Number(value) || 50 })}
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
          <AlignmentSelect
            value={align}
            onChange={value => setAttributes({ align: value })}
          />
          <EmailColor
            label={__('Text color', 'campaignbridge')}
            value={textColor}
            fallback='#333333'
            onChange={value => setAttributes({ textColor: value })}
          />
        </PanelBody>
      </InspectorControls>
      {excerpt || __('Post excerpt', 'campaignbridge')}
    </p>
  );
}
