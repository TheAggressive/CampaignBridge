import {
  BlockControls,
  InspectorControls,
  store as blockEditorStore,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import {
  BoxControl,
  Button,
  ColorPalette,
  PanelBody,
  SelectControl,
  Spinner,
  ToolbarGroup,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { layout as LayoutIcon } from '@wordpress/icons';
import type { ComponentType } from 'react';

import { fetchPosts, type PostItem } from '../shared/posts';
import { fetchPostTypes, type PostTypeItem } from '../shared/post-types';
import {
  normalizeSpacing,
  toControlSpacing,
  type NormalizedSpacing,
} from '../shared/spacing';
import { POST_CARD_ALLOWED_BLOCKS } from './config';
import { detectActiveLayout, POST_CARD_VARIATIONS } from './variations';
import type { EmailBlockEditProps } from '../types';

interface PostCardAttributes {
  postType: string;
  postId: number;
  padding?: NormalizedSpacing;
  backgroundColor?: string;
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
    select => select(blockEditorStore).getSelectedBlockClientId() === clientId,
    [clientId]
  );
  const { replaceInnerBlocks } = useDispatch(blockEditorStore);
  const innerBlocks = useSelect(
    select =>
      select(blockEditorStore).getBlocksByClientId(clientId)[0]?.innerBlocks ??
      [],
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
  const {
    padding = { top: 20, right: 0, bottom: 20, left: 0 },
    backgroundColor,
  } = attributes;
  const innerBlocksProps = useInnerBlocksProps(
    useBlockProps({
      className: 'cb-post-card',
      style: {
        padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
        backgroundColor,
      },
    }),
    {
      allowedBlocks: POST_CARD_ALLOWED_BLOCKS,
      templateLock: false,
    }
  );

  const activeLayout = detectActiveLayout(innerBlocks);

  const applyLayout = (
    variation: (typeof POST_CARD_VARIATIONS)[number]
  ): void => {
    const variationBlocks = createBlocksFromInnerBlocksTemplate(
      variation.innerBlocks as Array<
        [string, Record<string, unknown>, unknown[]]
      >
    );
    if (variationBlocks.length > 0) {
      // Keep the Post Card selected so the Layout control stays available for
      // trying another layout.
      replaceInnerBlocks(clientId, variationBlocks);
    }
  };

  const layoutControls = POST_CARD_VARIATIONS.map(variation => ({
    icon: variation.icon as ComponentType,
    title: variation.title,
    isActive: activeLayout === variation.name,
    onClick: () => applyLayout(variation),
  }));

  return (
    <>
      <BlockControls>
        <ToolbarGroup
          isCollapsed
          title={__('Layout', 'campaignbridge')}
          icon={LayoutIcon}
          controls={layoutControls}
        />
      </BlockControls>
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
        <PanelBody
          title={__('Card style', 'campaignbridge')}
          initialOpen={false}
        >
          <BoxControl
            label={__('Padding', 'campaignbridge')}
            values={toControlSpacing(padding)}
            onChange={values =>
              setAttributes({ padding: normalizeSpacing(values) })
            }
            __next40pxDefaultSize
          />
          <p>{__('Background color', 'campaignbridge')}</p>
          <ColorPalette
            value={backgroundColor}
            onChange={value => setAttributes({ backgroundColor: value })}
          />
          {backgroundColor !== undefined && (
            <Button
              variant='tertiary'
              onClick={() => setAttributes({ backgroundColor: undefined })}
            >
              {__('Clear background', 'campaignbridge')}
            </Button>
          )}
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
