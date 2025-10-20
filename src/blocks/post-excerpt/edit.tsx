import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useExcerptPreview } from './hooks/useExcerptPreview';
import { useInlinePlacementClass } from './hooks/useInlinePlacementClass';
import { useReadMoreTemplate } from './hooks/useReadMoreTemplate';
import { useSyncReadMoreStructure } from './hooks/useSyncReadMoreStructure';

/** Fallback defaults (keep block.json as source of truth). */
const DEFAULTS = {
  maxWords: 50,
  showMore: true,
  moreStyle: 'link', // 'link' | 'button'
  morePlacement: 'new-line', // 'new-line' | 'inline'
  linkTo: 'post', // 'post' | 'postType'
  addSpaceBeforeLink: true,

  enableSeparator: false,
  separatorType: 'custom',
  customSeparator: '',
  addSpaceBeforeSeparator: false,
};

const SEPARATOR_OPTIONS = [
  { label: __('Custom', 'campaignbridge'), value: 'custom' },
  { label: __('Em dash (—)', 'campaignbridge'), value: 'em-dash' },
  { label: __('En dash (–)', 'campaignbridge'), value: 'en-dash' },
  { label: __('Ellipsis (…)', 'campaignbridge'), value: 'ellipsis' },
  { label: __('Pipe (|)', 'campaignbridge'), value: 'pipe' },
  { label: __('Colon (:)', 'campaignbridge'), value: 'colon' },
  { label: __('Semicolon (;)', 'campaignbridge'), value: 'semicolon' },
  { label: __('Bullet (•)', 'campaignbridge'), value: 'bullet' },
];
const SEP_MAP = {
  'em-dash': '—',
  'en-dash': '–',
  ellipsis: '...',
  pipe: '|',
  colon: ':',
  semicolon: ';',
  bullet: '•',
};

const LINK_TO_OPTIONS = [
  { label: __('Post', 'campaignbridge'), value: 'post' },
  { label: __('Post Type', 'campaignbridge'), value: 'postType' },
];

const STYLE_OPTIONS = [
  { label: __('Link', 'campaignbridge'), value: 'link' },
  { label: __('Button', 'campaignbridge'), value: 'button' },
];

const PLACEMENT_OPTIONS = [
  { label: __('New line', 'campaignbridge'), value: 'new-line' },
  {
    label: __('Inline (after excerpt)', 'campaignbridge'),
    value: 'inline',
  },
];

const ALLOWED = ['core/paragraph', 'core/buttons', 'core/button'];

export default function Edit({
  attributes,
  setAttributes,
  context = {},
  clientId,
}) {
  const {
    maxWords,
    showMore,
    moreStyle,
    morePlacement,
    linkTo,
    addSpaceBeforeLink,
    enableSeparator,
    separatorType,
    customSeparator,
    addSpaceBeforeSeparator,
  } = { ...DEFAULTS, ...attributes };

  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';

  const excerpt = useExcerptPreview({
    postId,
    postType,
    maxWords,
    showMore,
  });
  const template = useReadMoreTemplate({
    showMore,
    moreStyle,
    morePlacement,
  });

  // Structure change & placement class toggling (preserve user edits)
  useSyncReadMoreStructure(clientId, { showMore, moreStyle }, template);
  useInlinePlacementClass(clientId, { showMore, moreStyle, morePlacement });

  const blockProps = useBlockProps({
    className: morePlacement === 'inline' ? 'has-inline-readmore' : undefined,
  });

  // Editor: render InnerBlocks via our own container (span when inline).
  const Tag = showMore ? (morePlacement === 'inline' ? 'span' : 'div') : 'div';
  const innerBlocksProps = useInnerBlocksProps(
    {
      className:
        morePlacement === 'inline' ? 'is-inline-readmore-wrap' : undefined,
    },
    {
      template: template as any,
      allowedBlocks: ALLOWED,
      templateLock: 'all',
      renderAppender: false as any,
      templateInsertUpdatesSelection: false,
    }
  );

  // Separator preview string
  const sep = useMemo(() => {
    return enableSeparator && customSeparator
      ? `${addSpaceBeforeSeparator ? '\u00A0' : ''}${customSeparator}`
      : '';
  }, [enableSeparator, customSeparator, addSpaceBeforeSeparator]);

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody
          title={__('Excerpt & More Link', 'campaignbridge')}
          initialOpen
        >
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Max words', 'campaignbridge')}
            value={Number(maxWords) || 0}
            onChange={v => setAttributes({ maxWords: Number(v) || 0 })}
            min={10}
            max={150}
            step={1}
          />

          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Show more', 'campaignbridge')}
            checked={!!showMore}
            onChange={v => setAttributes({ showMore: !!v })}
          />

          {showMore && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Style', 'campaignbridge')}
                value={moreStyle}
                options={STYLE_OPTIONS}
                onChange={v => setAttributes({ moreStyle: v })}
              />

              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Placement', 'campaignbridge')}
                value={morePlacement}
                options={PLACEMENT_OPTIONS}
                onChange={v => setAttributes({ morePlacement: v })}
                help={__(
                  'Choose “Inline” to place the link/button at the end of the excerpt line.',
                  'campaignbridge'
                )}
              />

              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Link to', 'campaignbridge')}
                value={linkTo}
                options={LINK_TO_OPTIONS}
                onChange={v => setAttributes({ linkTo: v })}
              />

              <ToggleControl
                __nextHasNoMarginBottom
                label={__('Add space before link', 'campaignbridge')}
                checked={!!addSpaceBeforeLink}
                onChange={v =>
                  setAttributes({
                    addSpaceBeforeLink: !!v,
                  })
                }
                help={__(
                  'Adds spacing between the excerpt text and the read more link',
                  'campaignbridge'
                )}
              />
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__('Separator', 'campaignbridge')}
          initialOpen={false}
        >
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Enable separator', 'campaignbridge')}
            checked={!!enableSeparator}
            onChange={v => setAttributes({ enableSeparator: !!v })}
            help={__(
              'Add a separator between the excerpt text and the read more link',
              'campaignbridge'
            )}
          />

          {enableSeparator && (
            <>
              <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Separator type', 'campaignbridge')}
                value={separatorType}
                options={SEPARATOR_OPTIONS}
                onChange={value =>
                  setAttributes({
                    separatorType: value,
                    customSeparator: SEP_MAP[value] || '',
                  })
                }
              />

              {separatorType === 'custom' && (
                <TextControl
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                  label={__('Custom separator', 'campaignbridge')}
                  value={customSeparator}
                  onChange={v => setAttributes({ customSeparator: v })}
                  placeholder={__('e.g., |, —, •, etc.', 'campaignbridge')}
                />
              )}

              <ToggleControl
                __nextHasNoMarginBottom
                label={__('Add space before separator', 'campaignbridge')}
                checked={!!addSpaceBeforeSeparator}
                onChange={v =>
                  setAttributes({
                    addSpaceBeforeSeparator: !!v,
                  })
                }
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>

      {!!excerpt && (
        <>
          {excerpt}
          {enableSeparator && customSeparator ? sep : ''}
          {addSpaceBeforeLink && showMore ? '\u00A0' : ''}

          {showMore && <Tag {...innerBlocksProps} />}
        </>
      )}
    </div>
  );
}
