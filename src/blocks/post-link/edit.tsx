import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
  isHttpsUrl,
  normalizeDestination,
  resolveDestinationPreview,
  usePostDestination,
} from '../shared/post-destination';
import type { EmailBlockEditProps, PostLinkAttributes } from '../types';

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostLinkAttributes>): JSX.Element {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const label = attributes.label || __('Read more', 'campaignbridge');
  const destination = normalizeDestination(attributes.destination);
  const customUrl =
    typeof attributes.customUrl === 'string' ? attributes.customUrl : '';
  const align = attributes.align ?? 'left';

  const { articleUrl, postParentUrl, postTypeArchiveUrl } = usePostDestination(
    postId,
    postType
  );

  const { previewUrl, destinationHelp } = resolveDestinationPreview({
    destination,
    customUrl,
    articleUrl,
    postParentUrl,
    postTypeArchiveUrl: postTypeArchiveUrl ?? '',
    helpMessages: {
      customUrlRequired: __(
        'Enter a custom absolute URL to preview it.',
        'campaignbridge'
      ),
      noAbsoluteUrlYet: __(
        'This post snapshot has no absolute URL yet; the link renders from the post data at send time.',
        'campaignbridge'
      ),
    },
  });

  const blockProps = useBlockProps({ style: { textAlign: align } });
  const { style: nativeStyle, ...wrapperProps } = blockProps;

  return (
    <div
      {...wrapperProps}
      className={wrapperProps.className
        .split(' ')
        .filter(name => !name.startsWith('has-'))
        .join(' ')}
      style={{ textAlign: align }}
    >
      <InspectorControls>
        <PanelBody title={__('Post link', 'campaignbridge')} initialOpen>
          <TextControl
            label={__('Label', 'campaignbridge')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Link destination', 'campaignbridge')}
            value={destination}
            options={[
              { label: __('Article', 'campaignbridge'), value: 'article' },
              {
                label: __('Post parent', 'campaignbridge'),
                value: 'postParent',
                disabled: !postParentUrl,
              },
              {
                label: __('Post type archive', 'campaignbridge'),
                value: 'postTypeArchive',
                disabled: !postTypeArchiveUrl,
              },
              { label: __('Custom URL', 'campaignbridge'), value: 'custom' },
            ]}
            onChange={value => setAttributes({ destination: value })}
            help={destinationHelp}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {destination === 'custom' && (
            <TextControl
              label={__('Custom URL', 'campaignbridge')}
              type='url'
              value={customUrl}
              onChange={value => setAttributes({ customUrl: value })}
              help={
                customUrl && !isHttpsUrl(customUrl)
                  ? __(
                      'Enter an absolute URL beginning with http:// or https://.',
                      'campaignbridge'
                    )
                  : undefined
              }
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
        </PanelBody>
      </InspectorControls>
      <a
        className={wrapperProps.className
          .split(' ')
          .filter(name => name.startsWith('has-'))
          .join(' ')}
        href={previewUrl || '#'}
        aria-disabled={!previewUrl}
        style={{
          textDecoration: 'underline',
          ...nativeStyle,
        }}
      >
        {label}
      </a>
    </div>
  );
}
