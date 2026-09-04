import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
  AlignmentSelect,
  EmailColor,
  type EmailAlignment,
} from '../shared/controls';
import { fetchPostTypes, type PostTypeItem } from '../shared/post-types';
import type { EmailBlockEditProps } from '../types';

type Destination = 'article' | 'postParent' | 'postTypeArchive' | 'custom';

interface PostCtaAttributes {
  label?: string;
  destination?: Destination;
  customUrl?: string;
  backgroundColor?: string;
  textColor?: string;
  align?: EmailAlignment;
  style?: 'button' | 'link';
  linkColor?: string;
}

interface PostRecord {
  link?: string;
  parent?: number;
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostRecord | null;
}

const DESTINATIONS: readonly Destination[] = [
  'article',
  'postParent',
  'postTypeArchive',
  'custom',
];

function normalizeDestination(value: unknown): Destination {
  return typeof value === 'string' &&
    DESTINATIONS.includes(value as Destination)
    ? (value as Destination)
    : 'article';
}

function isSafePreviewUrl(value: string): boolean {
  try {
    return ['http:', 'https:'].includes(new URL(value).protocol);
  } catch {
    return false;
  }
}

function isHttpsUrl(value: string): boolean {
  try {
    return new URL(value).protocol === 'https:';
  } catch {
    return false;
  }
}

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostCtaAttributes>): JSX.Element {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const label = attributes.label || __('Read more', 'campaignbridge');
  const destination = normalizeDestination(attributes.destination);
  const customUrl =
    typeof attributes.customUrl === 'string' ? attributes.customUrl : '';
  const backgroundColor = attributes.backgroundColor || '#111111';
  const textColor = attributes.textColor || '#ffffff';
  const ctaStyle = attributes.style === 'link' ? 'link' : 'button';
  const align = attributes.align ?? 'left';
  const linkColor = attributes.linkColor || '#111111';
  const [postTypes, setPostTypes] = useState<PostTypeItem[]>([]);
  useEffect(() => {
    let active = true;
    fetchPostTypes()
      .then(items => {
        if (active) setPostTypes(items);
      })
      .catch(() => {
        if (active) setPostTypes([]);
      });

    return () => {
      active = false;
    };
  }, []);
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
  const postParentId = Number(post?.parent) || 0;
  const postParent = useSelect(
    select =>
      postParentId
        ? (select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postParentId
          )
        : null,
    [postParentId, postType]
  );
  const articleUrl = post?.link || '';
  const postParentUrl = postParent?.link || '';
  const postTypeArchiveUrl =
    postTypes.find(item => item.id === postType)?.archive_url || '';
  const destinationUrls: Record<Destination, string> = {
    article: articleUrl,
    postParent: postParentUrl,
    postTypeArchive: postTypeArchiveUrl,
    custom: customUrl,
  };
  const destinationUrl = destinationUrls[destination];
  const previewUrl = isSafePreviewUrl(destinationUrl) ? destinationUrl : '';
  const destinationHelp =
    destination === 'postParent' && !postParentUrl
      ? __(
          'The selected article does not have a post parent.',
          'campaignbridge'
        )
      : destination === 'postTypeArchive' && !postTypeArchiveUrl
        ? __(
            'The selected post type does not have an archive page.',
            'campaignbridge'
          )
        : undefined;

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
            label={__('CTA destination', 'campaignbridge')}
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
            value={ctaStyle}
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
          <AlignmentSelect
            value={align}
            onChange={value => setAttributes({ align: value })}
          />
          {ctaStyle === 'link' ? (
            <EmailColor
              label={__('Link color', 'campaignbridge')}
              value={linkColor}
              fallback='#111111'
              onChange={value => setAttributes({ linkColor: value })}
            />
          ) : (
            <>
              <EmailColor
                label={__('Background color', 'campaignbridge')}
                value={backgroundColor}
                fallback='#111111'
                onChange={value => setAttributes({ backgroundColor: value })}
              />
              <EmailColor
                label={__('Text color', 'campaignbridge')}
                value={textColor}
                fallback='#ffffff'
                onChange={value => setAttributes({ textColor: value })}
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
          padding: ctaStyle === 'link' ? 0 : '12px 24px',
          borderRadius: ctaStyle === 'link' ? 0 : 4,
          backgroundColor:
            ctaStyle === 'link' ? 'transparent' : backgroundColor,
          color: ctaStyle === 'link' ? linkColor : textColor,
          textDecoration: ctaStyle === 'link' ? 'underline' : 'none',
          fontWeight: ctaStyle === 'link' ? 400 : 700,
        }}
      >
        {label}
      </a>
    </div>
  );
}
