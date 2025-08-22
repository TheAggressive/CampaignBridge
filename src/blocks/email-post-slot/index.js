/**
 * Email Post Slot Block - Dynamic content slot for email templates
 *
 * @package CampaignBridge
 */

import {
  __experimentalFontFamilyControl as FontFamilyControl,
  __experimentalFontSizeControl as FontSizeControl,
  InspectorControls,
  __experimentalLineHeightControl as LineHeightControl,
  __experimentalPanelColorGradientSettings as PanelColorGradientSettings,
  __experimentalPanelSpacingSettings as PanelSpacingSettings,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  Button,
  __experimentalNumberControl as NumberControl,
  PanelBody,
  SelectControl,
  Spinner,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { store as coreDataStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Email Post Slot Block Component
 */
function EmailPostSlotBlock({ attributes, setAttributes, isSelected }) {
  const {
    slotKey,
    postType,
    postId,
    displayMode,
    showImage,
    showTitle,
    showExcerpt,
    showReadMore,
    imageSize,
    excerptLength,
    textAlign,
    backgroundColor,
    textColor,
    padding,
  } = attributes;

  const [isLoading, setIsLoading] = useState(false);
  const [selectedPost, setSelectedPost] = useState(null);

  // Block props with dynamic styling
  const blockProps = useBlockProps({
    className: 'cb-email-post-slot',
    style: {
      backgroundColor,
      color: textColor,
      textAlign,
      padding: padding
        ? `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`
        : undefined,
    },
  });

  // Fetch post types
  const postTypes = useSelect((select) => {
    return select(coreDataStore).getPostTypes({ per_page: -1 });
  }, []);

  // Fetch selected post data
  const post = useSelect(
    (select) => {
      if (!postId) return null;
      return select(coreDataStore).getEntityRecord(
        'postType',
        postType,
        postId
      );
    },
    [postId, postType]
  );

  // Fetch posts for selection
  const posts = useSelect(
    (select) => {
      if (!postType) return [];
      return select(coreDataStore).getEntityRecords('postType', postType, {
        per_page: 20,
        status: 'publish',
        orderby: 'date',
        order: 'desc',
      });
    },
    [postType]
  );

  // Update selected post when postId changes
  useEffect(() => {
    if (post) {
      setSelectedPost(post);
    }
  }, [post]);

  // Handle post selection
  const handlePostSelect = (newPostId) => {
    setAttributes({ postId: parseInt(newPostId) });
  };

  // Handle post type change
  const handlePostTypeChange = (newPostType) => {
    setAttributes({
      postType: newPostType,
      postId: 0, // Reset post selection
    });
    setSelectedPost(null);
  };

  // Display mode options
  const displayModeOptions = [
    { label: __('Title Only', 'campaignbridge'), value: 'title_only' },
    { label: __('Title + Excerpt', 'campaignbridge'), value: 'title_excerpt' },
    {
      label: __('Title + Excerpt + Image', 'campaignbridge'),
      value: 'title_excerpt_image',
    },
    { label: __('Full Content', 'campaignbridge'), value: 'full_content' },
  ];

  // Image size options
  const imageSizeOptions = [
    { label: __('Thumbnail', 'campaignbridge'), value: 'thumbnail' },
    { label: __('Medium', 'campaignbridge'), value: 'medium' },
    { label: __('Large', 'campaignbridge'), value: 'large' },
    { label: __('Full Size', 'campaignbridge'), value: 'full' },
  ];

  // Render post preview
  const renderPostPreview = () => {
    if (!selectedPost) {
      return (
        <div className="cb-post-slot-placeholder">
          <p>{__('Select a post to preview content', 'campaignbridge')}</p>
        </div>
      );
    }

    return (
      <div className="cb-post-slot-preview">
        {showImage && selectedPost.featured_media && (
          <div className="cb-post-slot-image">
            <img
              src={
                selectedPost.featured_media_urls?.[imageSize] ||
                selectedPost.featured_media_urls?.thumbnail
              }
              alt={selectedPost.title?.rendered || ''}
              style={{ maxWidth: '100%', height: 'auto' }}
            />
          </div>
        )}

        {showTitle && (
          <h3
            className="cb-post-slot-title"
            dangerouslySetInnerHTML={{
              __html: selectedPost.title?.rendered || '',
            }}
          />
        )}

        {showExcerpt && selectedPost.excerpt?.rendered && (
          <div
            className="cb-post-slot-excerpt"
            dangerouslySetInnerHTML={{ __html: selectedPost.excerpt.rendered }}
          />
        )}

        {showReadMore && (
          <a href={selectedPost.link} className="cb-post-slot-readmore">
            {__('Read More', 'campaignbridge')}
          </a>
        )}
      </div>
    );
  };

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Post Slot Settings', 'campaignbridge')}
          initialOpen={true}
        >
          <TextControl
            label={__('Slot Key', 'campaignbridge')}
            value={slotKey}
            onChange={(value) => setAttributes({ slotKey: value })}
            help={__(
              'Unique identifier for this content slot.',
              'campaignbridge'
            )}
          />

          <SelectControl
            label={__('Post Type', 'campaignbridge')}
            value={postType}
            options={
              postTypes?.map((type) => ({
                label: type.labels?.singular_name || type.name,
                value: type.name,
              })) || []
            }
            onChange={handlePostTypeChange}
          />

          <SelectControl
            label={__('Select Post', 'campaignbridge')}
            value={postId || ''}
            options={[
              { label: __('— Select a post —', 'campaignbridge'), value: '' },
              ...(posts?.map((post) => ({
                label: post.title?.rendered || __('Untitled', 'campaignbridge'),
                value: post.id,
              })) || []),
            ]}
            onChange={handlePostSelect}
          />

          <SelectControl
            label={__('Display Mode', 'campaignbridge')}
            value={displayMode}
            options={displayModeOptions}
            onChange={(value) => setAttributes({ displayMode: value })}
          />
        </PanelBody>

        <PanelBody
          title={__('Content Options', 'campaignbridge')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Show Image', 'campaignbridge')}
            checked={showImage}
            onChange={(value) => setAttributes({ showImage: value })}
          />

          <ToggleControl
            label={__('Show Title', 'campaignbridge')}
            checked={showTitle}
            onChange={(value) => setAttributes({ showTitle: value })}
          />

          <ToggleControl
            label={__('Show Excerpt', 'campaignbridge')}
            checked={showExcerpt}
            onChange={(value) => setAttributes({ showExcerpt: value })}
          />

          <ToggleControl
            label={__('Show Read More Link', 'campaignbridge')}
            checked={showReadMore}
            onChange={(value) => setAttributes({ showReadMore: value })}
          />

          {showImage && (
            <SelectControl
              label={__('Image Size', 'campaignbridge')}
              value={imageSize}
              options={imageSizeOptions}
              onChange={(value) => setAttributes({ imageSize: value })}
            />
          )}

          {showExcerpt && (
            <NumberControl
              label={__('Excerpt Length', 'campaignbridge')}
              value={excerptLength}
              onChange={(value) => setAttributes({ excerptLength: value })}
              min={50}
              max={500}
              step={10}
            />
          )}
        </PanelBody>

        <PanelColorGradientSettings
          title={__('Color Settings', 'campaignbridge')}
          settings={[
            {
              colorValue: backgroundColor,
              onColorChange: (value) =>
                setAttributes({ backgroundColor: value }),
              label: __('Background Color', 'campaignbridge'),
            },
            {
              colorValue: textColor,
              onColorChange: (value) => setAttributes({ textColor: value }),
              label: __('Text Color', 'campaignbridge'),
            },
          ]}
        />

        <PanelSpacingSettings
          title={__('Spacing Settings', 'campaignbridge')}
          values={padding}
          onChange={(value) => setAttributes({ padding: value })}
          splitValues={false}
        />

        <PanelBody
          title={__('Typography', 'campaignbridge')}
          initialOpen={false}
        >
          <FontFamilyControl
            value={attributes.fontFamily}
            onChange={(value) => setAttributes({ fontFamily: value })}
          />

          <FontSizeControl
            value={attributes.fontSize}
            onChange={(value) => setAttributes({ fontSize: value })}
          />

          <LineHeightControl
            value={attributes.lineHeight}
            onChange={(value) => setAttributes({ lineHeight: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {/* Slot Header */}
        {isSelected && (
          <div className="cb-post-slot-header">
            <div className="cb-post-slot-info">
              <span className="cb-post-slot-key">{slotKey}</span>
              <span className="cb-post-slot-type">{postType}</span>
              {postId && <span className="cb-post-slot-id">#{postId}</span>}
            </div>
          </div>
        )}

        {/* Post Content Preview */}
        <div className="cb-post-slot-content">
          {isLoading ? (
            <div className="cb-post-slot-loading">
              <Spinner />
              <span>{__('Loading post content...', 'campaignbridge')}</span>
            </div>
          ) : (
            renderPostPreview()
          )}
        </div>

        {/* Slot Footer */}
        {isSelected && (
          <div className="cb-post-slot-footer">
            <div className="cb-post-slot-actions">
              <Button
                isPrimary
                onClick={() => {
                  // Post selection is handled by the existing post type and post selection controls
                  // This button provides quick access to post selection
                }}
              >
                {__('Select Post', 'campaignbridge')}
              </Button>
              <Button
                isSecondary
                onClick={() => {
                  // Slot configuration is handled by the existing inspector controls
                  // This button provides quick access to slot settings
                }}
              >
                {__('Configure', 'campaignbridge')}
              </Button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

export default EmailPostSlotBlock;
