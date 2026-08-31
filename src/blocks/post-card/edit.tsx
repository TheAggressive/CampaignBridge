import apiFetch from '@wordpress/api-fetch';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const ALLOWED_BLOCKS = [
  'campaignbridge/post-image',
  'campaignbridge/post-title',
  'campaignbridge/post-excerpt',
  'campaignbridge/post-cta',
];

const TEMPLATE = ALLOWED_BLOCKS.map(name => [name]);

interface ApiItem {
  id: number | string;
  label?: string;
  title?: string | { rendered?: string };
}

interface PostCardAttributes {
  postType: string;
  postId: number;
}

export default function Edit({ attributes, setAttributes }) {
  const { postType = 'post', postId = 0 } = attributes as PostCardAttributes;
  const [postTypes, setPostTypes] = useState<ApiItem[]>([]);
  const [posts, setPosts] = useState<ApiItem[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let active = true;
    apiFetch<{ items?: ApiItem[] }>({
      path: '/campaignbridge/v1/post-types',
    })
      .then(response => {
        if (active) setPostTypes(response.items ?? []);
      })
      .catch(() => {
        if (active) setPostTypes([]);
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    apiFetch<{ items?: ApiItem[] }>({
      path: `/campaignbridge/v1/posts?post_type=${encodeURIComponent(postType)}`,
    })
      .then(response => {
        if (active) setPosts(response.items ?? []);
      })
      .catch(() => {
        if (active) setPosts([]);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [postType]);

  const postTypeOptions = postTypes.map(item => ({
    label: item.label ?? String(item.id),
    value: String(item.id),
  }));
  const postOptions = posts.map(item => ({
    label:
      typeof item.title === 'string'
        ? item.title
        : item.title?.rendered || item.label || String(item.id),
    value: String(item.id),
  }));
  const innerBlocksProps = useInnerBlocksProps(
    useBlockProps({ className: 'cb-post-card' }),
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: TEMPLATE as any,
      templateLock: false,
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Post', 'campaignbridge')} initialOpen>
          <SelectControl
            label={__('Post type', 'campaignbridge')}
            value={postType}
            options={postTypeOptions}
            onChange={value => setAttributes({ postType: value, postId: 0 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <SelectControl
            label={__('Post', 'campaignbridge')}
            value={String(postId)}
            options={[
              { label: __('Select a post', 'campaignbridge'), value: '0' },
              ...postOptions,
            ]}
            onChange={value => setAttributes({ postId: Number(value) || 0 })}
            disabled={loading}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {loading && <Spinner />}
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
