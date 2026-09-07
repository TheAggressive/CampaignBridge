import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ColorPicker,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { toPortableHex } from '../../scripts/admin/brand-kit/color';
import {
  isHttpsUrl,
  normalizeDestination,
  resolveDestinationPreview,
  usePostDestination,
} from '../shared/post-destination';
import type { EmailBlockEditProps, PostButtonAttributes } from '../types';

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostButtonAttributes>): JSX.Element {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const label = attributes.label || __('Read more', 'campaignbridge');
  const destination = normalizeDestination(attributes.destination);
  const customUrl =
    typeof attributes.customUrl === 'string' ? attributes.customUrl : '';
  const backgroundColor = attributes.backgroundColor || '#111111';
  const textColor = attributes.textColor || '#ffffff';
  const buttonStyle = attributes.style === 'link' ? 'link' : 'button';
  const align = attributes.align ?? 'left';
  const linkColor = attributes.linkColor || '#111111';

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
        'Enter a custom HTTPS URL to preview it.',
        'campaignbridge'
      ),
      noHttpsUrlYet: __(
        'This post snapshot has no HTTPS URL yet; the link renders from the post data at send time.',
        'campaignbridge'
      ),
    },
  });

  return (
    <div {...useBlockProps({ style: { textAlign: align } })}>
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
            label={__('Button destination', 'campaignbridge')}
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
              label={__('Custom HTTPS URL', 'campaignbridge')}
              type='url'
              value={customUrl}
              onChange={value => setAttributes({ customUrl: value })}
              help={
                customUrl && !isHttpsUrl(customUrl)
                  ? __(
                      'Enter an absolute URL beginning with https://.',
                      'campaignbridge'
                    )
                  : undefined
              }
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
          <SelectControl
            label={__('Style', 'campaignbridge')}
            value={buttonStyle}
            options={[
              { label: __('Button', 'campaignbridge'), value: 'button' },
              { label: __('Text link', 'campaignbridge'), value: 'link' },
            ]}
            onChange={value =>
              setAttributes({ style: value as 'button' | 'link' })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {buttonStyle === 'link' && (
            <>
              <p className='components-base-control__label'>
                {__('Link color', 'campaignbridge')}
              </p>
              <ColorPicker
                color={linkColor}
                onChange={value => {
                  const hex = toPortableHex(value);
                  if (hex) {
                    setAttributes({ linkColor: hex });
                  }
                }}
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>
      <a
        href={previewUrl || '#'}
        aria-disabled={!previewUrl}
        style={{
          display: 'inline-block',
          padding: buttonStyle === 'link' ? 0 : '12px 24px',
          borderRadius: buttonStyle === 'link' ? 0 : 4,
          backgroundColor:
            buttonStyle === 'link' ? 'transparent' : backgroundColor,
          color: buttonStyle === 'link' ? linkColor : textColor,
          textDecoration: buttonStyle === 'link' ? 'underline' : 'none',
          fontWeight: buttonStyle === 'link' ? 400 : 700,
        }}
      >
        {label}
      </a>
    </div>
  );
}
