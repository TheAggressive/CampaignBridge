import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  ColorPalette,
  PanelBody,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes, context = {} }) {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const label = attributes.label || __('Read more', 'campaignbridge');
  const destination =
    attributes.destination === 'postParent' ? 'postParent' : 'article';
  const backgroundColor = attributes.backgroundColor || '#111111';
  const textColor = attributes.textColor || '#ffffff';
  const post = useSelect(
    select =>
      postId
        ? (select('core') as any).getEntityRecord('postType', postType, postId)
        : null,
    [postType, postId]
  );
  const postParentId = Number(post?.parent) || 0;
  const postParent = useSelect(
    select =>
      postParentId
        ? (select('core') as any).getEntityRecord(
            'postType',
            postType,
            postParentId
          )
        : null,
    [postParentId, postType]
  );
  const articleUrl = post?.link || '';
  const postParentUrl = postParent?.link || '';
  const destinationUrl =
    destination === 'postParent' ? postParentUrl : articleUrl;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__('Call to action', 'campaignbridge')} initialOpen>
          <TextControl
            label={__('Label', 'campaignbridge')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('CTA destination', 'campaignbridge')}
            value={destination}
            options={[
              { label: __('Article', 'campaignbridge'), value: 'article' },
              {
                label: __('Post parent', 'campaignbridge'),
                value: 'postParent',
                disabled: !postParentUrl,
              },
            ]}
            onChange={value => setAttributes({ destination: value })}
            help={
              !postParentUrl
                ? __(
                    'The selected article does not have a post parent.',
                    'campaignbridge'
                  )
                : undefined
            }
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
        href={destinationUrl || '#'}
        aria-disabled={!destinationUrl}
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
