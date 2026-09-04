import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useExcerptPreview } from './hooks/useExcerptPreview';
import type { EmailBlockEditProps } from '../types';

interface PostExcerptAttributes {
  maxWords?: number;
}

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostExcerptAttributes>): JSX.Element {
  const maxWords = Number(attributes.maxWords) || 50;
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const excerpt = useExcerptPreview({
    postId,
    postType,
    maxWords,
  });

  return (
    <p {...useBlockProps()}>
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
        </PanelBody>
      </InspectorControls>
      {excerpt || __('Post excerpt', 'campaignbridge')}
    </p>
  );
}
