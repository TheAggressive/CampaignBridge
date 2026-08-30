import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { ColorPalette, PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes, context = {} }) {
  const postId = Number(context['campaignbridge:postId']) || 0;
  const postType = context['campaignbridge:postType'] || 'post';
  const label = attributes.label || __('Read more', 'campaignbridge');
  const backgroundColor = attributes.backgroundColor || '#111111';
  const textColor = attributes.textColor || '#ffffff';
  const post = useSelect(
    select =>
      postId
        ? (select('core') as any).getEntityRecord('postType', postType, postId)
        : null,
    [postType, postId]
  );

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
        href={post?.link || '#'}
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
