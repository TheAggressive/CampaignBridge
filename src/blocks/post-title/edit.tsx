import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { createElement } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import {
  AlignmentSelect,
  EmailColor,
  type EmailAlignment,
} from '../shared/controls';
import type { EmailBlockEditProps } from '../types';

interface PostTitleAttributes {
  level?: number;
  align?: EmailAlignment;
  textColor?: string;
  linkToPost?: boolean;
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => { title?: { rendered?: string } } | null;
}

const LEVELS = [1, 2, 3, 4].map(level => ({
  label: `H${level}`,
  value: String(level),
}));

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostTitleAttributes>): JSX.Element {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const level = Number(attributes.level) || 2;
  const {
    align = 'left',
    textColor = '#111111',
    linkToPost = false,
  } = attributes;
  const post = useSelect(
    select =>
      postId
        ? (select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postId
          )
        : null,
    [postType, postId]
  );
  const title = decodeEntities(post?.title?.rendered || '');
  const tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4';
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Heading', 'campaignbridge')} initialOpen>
          <SelectControl
            label={__('Level', 'campaignbridge')}
            value={String(level)}
            options={LEVELS}
            onChange={value => setAttributes({ level: Number(value) })}
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
            fallback='#111111'
            onChange={value => setAttributes({ textColor: value })}
          />
          <ToggleControl
            label={__('Link to the post', 'campaignbridge')}
            checked={linkToPost}
            onChange={value => setAttributes({ linkToPost: value })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      {createElement(
        tag,
        { ...blockProps, style: { textAlign: align, color: textColor } },
        title || __('Post title', 'campaignbridge')
      )}
    </>
  );
}
