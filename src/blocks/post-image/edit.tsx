import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { AlignmentSelect, type EmailAlignment } from '../shared/controls';
import type { EmailBlockEditProps } from '../types';

interface PostImageAttributes {
  width?: number;
  align?: EmailAlignment;
  linkToPost?: boolean;
  decorative?: boolean;
}

interface PostRecord {
  featured_media?: number;
}

interface MediaRecord {
  source_url?: string;
  media_details?: {
    sizes?: { full?: { source_url?: string } };
  };
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostRecord | MediaRecord | null;
}

export default function Edit({
  attributes,
  setAttributes,
  context = {},
}: EmailBlockEditProps<PostImageAttributes>): JSX.Element {
  const {
    width,
    align = 'left',
    linkToPost = false,
    decorative = false,
  } = attributes;
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const post = useSelect(
    select =>
      postId
        ? ((select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postId
          ) as PostRecord | null)
        : null,
    [postType, postId]
  );
  const mediaId = (post && post.featured_media) || 0;
  const media = useSelect(
    select =>
      mediaId
        ? ((select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            'attachment',
            mediaId
          ) as MediaRecord | null)
        : null,
    [mediaId]
  );
  const url =
    (media &&
      media.media_details &&
      media.media_details.sizes &&
      media.media_details.sizes.full &&
      media.media_details.sizes.full.source_url) ||
    (media && media.source_url) ||
    '';
  const props = useBlockProps({ style: { textAlign: align } });
  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Post image', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Width', 'campaignbridge')}
            help={__(
              'Maximum width in pixels. Leave unset to use the image’s own width.',
              'campaignbridge'
            )}
            value={width}
            min={1}
            max={1200}
            allowReset
            onChange={value => setAttributes({ width: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <AlignmentSelect
            value={align}
            onChange={value => setAttributes({ align: value })}
          />
          <ToggleControl
            label={__('Link to the post', 'campaignbridge')}
            checked={linkToPost}
            onChange={value => setAttributes({ linkToPost: value })}
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__('Decorative image', 'campaignbridge')}
            help={__(
              'Hides the image from screen readers. Use only when the image adds nothing the text does not already say.',
              'campaignbridge'
            )}
            checked={decorative}
            onChange={value => setAttributes({ decorative: value })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...props}>
        {url ? (
          <img
            src={url}
            alt=''
            style={{
              display: 'inline-block',
              width: '100%',
              maxWidth: width === undefined ? undefined : `${width}px`,
              height: 'auto',
              border: 0,
            }}
          />
        ) : null}
      </div>
    </>
  );
}
