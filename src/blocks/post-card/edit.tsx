import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { fetchPosts, type PostItem } from '../shared/posts';
import { fetchPostTypes, type PostTypeItem } from '../shared/post-types';
import { POST_CARD_ALLOWED_BLOCKS } from './config';
import type { EmailBlockEditProps } from '../types';

interface PostCardAttributes {
  postType: string;
  postId: number;
}

interface BlockEditorSelectors {
  getSelectedBlockClientId: () => string | null;
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EmailBlockEditProps<PostCardAttributes>): JSX.Element {
  const { postType = 'post', postId = 0 } = attributes;
  const [postTypes, setPostTypes] = useState<PostTypeItem[]>([]);
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [loading, setLoading] = useState(false);
  const isSelected = useSelect(
    select =>
      (
        select('core/block-editor') as unknown as BlockEditorSelectors
      ).getSelectedBlockClientId() === clientId,
    [clientId]
  );

  useEffect(() => {
    if (!isSelected) {
      return;
    }

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
  }, [isSelected]);

  useEffect(() => {
    if (!isSelected) {
      return;
    }

    let active = true;
    setLoading(true);
    fetchPosts(postType)
      .then(items => {
        if (active) setPosts(items);
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
  }, [isSelected, postType]);

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
      allowedBlocks: POST_CARD_ALLOWED_BLOCKS,
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
